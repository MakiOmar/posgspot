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

    public function fulfillPaidTransaction(Transaction $transaction): void
    {
        $transaction->loadMissing(['contact', 'sell_lines']);
        if (strtolower(trim((string) $transaction->payment_status)) !== 'paid') {
            return;
        }

        $rows = StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $row) {
            $this->allocateOne($transaction, $row);
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
