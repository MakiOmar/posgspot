<?php

namespace App\Services\Accounts;

use App\Services\Storefront\Accounts\AccountsApiClient;
use App\StorefrontDigitalFulfillment;
use App\Transaction;
use App\TransactionSellLine;
use Illuminate\Support\Facades\Log;

/**
 * One Accounts allocation per ledger row plus the POS note writers, shared by storefront
 * checkout fulfilment and call-center sales orders.
 */
class AccountsAllocationService
{
    public function __construct(private AccountsApiClient $accounts)
    {
    }

    /**
     * Calls Accounts `orders/receive` for one ledger row and records the outcome on the row.
     *
     * @param  array<string, mixed>  $extraPayload  e.g. `notes` for sales orders
     * @return array<string, mixed>|null secrets on success; null when the row is marked failed, or
     *                                   left pending because the supplier order is still in progress
     */
    public function allocate(
        Transaction $transaction,
        StorefrontDigitalFulfillment $row,
        int $storeProfileId,
        array $extraPayload = []
    ): ?array {
        $meta = $row->request_meta ?? [];
        $contact = $transaction->contact;
        $buyerName = trim((string) ($contact->name ?? (($contact->first_name ?? '').' '.($contact->last_name ?? ''))));
        if ($buyerName === '') {
            $buyerName = 'Customer';
        }
        $buyerPhone = (string) ($contact->mobile ?? $contact->landline ?? '');
        $buyerEmail = (string) ($contact->email ?? '');
        if (! filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
            $buyerEmail = 'noreply@gamesspoteg.com';
        }

        $payload = array_merge([
            'store_profile_id' => $storeProfileId,
            // Accounts validates buyer_phone max:15.
            'buyer_phone' => $buyerPhone !== '' ? mb_substr($buyerPhone, 0, 15) : '00000000000',
            'buyer_name' => mb_substr($buyerName, 0, 100),
            'buyer_email' => $buyerEmail,
            'price' => (float) ($meta['price'] ?? $transaction->final_total),
            'storefront_order_id' => $row->storefront_order_id,
            'pos_transaction_id' => $transaction->id,
            // Accounts legacy field (still required on some deploys); use POS transaction id.
            'wc_order_id' => (int) $transaction->id,
            'storefront_line_key' => $row->line_key,
        ], $extraPayload);

        if ($row->kind === 'card') {
            $payload['card_category_id'] = (int) ($meta['card_category_id'] ?? 0);
        } else {
            $payload['game_id'] = (int) ($meta['game_id'] ?? 0);
            $payload['type'] = (string) ($meta['type'] ?? 'primary');
            $payload['platform'] = (string) ($meta['platform'] ?? '4');
        }

        $row->attempts = (int) $row->attempts + 1;
        $row->save();

        $response = $this->accounts->receiveOrder($payload);
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];

        if ($this->isSupplierPending($response, $body)) {
            $this->markPending($transaction, $row, $body);

            return null;
        }

        if (! $response['success'] || ($body['success'] ?? true) === false) {
            return $this->markFailed($transaction, $row, $response['error'] ?? ($body['message'] ?? null));
        }

        $secrets = $this->secretsFromBody($row, $body);
        if (array_filter($secrets, fn ($value) => $value !== null && $value !== '') === []) {
            return $this->markFailed($transaction, $row, 'Accounts response had no credentials');
        }

        $row->accounts_order_id = $body['order_id'] ?? $row->accounts_order_id;
        $row->storeSecrets($secrets);
        $row->status = 'allocated';
        $row->last_error = null;
        $row->allocated_at = now();
        $row->save();

