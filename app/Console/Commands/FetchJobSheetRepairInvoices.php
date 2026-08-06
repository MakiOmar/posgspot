<?php

namespace App\Console\Commands;

use App\Transaction;
use Illuminate\Console\Command;
use Modules\Repair\Entities\JobSheet;

/**
 * List repair sell invoices linked to a job sheet (transactions.repair_job_sheet_id).
 */
class FetchJobSheetRepairInvoices extends Command
{
    protected $signature = 'repair:job-sheet-invoices
                            {job_sheet_id : Job sheet id (repair_job_sheets.id)}
                            {--all-types : Include non-repair / non-final transactions linked to the job sheet}
                            {--json : Output raw JSON instead of a table}';

    protected $description = 'Fetch repair invoices linked to a job sheet via repair_job_sheet_id.';

    public function handle(): int
    {
        $jobSheetId = (int) $this->argument('job_sheet_id');
        $includeAllTypes = (bool) $this->option('all-types');

        $jobSheet = JobSheet::query()->find($jobSheetId);

        if (! $jobSheet) {
            $this->error("Job sheet #{$jobSheetId} not found.");

            return self::FAILURE;
        }

        $this->line('');
        $this->info("=== Repair invoices for job sheet #{$jobSheet->id} ({$jobSheet->job_sheet_no}) ===");
        $this->line("business_id: {$jobSheet->business_id}");
        $this->line("contact_id: {$jobSheet->contact_id}");
        $this->line("status_id: {$jobSheet->status_id}");
        $this->line("created_at: {$jobSheet->created_at}");
        $this->line('');

        $query = Transaction::query()
            ->where('repair_job_sheet_id', $jobSheetId)
            ->orderByDesc('id');

        if (! $includeAllTypes) {
            $query->where('type', 'sell')
                ->where('sub_type', 'repair')
                ->where('status', 'final');
        }

        $invoices = $query->get([
            'id',
            'business_id',
            'invoice_no',
            'type',
            'sub_type',
            'status',
            'payment_status',
            'transaction_date',
            'final_total',
            'contact_id',
            'location_id',
            'created_by',
            'created_at',
            'repair_job_sheet_id',
        ]);

        if ($invoices->isEmpty()) {
            $this->warn('No invoices found for this job sheet'
                . ($includeAllTypes ? '.' : ' (filtered: type=sell, sub_type=repair, status=final).'));
            $this->line('Tip: re-run with --all-types to include any linked transactions.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($invoices->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('Found '.$invoices->count().' invoice(s):');
        $this->table(
            [
                'id',
                'invoice_no',
                'type',
                'sub_type',
                'status',
                'payment_status',
                'final_total',
                'transaction_date',
                'contact_id',
                'location_id',
                'created_at',
            ],
            $invoices->map(fn (Transaction $t) => [
                $t->id,
                $t->invoice_no,
                $t->type,
                $t->sub_type,
                $t->status,
                $t->payment_status,
                $t->final_total,
                (string) $t->transaction_date,
                $t->contact_id,
                $t->location_id,
                (string) $t->created_at,
            ])->all()
        );

        return self::SUCCESS;
    }
}
