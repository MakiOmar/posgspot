<?php

namespace Modules\InstallmentCredit\Entities;

use App\BusinessLocation;
use App\Transaction;
use App\TransactionPayment;
use Illuminate\Database\Eloquent\Model;

class InstallmentReceivable extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'settled_on' => 'datetime',
        'due_amount' => 'float',
        'booked_settled_amount' => 'float',
        'actual_received_amount' => 'float',
        'is_imported' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(InstallmentCompany::class, 'company_id');
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function payment()
    {
        return $this->belongsTo(TransactionPayment::class, 'transaction_payment_id');
    }

    public function settlementLines()
    {
        return $this->hasMany(InstallmentSettlementLine::class, 'receivable_id');
    }

    public function getOutstandingAttribute()
    {
        return max(0, (float) $this->due_amount - (float) $this->booked_settled_amount);
    }

    public function getDaysDueAttribute()
    {
        if (empty($this->due_date)) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_date->copy()->startOfDay(), false);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('business_id', $business_id);
    }
}