        return $secrets;
    }

    /**
     * Accounts answers 202 `{pending: true}` while a supplier (Next Load) order is still in progress;
     * resending the same storefront_order_id + line_key later returns the code without buying twice.
     *
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $body
     */
    private function isSupplierPending(array $response, array $body): bool
    {
        return (int) ($response['status'] ?? 0) === 202 || ! empty($body['pending']);
    }

    /**
     * A pending supplier order is not a failed attempt, so it does not count toward the retry cap.
     *
     * @param  array<string, mixed>  $body
     */
    private function markPending(Transaction $transaction, StorefrontDigitalFulfillment $row, array $body): void
    {
        $row->status = 'pending';
        $row->attempts = max(0, (int) $row->attempts - 1);
        $row->last_error = mb_substr((string) ($body['message'] ?? 'Supplier order in progress'), 0, 500);
        $row->save();
        Log::info('Digital fulfillment waiting for supplier order', [
            'transaction_id' => $transaction->id,
            'line_key' => $row->line_key,
        ]);
    }

    private function markFailed(Transaction $transaction, StorefrontDigitalFulfillment $row, ?string $error): ?array
    {
        $row->status = 'failed';
        $row->last_error = mb_substr((string) ($error ?: 'Accounts allocate failed'), 0, 500);
        $row->save();
        Log::warning('Digital fulfillment allocate failed', [
            'transaction_id' => $transaction->id,
            'line_key' => $row->line_key,
            'error' => $row->last_error,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function secretsFromBody(StorefrontDigitalFulfillment $row, array $body): array
    {
        if ($row->kind === 'card') {
            return ['code' => $body['code'] ?? ($body['card_details']['code'] ?? null)];
        }

        return [
            'email' => $body['account_details']['email'] ?? null,
            'password' => $body['account_details']['password'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $secrets
     */
    public function appendSecretsToSellLineNote(?int $sellLineId, array $secrets): void
    {
        if (empty($sellLineId)) {
            return;
        }
        $line = TransactionSellLine::find($sellLineId);
        if (! $line) {
            return;
        }
        $addition = $this->formatSecretsLine($secrets);
        if ($addition === '') {
            return;
        }
        $note = trim((string) ($line->sell_line_note ?? ''));
        if (str_contains($note, $addition)) {
            return;
        }
        $line->sell_line_note = $note === '' ? $addition : $note."\n".$addition;
        $line->save();
    }

    /**
     * Fill the staff note like Accounts send-to-POS (game title + live credentials).
     *
     * @param  array<string, mixed>  $secrets
     * @return string|null the new staff note, or null when the transaction is gone
     */
    public function appendSecretsToStaffNote(int $transactionId, StorefrontDigitalFulfillment $row, array $secrets): ?string
    {
        $meta = is_array($row->request_meta) ? $row->request_meta : [];
        $title = (string) ($meta['title'] ?? 'N/A');
        $type = (string) ($meta['type'] ?? ($row->kind === 'card' ? 'card' : 'N/A'));
        [$account, $password] = $this->staffNoteCredentials($row, $secrets);

        // Always reload — sell edit may have a stale in-memory staff_note.
        $tx = Transaction::find($transactionId);
        if (! $tx) {
            return null;
        }
        $staff = (string) ($tx->staff_note ?? '');
        $filledBlock = 'Game Title: '.$title."\nType: ".$type."\nAccount: ".$account."\nPassword: ".$password.'<br>----------------------<br>';

        $count = 0;
        // Replace the first pending credential pair (tolerate \r\n and optional <br>).
        $updated = preg_replace(
            '/Account:\s*N\/A\s*\r?\nPassword:\s*N\/A(?:<br\s*\/?>)?/u',
            'Account: '.$account."\nPassword: ".$password,
            $staff,
            1,
            $count
        );

        if ($count > 0 && is_string($updated)) {
            $staff = $updated;
        } elseif ($staff === '' || ! str_contains($staff, 'Account: '.$account)) {
            $staff = trim($staff) === '' ? $filledBlock : rtrim($staff)."\n".$filledBlock;
        }

        $tx->staff_note = $staff;
        $tx->save();

        return $staff;
    }

    /**
     * Append a plain line (e.g. an allocation failure) to the staff note once.
     */
    public function appendStaffNoteLine(int $transactionId, string $line): void
    {
        $tx = Transaction::find($transactionId);
        if (! $tx) {
            return;
        }
        $staff = (string) ($tx->staff_note ?? '');
        if (str_contains($staff, $line)) {
            return;
        }
        $tx->staff_note = trim($staff) === '' ? $line : rtrim($staff)."\n".$line;
        $tx->save();
    }

    /**
     * Undo `appendSecretsToStaffNote`: put the first matching credential pair back to N/A so the
     * next allocation fills that block again.
     *
     * @param  array<string, mixed>  $secrets
     */
    public function revertSecretsInStaffNote(int $transactionId, StorefrontDigitalFulfillment $row, array $secrets): void
    {
        $tx = Transaction::find($transactionId);
        if (! $tx) {
            return;
        }
        [$account, $password] = $this->staffNoteCredentials($row, $secrets);
        if ($account === 'N/A') {
            return;
        }

        $updated = preg_replace(
            '/Account:\s*'.preg_quote($account, '/').'\s*\r?\nPassword:\s*'.preg_quote($password, '/').'/u',
            "Account: N/A\nPassword: N/A",
            (string) ($tx->staff_note ?? ''),
            1,
            $count
        );
        if ($count > 0 && is_string($updated)) {
            $tx->staff_note = $updated;
            $tx->save();
        }
    }

    /**
     * Undo `appendSecretsToSellLineNote`.
     *
     * @param  array<string, mixed>  $secrets
     */
    public function removeSecretsFromSellLineNote(?int $sellLineId, array $secrets): void
    {
        $addition = $this->formatSecretsLine($secrets);
        if (empty($sellLineId) || $addition === '') {
            return;
        }
        $line = TransactionSellLine::find($sellLineId);
        if (! $line) {
            return;
        }
        $note = (string) ($line->sell_line_note ?? '');
        $updated = preg_replace('/\r?\n?'.preg_quote($addition, '/').'/u', '', $note, 1, $count);
        if ($count > 0 && is_string($updated)) {
            $line->sell_line_note = trim($updated);
            $line->save();
        }
    }

    public function stampPosOrder(string $storefrontOrderId, int $transactionId, ?int $accountsOrderId): bool
    {
        $response = $this->accounts->stampPosOrder($storefrontOrderId, $transactionId, $accountsOrderId);

        return ! empty($response['success']);
    }

    /**
     * Account / Password values as written in the staff note (a card code goes in the Account field).
     *
     * @param  array<string, mixed>  $secrets
     * @return array{0: string, 1: string}
     */
    private function staffNoteCredentials(StorefrontDigitalFulfillment $row, array $secrets): array
    {
        if ($row->kind === 'card' && ! empty($secrets['code'])) {
            return [(string) $secrets['code'], 'N/A'];
        }

        return [
            (string) ($secrets['email'] ?? ($secrets['code'] ?? 'N/A')),
            (string) ($secrets['password'] ?? 'N/A'),
        ];
    }

    /**
     * @param  array<string, mixed>  $secrets
     */
    private function formatSecretsLine(array $secrets): string
    {
        $parts = [];
        if (! empty($secrets['email'])) {
            $parts[] = 'Account: '.$secrets['email'];
        }
        if (! empty($secrets['password'])) {
            $parts[] = 'Password: '.$secrets['password'];
        }
        if (! empty($secrets['code'])) {
            $parts[] = 'Code: '.$secrets['code'];
        }

        return implode(' | ', $parts);
    }
}
