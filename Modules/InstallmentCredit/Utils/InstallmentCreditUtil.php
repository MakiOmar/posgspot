<?php

namespace Modules\InstallmentCredit\Utils;

use App\AccountTransaction;
use App\ExpenseCategory;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\InstallmentCredit\Entities\InstallmentCompany;
use Modules\InstallmentCredit\Entities\InstallmentReceivable;
use Modules\InstallmentCredit\Entities\InstallmentSettlement;
use Modules\InstallmentCredit\Entities\InstallmentSettlementLine;

class InstallmentCreditUtil
{
    protected $transactionUtil;

    protected $moduleUtil;

    public function __construct(TransactionUtil $transactionUtil = null, ModuleUtil $moduleUtil = null)
    {
        $this->transactionUtil = $transactionUtil ?: new TransactionUtil();
        $this->moduleUtil = $moduleUtil ?: new ModuleUtil();
    }

    public function isInstalled()
    {
        return $this->moduleUtil->isModuleInstalled('InstallmentCredit');
    }

    public function isBnplPaymentMethod($business_id, $method)
    {
        return InstallmentCompany::findByPaymentMethod($business_id, $method) !== null;
    }

    /**
     * Manually create (or import) a pending receivable row.
     *
     * @param  array  $input  company_id|company_code, location_id|branch, invoice_no, invoice_date, due_date, due_amount, notes
     * @return InstallmentReceivable
     *
     * @throws \Exception
     */
    public function createManualPendingReceivable($business_id, array $input, $mark_imported = false)
    {
        $company = null;
        if (! empty($input['company_id'])) {
            $company = InstallmentCompany::where('business_id', $business_id)->find($input['company_id']);
        } elseif (! empty($input['company_code'])) {
            $code = strtolower(trim((string) $input['company_code']));
            if (in_array($code, ['true', 'tru', '1'], true)) {
                $code = 'tru';
            }
            $company = InstallmentCompany::where('business_id', $business_id)
                ->where(function ($q) use ($code) {
                    $q->where('code', $code)->orWhereRaw('LOWER(name) = ?', [$code]);
                })
                ->first();
        }

        if (! $company) {
            throw new \Exception(__('installmentcredit::lang.company_required'));
        }

        $due_amount = (float) ($input['due_amount'] ?? 0);
        if ($due_amount <= 0) {
            throw new \Exception(__('installmentcredit::lang.invalid_due_amount'));
        }

        $location_id = $input['location_id'] ?? null;
        if (empty($location_id) && ! empty($input['branch'])) {
            $location_id = $this->resolveLocationId($business_id, $input['branch']);
        }

        $invoice_no = trim((string) ($input['invoice_no'] ?? ''));
        $invoice_date = $this->parseFlexibleDate($input['invoice_date'] ?? null);
        $due_date = $this->parseFlexibleDate($input['due_date'] ?? null);
        if (! $due_date && $invoice_date) {
            $due_date = Carbon::parse($invoice_date)->addDays((int) $company->default_settlement_days)->toDateString();
        }
        if (! $due_date) {
            $due_date = Carbon::today()->addDays((int) $company->default_settlement_days)->toDateString();
        }
        if (! $invoice_date) {
            $invoice_date = Carbon::today()->toDateString();
        }

        $transaction_id = null;
        if ($invoice_no !== '') {
            $txn = Transaction::where('business_id', $business_id)
                ->where('type', 'sell')
                ->where('invoice_no', $invoice_no)
                ->first();
            $transaction_id = $txn->id ?? null;
            if ($txn && empty($location_id)) {
                $location_id = $txn->location_id;
            }
        }

        if ($invoice_no !== '' || $transaction_id) {
            $exists = InstallmentReceivable::where('business_id', $business_id)
                ->where('company_id', $company->id)
                ->where('status', 'pending')
                ->where(function ($q) use ($invoice_no, $transaction_id) {
                    if ($transaction_id) {
                        $q->where('transaction_id', $transaction_id);
                    } elseif ($invoice_no !== '') {
                        $q->where('invoice_no', $invoice_no);
                    } else {
                        $q->whereRaw('1=0');
                    }
                })
                ->exists();

            if ($exists) {
                throw new \Exception(__('installmentcredit::lang.duplicate_pending_invoice', ['invoice' => $invoice_no]));
            }
        }

        return InstallmentReceivable::create([
            'business_id' => $business_id,
            'location_id' => $location_id,
            'company_id' => $company->id,
            'transaction_id' => $transaction_id,
            'invoice_no' => $invoice_no !== '' ? $invoice_no : null,
            'invoice_date' => $invoice_date,
            'due_date' => $due_date,
            'due_amount' => $due_amount,
            'booked_settled_amount' => 0,
            'actual_received_amount' => 0,
            'status' => 'pending',
            'notes' => $input['notes'] ?? ($mark_imported ? 'Imported' : 'Manual entry'),
            'is_imported' => $mark_imported ? 1 : 0,
        ]);
    }

