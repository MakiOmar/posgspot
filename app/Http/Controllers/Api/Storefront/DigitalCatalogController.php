<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\DigitalCatalogService;
use App\Services\Storefront\DigitalGameOffer;
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
            'q' => 'nullable|string|max:120',
            'product_type' => 'nullable|in:game,subscription',
        ]);

        $productType = ($data['product_type'] ?? 'game') === 'subscription' ? 'subscription' : 'game';

        $result = $this->catalog->listGames(
            $businessId,
            (string) $data['platform'],
            (int) ($data['page'] ?? 1),
            isset($data['q']) ? (string) $data['q'] : null,
            $productType
        );

        if (! $result['success']) {
            // Soft-fail with empty list + debug so the Qwik page can explain why.
            $skus = $this->catalog->posSkuMap($businessId);

            return $this->jsonSuccess([
                'platform' => (string) $data['platform'],
                'product_type' => $productType,
                'skus' => $skus,
                'games' => [],
                'meta' => [
                    'current_page' => (int) ($data['page'] ?? 1),
                    'last_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                ],
                'debug' => $result['debug'] ?? [
                    'reason' => $result['error'] ?? 'Failed to load games',
                    'http_status' => (int) ($result['status'] ?: 502),
                    'accounts_ok' => false,
                ],
            ]);
        }

        return $this->jsonSuccess($result['data']);
    }

    public function featuredGames(Request $request)
    {
        $businessId = $this->businessId($request);
        if (! $this->catalog->isEnabled($businessId)) {
            return $this->jsonError('Digital catalog is not available.', 503);
        }

        $data = $request->validate([
            'count' => 'nullable|integer|min:1|max:50',
            'product_type' => 'nullable|in:game,subscription',
        ]);

        $productType = ($data['product_type'] ?? 'game') === 'subscription' ? 'subscription' : 'game';
        $count = (int) ($data['count'] ?? 10);

        $result = $this->catalog->getFeaturedGames($businessId, $count, $productType);
        if (! $result['success']) {
            return $this->jsonError($result['error'] ?? 'Failed to load featured games', (int) ($result['status'] ?: 502));
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
            'type' => 'required|in:primary,secondary,full',
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

        $body = is_array($result['body'] ?? null) ? $result['body'] : [];
        if (! DigitalGameOffer::checkStockIndicatesAvailable($body)) {
            return $this->jsonError('This offer is out of stock.', 422);
        }

        return $this->jsonSuccess($body);
    }

    /**
     * Proxy Accounts digital review submit (games or gift-card category).
     * Auth required — phone is taken from the signed-in contact.
     */
    public function submitReview(Request $request)
    {
        $businessId = $this->businessId($request);
        if (! $this->catalog->isEnabled($businessId)) {
            return $this->jsonError('Digital catalog is not available.', 503);
        }

        $contact = $request->user();
        $phone = trim((string) ($contact->mobile ?? ''));
        if ($phone === '') {
            return $this->jsonError('Add a phone number to your profile to leave a review.', 422);
        }

        $data = $request->validate([
            'stars' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'game_id' => 'nullable|integer|min:1',
            'card_category_id' => 'nullable|integer|min:1',
        ]);

        $hasGame = ! empty($data['game_id']);
        $hasCard = ! empty($data['card_category_id']);
        if ($hasGame === $hasCard) {
            return $this->jsonError('Provide exactly one of game_id or card_category_id.', 422);
        }

        $payload = [
            'phone' => $phone,
            'stars' => (int) $data['stars'],
            'comment' => (string) ($data['comment'] ?? ''),
        ];
        if ($hasGame) {
            $payload['game_id'] = (int) $data['game_id'];
        } else {
            $payload['card_category_id'] = (int) $data['card_category_id'];
        }

        $result = $this->catalog->submitReview($payload);
        if (! $result['success']) {
            $status = (int) ($result['status'] ?: 422);
            $message = $result['error'] ?? 'Failed to submit review';
            if (is_array($result['body'] ?? null) && isset($result['body']['message'])) {
                $message = (string) $result['body']['message'];
            }

            return $this->jsonError($message, $status);
        }

        return $this->jsonSuccess($result['body'] ?? [
            'message' => 'Review submitted and pending approval.',
        ], [], (int) ($result['status'] ?: 201));
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
