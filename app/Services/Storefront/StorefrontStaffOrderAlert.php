<?php

namespace App\Services\Storefront;

use App\StorefrontDigitalFulfillment;
use App\Transaction;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Who to alert about a new website order, and what to tell them. Each order alerts staff once.
 */
class StorefrontStaffOrderAlert
{
    public const PAYMENT_COD = 'cod';

    public const PAYMENT_ONLINE = 'online';

    private const OPEN_COUNT_TTL_SECONDS = 60;

    /**
     * Mark the order as alerted. False when another request (webhook, return page, scheduler) already did.
     */
    public function claim(Transaction $transaction): bool
    {
        return DB::table('transactions')
            ->where('id', $transaction->id)
            ->whereNull('storefront_staff_notified_at')
            ->update(['storefront_staff_notified_at' => now()]) === 1;
    }

    /**
     * Alerted website orders still at shipping status "ordered" (sidebar badge), cached briefly.
     *
     * @param  'all'|list<int>  $locations  User::permitted_locations()
     */
    public function openCount(int $businessId, string|array $locations): int
    {
        $key = 'storefront:open_orders:'.$businessId.':'.md5(json_encode($locations));

        return (int) Cache::remember($key, self::OPEN_COUNT_TTL_SECONDS, function () use ($businessId, $locations) {
            return Transaction::where('business_id', $businessId)
                ->where('type', 'sell')
                ->where('source', 'storefront')
                ->where('shipping_status', 'ordered')
                ->whereNotNull('storefront_staff_notified_at')
                ->when($locations !== 'all', fn ($query) => $query->whereIn('location_id', $locations))
                ->count();
        });
    }

    /**
     * Active staff who can view sales at the order's branch.
     *
     * @return Collection<int, User>
     */
    public function recipients(Transaction $transaction): Collection
    {
        $businessId = (int) $transaction->business_id;
        $locationId = (int) $transaction->location_id;

        return User::where('business_id', $businessId)
            ->user()
            ->where('status', 'active')
            ->where('allow_login', 1)
            ->get()
            ->filter(function (User $user) use ($businessId, $locationId) {
                if (! $user->can('sell.view') && ! $user->can('direct_sell.view')) {
                    return false;
                }
                $locations = $user->permitted_locations($businessId);

                return $locations === 'all' || in_array($locationId, array_map('intval', $locations), true);
            })
            ->values();
    }

    /**
     * Plain data for the bell notification and the email (no secrets: digital codes are never included).
     *
     * @return array<string, mixed>
     */
    public function summary(Transaction $transaction, string $payment): array
    {
        $transaction->loadMissing(['contact', 'location', 'business.currency', 'sell_lines.product', 'sell_lines.variations']);
        $lines = $transaction->sell_lines->whereNull('parent_sell_line_id')->values();
        $digitalCount = StorefrontDigitalFulfillment::where('transaction_id', $transaction->id)->count();

        return [
            'transaction_id' => (int) $transaction->id,
            'invoice_no' => (string) $transaction->invoice_no,
            'customer_name' => (string) ($transaction->contact->name ?? ''),
            'customer_mobile' => (string) ($transaction->contact->mobile ?? ''),
            'location' => (string) ($transaction->location->name ?? ''),
            'items' => $lines->map(fn ($line) => [
                'name' => trim(($line->product->name ?? '').' '.$this->variationLabel($line)),
                'quantity' => (float) $line->quantity,
            ])->all(),
            'items_count' => (int) round($lines->sum('quantity')),
            'total' => number_format((float) $transaction->final_total, 2),
            'currency' => (string) ($transaction->business->currency->code ?? ''),
            'payment' => $payment === self::PAYMENT_COD ? self::PAYMENT_COD : self::PAYMENT_ONLINE,
            'payment_status' => (string) $transaction->payment_status,
            'shipping_method' => (string) ($transaction->shipping_details ?? ''),
            'shipping_address' => (string) ($transaction->shipping_address ?? ''),
            'note' => (string) ($transaction->additional_notes ?? ''),
            'digital_only' => $digitalCount > 0 && $digitalCount >= $lines->count(),
        ];
    }

    private function variationLabel(object $line): string
    {
        $name = (string) ($line->variations->name ?? '');

        return $name === '' || $name === 'DUMMY' ? '' : '('.$name.')';
    }
}
