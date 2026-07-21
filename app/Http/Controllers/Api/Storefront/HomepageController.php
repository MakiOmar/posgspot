<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\Homepage\HomepageSectionService;
use App\Services\Storefront\StorefrontSettingService;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;

/**
 * Public homepage composition for Qwik + mobile.
 */
class HomepageController extends StorefrontController
{
    public function __construct(
        private StorefrontSettingService $settings,
        private HomepageSectionService $homepage
    ) {
    }

    public function show(Request $request)
    {
        $locale = StorefrontLocale::fromRequest($request);
        $all = $this->settings->get($this->businessId($request));
        $sections = $all['homepage_sections'] ?? [];

        return $this->jsonSuccess([
            'sections' => $this->homepage->presentForApi(
                is_array($sections) ? $sections : [],
                $locale
            ),
        ]);
    }
}
