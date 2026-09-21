<?php

namespace App\Console\Commands;

use App\Services\Storefront\StorefrontImageWebpBulkService;
use App\Support\ImageWebpConverter;
use Illuminate\Console\Command;

/**
 * Convert existing storefront + catalog raster images to WebP and update DB paths.
 */
class ConvertStorefrontImagesToWebp extends Command
{
    protected $signature = 'storefront:convert-images-to-webp
                            {business_id? : Business ID (default: all businesses with storefront settings or catalog)}
                            {--dry-run : Report conversions without writing files or DB}
                            {--keep-originals : Keep source JPEG/PNG/GIF beside the new WebP}
                            {--verbose-details : Print every converted path}
                            {--only=all : Scope: all|storefront|catalog (catalog = products/categories/brands/media)}';

    protected $description = 'Bulk-convert storefront + product/category images to WebP (incl. remote promo tile URLs) and rewrite stored paths';

    public function handle(
        StorefrontImageWebpBulkService $bulk,
        ImageWebpConverter $webp
    ): int {
        if (! $webp->isAvailable()) {
            $this->error('PHP GD imagewebp() is not available. Enable the GD WebP extension first.');

            return self::FAILURE;
        }

        $only = strtolower((string) $this->option('only') ?: 'all');
        if (! in_array($only, ['all', 'storefront', 'catalog'], true)) {
            $this->error('Invalid --only value. Use all, storefront, or catalog.');

            return self::FAILURE;
        }

        $arg = $this->argument('business_id');
        $ids = ($arg !== null && $arg !== '')
            ? [(int) $arg]
            : $bulk->allBusinessIds();

        if ($ids === []) {
            $this->info('No business IDs found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $keepOriginals = (bool) $this->option('keep-originals');
        $verbose = (bool) $this->option('verbose-details');

        if ($dryRun) {
            $this->warn('Dry run — no files or database rows will be changed.');
        }
        $this->line('Scope: '.$only);

        $totals = [
            'converted' => 0,
            'skipped' => 0,
            'failed' => 0,
            'rewritten_settings' => 0,
            'rewritten_media' => 0,
            'rewritten_catalog' => 0,
            'rewritten_remote' => 0,
        ];

        foreach ($ids as $id) {
            try {
                $result = $bulk->convertBusiness($id, $dryRun, $keepOriginals, $only);
            } catch (\Throwable $e) {
                $this->error('business_id='.$id.' failed: '.$e->getMessage());

                return self::FAILURE;
            }

            $this->info(sprintf(
                'business_id=%d converted=%d skipped=%d failed=%d settings=%d library=%d catalog=%d remote=%d%s',
                $result['business_id'],
                $result['converted'],
                $result['skipped'],
                $result['failed'],
                $result['rewritten_settings'],
                $result['rewritten_media'],
                $result['rewritten_catalog'],
                $result['rewritten_remote'],
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
            $totals['rewritten_catalog'] += $result['rewritten_catalog'];
            $totals['rewritten_remote'] += $result['rewritten_remote'];
        }

        $this->newLine();
        $this->info(sprintf(
            'Totals: converted=%d skipped=%d failed=%d settings=%d library=%d catalog=%d remote=%d',
            $totals['converted'],
            $totals['skipped'],
            $totals['failed'],
            $totals['rewritten_settings'],
            $totals['rewritten_media'],
            $totals['rewritten_catalog'],
            $totals['rewritten_remote']
        ));

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
