<?php

namespace App\Console\Commands;

use App\Transaction;
use App\TransactionPayment;
use Illuminate\Console\Command;
use Modules\InstallmentCredit\Entities\InstallmentCompany;
use Modules\InstallmentCredit\Entities\InstallmentReceivable;
use Modules\InstallmentCredit\Utils\InstallmentCreditUtil;

/**
 * Diagnose why a sell invoice is missing / wrong on Installment Credit → Pending Receivables.
 *
 * Pending list only shows installment_receivables.status = pending.
 * One receivable per BNPL payment line (same company may appear more than once).
 */
class DebugInstallmentReceivableInvoice extends Command
{
    protected $signature = 'installment:debug-invoice
                            {invoice_no : Invoice number (e.g. 26817)}
                            {--business= : Limit lookup to business_id}
                            {--sync : Re-sync receivables from current BNPL payment lines (live fix)}';

    protected $description = 'Debug installment receivable state for a POS invoice number.';

    public function handle(InstallmentCreditUtil $util): int
    {
        $invoiceNo = (string) $this->argument('invoice_no');
        $businessIdOpt = $this->option('business');

        $this->line('');
        $this->info("=== Installment receivable debug: invoice #{$invoiceNo} ===");
        $this->line('Pending list = installment_receivables WHERE status = pending only.');
        $this->line('Rule: one receivable per BNPL payment line (same company may have multiple rows).');
        $this->line('');

        $txQuery = Transaction::query()->where(function ($q) use ($invoiceNo) {
            $q->where('invoice_no', $invoiceNo)
                ->orWhere('invoice_no', 'like', '%'.$invoiceNo.'%');
            if (ctype_digit($invoiceNo)) {
                $q->orWhere('id', (int) $invoiceNo);
            }
        });
        if (! empty($businessIdOpt)) {
            $txQuery->where('business_id', $businessIdOpt);
        }

        $matches = (clone $txQuery)->limit(20)->get([
            'id', 'business_id', 'type', 'status', 'payment_status',
            'transaction_date', 'final_total', 'location_id', 'contact_id', 'invoice_no',
        ]);

        if ($matches->isEmpty()) {
            $this->error("No transaction found with invoice_no / id matching {$invoiceNo}");
            $this->line('DB: '.config('database.connections.'.config('database.default').'.database')
                .' @ '.config('database.connections.'.config('database.default').'.host'));
            $this->line('Sell count: '.Transaction::where('type', 'sell')->count()
                .' | receivables: '.InstallmentReceivable::count());
            $this->line('Tip: run this on the same server/DB where you edited the invoice (local DB may not have production invoices).');

            return self::FAILURE;
        }

        if ($matches->count() > 1) {
            $this->warn('Multiple transactions matched — dumping each:');
            $this->table(
                ['id', 'invoice_no', 'business_id', 'type', 'payment_status', 'transaction_date'],
                $matches->map(fn ($t) => [
                    $t->id,
                    $t->invoice_no,
                    $t->business_id,
                    $t->type,
                    $t->payment_status,
                    (string) $t->transaction_date,
                ])->all()
            );
            $this->line('');
        }

        foreach ($matches as $tx) {
            if ($this->option('sync')) {
                $this->warn("Running syncReceivablesForSellTransaction for txn #{$tx->id} …");
                $synced = $util->syncReceivablesForSellTransaction($tx->fresh(['payment_lines']));
                $this->info('Synced '.count($synced).' receivable(s).');
                $this->line('');
            }
            $this->dumpTransaction($tx->fresh(), $util);
        }

        return self::SUCCESS;
    }

    protected function dumpTransaction(Transaction $tx, InstallmentCreditUtil $util): void
    {
        $this->info("--- Transaction id={$tx->id} business_id={$tx->business_id} ---");
        $this->table(
            ['field', 'value'],
            [
                ['invoice_no', $tx->invoice_no],
                ['type', $tx->type],
                ['status', $tx->status],
                ['payment_status', $tx->payment_status],
                ['transaction_date', (string) $tx->transaction_date],
                ['final_total', $tx->final_total],
                ['location_id', $tx->location_id],
                ['contact_id', $tx->contact_id],
            ]
        );

        if ($tx->type !== 'sell') {
            $this->warn('Not a sell transaction — installment module only creates receivables for sells.');
        }

        $companies = InstallmentCompany::where('business_id', $tx->business_id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'payment_method_key', 'is_active', 'default_settlement_days']);

