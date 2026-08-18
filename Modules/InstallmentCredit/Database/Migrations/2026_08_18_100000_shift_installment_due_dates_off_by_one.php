<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recast auto due dates so the settlement period starts the day after the invoice
 * (18 Aug + 30 days → 18 Sep instead of 17 Sep).
 */
class ShiftInstallmentDueDatesOffByOne extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('installment_receivables') || ! Schema::hasTable('installment_companies')) {
            return;
        }

        $rows = DB::table('installment_receivables as ir')
            ->join('installment_companies as ic', 'ic.id', '=', 'ir.company_id')
            ->where('ir.status', 'pending')
            ->whereNotNull('ir.invoice_date')
            ->whereNotNull('ir.due_date')
            ->select('ir.id', 'ir.invoice_date', 'ir.due_date', 'ic.default_settlement_days')
            ->get();

        foreach ($rows as $row) {
            $days = (int) $row->default_settlement_days;
            $old_due = Carbon::parse($row->invoice_date)->startOfDay()->addDays($days)->toDateString();
            $actual_due = Carbon::parse($row->due_date)->toDateString();
            if ($actual_due !== $old_due) {
                continue;
            }
            $new_due = \Modules\InstallmentCredit\Utils\InstallmentCreditUtil::dueDateFromInvoiceDate(
                $row->invoice_date,
                $days
            );
            DB::table('installment_receivables')->where('id', $row->id)->update(['due_date' => $new_due]);
        }
    }

    public function down()
    {
        if (! Schema::hasTable('installment_receivables') || ! Schema::hasTable('installment_companies')) {
            return;
        }

        $rows = DB::table('installment_receivables as ir')
            ->join('installment_companies as ic', 'ic.id', '=', 'ir.company_id')
            ->where('ir.status', 'pending')
            ->whereNotNull('ir.invoice_date')
            ->whereNotNull('ir.due_date')
            ->select('ir.id', 'ir.invoice_date', 'ir.due_date', 'ic.default_settlement_days')
            ->get();

        foreach ($rows as $row) {
            $days = (int) $row->default_settlement_days;
            $new_due = \Modules\InstallmentCredit\Utils\InstallmentCreditUtil::dueDateFromInvoiceDate(
                $row->invoice_date,
                $days
            );
            $actual_due = Carbon::parse($row->due_date)->toDateString();
            if ($actual_due !== $new_due) {
                continue;
            }
            $old_due = Carbon::parse($row->invoice_date)->startOfDay()->addDays($days)->toDateString();
            DB::table('installment_receivables')->where('id', $row->id)->update(['due_date' => $old_due]);
        }
    }
}
