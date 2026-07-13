<?php

namespace Modules\InstallmentCredit\Listeners;

use App\Events\TransactionPaymentAdded;
use Modules\InstallmentCredit\Utils\InstallmentCreditUtil;

class CreateReceivableFromPayment
{
    public function handle(TransactionPaymentAdded $event)
    {
        try {
            $util = new InstallmentCreditUtil();
            $util->createReceivableFromPayment($event->transactionPayment);
        } catch (\Throwable $e) {
            \Log::error('InstallmentCredit CreateReceivableFromPayment: '.$e->getMessage());
        }
    }
}
