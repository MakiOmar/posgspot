<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CustomBundleService;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;

class CustomBundleController extends StorefrontController
{
    public function __construct(private CustomBundleService $customBundle)
    {
    }

    public function meta(Request $request)
    {
        if (! $this->customBundle->isEnabled()) {
            return $this->jsonError('Custom Bundle is not available.', 404);
        }

        $locale = StorefrontLocale::fromRequest($request);

        return $this->jsonSuccess($this->customBundle->meta($locale));
    }

    public function products(Request $request)
    {
        if (! $this->customBundle->isEnabled()) {
            return $this->jsonError('Custom Bundle is not available.', 404);
        }

        $validated = $request->validate([
            'platform' => 'required|string|in:ps4,ps5',
            'tab' => 'nullable|string|in:all,consoles,accessories,games',
            'q' => 'nullable|string|max:120',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $perPage = min(50, max(1, (int) ($validated['per_page'] ?? 20)));
        $locale = StorefrontLocale::fromRequest($request);
        $paginator = $this->customBundle->listProducts(
            $this->businessId($request),
            [
                'platform' => $validated['platform'],
                'tab' => $validated['tab'] ?? 'all',
                'q' => $validated['q'] ?? null,
            ],
            $perPage,
            $locale
        );

        return $this->jsonSuccess($paginator->items(), [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'min_items' => $this->customBundle->minItems(),
            'max_items' => $this->customBundle->maxItems(),
        ]);
    }
}
