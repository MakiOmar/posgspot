<?php

namespace App\Services\Storefront;

use App\Contact;
use App\StorefrontSocialIdentity;
use App\Utils\ContactUtil;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;

/**
 * Storefront Google / Facebook auth via Socialite → Sanctum on Contact.
 */
class SocialAuthService
{
    public const PROVIDERS = ['google', 'facebook'];

    public function __construct(
        private CustomerAuthService $authService,
        private ContactDuplicateService $duplicates,
        private ContactUtil $contactUtil,
    ) {
    }

    public function isProviderEnabled(string $provider): bool
    {
        $provider = strtolower($provider);
        if (! in_array($provider, self::PROVIDERS, true)) {
            return false;
        }

        return (bool) config("storefront.social_login.{$provider}.enabled");
    }

    public function assertProviderEnabled(string $provider): string
    {
        $provider = strtolower($provider);
        if (! $this->isProviderEnabled($provider)) {
            abort(404, 'Social provider not available.');
        }

        return $provider;
    }

    /**
     * @param  'login'|'link'  $intent
     */
    public function buildRedirectUrl(string $provider, string $intent, int $businessId, ?Contact $linkContact = null, ?string $locale = null, ?string $next = null): string
    {
        $provider = $this->assertProviderEnabled($provider);
        $intent = $intent === 'link' ? 'link' : 'login';

        if ($intent === 'link' && ! $linkContact) {
            throw ValidationException::withMessages([
                'intent' => ['Sign in to connect a social account.'],
            ]);
        }

        $state = $this->encodeState([
            'intent' => $intent,
            'business_id' => $businessId,
            'contact_id' => $linkContact?->id,
            'locale' => $locale ?: 'en',
            'next' => $this->sanitizeNext($next),
            'nonce' => Str::random(32),
            'exp' => now()->addSeconds((int) config('storefront.social_login.state_ttl_seconds', 600))->timestamp,
        ]);

        return Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();
    }

    /**
     * Complete web OAuth callback → one-time exchange code + frontend redirect URL.
     *
     * @return array{redirect_url: string}
     */
    public function handleWebCallback(string $provider, string $statePayload, int $businessId): array
    {
        $provider = $this->assertProviderEnabled($provider);
        $state = $this->decodeState($statePayload);

        if ((int) ($state['business_id'] ?? 0) !== $businessId) {
            throw ValidationException::withMessages(['state' => ['Invalid OAuth state.']]);
        }

        $linkContact = null;
        if (($state['intent'] ?? '') === 'link') {
            $linkContact = Contact::where('business_id', $businessId)
                ->where('id', (int) ($state['contact_id'] ?? 0))
                ->first();
            if (! $linkContact) {
                throw ValidationException::withMessages(['intent' => ['Invalid link session.']]);
            }
        }

        /** @var SocialiteUser $socialUser */
        $socialUser = Socialite::driver($provider)->stateless()->user();
        $session = $this->loginOrRegisterFromProviderUser(
            $businessId,
            $provider,
            $socialUser,
            $linkContact
        );

        $code = $this->storeExchangePayload($session);
        $locale = (string) ($state['locale'] ?? 'en');
        $next = $this->sanitizeNext($state['next'] ?? null) ?: '/account';
        $base = rtrim((string) config('storefront.url'), '/');

        return [
            'redirect_url' => $base.'/'.$locale.'/auth/social/callback?code='.urlencode($code)
                .'&next='.urlencode($next),
        ];
    }

