<?php

namespace App\Services\Storefront\Newsletter;

interface NewsletterProviderInterface
{
    /**
     * Subscribe an email using decrypted provider config from storefront settings.
     *
     * @param  array<string, mixed>  $config  Full newsletter settings (incl. provider-specific block)
     * @param  array{ip?: string|null, locale?: string|null}  $meta
     */
    public function subscribe(string $email, array $config, array $meta = []): NewsletterResult;
}
