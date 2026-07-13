<?php

namespace Modules\InstallmentCredit\Entities;

use App\Account;
use App\BusinessLocation;
use Illuminate\Database\Eloquent\Model;

class InstallmentCompany extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'fee_percent' => 'float',
        'fee_fixed' => 'float',
    ];

    public function depositAccount()
    {
        return $this->belongsTo(Account::class, 'default_deposit_account_id');
    }

    public function receivables()
    {
        return $this->hasMany(InstallmentReceivable::class, 'company_id');
    }

    public static function forDropdown($business_id, $active_only = true)
    {
        $q = self::where('business_id', $business_id)->orderBy('name');
        if ($active_only) {
            $q->where('is_active', 1);
        }

        return $q->pluck('name', 'id');
    }

    public static function findByPaymentMethod($business_id, $method)
    {
        if (empty($method)) {
            return null;
        }

        return self::where('business_id', $business_id)
            ->where('is_active', 1)
            ->where('payment_method_key', $method)
            ->first();
    }

    /**
     * Default BNPL partners from Credit Analyses workbook.
     */
    public static function defaultCompanyDefs()
    {
        return [
            ['name' => 'Value', 'code' => 'value'],
            ['name' => 'Maylo', 'code' => 'maylo'],
            ['name' => 'Tru', 'code' => 'tru'],
            ['name' => 'Aman', 'code' => 'aman'],
            ['name' => 'Forsa', 'code' => 'forsa'],
            ['name' => 'Seven', 'code' => 'seven'],
            ['name' => 'Sohoula', 'code' => 'sohoula'],
        ];
    }

    /**
     * Payment-label map from business.custom_labels.payments
     */
    public static function businessPaymentLabels($business_id): array
    {
        $business = \App\Business::find($business_id);
        if (empty($business) || empty($business->custom_labels)) {
            return [];
        }

        $labels = is_array($business->custom_labels)
            ? $business->custom_labels
            : json_decode($business->custom_labels, true);

        return $labels['payments'] ?? [];
    }

    /**
     * Slot is free if it has no custom label, or the label already matches this company.
     */
    public static function isPaymentSlotAvailable($business_id, string $method, ?self $for_company = null): bool
    {
        $labels = self::businessPaymentLabels($business_id);
        $label = trim((string) ($labels[$method] ?? ''));

        if ($label === '') {
            return true;
        }

        if ($for_company) {
            return strcasecmp($label, $for_company->name) === 0
                || strcasecmp($label, $for_company->code) === 0;
        }

        return false;
    }

    /**
     * Next free custom_pay_N not used by another company and not labeled for something else.
     */
    public static function allocatePaymentMethod($business_id, ?self $for_company = null, array &$reserved = []): ?string
    {
        $used = array_merge(
            self::where('business_id', $business_id)
                ->when($for_company, fn ($q) => $q->where('id', '!=', $for_company->id))
                ->whereNotNull('payment_method_key')
                ->pluck('payment_method_key')
                ->all(),
            $reserved
        );

        for ($i = 1; $i <= 30; $i++) {
            $candidate = 'custom_pay_'.$i;
            if (in_array($candidate, $used, true)) {
                continue;
            }
            if (! self::isPaymentSlotAvailable($business_id, $candidate, $for_company)) {
                continue;
            }
            $reserved[] = $candidate;

            return $candidate;
        }

        return null;
    }

    /**
     * Write company name into Business custom payment label for POS display.
     */
    public static function syncPaymentLabel($business_id, ?string $method, string $name): void
    {
        if (empty($method)) {
            return;
        }

        $business = \App\Business::find($business_id);
        if (empty($business)) {
            return;
        }

        $labels = is_array($business->custom_labels)
            ? $business->custom_labels
            : (json_decode($business->custom_labels ?? '{}', true) ?: []);

        if (! isset($labels['payments']) || ! is_array($labels['payments'])) {
            $labels['payments'] = [];
        }

        $labels['payments'][$method] = $name;
        $business->custom_labels = $labels;
        $business->save();
    }

    /**
     * Move companies off slots already labeled for other methods (e.g. InstaPay).
     */
    public static function remapConflictingPaymentMethods($business_id): int
    {
        $moved = 0;
        $reserved = [];

        $companies = self::where('business_id', $business_id)->orderBy('id')->get();
        foreach ($companies as $company) {
            $method = $company->payment_method_key;
            $needs_new = empty($method)
                || ! self::isPaymentSlotAvailable($business_id, $method, $company)
                || in_array($method, $reserved, true);

            if ($needs_new) {
                $new_method = self::allocatePaymentMethod($business_id, $company, $reserved);
                if ($new_method && $new_method !== $method) {
                    $company->payment_method_key = $new_method;
                    $company->save();
                    $moved++;
                    $method = $new_method;
                } elseif ($new_method) {
                    $reserved[] = $new_method;
                }
            } else {
                $reserved[] = $method;
            }

            if (! empty($method)) {
                self::syncPaymentLabel($business_id, $method, $company->name);
            }
        }

        return $moved;
    }

    /**
     * Seed defaults onto free custom_pay_N slots (skips labeled slots like InstaPay).
     */
    public static function seedDefaultsForBusiness($business_id)
    {
        $reserved = self::where('business_id', $business_id)
            ->whereNotNull('payment_method_key')
            ->pluck('payment_method_key')
            ->all();

        foreach (self::defaultCompanyDefs() as $def) {
            $existing = self::where('business_id', $business_id)->where('code', $def['code'])->first();
            if ($existing) {
                continue;
            }

            $method = self::allocatePaymentMethod($business_id, null, $reserved);

            self::create([
                'business_id' => $business_id,
                'name' => $def['name'],
                'code' => $def['code'],
                'is_active' => 1,
                'default_settlement_days' => 20,
                'fee_mode' => 'none',
                'payment_method_key' => $method,
            ]);

            if ($method) {
                self::syncPaymentLabel($business_id, $method, $def['name']);
            }
        }

        // Fix any companies still colliding with pre-existing payment labels
        self::remapConflictingPaymentMethods($business_id);
    }
}
