<?php

namespace App\Services;

use App\Contact;
use App\Transaction;
use App\TransactionSellLine;

/**
 * Builds the "what changed" payload stored on sell / sales order activity log entries
 * (properties.line_changes + properties.field_changes), rendered in sale_pos.partials.activity_row.
 */
class SellActivityChangesService
{
    private const QTY_PRECISION = 4;

    private const PRICE_PRECISION = 4;

    /**
     * Header fields compared on edit: attribute => [translation key, value type].
     */
    private const TRACKED_FIELDS = [
        'contact_id' => ['contact.customer', 'contact'],
        'transaction_date' => ['sale.sale_date', 'datetime'],
        'discount_amount' => ['sale.discount', 'discount'],
        'shipping_charges' => ['sale.shipping_charges', 'money'],
        'additional_notes' => ['sale.sell_note', 'text'],
        'staff_note' => ['sale.staff_note', 'text'],
    ];

    /**
     * Parent sell lines keyed by line id: product label, quantity, unit price (inc. tax).
     *
     * @return array<int, array{product: string, qty: float, price: float}>
     */
    public function snapshotLines(int $transactionId): array
    {
        return TransactionSellLine::where('transaction_id', $transactionId)
            ->whereNull('parent_sell_line_id')
            ->with(['product:id,name,type', 'variations:id,name'])
            ->get()
            ->mapWithKeys(fn (TransactionSellLine $line) => [$line->id => [
                'product' => $this->productLabel($line),
                'qty' => round((float) $line->quantity, self::QTY_PRECISION),
                'price' => round((float) $line->unit_price_inc_tax, self::PRICE_PRECISION),
            ]])
            ->all();
    }

    /**
     * @return list<array{change: string, product: string, qty_from: ?float, qty_to: ?float, price_from: ?float, price_to: ?float}>
     */
    public function lineChanges(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $id => $line) {
            if (! isset($before[$id])) {
                $changes[] = $this->lineChange('added', $line['product'], null, $line);
            } elseif ($before[$id]['qty'] != $line['qty'] || $before[$id]['price'] != $line['price']) {
                $changes[] = $this->lineChange('updated', $line['product'], $before[$id], $line);
            }
        }

        foreach ($before as $id => $line) {
            if (! isset($after[$id])) {
                $changes[] = $this->lineChange('removed', $line['product'], $line, null);
            }
        }

        return $changes;
    }

    /**
     * @return list<array{label: string, type: string, from: mixed, to: mixed}>
     */
    public function fieldChanges(Transaction $before, Transaction $after): array
    {
        $changes = [];

        foreach (self::TRACKED_FIELDS as $attribute => [$label, $type]) {
            $from = $this->fieldValue($before, $attribute, $type);
            $to = $this->fieldValue($after, $attribute, $type);

            if ($from !== $to) {
                $changes[] = ['label' => $label, 'type' => $type, 'from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    /**
     * Activity properties for a newly added sell: every line is "added".
     */
    public function addedProperties(int $transactionId): array
    {
        $lines = $this->lineChanges([], $this->snapshotLines($transactionId));

        return empty($lines) ? [] : ['line_changes' => $lines];
    }

    /**
     * Activity properties for an edit, given the pre-edit model and line snapshot.
     */
    public function editedProperties(Transaction $before, array $linesBefore, Transaction $after): array
    {
        $properties = array_filter([
            'line_changes' => $this->lineChanges($linesBefore, $this->snapshotLines((int) $after->id)),
            'field_changes' => $this->fieldChanges($before, $after->fresh()),
        ]);

        return $properties;
    }

    private function lineChange(string $change, string $product, ?array $from, ?array $to): array
    {
        return [
            'change' => $change,
            'product' => $product,
            'qty_from' => $from['qty'] ?? null,
            'qty_to' => $to['qty'] ?? null,
            'price_from' => $from['price'] ?? null,
            'price_to' => $to['price'] ?? null,
        ];
    }

    private function productLabel(TransactionSellLine $line): string
    {
        $name = (string) ($line->product->name ?? ('#' . $line->product_id));
        $variation = (string) ($line->variations->name ?? '');

        if (($line->product->type ?? '') === 'variable' && $variation !== '' && $variation !== 'DUMMY') {
            $name .= ' - ' . $variation;
        }

        return $name;
    }

    private function fieldValue(Transaction $transaction, string $attribute, string $type): ?string
    {
        $value = $transaction->getAttribute($attribute);

        return match ($type) {
            'contact' => empty($value) ? null : (string) (Contact::whereKey($value)->value('name') ?? ('#' . $value)),
            'money' => $value === null ? null : (string) round((float) $value, self::PRICE_PRECISION),
            'discount' => $this->discountValue($transaction),
            'datetime' => empty($value) ? null : (string) \Carbon::parse($value)->format('Y-m-d H:i'),
            default => ($value === null || trim((string) $value) === '') ? null : trim((string) $value),
        };
    }

    private function discountValue(Transaction $transaction): ?string
    {
        $amount = round((float) $transaction->discount_amount, self::PRICE_PRECISION);
        if ($amount == 0.0) {
            return null;
        }

        return $transaction->discount_type === 'percentage' ? $amount . '%' : (string) $amount;
    }
}
