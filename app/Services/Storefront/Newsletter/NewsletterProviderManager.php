<?php

namespace App\Services\Storefront\Newsletter;

use App\Services\Storefront\StorefrontSettingService;
use InvalidArgumentException;

class NewsletterProviderManager
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings,
    ) {
    }

    public function driver(string $provider): NewsletterProviderInterface
    {
        $class = config("storefront-newsletter.drivers.{$provider}");

        if (empty($class) || ! class_exists($class)) {
            throw new InvalidArgumentException("Unknown newsletter provider [{$provider}].");
        }

        return app($class);
    }

    public function isEnabled(int $businessId): bool
    {
        return $this->resolvedConfig($businessId) !== null;
    }

    /**
     * @param  array<string, mixed>  $newsletter
     */
    public function credentialsConfigured(array $newsletter): bool
    {
        $provider = (string) ($newsletter['provider'] ?? '');

        return match ($provider) {
            'mailchimp' => $this->mailchimpReady($newsletter),
            'mailerlite' => $this->mailerliteReady($newsletter),
            'aweber' => $this->aweberReady($newsletter),
            default => false,
        };
    }

    /**
     * Decrypted config for the active provider (secrets resolved).
     *
     * @return array<string, mixed>|null
     */
    public function resolvedConfig(int $businessId): ?array
    {
        $newsletter = $this->storefrontSettings->get($businessId)['newsletter'] ?? [];
        if (empty($newsletter['enabled']) || empty($newsletter['provider'])) {
            return null;
        }

        $provider = (string) $newsletter['provider'];
        $newsletter = $this->storefrontSettings->withDecryptedNewsletterSecrets($newsletter);

        if (! $this->credentialsConfigured($newsletter)) {
            return null;
        }

        return $newsletter;
    }

    /**
     * @param  array<string, mixed>  $newsletter
     */
    private function mailchimpReady(array $newsletter): bool
    {
        $block = $newsletter['mailchimp'] ?? [];
        $key = trim((string) ($block['api_key'] ?? ''));
        $audience = trim((string) ($block['audience_id'] ?? ''));

        return $key !== '' && $audience !== '' && str_contains($key, '-');
    }

    /**
     * @param  array<string, mixed>  $newsletter
     */
    private function mailerliteReady(array $newsletter): bool
    {
        $block = $newsletter['mailerlite'] ?? [];

        return trim((string) ($block['api_token'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $newsletter
     */
    private function aweberReady(array $newsletter): bool
    {
        $block = $newsletter['aweber'] ?? [];

        return trim((string) ($block['access_token'] ?? '')) !== ''
            && trim((string) ($block['account_id'] ?? '')) !== ''
            && trim((string) ($block['list_id'] ?? '')) !== '';
    }
}
