<?php

namespace Modules\InstallmentCredit\Entities;

use Illuminate\Database\Eloquent\Model;

class InstallmentSettlementLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount_booked' => 'float',
        'amount_received' => 'float',
    ];

    public function settlement()
    {
        return $this->belongsTo(InstallmentSettlement::class, 'settlement_id');
    }

    public function receivable()
    {
        return $this->belongsTo(InstallmentReceivable::class, 'receivable_id');
    }
}
