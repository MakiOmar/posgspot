<?php

use App\Business;
use Illuminate\Database\Migrations\Migration;
use Modules\InstallmentCredit\Entities\InstallmentCompany;

/**
 * Remap installment companies off custom_pay slots already used
 * for other methods (InstaPay, Vodafone Cash, Wallet, etc.).
 */
class RemapInstallmentCompaniesOffUsedPaymentSlots extends Migration
{
    public function up()
    {
        if (! class_exists(InstallmentCompany::class)) {
            return;
        }

        foreach (Business::pluck('id') as $business_id) {
            InstallmentCompany::remapConflictingPaymentMethods($business_id);
        }
    }

    public function down()
    {
        // Irreversible remap — labels/slots were already in use by other methods
    }
}
