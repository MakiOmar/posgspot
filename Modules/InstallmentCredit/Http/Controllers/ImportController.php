<?php

namespace Modules\InstallmentCredit\Http\Controllers;

use App\BusinessLocation;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\InstallmentCredit\Entities\InstallmentCompany;
use Modules\InstallmentCredit\Entities\InstallmentReceivable;
use Modules\InstallmentCredit\Http\Controllers\Concerns\AuthorizesInstallmentModule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    use AuthorizesInstallmentModule;

    public function __construct(protected ModuleUtil $moduleUtil, protected Util $commonUtil)
    {
    }

    public function index()
    {
        $this->assertModuleAllowed('installment.import');

        return view('installmentcredit::import.index');
    }

    public function template()
    {
        $this->assertModuleAllowed('installment.import');

        return new StreamedResponse(function () {
            $h = fopen('php://output', 'w');
            fputcsv($h, [
                'invoice_date', 'due_date', 'invoice_no', 'branch', 'company_code',
                'due_amount', 'notes',
            ]);
            fputcsv($h, [
                '2026-05-01', '2026-05-21', '12345', 'Nasr City', 'value',
                '10000', 'Open balance from Excel',
            ]);
            fclose($h);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="installment-import-template.csv"',
        ]);
    }

    public function store(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.import');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $companies = InstallmentCompany::where('business_id', $business_id)->get()->keyBy(function ($c) {
            return strtolower($c->code);
        });
        $companies_by_name = InstallmentCompany::where('business_id', $business_id)->get()->keyBy(function ($c) {
            return strtolower(trim($c->name));
        });

        $locations = BusinessLocation::where('business_id', $business_id)->get();
        $location_map = [];
        foreach ($locations as $loc) {
            $location_map[strtolower(trim($loc->name))] = $loc->id;
            // Excel aliases
            $aliases = [
                'biverlly hills' => $loc->id,
                'beverly hills' => $loc->id,
                'el shourok' => $loc->id,
                'el sherouk' => $loc->id,
                'alex' => $loc->id,
                'city stars' => $loc->id,
            ];
            foreach ($aliases as $alias => $id) {
                if (str_contains(strtolower($loc->name), explode(' ', $alias)[0]) || strtolower(trim($loc->name)) === $alias) {
                    $location_map[$alias] = $loc->id;
                }
            }
        }

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            return redirect()->back()->with('status', ['success' => 0, 'msg' => 'Empty file']);
        }
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $pending_total = 0;

        DB::beginTransaction();
        try {
            $row_num = 1;
            while (($data = fgetcsv($handle)) !== false) {
                $row_num++;
                if (count(array_filter($data)) === 0) {
                    continue;
                }
                $row = [];
                foreach ($header as $i => $key) {
                    $row[$key] = $data[$i] ?? null;
                }

                $company_code = strtolower(trim((string) ($row['company_code'] ?? $row['company'] ?? '')));
                // Normalize Tru variants from Excel
                if (in_array($company_code, ['true', 'tru', '1'], true)) {
                    $company_code = 'tru';
                }

                $company = $companies->get($company_code) ?: $companies_by_name->get($company_code);
                if (! $company) {
                    $skipped++;
                    $errors[] = "Row {$row_num}: unknown company '{$company_code}'";
                    continue;
                }

                $branch = strtolower(trim((string) ($row['branch'] ?? $row['business_location'] ?? '')));
                $location_id = $location_map[$branch] ?? null;
                if (! $location_id) {
                    // fuzzy contains
                    foreach ($location_map as $name => $id) {
                        if ($branch !== '' && (str_contains($name, $branch) || str_contains($branch, $name))) {
                            $location_id = $id;
                            break;
                        }
                    }
                }

                $due_amount = (float) str_replace(',', '', (string) ($row['due_amount'] ?? $row['total'] ?? 0));
                if ($due_amount <= 0) {
                    $skipped++;
                    continue;
                }

                $invoice_no = trim((string) ($row['invoice_no'] ?? $row['inv.no'] ?? $row['inv_no'] ?? ''));
                $invoice_date = $this->parseDate($row['invoice_date'] ?? null);
                $due_date = $this->parseDate($row['due_date'] ?? null);
                if (! $due_date && $invoice_date) {
                    $due_date = Carbon::parse($invoice_date)->addDays((int) $company->default_settlement_days)->toDateString();
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

                // Avoid duplicate open import for same invoice+company
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
                    $skipped++;
                    $errors[] = "Row {$row_num}: duplicate pending for invoice {$invoice_no}";
                    continue;
                }

                // Unique constraint requires transaction_id — for imports without match use null uniquely via notes key
                // MySQL unique allows multiple NULLs in transaction_id
                InstallmentReceivable::create([
                    'business_id' => $business_id,
                    'location_id' => $location_id,
                    'company_id' => $company->id,
                    'transaction_id' => $transaction_id,
                    'invoice_no' => $invoice_no ?: null,
                    'invoice_date' => $invoice_date,
                    'due_date' => $due_date,
                    'due_amount' => $due_amount,
                    'booked_settled_amount' => 0,
                    'actual_received_amount' => 0,
                    'status' => 'pending',
                    'notes' => $row['notes'] ?? 'Imported from Excel',
                    'is_imported' => 1,
                ]);

                $imported++;
                $pending_total += $due_amount;
            }
            fclose($handle);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);

            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => $e->getMessage(),
            ]);
        }

        $msg = __('installmentcredit::lang.import_result', [
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => $this->commonUtil->num_f($pending_total),
        ]);
        if (! empty($errors)) {
            $msg .= ' | '.implode('; ', array_slice($errors, 0, 10));
        }

        return redirect('/installment-credit/receivables')->with('status', [
            'success' => 1,
            'msg' => $msg,
        ]);
    }

    protected function parseDate($value)
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }
}
