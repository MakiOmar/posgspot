<?php

namespace App\Services\Storefront;

use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile verification for storefront public forms.
 */
class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private StorefrontSettingService $storefrontSettings)
    {
    }

    public function isEnabled(int $businessId): bool
    {
        $settings = $this->storefrontSettings->get($businessId);
        $siteKey = trim((string) ($settings['turnstile']['site_key'] ?? ''));
        $secret = $this->storefrontSettings->decryptTurnstileSecretKey($settings);

        return $siteKey !== '' && ! empty($secret);
    }

    /**
     * When Turnstile is configured, verify the client token. Returns an error message or null.
     */
    public function validate(int $businessId, ?string $token, ?string $remoteIp = null): ?string
    {
        if (! $this->isEnabled($businessId)) {
            return null;
        }

        if (trim((string) $token) === '') {
            return 'Please complete the security check.';
        }

        $settings = $this->storefrontSettings->get($businessId);
        $secret = $this->storefrontSettings->decryptTurnstileSecretKey($settings);

        $payload = [
            'secret' => $secret,
            'response' => $token,
        ];

        if (! empty($remoteIp)) {
            $payload['remoteip'] = $remoteIp;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::VERIFY_URL, $payload);
            $body = $response->json();
        } catch (\Throwable) {
            return 'Security check could not be verified. Please try again.';
        }

        if (empty($body['success'])) {
            return 'Security check failed. Please try again.';
        }

        return null;
    }
}
