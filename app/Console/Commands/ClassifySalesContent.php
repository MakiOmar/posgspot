<?php

namespace App\Console\Commands;

use App\Services\TransactionContentKindService;
use App\Transaction;
use Illuminate\Console\Command;

/**
 * Backfill / safety net for transactions.content_kind (accounts_only | mixed | pos_only).
 */
class ClassifySalesContent extends Command
{
    protected $signature = 'sales:classify-content
        {--recent : Only transactions updated in the last 2 days}
        {--chunk=500 : Transactions per grouped query}';

    protected $description = 'Tag sells and sales orders as accounts-only, mixed or POS-only for listing filters';

    public function handle(TransactionContentKindService $kinds): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $query = Transaction::query()
            ->whereIn('type', TransactionContentKindService::TYPES)
            ->select('id')
            ->orderBy('id');
        if ($this->option('recent')) {
            $query->where('updated_at', '>=', now()->subDays(2));
        }

        $updated = 0;
        $query->chunkById($chunk, function ($rows) use ($kinds, &$updated) {
            $updated += $kinds->refreshMany($rows->pluck('id')->map(fn ($id) => (int) $id)->all());
        });

        $this->info("Classified {$updated} transaction(s).");

        return self::SUCCESS;
    }
}