        $this->line('');
        $this->comment('Installment companies (payment_method_key must match payment.method):');
        if ($companies->isEmpty()) {
            $this->warn('No installment companies for this business.');
        } else {
            $this->table(
                ['id', 'name', 'code', 'payment_method_key', 'is_active', 'settlement_days'],
                $companies->map(fn ($c) => [
                    $c->id,
                    $c->name,
                    $c->code,
                    $c->payment_method_key,
                    $c->is_active ? '1' : '0',
                    $c->default_settlement_days,
                ])->all()
            );
        }

        $payments = TransactionPayment::where('transaction_id', $tx->id)
            ->orderBy('id')
            ->get();

        $this->line('');
        $this->comment('Current payment lines on invoice:');
        if ($payments->isEmpty()) {
            $this->warn('No payment lines. Editing a credit sale often removes BNPL payment → receivable cancelled and never revived.');
        } else {
            $rows = [];
            foreach ($payments as $p) {
                $company = InstallmentCompany::findByPaymentMethod($tx->business_id, $p->method);
                $rows[] = [
                    $p->id,
                    $p->payment_ref_no,
                    (string) $p->paid_on,
                    $p->method,
                    $p->amount,
                    $p->payment_line_status ?? '',
                    ! empty($p->is_return) ? '1' : '0',
                    $company ? "{$company->name} (#{$company->id})" : '— NOT BNPL —',
                    $util->isBnplPaymentMethod($tx->business_id, $p->method) ? 'yes' : 'no',
                ];
            }
            $this->table(
                ['payment_id', 'ref', 'paid_on', 'method', 'amount', 'line_status', 'is_return', 'company', 'bnpl?'],
                $rows
            );
        }

        $bnplPayments = $payments->filter(function ($p) use ($tx, $util) {
            return empty($p->is_return) && $util->isBnplPaymentMethod($tx->business_id, $p->method);
        });

        $this->line('');
        $this->comment('BNPL payment lines → expected pending receivables (1:1):');
        foreach ($bnplPayments as $p) {
            $company = InstallmentCompany::findByPaymentMethod($tx->business_id, $p->method);
            $this->line(sprintf(
                '  payment #%d (%s) %s → due_amount %.4f | paid_on %s',
                $p->id,
                $company->name ?? $p->method,
                $p->payment_ref_no,
                (float) $p->amount,
                (string) $p->paid_on
            ));
        }
        if ($bnplPayments->isEmpty()) {
            $this->warn('  (none)');
        }

        $this->line('');
        $this->comment('Receivables linked to this transaction_id (any status):');
        $receivables = InstallmentReceivable::with('company')
            ->where('business_id', $tx->business_id)
            ->where(function ($q) use ($tx) {
                $q->where('transaction_id', $tx->id)
                    ->orWhere('invoice_no', $tx->invoice_no);
            })
            ->orderBy('id')
            ->get();

        if ($receivables->isEmpty()) {
            $this->warn('No installment_receivables rows for this invoice/transaction.');
        } else {
            $this->table(
                ['id', 'status', 'company', 'invoice_date', 'due_date', 'due_amount', 'booked_settled', 'actual_received', 'outstanding', 'payment_id', 'txn_id'],
                $receivables->map(fn ($r) => [
                    $r->id,
                    $r->status,
                    $r->company->name ?? $r->company_id,
                    (string) $r->invoice_date,
                    (string) $r->due_date,
                    $r->due_amount,
                    $r->booked_settled_amount,
                    $r->actual_received_amount,
                    $r->outstanding,
                    $r->transaction_payment_id,
                    $r->transaction_id,
                ])->all()
            );
        }

        $this->line('');
        $this->comment('Diagnosis:');

        $pending = $receivables->where('status', 'pending');
        $cancelled = $receivables->where('status', 'cancelled');
        $settled = $receivables->where('status', 'settled');

