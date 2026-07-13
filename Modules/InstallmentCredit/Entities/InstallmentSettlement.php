<?php

namespace Modules\InstallmentCredit\Entities;

use App\Account;
use App\BusinessLocation;
use App\User;
use Illuminate\Database\Eloquent\Model;

class InstallmentSettlement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'settlement_date' => 'date',
        'amount_booked' => 'float',
        'amount_received' => 'float',
        'fee_amount' => 'float',
    ];

    public function company()
    {
        return $this->belongsTo(InstallmentCompany::class, 'company_id');
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines()
    {
        return $this->hasMany(InstallmentSettlementLine::class, 'settlement_id');
    }
}
