<?php

namespace App\Console\Commands;

use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Console\Command;

/**
 * Replace saved storefront footer menus with the code defaults (Shop / My Account / Help / Company).
 */
class ResetStorefrontFooter extends Command
{
    protected $signature = 'storefront:reset-footer
        {--business_id= : Business id (defaults to config storefront.business_id)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Reseed storefront footer menus from defaults (prints the previous footer as a JSON backup)';

    public function handle(StorefrontSettingService $settings): int
    {
        $businessId = (int) ($this->option('business_id') ?: config('storefront.business_id'));
        if ($businessId < 1) {
            $this->error('No business id: pass --business_id or set STOREFRONT_BUSINESS_ID.');

            return self::FAILURE;
        }

        $current = $settings->get($businessId)['footer'] ?? null;
        $this->line('Current footer (keep this to restore via Storefront Settings → Footer):');
        $this->line(json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (! $this->option('force')
            && ! $this->confirm("Replace the footer menus for business #{$businessId} with the defaults?")) {
            $this->info('Aborted; nothing changed.');

            return self::SUCCESS;
        }

        $saved = $settings->saveFooter($businessId, $settings->defaultFooter());

        $columns = array_map(
            fn (array $col) => $col['title']['en'].' ('.count($col['links']).')',
            $saved['columns']
        );
        $this->info('Footer reset. Columns: '.implode(', ', $columns));

        return self::SUCCESS;
    }
}