    public function resolveLocationId($business_id, $branch_name)
    {
        $branch = strtolower(trim((string) $branch_name));
        if ($branch === '') {
            return null;
        }

        $locations = \App\BusinessLocation::where('business_id', $business_id)->get();
        $location_map = [];
        foreach ($locations as $loc) {
            $location_map[strtolower(trim($loc->name))] = $loc->id;
            $aliases = [
                'biverlly hills', 'beverly hills', 'el shourok', 'el sherouk', 'alex', 'city stars',
            ];
            foreach ($aliases as $alias) {
                if (str_contains(strtolower($loc->name), explode(' ', $alias)[0]) || strtolower(trim($loc->name)) === $alias) {
                    $location_map[$alias] = $loc->id;
                }
            }
        }

        if (isset($location_map[$branch])) {
            return $location_map[$branch];
        }

        foreach ($location_map as $name => $id) {
            if (str_contains($name, $branch) || str_contains($branch, $name)) {
                return $id;
            }
        }

        return null;
    }

    public function parseFlexibleDate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\Carbon\Carbon::parse($value))->toDateString();
        }
        // Excel serial date
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                // fall through
            }
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function createReceivableFromPayment($payment, $transaction = null)
    {
        if (! $this->isInstalled()) {
            return null;
        }

        if (empty($payment) || empty($payment->method) || ! empty($payment->is_return)) {
            return null;
        }

        $business_id = $payment->business_id;
        $company = InstallmentCompany::findByPaymentMethod($business_id, $payment->method);
        if (! $company) {
            return null;
        }

        if (empty($transaction) && ! empty($payment->transaction_id)) {
            $transaction = Transaction::find($payment->transaction_id);
        }

        if (empty($transaction) || $transaction->type != 'sell') {
            return null;
        }

        $existing = InstallmentReceivable::where('business_id', $business_id)
            ->where('transaction_id', $transaction->id)
            ->where('company_id', $company->id)
            ->first();

        $invoice_date = Carbon::parse($transaction->transaction_date)->toDateString();
        $due_date = Carbon::parse($invoice_date)->addDays((int) $company->default_settlement_days)->toDateString();

        if ($existing) {
            // Revive cancelled rows when the payment is re-added; never overwrite settled
            if ($existing->status === 'settled') {
                return $existing;
            }
            $existing->transaction_payment_id = $payment->id;
            $existing->due_amount = $payment->amount;
            $existing->invoice_no = $transaction->invoice_no;
            $existing->location_id = $transaction->location_id;
            $existing->invoice_date = $invoice_date;
            $existing->due_date = $due_date;
            $existing->status = 'pending';
            $existing->booked_settled_amount = 0;
            $existing->actual_received_amount = 0;
            $existing->settled_on = null;
            $existing->save();

            return $existing;
        }

        return InstallmentReceivable::create([
            'business_id' => $business_id,
            'location_id' => $transaction->location_id,
            'company_id' => $company->id,
            'transaction_id' => $transaction->id,
            'transaction_payment_id' => $payment->id,
            'invoice_no' => $transaction->invoice_no,
            'invoice_date' => $invoice_date,
            'due_date' => $due_date,
            'due_amount' => $payment->amount,
            'booked_settled_amount' => 0,
            'actual_received_amount' => 0,
            'status' => 'pending',
        ]);
    }

    public function cancelReceivableForPayment($payment)
    {
        if (empty($payment) || empty($payment->id)) {
            return;
        }

        $receivables = InstallmentReceivable::where('transaction_payment_id', $payment->id)
            ->where('status', 'pending')
            ->get();

        foreach ($receivables as $recv) {
            if ((float) $recv->booked_settled_amount > 0) {
                continue;
            }
            $recv->status = 'cancelled';
            $recv->save();
        }
    }

    /**
     * Settle one or more pending receivables.
     *
     * @param  array  $lines  [ ['receivable_id' => int, 'amount_booked' => float, 'amount_received' => float], ... ]
     */
    public function settle(array $input, array $lines, $user_id)
    {
        return DB::transaction(function () use ($input, $lines, $user_id) {
            $business_id = $input['business_id'];
            $company_id = $input['company_id'];
            $settlement_date = $input['settlement_date'];
            $account_id = $input['account_id'] ?? null;
            $external_ref = $input['external_ref'] ?? null;
            $notes = $input['notes'] ?? null;
            $location_id = $input['location_id'] ?? null;

            $amount_booked = 0;
            $amount_received = 0;
            $prepared_lines = [];

            foreach ($lines as $line) {
                $recv = InstallmentReceivable::where('business_id', $business_id)
                    ->where('id', $line['receivable_id'])
                    ->where('company_id', $company_id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->firstOrFail();

                $booked = (float) $line['amount_booked'];
                $received = (float) $line['amount_received'];
                $outstanding = $recv->outstanding;

                if ($booked <= 0 || $booked > $outstanding + 0.0001) {
                    throw new \Exception(__('installmentcredit::lang.invalid_settlement_amount'));
                }

                $amount_booked += $booked;
                $amount_received += $received;
                $prepared_lines[] = compact('recv', 'booked', 'received');
            }

            $fee_amount = max(0, $amount_booked - $amount_received);

            $settlement = InstallmentSettlement::create([
                'business_id' => $business_id,
                'company_id' => $company_id,
                'location_id' => $location_id,
                'settlement_date' => $settlement_date,
                'amount_booked' => $amount_booked,
                'amount_received' => $amount_received,
                'fee_amount' => $fee_amount,
                'account_id' => $account_id,
                'external_ref' => $external_ref,
                'notes' => $notes,
                'created_by' => $user_id,
            ]);

            foreach ($prepared_lines as $pl) {
                /** @var InstallmentReceivable $recv */
                $recv = $pl['recv'];
                InstallmentSettlementLine::create([
                    'settlement_id' => $settlement->id,
                    'receivable_id' => $recv->id,
                    'amount_booked' => $pl['booked'],
                    'amount_received' => $pl['received'],
                ]);

                $recv->booked_settled_amount = (float) $recv->booked_settled_amount + $pl['booked'];
                $recv->actual_received_amount = (float) $recv->actual_received_amount + $pl['received'];
                if ($recv->outstanding <= 0.0001) {
                    $recv->status = 'settled';
                    $recv->settled_on = Carbon::parse($settlement_date)->endOfDay();
                }
                $recv->save();
            }

            // Credit cashbook for actual cash received
            if (! empty($account_id) && $amount_received > 0 && $this->moduleUtil->isModuleEnabled('account', $business_id)) {
                $at = AccountTransaction::createAccountTransaction([
                    'amount' => $amount_received,
                    'account_id' => $account_id,
                    'type' => 'credit',
                    'operation_date' => Carbon::parse($settlement_date)->toDateTimeString(),
                    'created_by' => $user_id,
                    'transaction_id' => null,
                    'note' => 'Installment settlement #'.$settlement->id.($external_ref ? ' / '.$external_ref : ''),
                ]);
                $settlement->account_transaction_id = $at->id;
                $settlement->save();
            }

            // Post BNPL fee as expense when fee > 0
            if ($fee_amount > 0) {
                $expense_txn = $this->createFeeExpense($business_id, $location_id, $fee_amount, $settlement_date, $user_id, $settlement);
                if ($expense_txn) {
                    $settlement->fee_expense_transaction_id = $expense_txn->id;
                    $settlement->save();
                }
            }

            return $settlement;
        });
    }

    protected function createFeeExpense($business_id, $location_id, $fee_amount, $settlement_date, $user_id, InstallmentSettlement $settlement)
    {
        $category = ExpenseCategory::firstOrCreate(
            [
                'business_id' => $business_id,
                'name' => 'BNPL Fees',
            ],
            [
                'code' => 'BNPL_FEE',
                'parent_id' => null,
            ]
        );

        $ref_count = $this->transactionUtil->setAndGetReferenceCount('expense', $business_id);
        $ref_no = $this->transactionUtil->generateReferenceNumber('expense', $ref_count, $business_id);

        $transaction = Transaction::create([
            'business_id' => $business_id,
            'location_id' => $location_id,
            'type' => 'expense',
            'status' => 'final',
            'payment_status' => 'paid',
            'transaction_date' => Carbon::parse($settlement_date)->toDateTimeString(),
            'total_before_tax' => $fee_amount,
            'final_total' => $fee_amount,
            'tax_amount' => 0,
            'expense_category_id' => $category->id,
            'additional_notes' => 'BNPL fee for installment settlement #'.$settlement->id,
            'created_by' => $user_id,
            'ref_no' => $ref_no,
        ]);

        // If settlement deposited to an account, debit same account for fee (net effect = received)
        if (! empty($settlement->account_id) && $this->moduleUtil->isModuleEnabled('account', $business_id)) {
            AccountTransaction::createAccountTransaction([
                'amount' => $fee_amount,
                'account_id' => $settlement->account_id,
                'type' => 'debit',
                'operation_date' => Carbon::parse($settlement_date)->toDateTimeString(),
                'created_by' => $user_id,
                'transaction_id' => $transaction->id,
                'note' => 'BNPL fee settlement #'.$settlement->id,
            ]);
        }

        return $transaction;
    }

    public function pendingByBranchCompany($business_id)
    {
        return InstallmentReceivable::query()
            ->select(
                'location_id',
                'company_id',
                DB::raw('SUM(due_amount - booked_settled_amount) as pending_total'),
                DB::raw('COUNT(*) as rows_count')
            )
            ->where('business_id', $business_id)
            ->where('status', 'pending')
            ->groupBy('location_id', 'company_id')
            ->with(['location', 'company'])
            ->get();
    }

    public function agingByCompany($business_id)
    {
        $rows = InstallmentReceivable::query()
            ->where('business_id', $business_id)
            ->where('status', 'pending')
            ->with('company')
            ->get();

        $buckets = [];
        foreach ($rows as $row) {
            $cid = $row->company_id;
            if (! isset($buckets[$cid])) {
                $buckets[$cid] = [
                    'company_id' => $cid,
                    'company_name' => $row->company ? $row->company->name : '',
                    'd30' => 0,
                    'd60' => 0,
                    'd90' => 0,
                    'd120' => 0,
                    'd_gt120' => 0,
                    'total' => 0,
                ];
            }
            $outstanding = $row->outstanding;
            // Positive = days past due; not-yet-due counts in the 0–30 bucket
            $days_overdue = 0;
            if ($row->due_date) {
                $days_overdue = (int) $row->due_date->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);
                if ($days_overdue < 0) {
                    $days_overdue = 0;
                }
            }

            if ($days_overdue <= 30) {
                $buckets[$cid]['d30'] += $outstanding;
            } elseif ($days_overdue <= 60) {
                $buckets[$cid]['d60'] += $outstanding;
            } elseif ($days_overdue <= 90) {
                $buckets[$cid]['d90'] += $outstanding;
            } elseif ($days_overdue <= 120) {
                $buckets[$cid]['d120'] += $outstanding;
            } else {
                $buckets[$cid]['d_gt120'] += $outstanding;
            }
            $buckets[$cid]['total'] += $outstanding;
        }

        return array_values($buckets);
    }
}
