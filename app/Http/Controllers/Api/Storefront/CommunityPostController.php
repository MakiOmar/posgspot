<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\CommunityPostService;
use App\Services\Storefront\TurnstileService;
use App\StorefrontCommunityApplication;
use App\StorefrontCommunityPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunityPostController extends StorefrontController
{
    public function __construct(
        private CommunityPostService $community,
        private TurnstileService $turnstile
    ) {
    }

    public function index(Request $request)
    {
        if (! $this->community->isEnabled()) {
            return $this->jsonError('Community is not available.', 404);
        }

        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(StorefrontCommunityPost::TYPES)],
            'scope' => ['nullable', 'string', Rule::in(['upcoming', 'previous'])],
            'q' => ['nullable', 'string', 'max:191'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $locale = $this->community->localeFromRequest($request);
        $limit = (int) ($validated['limit'] ?? 48);
        $page = (int) ($validated['page'] ?? 1);
        $items = $this->community->list(
            $this->businessId($request),
            $locale,
            $validated['type'] ?? null,
            $validated['scope'] ?? null,
            $validated['q'] ?? null,
            $limit,
            $page
        );

        return $this->jsonSuccess($items, [
            'page' => $page,
            'limit' => max(1, min(100, $limit)),
            'count' => count($items),
        ]);
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

    public function apply(Request $request, string $slug)
    {
        if (! $this->community->isEnabled()) {
            return $this->jsonError('Community is not available.', 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:191',
            'mobile' => 'required|string|max:50',
            'dial_code' => 'nullable|string|max:10',
            'source' => ['nullable', 'string', Rule::in([
                StorefrontCommunityApplication::SOURCE_WEB,
                StorefrontCommunityApplication::SOURCE_MOBILE,
            ])],
            'turnstile_token' => 'nullable|string',
        ]);

        $businessId = $this->businessId($request);
        $turnstileError = $this->turnstile->validate(
            $businessId,
            $data['turnstile_token'] ?? null,
            $request->ip()
        );
        if ($turnstileError !== null) {
            return $this->jsonError($turnstileError, 422, ['turnstile_token' => [$turnstileError]]);
        }

        $mobile = trim((string) $data['mobile']);
        $dial = trim((string) ($data['dial_code'] ?? ''));
        if ($dial !== '' && ! str_starts_with($mobile, '+') && ! str_starts_with($mobile, $dial)) {
            $mobile = $dial.ltrim($mobile, '0');
        }

        try {
            $contact = Auth::guard('sanctum')->user();
            $result = $this->community->apply(
                $businessId,
                $this->community->localeFromRequest($request),
                $slug,
                (string) $data['name'],
                $mobile,
                (string) ($data['source'] ?? StorefrontCommunityApplication::SOURCE_WEB),
                $contact?->id ? (int) $contact->id : null
            );
        } catch (ValidationException $e) {
            $messages = $e->errors();
            if (isset($messages['slug'])) {
                return $this->jsonError('Post not found.', 404);
            }
            throw $e;
        }

        return $this->jsonSuccess($result, [], 201);
    }
}
