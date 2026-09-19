<?php

namespace Database\Seeders;

use App\Services\Storefront\CommunityDemoContent;
use Illuminate\Database\Seeder;

/**
 * Optional Community CMS demo content. Prefer:
 *   php artisan storefront:community-demo seed
 * Not called from DatabaseSeeder by default.
 */
class StorefrontCommunityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $businessId = (int) config('storefront.business_id', 1);
        $count = app(CommunityDemoContent::class)->seed($businessId);
        $this->command?->info("Seeded {$count} demo community posts (business {$businessId}).");
    }
}
