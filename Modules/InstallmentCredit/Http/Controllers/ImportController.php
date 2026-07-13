<?php

namespace Modules\InstallmentCredit\Http\Controllers;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\InstallmentCredit\Http\Controllers\Concerns\AuthorizesInstallmentModule;
use Modules\InstallmentCredit\Utils\InstallmentCreditUtil;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    use AuthorizesInstallmentModule;

    public function __construct(
        protected ModuleUtil $moduleUtil,
        protected Util $commonUtil,
        protected InstallmentCreditUtil $installmentUtil
    ) {
    }

    public function index()
    {
        $this->assertModuleAllowed('installment.import');

        return view('installmentcredit::import.index');
    }

    /**
     * CSV template (legacy / Excel-compatible).
     */
    public function template()
    {
        $this->assertModuleAllowed('installment.import');

        return new StreamedResponse(function () {
            $h = fopen('php://output', 'w');
            fputcsv($h, $this->templateHeaders());
            fputcsv($h, $this->templateSampleRow());
            fclose($h);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="installment-import-template.csv"',
        ]);
    }

    /**
     * Downloadable .xlsx sample for Excel users.
     */
    public function templateXlsx()
    {
        $this->assertModuleAllowed('installment.import');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Open Balances');

        $sheet->fromArray([
            $this->templateHeaders(),
            $this->templateSampleRow(),
            [
                '2026-06-15', '2026-07-05', '24631', 'Water Way', 'maylo',
                '27500', 'Sample Maylo pending',
            ],
        ], null, 'A1', true);

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="installment-import-sample.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function store(Request $request)
    {
        $business_id = $this->assertModuleAllowed('installment.import');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        $path = $request->file('file')->getRealPath();
        $ext = strtolower($request->file('file')->getClientOriginalExtension());

        try {
            $rows = in_array($ext, ['xlsx', 'xls'], true)
                ? $this->readSpreadsheetRows($path)
                : $this->readCsvRows($path);
        } catch (\Exception $e) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => $e->getMessage(),
            ]);
        }

        if (empty($rows)) {
            return redirect()->back()->with('status', ['success' => 0, 'msg' => 'Empty file']);
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $pending_total = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $row_num => $row) {
                try {
                    $recv = $this->installmentUtil->createManualPendingReceivable($business_id, [
                        'company_code' => $row['company_code'] ?? $row['company'] ?? null,
                        'branch' => $row['branch'] ?? $row['business_location'] ?? null,
                        'invoice_no' => $row['invoice_no'] ?? $row['inv.no'] ?? $row['inv_no'] ?? null,
                        'invoice_date' => $row['invoice_date'] ?? null,
                        'due_date' => $row['due_date'] ?? null,
                        'due_amount' => (float) str_replace(',', '', (string) ($row['due_amount'] ?? $row['total'] ?? 0)),
                        'notes' => $row['notes'] ?? 'Imported from file',
                    ], true);
                    $imported++;
                    $pending_total += (float) $recv->due_amount;
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = 'Row '.($row_num + 2).': '.$e->getMessage();
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

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
            'success' => $imported > 0 ? 1 : 0,
            'msg' => $msg,
        ]);
    }

    protected function templateHeaders(): array
    {
        return [
            'invoice_date', 'due_date', 'invoice_no', 'branch', 'company_code',
            'due_amount', 'notes',
        ];
    }

    protected function templateSampleRow(): array
    {
        return [
            '2026-05-01', '2026-05-21', '12345', 'Nasr City', 'value',
            '10000', 'Open balance sample',
        ];
    }

    protected function readCsvRows($path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return [];
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count(array_filter($data, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $data[$i] ?? null;
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    protected function readSpreadsheetRows($path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);
        if (empty($matrix)) {
            return [];
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $matrix[0]);
        $rows = [];
        for ($r = 1; $r < count($matrix); $r++) {
            $data = $matrix[$r];
            if (count(array_filter($data, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = $data[$i] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
