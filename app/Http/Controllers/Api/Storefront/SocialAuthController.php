<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Contact;
use App\Services\Storefront\SocialAuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class SocialAuthController extends StorefrontController
{
    public function __construct(private SocialAuthService $socialAuth)
    {
    }

    public function redirect(Request $request, string $provider)
    {
        $data = $request->validate([
            'intent' => 'nullable|in:login,link',
            'locale' => 'nullable|string|max:8',
            'next' => 'nullable|string|max:500',
            'format' => 'nullable|in:json,redirect',
        ]);

        $intent = $data['intent'] ?? 'login';
        $contact = $this->optionalSanctumContact($request);

        if ($intent === 'link') {
            if (! $contact) {
                return $this->jsonError('Sign in to connect a social account.', 401);
            }
        }

        try {
            $url = $this->socialAuth->buildRedirectUrl(
                $provider,
                $intent,
                $this->businessId($request),
                $intent === 'link' ? $contact : null,
                $data['locale'] ?? $request->header('X-Content-Locale', 'en'),
                $data['next'] ?? null
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        $wantJson = ($data['format'] ?? '') === 'json'
            || $request->wantsJson()
            || $request->expectsJson();

        if ($wantJson) {
            return $this->jsonSuccess(['url' => $url]);
        }

        return redirect()->away($url);
    }

    public function callback(Request $request, string $provider)
    {
        $state = (string) $request->query('state', '');
        if ($state === '') {
            return $this->jsonError('Missing OAuth state.', 422);
        }

        try {
            $result = $this->socialAuth->handleWebCallback(
                $provider,
                $state,
                $this->businessId($request)
            );
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Social login failed.';
            $base = rtrim((string) config('storefront.url'), '/');

            return redirect()->away($base.'/en/auth/social/callback?error='.urlencode((string) $message));
        }

        return redirect()->away($result['redirect_url']);
    }

    public function exchange(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|min:20|max:128',
        ]);

        $session = $this->socialAuth->exchangeCode($data['code']);

        return $this->jsonSuccess($session);
    }

    public function token(Request $request, string $provider)
    {
        $data = $request->validate([
            'access_token' => 'nullable|string|max:4096',
            'id_token' => 'nullable|string|max:4096',
            'intent' => 'nullable|in:login,link',
        ]);

        $intent = $data['intent'] ?? 'login';
        $contact = $this->optionalSanctumContact($request);

        if ($intent === 'link') {
            if (! $contact) {
                return $this->jsonError('Sign in to connect a social account.', 401);
            }
        }

        if (empty($data['access_token']) && empty($data['id_token'])) {
            return $this->jsonError('access_token or id_token is required.', 422, [
                'access_token' => ['access_token or id_token is required.'],
            ]);
        }

        $session = $this->socialAuth->loginWithProviderToken(
            $this->businessId($request),
            $provider,
            $data['access_token'] ?? null,
            $data['id_token'] ?? null,
            $intent === 'link' ? $contact : null
        );

        return $this->jsonSuccess($session);
    }

    public function index(Request $request)
    {
        /** @var Contact $contact */
        $contact = $request->user();

        return $this->jsonSuccess([
            'identities' => $this->socialAuth->listIdentities($contact),
        ]);
    }

    public function destroy(Request $request, string $provider)
    {
        /** @var Contact $contact */
        $contact = $request->user();
        $this->socialAuth->unlink($contact, $provider);

        return $this->jsonSuccess([
            'message' => 'Social account disconnected.',
            'identities' => $this->socialAuth->listIdentities($contact->fresh()),
        ]);
    }

    private function optionalSanctumContact(Request $request): ?Contact
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }
        $accessToken = PersonalAccessToken::findToken($bearer);
        if (! $accessToken || ! ($accessToken->tokenable instanceof Contact)) {
            return null;
        }

        return $accessToken->tokenable;
    }
}
