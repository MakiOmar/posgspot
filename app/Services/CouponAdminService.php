<?php

namespace App\Services;

use App\Category;
use App\Coupon;
use App\Variation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Back-office CRUD for storefront promo codes.
 */
class CouponAdminService
{
    public function listQuery(int $businessId)
    {
        return Coupon::where('business_id', $businessId)->orderByDesc('id');
    }

    public function create(int $businessId, array $data): Coupon
    {
        return DB::transaction(function () use ($businessId, $data) {
            $coupon = Coupon::create($this->mapPayload($businessId, $data));
            $this->syncRelations($coupon, $data);

            return $coupon->fresh(['categories', 'variations']);
        });
    }

    public function update(Coupon $coupon, array $data): Coupon
    {
        return DB::transaction(function () use ($coupon, $data) {
            $coupon->update($this->mapPayload((int) $coupon->business_id, $data, $coupon));
            $this->syncRelations($coupon, $data);

            return $coupon->fresh(['categories', 'variations']);
        });
    }

    public function duplicate(Coupon $coupon): Coupon
    {
        $copy = $coupon->replicate(['times_used']);
        $copy->code = $this->uniqueCode((int) $coupon->business_id, $coupon->code.'-COPY');
        $copy->name = $coupon->name.' (copy)';
        $copy->times_used = 0;
        $copy->is_active = false;
        $copy->save();

        $copy->categories()->sync($coupon->categories()->pluck('categories.id'));
        $copy->variations()->sync($coupon->variations()->pluck('variations.id'));

        return $copy;
    }

    public function formOptions(int $businessId): array
    {
        return [
            'categories' => Category::where('business_id', $businessId)->pluck('name', 'id'),
            'variations' => Variation::join('products', 'variations.product_id', '=', 'products.id')
                ->where('products.business_id', $businessId)
                ->whereNull('variations.deleted_at')
                ->select([
                    'variations.id',
                    DB::raw("CONCAT(products.name, ' - ', variations.name, ' (', variations.sub_sku, ')') as label"),
                ])
                ->pluck('label', 'variations.id'),
        ];
    }

    /** Generate a random promo code guaranteed unique for the business. */
    public function generateUniqueCode(int $businessId): string
    {
        $segment = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        return $this->uniqueCode($businessId, 'SAVE-'.$segment);
    }

    private function mapPayload(int $businessId, array $data, ?Coupon $existing = null): array
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            throw ValidationException::withMessages(['code' => ['Promo code is required.']]);
        }

        $duplicate = Coupon::where('business_id', $businessId)
            ->where('code', $code)
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['code' => ['This promo code already exists.']]);
        }

        return [
            'business_id' => $businessId,
            'code' => $code,
            'name' => trim((string) ($data['name'] ?? $code)),
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? Coupon::TYPE_PERCENT_ORDER,
            'discount_amount' => (float) ($data['discount_amount'] ?? 0),
            'max_discount_amount' => $data['max_discount_amount'] !== null && $data['max_discount_amount'] !== ''
                ? (float) $data['max_discount_amount']
                : null,
            'min_order_subtotal' => (float) ($data['min_order_subtotal'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => ! empty($data['is_active']),
            'channel' => $data['channel'] ?? Coupon::CHANNEL_STOREFRONT,
            'max_uses_total' => $this->nullableInt($data['max_uses_total'] ?? null),
            'max_uses_per_customer' => $this->nullableInt($data['max_uses_per_customer'] ?? null),
            'first_order_only' => ! empty($data['first_order_only']),
            'exclude_sale_items' => ! empty($data['exclude_sale_items']),
            'stack_with_reward_points' => array_key_exists('stack_with_reward_points', $data)
                ? ! empty($data['stack_with_reward_points'])
                : true,
            'applies_to' => $data['applies_to'] ?? Coupon::APPLIES_ALL,
        ];
    }

    private function syncRelations(Coupon $coupon, array $data): void
    {
        if (($data['applies_to'] ?? Coupon::APPLIES_ALL) === Coupon::APPLIES_CATEGORIES) {
            $coupon->categories()->sync(array_map('intval', (array) ($data['category_ids'] ?? [])));
            $coupon->variations()->sync([]);
        } elseif (($data['applies_to'] ?? Coupon::APPLIES_ALL) === Coupon::APPLIES_PRODUCTS) {
            $coupon->variations()->sync(array_map('intval', (array) ($data['variation_ids'] ?? [])));
            $coupon->categories()->sync([]);
        } else {
            $coupon->categories()->sync([]);
            $coupon->variations()->sync([]);
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    private function uniqueCode(int $businessId, string $base): string
    {
        $code = strtoupper(substr(preg_replace('/[^A-Z0-9\-]/', '', strtoupper($base)) ?? '', 0, 60));
        if ($code === '') {
            $code = 'COPY';
        }

        $candidate = $code;
        $suffix = 1;
        while (Coupon::where('business_id', $businessId)->where('code', $candidate)->exists()) {
            $candidate = substr($code, 0, 55).'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
