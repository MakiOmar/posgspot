<?php

namespace App\Console\Commands;

use App\Services\Storefront\StorefrontImageWebpBulkService;
use App\Support\ImageWebpConverter;
use Illuminate\Console\Command;

/**
 * Convert existing storefront setting / library raster images to WebP and update DB paths.
 */
class ConvertStorefrontImagesToWebp extends Command
{
    protected $signature = 'storefront:convert-images-to-webp
                            {business_id? : Business ID (default: all storefront_settings rows)}
                            {--dry-run : Report conversions without writing files or DB}
                            {--keep-originals : Keep source JPEG/PNG/GIF beside the new WebP}
                            {--verbose-details : Print every converted path}';

    protected $description = 'Bulk-convert storefront settings/library images to WebP and rewrite stored paths';

    public function handle(
        StorefrontImageWebpBulkService $bulk,
        ImageWebpConverter $webp
    ): int {
        if (! $webp->isAvailable()) {
            $this->error('PHP GD imagewebp() is not available. Enable the GD WebP extension first.');

            return self::FAILURE;
        }

        $arg = $this->argument('business_id');
        $ids = ($arg !== null && $arg !== '')
            ? [(int) $arg]
            : $bulk->allBusinessIds();

        if ($ids === []) {
            $this->info('No storefront_settings rows found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $keepOriginals = (bool) $this->option('keep-originals');
        $verbose = (bool) $this->option('verbose-details');

        if ($dryRun) {
            $this->warn('Dry run — no files or database rows will be changed.');
        }

        $totals = [
            'converted' => 0,
            'skipped' => 0,
            'failed' => 0,
            'rewritten_settings' => 0,
            'rewritten_media' => 0,
        ];

        foreach ($ids as $id) {
            try {
                $result = $bulk->convertBusiness($id, $dryRun, $keepOriginals);
            } catch (\Throwable $e) {
                $this->error('business_id='.$id.' failed: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->info(sprintf(
                'business_id=%d converted=%d skipped=%d failed=%d settings_refs=%d media_rows=%d%s',
                $result['business_id'],
                $result['converted'],
                $result['skipped'],
                $result['failed'],
                $result['rewritten_settings'],
                $result['rewritten_media'],
                $dryRun ? ' (dry-run)' : ''
            ));

            if ($verbose) {
                foreach ($result['details'] as $line) {
                    $this->line('  '.$line);
                }
            }

            $totals['converted'] += $result['converted'];
            $totals['skipped'] += $result['skipped'];
            $totals['failed'] += $result['failed'];
            $totals['rewritten_settings'] += $result['rewritten_settings'];
            $totals['rewritten_media'] += $result['rewritten_media'];
        }

        $this->newLine();
        $this->info(sprintf(
            'Totals: converted=%d skipped=%d failed=%d settings_refs=%d media_rows=%d',
            $totals['converted'],
            $totals['skipped'],
            $totals['failed'],
            $totals['rewritten_settings'],
            $totals['rewritten_media']
        ));

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
