<?php

namespace App\Console\Commands;

use App\Product;
use App\Services\Storefront\CatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Backfill product slugs for storefront PDP URLs.
 */
class BackfillProductSlugs extends Command
{
    protected $signature = 'storefront:backfill-slugs {--business_id=1}';

    protected $description = 'Generate URL slugs for products missing a slug';

    public function handle(CatalogService $catalog): int
    {
        $businessId = (int) $this->option('business_id');
        $products = Product::where('business_id', $businessId)
            ->where(function ($q) {
                $q->whereNull('slug')->orWhere('slug', '');
            })
            ->get();

        foreach ($products as $product) {
            $product->slug = $catalog->generateSlug($product->name, $businessId, $product->id);
            $product->save();
            $this->line("Slug for #{$product->id}: {$product->slug}");
        }

        $this->info('Done. Updated '.$products->count().' products.');

        return self::SUCCESS;
    }
}
