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
     * Seed defaults and auto-map unused custom_pay_N slots.
     */
    public static function seedDefaultsForBusiness($business_id)
    {
        $used_methods = self::where('business_id', $business_id)
            ->whereNotNull('payment_method_key')
            ->pluck('payment_method_key')
            ->all();

        $next_slot = 1;
        foreach (self::defaultCompanyDefs() as $def) {
            $existing = self::where('business_id', $business_id)->where('code', $def['code'])->first();
            if ($existing) {
                continue;
            }

            $method = null;
            while ($next_slot <= 30) {
                $candidate = 'custom_pay_'.$next_slot;
                $next_slot++;
                if (! in_array($candidate, $used_methods, true)) {
                    $method = $candidate;
                    $used_methods[] = $method;
                    break;
                }
            }

            self::create([
                'business_id' => $business_id,
                'name' => $def['name'],
                'code' => $def['code'],
                'is_active' => 1,
                'default_settlement_days' => 20,
                'fee_mode' => 'none',
                'payment_method_key' => $method,
            ]);
        }
    }
}
