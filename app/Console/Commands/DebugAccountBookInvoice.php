<?php

namespace App\Console\Commands;

use App\Account;
use App\AccountTransaction;
use App\Transaction;
use App\TransactionPayment;
use App\Utils\ModuleUtil;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Diagnose why a sell invoice does not appear on /account/account/{id}.
 *
 * Account book lists account_transactions (cash/bank ledger), not invoices directly.
 * A sell shows up only when a payment has account_id and an AccountTransaction was created.
 * Date filter uses operation_date (= payment paid_on), not transaction_date / created_at.
 */
class DebugAccountBookInvoice extends Command
{
    protected $signature = 'account:debug-invoice
                            {invoice_no : Invoice number (e.g. 28060)}
                            {account_id? : Cash/bank account id from /account/account/{id}}
                            {--start= : Optional UI start_date (Y-m-d) to test date filter}
                            {--end= : Optional UI end_date (Y-m-d) to test date filter}
                            {--business= : Limit transaction lookup to business_id}';

    protected $description = 'Explain why an invoice is missing from the account book (cash/bank ledger).';

    public function handle(ModuleUtil $moduleUtil): int
    {
        $invoiceNo = (string) $this->argument('invoice_no');
        $accountId = $this->argument('account_id');
        $start = $this->option('start');
        $end = $this->option('end');
        $businessIdOpt = $this->option('business');

        $this->line('');
        $this->info("=== Account book debug: invoice #{$invoiceNo}" . ($accountId ? " vs account {$accountId}" : '') . ' ===');
        $this->line('Note: /account/account/{id} lists account_transactions filtered by operation_date (payment paid_on), not invoice date.');
        $this->line('');

        $txQuery = Transaction::query()->where('invoice_no', $invoiceNo);
        if (! empty($businessIdOpt)) {
            $txQuery->where('business_id', $businessIdOpt);
        }

        $matches = (clone $txQuery)->get(['id', 'business_id', 'type', 'status', 'payment_status', 'transaction_date', 'created_at', 'final_total', 'location_id', 'contact_id']);

        if ($matches->isEmpty()) {
            // Fallback: numeric invoice may be stored with prefix
            $matches = Transaction::query()
                ->when(! empty($businessIdOpt), fn ($q) => $q->where('business_id', $businessIdOpt))
                ->where(function ($q) use ($invoiceNo) {
                    $q->where('invoice_no', $invoiceNo)
                        ->orWhere('invoice_no', 'like', '%' . $invoiceNo);
                })
                ->limit(20)
                ->get(['id', 'business_id', 'type', 'status', 'payment_status', 'transaction_date', 'created_at', 'final_total', 'invoice_no']);

            if ($matches->isEmpty()) {
                $this->error("No transaction found with invoice_no = {$invoiceNo}");

                return self::FAILURE;
            }

            $this->warn('Exact invoice_no not found; showing similar matches:');
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
            $this->line('Re-run with the exact invoice_no from the table above.');

            return self::FAILURE;
        }

        if ($matches->count() > 1) {
            $this->warn('Multiple transactions share this invoice_no. Pass --business=ID to narrow:');
            $this->table(
                ['id', 'business_id', 'type', 'payment_status', 'transaction_date'],
                $matches->map(fn ($t) => [
                    $t->id,
                    $t->business_id,
                    $t->type,
                    $t->payment_status,
                    (string) $t->transaction_date,
                ])->all()
            );

            return self::FAILURE;
        }

        $tx = $matches->first();
        $businessId = (int) $tx->business_id;

        $this->table(
            ['field', 'value'],
            [
                ['transaction_id', $tx->id],
                ['business_id', $businessId],
                ['type', $tx->type],
                ['status', $tx->status],
                ['payment_status', $tx->payment_status],
                ['transaction_date', (string) $tx->transaction_date],
                ['created_at', (string) $tx->created_at],
                ['final_total', $tx->final_total],
            ]
        );

        $accountModuleOn = $moduleUtil->isModuleEnabled('account', $businessId);
        $this->line('Account module enabled for business: ' . ($accountModuleOn ? 'YES' : 'NO (payments will not create account_transactions)'));
        $this->line('');

        if (! empty($accountId)) {
            $account = Account::withTrashed()->find($accountId);
            if (! $account) {
                $this->error("Account id {$accountId} does not exist.");

                return self::FAILURE;
            }
            $this->table(
                ['account field', 'value'],
                [
                    ['id', $account->id],
                    ['name', $account->name],
                    ['business_id', $account->business_id],
                    ['is_closed', $account->is_closed ?? null],
                    ['deleted_at', (string) ($account->deleted_at ?? '')],
                ]
            );
            if ((int) $account->business_id !== $businessId) {
                $this->error("MISMATCH: account business_id={$account->business_id} but invoice business_id={$businessId}");
            }
            $this->line('');
        }

        $payments = TransactionPayment::where('transaction_id', $tx->id)
            ->orderBy('id')
            ->get([
                'id', 'parent_id', 'amount', 'method', 'account_id', 'paid_on',
                'is_return', 'is_advance', 'payment_type', 'created_by', 'business_id',
            ]);

        if ($payments->isEmpty()) {
            $this->error('No transaction_payments for this invoice — unpaid / no payment rows → never appears in account book.');
            $this->printVerdict([], $accountId, $start, $end);

            return self::SUCCESS;
        }

        $this->info('Payments on invoice:');
        $this->table(
            ['id', 'parent_id', 'amount', 'method', 'account_id', 'paid_on', 'is_return', 'is_advance'],
            $payments->map(fn ($p) => [
                $p->id,
                $p->parent_id,
                $p->amount,
                $p->method,
                $p->account_id ?? 'NULL',
                (string) $p->paid_on,
                $p->is_return,
                $p->is_advance,
            ])->all()
        );

        $paymentIds = $payments->pluck('id');

        $ledgerRows = AccountTransaction::withTrashed()
            ->where(function ($q) use ($tx, $paymentIds) {
                $q->where('transaction_id', $tx->id)
                    ->orWhereIn('transaction_payment_id', $paymentIds);
            })
            ->orderBy('id')
            ->get([
                'id', 'account_id', 'type', 'amount', 'operation_date',
                'transaction_id', 'transaction_payment_id', 'sub_type', 'deleted_at',
            ]);

        $this->info('AccountTransaction rows linked to invoice/payments (incl. soft-deleted):');
        if ($ledgerRows->isEmpty()) {
            $this->warn('NONE — payment may have null account_id, advance method, account module off, or BNPL skip.');
        } else {
            $this->table(
                ['id', 'account_id', 'type', 'amount', 'operation_date', 'tp_id', 'deleted_at'],
                $ledgerRows->map(fn ($r) => [
                    $r->id,
                    $r->account_id,
                    $r->type,
                    $r->amount,
                    (string) $r->operation_date,
                    $r->transaction_payment_id,
                    (string) ($r->deleted_at ?? ''),
                ])->all()
            );
        }
        $this->line('');

        // Per-payment diagnosis
        $reasons = [];
        foreach ($payments as $p) {
            $label = "payment #{$p->id}";
            if ($p->method === 'advance') {
                $reasons[] = "{$label}: method=advance → AddAccountTransaction skips cashbook row";
                continue;
            }
            if (empty($p->account_id)) {
                $reasons[] = "{$label}: account_id is NULL → never linked to any cash/bank account (use Payment Account Report to link)";
                continue;
            }
            if (! empty($accountId) && (int) $p->account_id !== (int) $accountId) {
                $reasons[] = "{$label}: account_id={$p->account_id} (not {$accountId}) → appears on /account/account/{$p->account_id} instead";
            }

            $at = $ledgerRows->firstWhere('transaction_payment_id', $p->id);
            if (! $at) {
                $reasons[] = "{$label}: has account_id={$p->account_id} but NO account_transactions row (listener skipped or failed — check account module / InstallmentCredit)";
                continue;
            }
            if ($at->deleted_at) {
                $reasons[] = "{$label}: account_transaction #{$at->id} is soft-deleted (deleted_at={$at->deleted_at})";
            }
            if (! empty($accountId) && (int) $at->account_id !== (int) $accountId) {
                $reasons[] = "{$label}: ledger row account_id={$at->account_id} ≠ book account {$accountId}";
            }
            if (! empty($start) && ! empty($end) && $at->operation_date) {
                $opDay = Carbon::parse($at->operation_date)->toDateString();
                if ($opDay < $start || $opDay > $end) {
                    $reasons[] = "{$label}: operation_date={$opDay} OUTSIDE UI range {$start}..{$end} (UI filters paid_on/operation_date, not invoice transaction_date)";
                }
            }
        }

        // Simulate account book query for this account + invoice
        if (! empty($accountId)) {
            $inBookQuery = AccountTransaction::query()
                ->where('account_id', $accountId)
                ->where(function ($q) use ($tx, $paymentIds) {
                    $q->where('transaction_id', $tx->id)
                        ->orWhereIn('transaction_payment_id', $paymentIds);
                });

            if (! empty($start) && ! empty($end)) {
                $inBookQuery->whereDate('operation_date', '>=', $start)
                    ->whereDate('operation_date', '<=', $end);
            }

            $visible = $inBookQuery->get(['id', 'operation_date', 'amount', 'type']);
            $this->info('Would appear on account book' . (! empty($start) ? " with range {$start}..{$end}" : ' (no date filter)') . ': ' . ($visible->isEmpty() ? 'NO' : 'YES'));
            if ($visible->isNotEmpty()) {
                $this->table(
                    ['id', 'type', 'amount', 'operation_date'],
                    $visible->map(fn ($r) => [$r->id, $r->type, $r->amount, (string) $r->operation_date])->all()
                );
            }
            $this->line('');
        }

        $this->printVerdict($reasons, $accountId, $start, $end);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function printVerdict(array $reasons, $accountId, $start, $end): void
    {
        $this->info('=== Verdict / next steps ===');
        if (empty($reasons)) {
            if (empty($accountId)) {
                $this->line('No blocking issues detected on payments. Re-run with account id, e.g.:');
                $this->line('  php artisan account:debug-invoice 28060 35 --start=2026-07-20 --end=2026-07-23');
            } elseif (empty($start) || empty($end)) {
                $this->line('Payments look linked. If still missing in UI, pass the same date range as the account book filter:');
                $this->line('  php artisan account:debug-invoice {invoice} {account} --start=YYYY-MM-DD --end=YYYY-MM-DD');
                $this->line('Remember: filter uses payment paid_on / operation_date, not invoice created date.');
            } else {
                $this->line('No exclusion reasons found for this account + date range. If UI still hides it, hard-refresh and confirm business/session and type (debit/credit) filter.');
            }
        } else {
            foreach ($reasons as $r) {
                $this->warn('- ' . $r);
            }
        }
        $this->line('');
    }
}
