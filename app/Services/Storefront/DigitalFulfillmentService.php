<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Services\Storefront\Accounts\AccountsApiClient;
use App\StorefrontDigitalFulfillment;
use App\Transaction;
use App\TransactionSellLine;
use Illuminate\Support\Facades\Log;

/**
 * Allocates Accounts secrets for paid storefront digital lines and tracks the POS↔Accounts trip.
 */
class DigitalFulfillmentService
{
    public function __construct(
        private AccountsApiClient $accounts,
        private StorefrontSettingService $settings
    ) {
    }

    /**
     * Create pending ledger rows after checkout (no Accounts call yet).
     *
     * @param  list<array<string, mixed>>  $digitalItems
     */
    public function queuePending(Transaction $transaction, array $digitalItems): void
    {
        $orderId = (string) $transaction->storefront_order_id;
        foreach ($digitalItems as $item) {
            $kind = ($item['kind'] ?? '') === 'card' ? 'card' : 'game';
            $lineKey = (string) ($item['line_key'] ?? $this->defaultLineKey($item, $kind));
            StorefrontDigitalFulfillment::updateOrCreate(
                [
                    'storefront_order_id' => $orderId,
                    'line_key' => $lineKey,
                ],
                [
                    'business_id' => (int) $transaction->business_id,
                    'transaction_id' => (int) $transaction->id,
                    'sell_line_id' => $item['sell_line_id'] ?? null,
                    'kind' => $kind,
                    'status' => 'pending',
                    'request_meta' => $item,
                ]
            );
            $this->repairSellLinePriceFromItem($transaction, $item);
        }

        StorefrontDigitalFulfillDebug::log('queue_pending.done', [
            'transaction_id' => $transaction->id,
            'storefront_order_id' => $orderId,
            'item_count' => count($digitalItems),
            'line_keys' => array_map(
                fn ($item) => $item['line_key'] ?? null,
                $digitalItems
            ),
        ]);

        $this->refreshTransactionTotalsFromSellLines($transaction);
    }

