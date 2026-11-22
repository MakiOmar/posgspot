<?php

namespace App\Console\Commands;

use App\Services\Storefront\StorefrontUnpaidOrderPruner;
use Illuminate\Console\Command;

/**
 * Delete abandoned unpaid online website orders past the configured waiting time.
 */
class PruneUnpaidStorefrontOrders extends Command
{
    protected $signature = 'storefront:prune-unpaid-orders
        {--business_id= : Limit to one business id}
        {--hours= : Override waiting hours (still requires > 0)}
        {--limit=500 : Max deletes per run}
        {--dry-run : Count/list candidates without deleting}';

    protected $description = 'Delete unpaid online storefront checkouts that never completed payment after the waiting time';

    public function handle(StorefrontUnpaidOrderPruner $pruner): int
    {
        $onlyBusiness = $this->option('business_id');
        $onlyBusinessId = ($onlyBusiness !== null && $onlyBusiness !== '')
            ? (int) $onlyBusiness
            : null;
        $hoursOverride = $this->option('hours');
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $totalCandidates = 0;
        $totalDeleted = 0;
        $totalSkipped = 0;

        foreach ($pruner->businessIdsToScan($onlyBusinessId) as $businessId) {
            $hours = ($hoursOverride !== null && $hoursOverride !== '')
                ? max(0, (int) $hoursOverride)
                : $pruner->waitingHours($businessId);

            if ($hours <= 0) {
                $this->line("Business {$businessId}: unpaid auto-delete disabled (0 hours).");

                continue;
            }

            $result = $pruner->prune($businessId, $hours, $limit, $dryRun);
            $totalCandidates += $result['candidates'];
            $totalDeleted += $result['deleted'];
            $totalSkipped += $result['skipped'];

            $mode = $dryRun ? 'dry-run' : 'deleted';
            $this->info(sprintf(
                'Business %d (>%d h): candidates=%d %s=%d skipped=%d',
                $businessId,
                $hours,
                $result['candidates'],
                $mode,
                $dryRun ? $result['candidates'] : $result['deleted'],
                $result['skipped']
            ));

            if ($dryRun && ! empty($result['ids'])) {
                $this->line('  ids: '.implode(', ', array_slice($result['ids'], 0, 50)).(
                    count($result['ids']) > 50 ? '…' : ''
                ));
            }
        }

        $this->info(sprintf(
            'Done. candidates=%d deleted=%d skipped=%d%s',
            $totalCandidates,
            $totalDeleted,
            $totalSkipped,
            $dryRun ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }
}
