<?php

namespace App\Console\Commands;

use App\Services\Storefront\StorefrontSettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remove leftover inline SVG markup from storefront_settings JSON blobs (OOM fix).
 */
class ScrubStorefrontInlineSvg extends Command
{
    protected $signature = 'storefront:scrub-inline-svg {business_id? : Business ID (default: all rows)}';

    protected $description = 'Strip svg_markup / svg_markup_b64 from storefront_settings to fix OOM on save';

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

        foreach ($ids as $id) {
            $id = (int) $id;
            try {
                $result = $settings->scrubInlineSvgFromStoredSettings($id);
                $this->info(sprintf(
                    'business_id=%d before=%d after=%d removed_keys=%d',
                    $result['business_id'],
                    $result['before_bytes'],
                    $result['after_bytes'],
                    $result['removed_keys']
                ));
            } catch (\Throwable $e) {
                $this->error('business_id='.$id.' failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
