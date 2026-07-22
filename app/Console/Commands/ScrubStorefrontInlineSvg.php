<?php

namespace App\Console\Commands;

use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remove leftover inline SVG / oversized strings from storefront_settings (OOM fix).
 */
class ScrubStorefrontInlineSvg extends Command
{
    protected $signature = 'storefront:scrub-inline-svg
                            {business_id? : Business ID (default: all rows)}
                            {--inspect : Only report largest string paths; do not write}';

    protected $description = 'Strip oversized/inline SVG junk from storefront_settings to fix OOM on save';

    public function handle(StorefrontSettingService $settings): int
    {
        $businessId = $this->argument('business_id');
        $ids = [];
        if ($businessId !== null && $businessId !== '') {
            $ids[] = (int) $businessId;
        } else {
            $ids = DB::table('storefront_settings')->orderBy('business_id')->pluck('business_id')->all();
        }

        if ($ids === []) {
            $this->info('No storefront_settings rows found.');

            return self::SUCCESS;
        }

        $inspect = (bool) $this->option('inspect');

        foreach ($ids as $id) {
            $id = (int) $id;
            try {
                if ($inspect) {
                    $info = $settings->inspectSettingsBlob($id);
                    $this->info(sprintf(
                        'business_id=%d blob_bytes=%d raw_type=%s',
                        $info['business_id'],
                        $info['blob_bytes'],
                        $info['raw_type']
                    ));
                    foreach ($info['largest'] as $path => $bytes) {
                        $this->line(sprintf('  %s => %d bytes', $path, $bytes));
                    }
                    if ($info['largest'] === []) {
                        $this->line('  (no string fields > 1KB found, or blob is opaque string — try scrub without --inspect)');
                    }
                    continue;
                }

                $result = $settings->scrubInlineSvgFromStoredSettings($id);
                $this->info(sprintf(
                    'business_id=%d before=%d after=%d removed_keys=%d reset_homepage=%s raw_type=%s',
                    $result['business_id'],
                    $result['before_bytes'],
                    $result['after_bytes'],
                    $result['removed_keys'],
                    $result['reset_homepage_sections'] ? 'yes' : 'no',
                    $result['raw_type']
                ));
                foreach ($result['cleared_strings'] as $path => $bytes) {
                    $this->line(sprintf('  cleared %s (%d bytes)', $path, $bytes));
                }
            } catch (\Throwable $e) {
                $this->error('business_id='.$id.' failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        if (! $inspect) {
            $this->warn('If homepage was reset, re-build it in Storefront Settings → Homepage (media library only).');
            $this->warn('Also run: php artisan cache:clear');
        }

        return self::SUCCESS;
    }
}
