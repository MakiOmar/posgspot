<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CommunityPostService;
use App\StorefrontCommunityPost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommunityPostController extends StorefrontController
{
    public function __construct(private CommunityPostService $community)
    {
    }

    public function index(Request $request)
    {
        if (! $this->community->isEnabled()) {
            return $this->jsonError('Community is not available.', 404);
        }

        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(StorefrontCommunityPost::TYPES)],
            'scope' => ['nullable', 'string', Rule::in(['upcoming', 'previous'])],
        ]);

        $locale = $this->community->localeFromRequest($request);
        $items = $this->community->list(
            $this->businessId($request),
            $locale,
            $validated['type'] ?? null,
            $validated['scope'] ?? null
        );

        return $this->jsonSuccess($items);
    }

    public function show(Request $request, string $slug)
    {
        if (! $this->community->isEnabled()) {
            return $this->jsonError('Community is not available.', 404);
        }

        $locale = $this->community->localeFromRequest($request);
        $item = $this->community->showBySlug($this->businessId($request), $locale, $slug);
        if ($item === null) {
            return $this->jsonError('Post not found.', 404);
        }

        return $this->jsonSuccess($item);
    }
}