    /**
     * Fix zero-price digital sell lines using catalog price stored on the line item / meta.
     *
     * @param  array<string, mixed>  $item
     */
    private function repairSellLinePriceFromItem(Transaction $transaction, array $item): void
    {
        $price = null;
        foreach ([$item['price'] ?? null, $item['unit_price'] ?? null] as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate) && (float) $candidate > 0) {
                $price = (float) $candidate;
                break;
            }
        }
        if ($price === null && ! empty($item['kind'])) {
            $price = app(DigitalCatalogService::class)->resolveOfferPrice(
                (int) $transaction->business_id,
                $item
            );
        }
        if ($price === null) {
            return;
        }

        $line = null;
        if (! empty($item['sell_line_id'])) {
            $line = TransactionSellLine::find($item['sell_line_id']);
        }
        if (! $line && ! empty($item['variation_id'])) {
            $line = TransactionSellLine::where('transaction_id', $transaction->id)
                ->where('variation_id', (int) $item['variation_id'])
                ->whereNull('parent_sell_line_id')
                ->first();
        }
        if (! $line) {
            return;
        }
        if ((float) $line->unit_price_inc_tax > 0 && abs((float) $line->unit_price_inc_tax - $price) < 0.0001) {
            return;
        }

        $line->unit_price_before_discount = $price;
        $line->unit_price = $price;
        $line->unit_price_inc_tax = $price;
        $line->item_tax = 0;
        if (! empty($item['title']) && trim((string) $line->sell_line_note) === '') {
            $line->sell_line_note = (string) $item['title'];
        }
        $line->save();
    }

    private function refreshTransactionTotalsFromSellLines(Transaction $transaction): void
    {
        $transaction->load('sell_lines');
        $subtotal = 0.0;
        foreach ($transaction->sell_lines as $line) {
            if (! empty($line->parent_sell_line_id)) {
                continue;
            }
            $subtotal += (float) $line->unit_price_inc_tax * (float) $line->quantity;
        }
        $shipping = (float) $transaction->shipping_charges;
        $discount = (float) ($transaction->discount_amount ?? 0);
        $rp = (float) ($transaction->rp_redeemed_amount ?? 0);
        $final = max(0, round($subtotal + $shipping - $discount - $rp, 4));
        if (abs((float) $transaction->final_total - $final) < 0.0001
            && abs((float) $transaction->total_before_tax - $subtotal) < 0.0001) {
            return;
        }
        $transaction->total_before_tax = round($subtotal, 4);
        $transaction->final_total = $final;
        $transaction->save();
    }

    /**
     * Repair existing digital orders that saved with L.E. 0.00 sell lines.
     */
    public function repairZeroPriceDigitalOrders(?int $transactionId = null): int
    {
        $query = StorefrontDigitalFulfillment::query()->orderBy('id');
        if ($transactionId) {
            $query->where('transaction_id', $transactionId);
        }
        $fixed = 0;
        $seenTx = [];
        foreach ($query->get() as $row) {
            $meta = is_array($row->request_meta) ? $row->request_meta : [];
            $meta['sell_line_id'] = $row->sell_line_id;
            $tx = Transaction::find($row->transaction_id);
            if (! $tx) {
                continue;
            }
            $before = (float) $tx->final_total;
            $this->repairSellLinePriceFromItem($tx, $meta);
            $this->refreshTransactionTotalsFromSellLines($tx);
            $tx->refresh();
            if (abs((float) $tx->final_total - $before) > 0.0001 || ! isset($seenTx[$tx->id])) {
                if ((float) $tx->final_total > $before) {
                    $fixed++;
                }
                $seenTx[$tx->id] = true;
            }
        }

        return $fixed;
    }

    public function fulfillPaidTransaction(Transaction $transaction): int
    {
        $transaction->loadMissing(['contact', 'sell_lines']);
        if (strtolower(trim((string) $transaction->payment_status)) !== 'paid') {
            return 0;
        }

        $rows = StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $allocated = 0;
        foreach ($rows as $row) {
            $before = $row->status;
            $this->allocateOne($transaction, $row);
            $row->refresh();
            if ($row->status === 'allocated' && $before !== 'allocated') {
                $allocated++;
            }
        }

        return $allocated;
    }

    /**
     * Run after POS payment status becomes paid (edit sell + cash, payment modal, Fawry, etc.).
     */
    public function handleStorefrontBecamePaid(Transaction $transaction): void
    {
        $transaction->refresh();
        $snap = StorefrontDigitalFulfillDebug::snapshot($transaction);
        StorefrontDigitalFulfillDebug::log('became_paid.enter', $snap);

        if (strtolower(trim((string) $transaction->payment_status)) !== 'paid') {
            StorefrontDigitalFulfillDebug::log('became_paid.skip_not_paid', [
                'transaction_id' => $transaction->id,
                'payment_status' => $transaction->payment_status,
            ]);

            return;
        }

        $hasPending = StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)
            ->whereIn('status', ['pending', 'failed'])
            ->exists();
        if (! $hasPending) {
            StorefrontDigitalFulfillDebug::log('became_paid.skip_no_pending_rows', [
                'transaction_id' => $transaction->id,
                'source' => $transaction->source,
                'storefront_order_id' => $transaction->storefront_order_id,
                'fulfillment_count' => $snap['fulfillment_count'],
                'hint' => $snap['fulfillment_count'] === 0
                    ? 'No storefront_digital_fulfillments rows — checkout may not have queued digital lines.'
                    : 'Rows exist but none are pending/failed (already allocated or other status).',
            ]);

            return;
        }

        try {
            $allocated = $this->fulfillPaidTransaction($transaction->fresh(['contact', 'sell_lines']));
            StorefrontDigitalFulfillDebug::log('became_paid.after_fulfill', [
                'transaction_id' => $transaction->id,
                'allocated' => $allocated,
                'snapshot' => StorefrontDigitalFulfillDebug::snapshot($transaction->fresh()),
            ]);
            if ($allocated > 0) {
                app(StorefrontMailService::class)
                    ->sendPaidDigitalConfirmation($transaction->fresh(['contact', 'sell_lines']));
            }
        } catch (\Throwable $e) {
            StorefrontDigitalFulfillDebug::log('became_paid.exception', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
            Log::warning('Storefront digital fulfill after paid failed: '.$e->getMessage(), [
                'transaction_id' => $transaction->id,
                'invoice_no' => $transaction->invoice_no,
            ]);
        }
    }

    public function retryFailed(int $transactionId): void
    {
        $transaction = Transaction::with(['contact', 'sell_lines'])->find($transactionId);
        if (! $transaction) {
            return;
        }
        $this->fulfillPaidTransaction($transaction);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customerDeliveriesForTransaction(Transaction $transaction): array
    {
        if (strtolower(trim((string) $transaction->payment_status)) !== 'paid') {
            return [];
        }

        return StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)
            ->where('status', 'allocated')
            ->get()
            ->map(fn (StorefrontDigitalFulfillment $row) => $row->toCustomerDelivery())
            ->filter()
            ->values()
            ->all();
    }

    private function allocateOne(Transaction $transaction, StorefrontDigitalFulfillment $row): void
    {
        $meta = $row->request_meta ?? [];
        $settings = $this->settings->get((int) $transaction->business_id);
        $digital = $settings['digital'] ?? [];
        $storeProfileId = (int) ($digital['accounts_store_profile_id'] ?? 17);

        $contact = $transaction->contact;
        $buyerName = trim((string) ($contact->name ?? (($contact->first_name ?? '').' '.($contact->last_name ?? ''))));
        if ($buyerName === '') {
            $buyerName = 'Customer';
        }
        $buyerPhone = (string) ($contact->mobile ?? $contact->landline ?? '');
        $buyerEmail = (string) ($contact->email ?? 'noreply@gamesspoteg.com');

        $payload = [
            'store_profile_id' => $storeProfileId,
            'buyer_phone' => $buyerPhone !== '' ? $buyerPhone : '00000000000',
            'buyer_name' => $buyerName,
            'buyer_email' => $buyerEmail,
            'price' => (float) ($meta['price'] ?? $transaction->final_total),
            'storefront_order_id' => $transaction->storefront_order_id,
            'pos_transaction_id' => $transaction->id,
            // Accounts legacy field (still required on some deploys); use POS transaction id.
            'wc_order_id' => (int) $transaction->id,
            'storefront_line_key' => $row->line_key,
        ];

        if ($row->kind === 'card') {
            $payload['card_category_id'] = (int) ($meta['card_category_id'] ?? 0);
        } else {
            $payload['game_id'] = (int) ($meta['game_id'] ?? 0);
            $payload['type'] = (string) ($meta['type'] ?? 'primary');
            $payload['platform'] = (string) ($meta['platform'] ?? '4');
        }

        $row->attempts = (int) $row->attempts + 1;
        $row->save();

        StorefrontDigitalFulfillDebug::log('allocate.request', [
            'transaction_id' => $transaction->id,
            'fulfillment_id' => $row->id,
            'line_key' => $row->line_key,
            'kind' => $row->kind,
            'payload' => $payload,
            'accounts_base' => $this->accounts->baseUrl(),
        ]);

        $response = $this->accounts->receiveOrder($payload);
        StorefrontDigitalFulfillDebug::log('allocate.response', [
            'transaction_id' => $transaction->id,
            'fulfillment_id' => $row->id,
            'success' => $response['success'] ?? false,
            'status' => $response['status'] ?? null,
            'error' => $response['error'] ?? null,
            'body_keys' => is_array($response['body'] ?? null) ? array_keys($response['body']) : null,
            'order_id' => $response['body']['order_id'] ?? null,
        ]);
        if (! $response['success']) {
            $row->status = 'failed';
            $row->last_error = $response['error'] ?? 'Accounts allocate failed';
            $row->save();
            Log::warning('Digital fulfillment allocate failed', [
                'transaction_id' => $transaction->id,
                'line_key' => $row->line_key,
                'error' => $row->last_error,
            ]);

            return;
        }

        $body = $response['body'] ?? [];
        $secrets = [];
        if ($row->kind === 'card') {
            $code = $body['code'] ?? ($body['card_details']['code'] ?? null);
            $secrets = ['code' => $code];
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

        $this->appendSecretsToSellLineNote($row, $secrets);
        $this->appendSecretsToStaffNote($transaction, $row, $secrets);
    }

    /**
     * @param  array<string, mixed>  $secrets
     */
    private function appendSecretsToSellLineNote(StorefrontDigitalFulfillment $row, array $secrets): void
    {
        if (empty($row->sell_line_id)) {
            return;
        }
        $line = TransactionSellLine::find($row->sell_line_id);
        if (! $line) {
            return;
        }
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
        if ($parts === []) {
            return;
        }
        $note = trim((string) ($line->sell_line_note ?? ''));
        $addition = implode(' | ', $parts);
        $line->sell_line_note = $note === '' ? $addition : $note."\n".$addition;
        $line->save();
    }

    /**
     * Fill invoice Staff note like Accounts send-to-POS (game title + live credentials).
     *
     * @param  array<string, mixed>  $secrets
     */
    private function appendSecretsToStaffNote(Transaction $transaction, StorefrontDigitalFulfillment $row, array $secrets): void
    {
        $meta = is_array($row->request_meta) ? $row->request_meta : [];
        $title = (string) ($meta['title'] ?? 'N/A');
        $type = (string) ($meta['type'] ?? ($row->kind === 'card' ? 'card' : 'N/A'));
        $account = (string) ($secrets['email'] ?? ($secrets['code'] ?? 'N/A'));
        $password = (string) ($secrets['password'] ?? ($row->kind === 'card' ? 'N/A' : 'N/A'));
        if ($row->kind === 'card' && ! empty($secrets['code'])) {
            $account = (string) $secrets['code'];
            $password = 'N/A';
        }

        $filled = 'Game Title: '.$title."\nType: ".$type."\nAccount: ".$account."\nPassword: ".$password.'<br>----------------------<br>';
        $placeholder = 'Game Title: '.$title."\nType: ".$type."\nAccount: N/A\nPassword: N/A<br>----------------------<br>";

        $staff = (string) ($transaction->staff_note ?? '');
        if ($staff !== '' && str_contains($staff, $placeholder)) {
            $staff = str_replace($placeholder, $filled, $staff);
        } elseif ($staff !== '' && $title !== 'N/A' && str_contains($staff, 'Game Title: '.$title) && str_contains($staff, 'Account: N/A')) {
            // Replace first N/A credential pair after this title.
            $staff = preg_replace(
                '/(Game Title:\s*'.preg_quote($title, '/').'\nType:\s*[^\n]+\n)Account:\s*N\/A\nPassword:\s*N\/A/u',
                '$1Account: '.$account."\nPassword: ".$password,
                $staff,
                1
            ) ?? $staff;
        } else {
            $staff = trim($staff) === '' ? $filled : rtrim($staff)."\n".$filled;
        }

        $transaction->staff_note = $staff;
        $transaction->save();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function defaultLineKey(array $item, string $kind): string
    {
        if ($kind === 'card') {
            return 'card|category:'.($item['card_category_id'] ?? '0');
        }

        return 'ps'.($item['platform'] ?? '4').'_'.($item['type'] ?? 'primary').'_stock|game:'.($item['game_id'] ?? '0');
    }
}
