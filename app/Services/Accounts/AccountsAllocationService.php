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
     * @return array<string, mixed>|null secrets on success, null on failure (row marked failed)
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
        if (! $response['success']) {
            $row->status = 'failed';
            $row->last_error = $response['error'] ?? 'Accounts allocate failed';
            $row->save();
            Log::warning('Digital fulfillment allocate failed', [
                'transaction_id' => $transaction->id,
                'line_key' => $row->line_key,
                'error' => $row->last_error,
            ]);

            return null;
        }

        $body = $response['body'] ?? [];
        if ($row->kind === 'card') {
            $secrets = ['code' => $body['code'] ?? ($body['card_details']['code'] ?? null)];
        } else {
            $secrets = [
                'email' => $body['account_details']['email'] ?? null,
                'password' => $body['account_details']['password'] ?? null,
            ];
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
        $account = (string) ($secrets['email'] ?? ($secrets['code'] ?? 'N/A'));
        $password = (string) ($secrets['password'] ?? 'N/A');
        if ($row->kind === 'card' && ! empty($secrets['code'])) {
            $account = (string) $secrets['code'];
            $password = 'N/A';
        }

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

    public function stampPosOrder(string $storefrontOrderId, int $transactionId, ?int $accountsOrderId): bool
    {
        $response = $this->accounts->stampPosOrder($storefrontOrderId, $transactionId, $accountsOrderId);

        return ! empty($response['success']);
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
