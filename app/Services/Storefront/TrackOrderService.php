<?php

namespace App\Services\Storefront;

use App\Contact;
use App\Transaction;

/**
 * Guest + customer order tracking by invoice + phone/email match.
 */
class TrackOrderService
{
    public function __construct(
        private PhoneValidationService $phoneValidation,
        private CheckoutService $checkout
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lookup(int $businessId, string $invoiceNo, ?string $phone, ?string $email): ?array
    {
        $invoiceNo = trim($invoiceNo);
        $phone = trim((string) $phone);
        $email = strtolower(trim((string) $email));

        if ($invoiceNo === '' || ($phone === '' && $email === '')) {
            return null;
        }

        $transaction = Transaction::query()
            ->where('business_id', $businessId)
            ->where('type', 'sell')
            ->where(function ($q) {
                $q->where('status', 'final')
                    ->orWhere(function ($q2) {
                        $q2->where('source', 'storefront')
                            ->where('is_quotation', 1);
                    });
            })
            ->where(function ($q) use ($invoiceNo) {
                $q->where('invoice_no', $invoiceNo)
                    ->orWhere('storefront_order_id', $invoiceNo);
                if (ctype_digit($invoiceNo)) {
                    $q->orWhere('id', (int) $invoiceNo);
                }
            })
            ->with('contact')
            ->orderByDesc('id')
            ->first();

        if (! $transaction || ! $transaction->contact) {
            return null;
        }

        /** @var Contact $contact */
        $contact = $transaction->contact;

        if (! $this->contactMatches($contact, $phone, $email)) {
            return null;
        }

        return $this->safePayload($transaction);
    }

    private function contactMatches(Contact $contact, string $phone, string $email): bool
    {
        if ($email !== '') {
            $stored = strtolower(trim((string) ($contact->email ?? '')));
            if ($stored !== '' && hash_equals($stored, $email)) {
                return true;
            }
        }

        if ($phone !== '') {
            $searchNeedles = $this->phoneValidation->nationalDigitNeedles($phone);
            $storedNeedles = $this->phoneValidation->nationalDigitNeedles((string) ($contact->mobile ?? ''));
            if ($searchNeedles !== [] && $storedNeedles !== []) {
                foreach ($searchNeedles as $needle) {
                    if (in_array($needle, $storedNeedles, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function safePayload(Transaction $transaction): array
    {
        $formatted = $this->checkout->formatOrderResponse($transaction);

        $lines = [];
        $transaction->loadMissing(['sell_lines.product']);
        foreach ($transaction->sell_lines as $line) {
            $lines[] = [
                'product_name' => $line->product?->name,
                'quantity' => (float) $line->quantity,
            ];
        }

        return [
            'id' => (int) $formatted['id'],
            'invoice_no' => $formatted['invoice_no'] ?? null,
            'storefront_order_id' => $formatted['storefront_order_id'] ?? null,
            'status' => $formatted['status'] ?? null,
            'payment_status' => $formatted['payment_status'] ?? null,
            'shipping_status' => $formatted['shipping_status'] ?? null,
            'shipping_carrier' => $transaction->shipping_carrier,
            'shipping_tracking_number' => $transaction->shipping_tracking_number,
            'shipping_tracking_url' => $transaction->shipping_tracking_url,
            'final_total' => $formatted['final_total'] ?? null,
            'transaction_date' => $formatted['transaction_date'] ?? null,
            'lines' => $lines,
        ];
    }
}
