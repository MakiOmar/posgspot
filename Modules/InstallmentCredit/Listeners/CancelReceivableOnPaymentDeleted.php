<?php

namespace Modules\InstallmentCredit\Listeners;

use App\Events\TransactionPaymentDeleted;
use Modules\InstallmentCredit\Utils\InstallmentCreditUtil;

class CancelReceivableOnPaymentDeleted
{
    public function handle(TransactionPaymentDeleted $event)
    {
        try {
            $util = new InstallmentCreditUtil();
            $util->cancelReceivableForPayment($event->transactionPayment);
        } catch (\Throwable $e) {
            \Log::error('InstallmentCredit CancelReceivableOnPaymentDeleted: '.$e->getMessage());
        }
    }
}