    /**
     * Exchange one-time code for Sanctum session.
     *
     * @return array{token: string, token_type: string, contact: array<string, mixed>}
     */
    public function exchangeCode(string $code): array
    {
        $key = $this->exchangeCacheKey($code);
        $payload = Cache::pull($key);
        if (! is_array($payload) || empty($payload['token']) || empty($payload['contact'])) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired social login code.'],
            ]);
        }

        return [
            'token' => (string) $payload['token'],
            'token_type' => 'Bearer',
            'contact' => $payload['contact'],
        ];
    }

    /**
     * Mobile: access_token or Google id_token → Sanctum session.
     *
     * @return array{token: string, token_type: string, contact: array<string, mixed>}
     */
    public function loginWithProviderToken(
        int $businessId,
        string $provider,
        ?string $accessToken,
        ?string $idToken,
        ?Contact $linkContact = null
    ): array {
        $provider = $this->assertProviderEnabled($provider);

        $socialUser = null;
        if ($provider === 'google' && filled($idToken)) {
            $socialUser = $this->googleUserFromIdToken((string) $idToken);
        } elseif (filled($accessToken)) {
            $socialUser = Socialite::driver($provider)->stateless()->userFromToken((string) $accessToken);
        }

        if (! $socialUser) {
            throw ValidationException::withMessages([
                'access_token' => ['A valid access_token or id_token is required.'],
            ]);
        }

        return $this->loginOrRegisterFromProviderUser($businessId, $provider, $socialUser, $linkContact);
    }

    /**
     * @return array{token: string, token_type: string, contact: array<string, mixed>}
     */
    public function loginOrRegisterFromProviderUser(
        int $businessId,
        string $provider,
        SocialiteUser $socialUser,
        ?Contact $linkContact = null
    ): array {
        $provider = $this->assertProviderEnabled($provider);
        $providerUserId = trim((string) $socialUser->getId());
        if ($providerUserId === '') {
            throw ValidationException::withMessages(['provider' => ['Provider did not return a user id.']]);
        }

        $email = strtolower(trim((string) ($socialUser->getEmail() ?? '')));
        $emailVerified = $this->providerEmailIsVerified($provider, $socialUser);
        $avatar = $socialUser->getAvatar();
        $name = trim((string) ($socialUser->getName() ?? ''));
        $firstName = $name !== '' ? explode(' ', $name, 2)[0] : 'Customer';
        $lastName = $name !== '' && str_contains($name, ' ')
            ? trim(explode(' ', $name, 2)[1])
            : null;

        return DB::transaction(function () use (
            $businessId,
            $provider,
            $providerUserId,
            $email,
            $emailVerified,
            $avatar,
            $firstName,
            $lastName,
            $linkContact
        ) {
            $identity = StorefrontSocialIdentity::where('business_id', $businessId)
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($linkContact) {
                return $this->linkIdentityToContact(
                    $linkContact,
                    $provider,
                    $providerUserId,
                    $email,
                    $emailVerified,
                    $avatar,
                    $identity
                );
            }

            if ($identity) {
                $contact = Contact::where('business_id', $businessId)
                    ->where('id', $identity->contact_id)
                    ->firstOrFail();
                $this->refreshIdentitySnapshot($identity, $email, $avatar);
                $this->maybeMarkEmailVerified($contact, $email, $emailVerified);

                return $this->issueSession($contact->fresh(['media']));
            }

            if ($email !== '') {
                $existing = $this->duplicates->findCustomerByEmail($businessId, $email);
                if ($existing) {
                    $hasPassword = ! empty($existing->password);
                    $alreadyLinked = StorefrontSocialIdentity::where('contact_id', $existing->id)
                        ->where('provider', $provider)
                        ->exists();

                    // Account takeover guard: password account without this provider → refuse auto-login.
                    if ($hasPassword && ! $alreadyLinked) {
                        throw ValidationException::withMessages([
                            'account_exists' => [
                                'An account with this email already exists. Sign in with your password, then connect '.$provider.' from Login & Security.',
                            ],
                        ]);
                    }

                    // Auto-merge only when email is verified by the provider (or account has no password).
                    if (! $emailVerified && $hasPassword) {
                        throw ValidationException::withMessages([
                            'account_exists' => [
                                'An account with this email already exists. Sign in with your password, then connect '.$provider.' from Login & Security.',
                            ],
                        ]);
                    }

                    $this->attachIdentity($existing, $businessId, $provider, $providerUserId, $email, $avatar);
                    $this->maybeMarkEmailVerified($existing, $email, $emailVerified);

                    return $this->issueSession($existing->fresh(['media']));
                }
            }

            if ($email === '') {
                throw ValidationException::withMessages([
                    'email' => ['Your social account did not provide an email address.'],
                ]);
            }

            $result = $this->contactUtil->createNewContact([
                'business_id' => $businessId,
                'type' => 'customer',
                'contact_status' => 'active',
                'name' => trim($firstName.' '.($lastName ?? '')),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'mobile' => '',
                'password' => null,
                'email_verified_at' => $emailVerified ? now() : null,
                'created_by' => 1,
            ]);

            $contact = $result['data'] ?? Contact::find($result['id'] ?? null);
            if (! $contact instanceof Contact) {
                throw new \RuntimeException('Failed to create customer contact.');
            }

            $this->attachIdentity($contact, $businessId, $provider, $providerUserId, $email, $avatar);

            return $this->issueSession($contact->fresh(['media']));
        });
    }

    /**
     * @return list<array{provider: string, email: string|null, connected: bool}>
     */
    public function listIdentities(Contact $contact): array
    {
        $linked = StorefrontSocialIdentity::where('contact_id', $contact->id)
            ->get()
            ->keyBy('provider');

        $rows = [];
        foreach (self::PROVIDERS as $provider) {
            if (! $this->isProviderEnabled($provider)) {
                continue;
            }
            $row = $linked->get($provider);
            $rows[] = [
                'provider' => $provider,
                'email' => $row?->email,
                'connected' => $row !== null,
            ];
        }

        return $rows;
    }

    public function unlink(Contact $contact, string $provider): void
    {
        $provider = $this->assertProviderEnabled($provider);
        $identity = StorefrontSocialIdentity::where('contact_id', $contact->id)
            ->where('provider', $provider)
            ->first();

        if (! $identity) {
            throw ValidationException::withMessages([
                'provider' => ['This social account is not connected.'],
            ]);
        }

        $remaining = StorefrontSocialIdentity::where('contact_id', $contact->id)
            ->where('id', '!=', $identity->id)
            ->count();
        $hasPassword = ! empty($contact->password);

        if (! $hasPassword && $remaining === 0) {
            throw ValidationException::withMessages([
                'provider' => ['Set a password before disconnecting your only sign-in method.'],
            ]);
        }

        $identity->delete();
    }

    /**
     * @return array{token: string, token_type: string, contact: array<string, mixed>}
     */
    private function linkIdentityToContact(
        Contact $linkContact,
        string $provider,
        string $providerUserId,
        string $email,
        bool $emailVerified,
        ?string $avatar,
        ?StorefrontSocialIdentity $existingIdentity
    ): array {
        if ($existingIdentity && (int) $existingIdentity->contact_id !== (int) $linkContact->id) {
            throw ValidationException::withMessages([
                'provider' => ['This social account is already linked to another customer.'],
            ]);
        }

        $otherOnContact = StorefrontSocialIdentity::where('contact_id', $linkContact->id)
            ->where('provider', $provider)
            ->when($existingIdentity, fn ($q) => $q->where('id', '!=', $existingIdentity->id))
            ->exists();
        if ($otherOnContact) {
            throw ValidationException::withMessages([
                'provider' => ['This provider is already connected to your account.'],
            ]);
        }

        if ($existingIdentity) {
            $this->refreshIdentitySnapshot($existingIdentity, $email, $avatar);
        } else {
            $this->attachIdentity(
                $linkContact,
                (int) $linkContact->business_id,
                $provider,
                $providerUserId,
                $email,
                $avatar
            );
        }

        $this->maybeMarkEmailVerified($linkContact, $email, $emailVerified);

        return $this->issueSession($linkContact->fresh(['media']));
    }

    private function attachIdentity(
        Contact $contact,
        int $businessId,
        string $provider,
        string $providerUserId,
        string $email,
        ?string $avatar
    ): StorefrontSocialIdentity {
        return StorefrontSocialIdentity::create([
            'business_id' => $businessId,
            'contact_id' => $contact->id,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => $email !== '' ? $email : null,
            'avatar_url' => $avatar ? substr($avatar, 0, 500) : null,
        ]);
    }

    private function refreshIdentitySnapshot(StorefrontSocialIdentity $identity, string $email, ?string $avatar): void
    {
        $identity->email = $email !== '' ? $email : $identity->email;
        if ($avatar) {
            $identity->avatar_url = substr($avatar, 0, 500);
        }
        $identity->save();
    }

    private function maybeMarkEmailVerified(Contact $contact, string $email, bool $emailVerified): void
    {
        if (! $emailVerified || $email === '') {
            return;
        }
        if (strcasecmp((string) $contact->email, $email) !== 0 && ! empty($contact->email)) {
            return;
        }
        if (empty($contact->email)) {
            $contact->email = $email;
        }
        if (empty($contact->email_verified_at)) {
            $contact->email_verified_at = now();
            $contact->email_verify_code_hash = null;
            $contact->email_verify_expires_at = null;
            $contact->save();
        }
    }

    /**
     * @return array{token: string, token_type: string, contact: array<string, mixed>}
     */
    private function issueSession(Contact $contact): array
    {
        $contact->tokens()->delete();
        $token = $contact->createToken('storefront')->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'contact' => $this->authService->formatContact($contact),
        ];
    }

    /**
     * @param  array{token: string, token_type: string, contact: array<string, mixed>}  $session
     */
    private function storeExchangePayload(array $session): string
    {
        $code = Str::random(64);
        $ttl = max(15, (int) config('storefront.social_login.exchange_ttl_seconds', 60));
        Cache::put($this->exchangeCacheKey($code), [
            'token' => $session['token'],
            'contact' => $session['contact'],
        ], $ttl);

        return $code;
    }

    private function exchangeCacheKey(string $code): string
    {
        return 'storefront:social:exchange:'.hash('sha256', $code);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeState(array $payload): string
    {
        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeState(string $state): array
    {
        try {
            $decoded = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['state' => ['Invalid OAuth state.']]);
        }

        if (! is_array($decoded) || empty($decoded['exp']) || (int) $decoded['exp'] < now()->timestamp) {
            throw ValidationException::withMessages(['state' => ['OAuth state expired.']]);
        }

        return $decoded;
    }

    private function sanitizeNext(?string $next): ?string
    {
        if ($next === null || $next === '') {
            return null;
        }
        $next = trim($next);
        if (! str_starts_with($next, '/') || str_starts_with($next, '//')) {
            return null;
        }

        return substr($next, 0, 500);
    }

    private function providerEmailIsVerified(string $provider, SocialiteUser $user): bool
    {
        $raw = method_exists($user, 'getRaw') ? $user->getRaw() : [];
        if (! is_array($raw)) {
            $raw = [];
        }

        if ($provider === 'google') {
            return ! empty($raw['email_verified']) || ($raw['verified_email'] ?? null) === true;
        }

        if ($provider === 'facebook') {
            // Facebook Graph only returns email when verified for the app.
            return filled($user->getEmail());
        }

        return false;
    }

    private function googleUserFromIdToken(string $idToken): SocialiteTwoUser
    {
        $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if (! $response->ok()) {
            throw ValidationException::withMessages([
                'id_token' => ['Invalid Google id_token.'],
            ]);
        }

        $payload = $response->json();
        $aud = (string) ($payload['aud'] ?? '');
        $allowed = array_filter([
            (string) config('services.google.client_id'),
            (string) env('GOOGLE_ANDROID_CLIENT_ID', ''),
            (string) env('GOOGLE_IOS_CLIENT_ID', ''),
        ]);

        if ($aud === '' || ($allowed !== [] && ! in_array($aud, $allowed, true))) {
            throw ValidationException::withMessages([
                'id_token' => ['Google id_token audience mismatch.'],
            ]);
        }

        $user = new SocialiteTwoUser();
        $user->id = (string) ($payload['sub'] ?? '');
        $user->email = (string) ($payload['email'] ?? '');
        $user->name = (string) ($payload['name'] ?? '');
        $user->avatar = (string) ($payload['picture'] ?? '');
        $user->user = $payload;

        return $user;
    }
}
