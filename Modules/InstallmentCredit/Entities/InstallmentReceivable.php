<?php

namespace Modules\InstallmentCredit\Entities;

use App\BusinessLocation;
use App\Transaction;
use App\TransactionPayment;
use Carbon\Carbon;
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

    /**
     * Date used for aging buckets: POS sale date, else invoice date, else due date.
     */
    public function agingAnchorDate(): ?Carbon
    {
        $txn_date = optional($this->transaction)->transaction_date;
        if (! empty($txn_date)) {
            return Carbon::parse($txn_date)->startOfDay();
        }
        if (! empty($this->invoice_date)) {
            return Carbon::parse($this->invoice_date)->startOfDay();
        }
        if (! empty($this->due_date)) {
            return Carbon::parse($this->due_date)->startOfDay();
        }

        return null;
    }

    /**
     * Whole calendar days from $from to $asOf (0 if missing or in the future).
     * Uses DateTime::diff so Carbon 2/3 signed diffInDays cannot collapse overdue rows into 0.
     */
    public static function calendarDaysSince($from, $asOf = null): int
    {
        if ($from === null || $from === '') {
            return 0;
        }

        $from_day = Carbon::parse($from)->startOfDay();
        $as_of_day = Carbon::parse($asOf ?? Carbon::today())->startOfDay();
        if ($from_day->greaterThan($as_of_day)) {
            return 0;
        }

        return (int) $from_day->diff($as_of_day)->days;
    }

    /**
     * Days past due date (0 if missing or not yet due).
     */
    public function getDaysDueAttribute()
    {
        return self::calendarDaysSince($this->due_date);
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
