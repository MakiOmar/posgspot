<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\DigitalCatalogService;
use Illuminate\Http\Request;

/**
 * Public digital catalog proxy (Accounts games / gift cards; credentials stay server-side).
 */
class DigitalCatalogController extends StorefrontController
{
    public function __construct(private DigitalCatalogService $catalog)
    {
    }

    public function games(Request $request)
    {
        $businessId = $this->businessId($request);
        if (! $this->catalog->isEnabled($businessId)) {
            return $this->jsonError('Digital catalog is not available.', 503);
        }

        $data = $request->validate([
            'platform' => 'required|in:4,5',
            'page' => 'nullable|integer|min:1',
        ]);

        $result = $this->catalog->listGames(
            $businessId,
            (string) $data['platform'],
            (int) ($data['page'] ?? 1)
        );

        if (! $result['success']) {
            return $this->jsonError($result['error'] ?? 'Failed to load games', (int) ($result['status'] ?: 502));
        }

        return $this->jsonSuccess($result['data']);
    }

    public function game(Request $request, int $id)
    {
        $businessId = $this->businessId($request);
        if (! $this->catalog->isEnabled($businessId)) {
            return $this->jsonError('Digital catalog is not available.', 503);
        }

        $result = $this->catalog->getGame($businessId, $id);
        if (! $result['success']) {
            return $this->jsonError($result['error'] ?? 'Game not found', (int) ($result['status'] ?: 404));
        }

        return $this->jsonSuccess($result['data']);
    }

    public function cardCategories(Request $request)
    {
        $businessId = $this->businessId($request);
        if (! $this->catalog->isEnabled($businessId)) {
            return $this->jsonError('Digital catalog is not available.', 503);
        }

        $result = $this->catalog->listCardCategories($businessId);
        if (! $result['success']) {
            return $this->jsonError($result['error'] ?? 'Failed to load gift cards', (int) ($result['status'] ?: 502));
        }

        return $this->jsonSuccess($result['data']);
    }

    public function checkGameStock(Request $request)
    {
        $businessId = $this->businessId($request);
        if (! $this->catalog->isEnabled($businessId)) {
            return $this->jsonError('Digital catalog is not available.', 503);
        }

        $data = $request->validate([
            'game_id' => 'required|integer|min:1',
            'type' => 'required|in:primary,secondary',
            'platform' => 'required|in:4,5',
            'store_profile_id' => 'nullable|integer|min:1',
        ]);

        $settings = app(\App\Services\Storefront\StorefrontSettingService::class)->get($businessId);
        $data['store_profile_id'] = (int) ($data['store_profile_id']
            ?? ($settings['digital']['accounts_store_profile_id'] ?? 17));

        $result = $this->catalog->checkGameStock($data);
        if (! $result['success']) {
            return $this->jsonError($result['error'] ?? 'Stock check failed', (int) ($result['status'] ?: 422));
        }

        return $this->jsonSuccess($result['body'] ?? ['available' => true]);
    }

    public function checkCardStock(Request $request)
    {
        $businessId = $this->businessId($request);
        if (! $this->catalog->isEnabled($businessId)) {
            return $this->jsonError('Digital catalog is not available.', 503);
        }

        $data = $request->validate([
            'card_category_id' => 'required|integer|min:1',
            'store_profile_id' => 'nullable|integer|min:1',
        ]);

        $settings = app(\App\Services\Storefront\StorefrontSettingService::class)->get($businessId);
        $data['store_profile_id'] = (int) ($data['store_profile_id']
            ?? ($settings['digital']['accounts_store_profile_id'] ?? 17));

        $result = $this->catalog->checkCardStock($data);
        if (! $result['success']) {
            return $this->jsonError($result['error'] ?? 'Stock check failed', (int) ($result['status'] ?: 422));
        }

        return $this->jsonSuccess($result['body'] ?? ['available' => true]);
    }
}