        if ($pending->isNotEmpty()) {
            $this->info('✓ Has pending receivable(s) — should appear on Pending Receivables list: '.$pending->pluck('id')->implode(', '));
        } else {
            $this->error('✗ No pending receivable — will NOT appear on Pending Receivables list.');
        }

        $bnplCount = $bnplPayments->count();
        $pendingAuto = $pending->filter(fn ($r) => ! empty($r->transaction_payment_id));
        if ($bnplCount > 0 && $pendingAuto->count() !== $bnplCount) {
            $this->error(sprintf(
                '✗ Count mismatch: %d BNPL payment line(s) but %d pending receivable(s) linked to a payment. Expected one pending row per BNPL line.',
                $bnplCount,
                $pendingAuto->count()
            ));
            $this->line('  Fix: php artisan installment:debug-invoice '.$tx->invoice_no.' --sync');
        } elseif ($bnplCount > 0 && $pendingAuto->count() === $bnplCount) {
            $this->info(sprintf('✓ Count OK: %d BNPL payment line(s) → %d pending receivable(s).', $bnplCount, $pendingAuto->count()));
        }

        foreach ($bnplPayments as $p) {
            $match = $receivables->first(fn ($r) => (int) $r->transaction_payment_id === (int) $p->id);
            if (! $match) {
                $this->error("✗ Missing receivable for payment #{$p->id} amount={$p->amount}");
                continue;
            }
            if ($match->status !== 'pending' && $match->status !== 'settled') {
                $this->warn("Payment #{$p->id} linked to receivable #{$match->id} with status={$match->status}");
            }
            if ($match->status === 'pending' && abs((float) $match->due_amount - (float) $p->amount) > 0.0001) {
                $this->error(sprintf(
                    '✗ Amount mismatch payment #%d: receivable due_amount=%.4f vs payment amount=%.4f',
                    $p->id,
                    (float) $match->due_amount,
                    (float) $p->amount
                ));
            }
        }

        if ($cancelled->isNotEmpty()) {
            $this->warn('Cancelled receivable id(s): '.$cancelled->pluck('id')->implode(', '));
            $this->line('Likely cause: sale edit deleted payment line(s) → CancelReceivableOnPaymentDeleted set status=cancelled.');
        }

        if ($settled->isNotEmpty()) {
            $this->line('Settled receivable id(s): '.$settled->pluck('id')->implode(', '));
        }

        if ($bnplPayments->isEmpty() && $cancelled->isNotEmpty()) {
            $this->error('Root cause candidate: invoice currently has NO BNPL payment method, so CreateReceivableFromPayment cannot revive the cancelled row after edit.');
        } elseif ($bnplPayments->isNotEmpty() && $cancelled->isNotEmpty() && $pending->isEmpty()) {
            $this->error('Root cause candidate: BNPL payment exists NOW, but receivable stayed cancelled — TransactionPaymentAdded may not have fired (check payment_line_status), or create failed silently.');
            foreach ($bnplPayments as $p) {
                if (($p->payment_line_status ?? 'completed') !== 'completed') {
                    $this->warn("Payment #{$p->id} payment_line_status={$p->payment_line_status} — TransactionPaymentAdded only fires when status is completed.");
                }
            }
        } elseif ($bnplPayments->isNotEmpty() && $receivables->isEmpty()) {
            $this->error('Root cause candidate: BNPL payment exists but no receivable row — listener never created one (module off at sale time, or event not fired).');
            $this->line('You can revive via: php artisan installment:debug-invoice '.$tx->invoice_no.' --sync');
        } elseif ($bnplPayments->isEmpty() && $receivables->isEmpty()) {
            $this->warn('No BNPL payment and no receivable — nothing to show on pending (expected if invoice is not an installment sale).');
        }

        // Orphan: cancelled payment_id no longer on invoice
        foreach ($receivables as $r) {
            if ($r->transaction_payment_id && ! $payments->contains('id', $r->transaction_payment_id)) {
                $this->warn("Receivable #{$r->id} points at deleted payment_id={$r->transaction_payment_id} (typical after sale edit).");
            }
        }

        $this->line('');
        $this->comment('Dates note: invoice_date / due_date follow each payment paid_on + company settlement days.');
        $this->line('');
    }
}
