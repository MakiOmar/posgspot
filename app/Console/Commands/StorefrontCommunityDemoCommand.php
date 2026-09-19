<?php

namespace App\Console\Commands;

use App\Services\Storefront\CommunityDemoContent;
use Illuminate\Console\Command;

/**
 * Seed or wipe Community CMS demo posts (slug prefix {@see CommunityDemoContent::SLUG_PREFIX}).
 */
class StorefrontCommunityDemoCommand extends Command
{
    protected $signature = 'storefront:community-demo
                            {action=seed : seed|wipe}
                            {--business_id= : Storefront business id (defaults to config storefront.business_id)}';

    protected $description = 'Seed or wipe wipeable Community CMS demo content (news, tournaments, events)';

    public function handle(CommunityDemoContent $demo): int
    {
        $action = strtolower(trim((string) $this->argument('action')));
        $businessId = (int) ($this->option('business_id') ?: config('storefront.business_id', 1));

        if (! in_array($action, ['seed', 'wipe'], true)) {
            $this->error('Action must be seed or wipe.');

            return self::FAILURE;
        }

        if ($action === 'wipe') {
            $removed = $demo->wipe($businessId);
            $this->info("Wiped {$removed} demo community post(s) for business {$businessId}.");

            return self::SUCCESS;
        }

        $created = $demo->seed($businessId);
        $this->info("Seeded {$created} demo community post(s) for business {$businessId}.");
        $this->line('Wipe later with: php artisan storefront:community-demo wipe');

        return self::SUCCESS;
    }
}
