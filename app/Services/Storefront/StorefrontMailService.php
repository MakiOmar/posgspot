<?php

namespace App\Services\Storefront;

use App\Business;
use App\Utils\BusinessUtil;
use Illuminate\Support\Facades\Config;

/**
 * Applies business SMTP settings and resolves a valid From header for storefront mail.
 */
class StorefrontMailService
{
    public function __construct(
        private BusinessUtil $businessUtil,
        private StorefrontSettingService $storefrontSettings
    ) {
    }

    /**
     * Configure Laravel mail for the business and return the From address/name.
     *
     * @return array{address: string, name: string}
     */
    public function applyForBusiness(int $businessId): array
    {
        $business = Business::find($businessId);
        $emailSettings = $this->emailSettingsForBusiness($business);

        if (! empty($emailSettings['mail_host'])) {
            Config::set('mail.default', $emailSettings['mail_driver'] ?? 'smtp');
            Config::set('mail.mailers.smtp.host', $emailSettings['mail_host']);
            Config::set('mail.mailers.smtp.port', $emailSettings['mail_port']);
            Config::set('mail.mailers.smtp.username', $emailSettings['mail_username']);
            Config::set('mail.mailers.smtp.password', $emailSettings['mail_password']);
            Config::set('mail.mailers.smtp.encryption', $emailSettings['mail_encryption']);
        }

        $from = $this->resolveFrom($businessId, $emailSettings, $business);
        Config::set('mail.from.address', $from['address']);
        Config::set('mail.from.name', $from['name']);

        return $from;
    }

    /**
     * Resolve a non-empty From address/name (Symfony rejects mail without From).
     *
     * @return array{address: string, name: string}
     */
    public function resolveFrom(int $businessId, ?array $emailSettings = null, ?Business $business = null): array
    {
        $business ??= Business::find($businessId);
        $emailSettings ??= $this->emailSettingsForBusiness($business);

        $address = trim((string) ($emailSettings['mail_from_address'] ?? ''));
        $name = trim((string) ($emailSettings['mail_from_name'] ?? ''));

        if ($address === '') {
            $address = trim((string) config('mail.from.address', ''));
        }
        if ($name === '') {
            $name = trim((string) config('mail.from.name', ''));
        }

        if ($address === '') {
            $storefront = $this->storefrontSettings->get($businessId);
            $address = trim((string) ($storefront['contact']['email'] ?? ''));
        }

        if ($name === '' && $business) {
            $name = trim((string) ($business->name ?? ''));
        }

        if ($address === '') {
            $address = trim((string) config('mail.mailers.smtp.username', ''));
        }

        if ($address === '') {
            $host = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST);
            $address = 'noreply@'.($host ?: 'localhost.localdomain');
        }

        if ($name === '') {
            $name = 'Store';
        }

        return ['address' => $address, 'name' => $name];
    }

    private function emailSettingsForBusiness(?Business $business): array
    {
        if (empty($business) || empty($business->email_settings)) {
            return $this->businessUtil->defaultEmailSettings();
        }

        return array_merge(
            $this->businessUtil->defaultEmailSettings(),
            $business->email_settings
        );
    }
}
