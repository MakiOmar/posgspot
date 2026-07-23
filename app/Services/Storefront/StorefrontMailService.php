<?php

namespace App\Services\Storefront;

use App\Business;
use App\Mail\StorefrontOrderShipped;
use App\System;
use App\Transaction;
use App\Utils\BusinessUtil;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Applies business or system mail settings and resolves a valid From header for storefront mail.
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
     * When the business uses superadmin settings, keep the system mailer
     * (e.g. Mailgun API from .env) and only override the From header.
     *
     * @return array{address: string, name: string}
     */
    public function applyForBusiness(int $businessId): array
    {
        $business = Business::find($businessId);
        $emailSettings = $this->emailSettingsForBusiness($business);

        $useSuperadmin = ! empty($emailSettings['use_superadmin_settings'])
            && ! empty(System::getProperty('allow_email_settings_to_businesses'));

        if ($useSuperadmin) {
            // Leave mail.default / Mailgun credentials from .env unchanged.
        } elseif (! empty($emailSettings['mail_host'])) {
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

    /**
     * Inbox for public contact form submissions (SMTP username from business email settings).
     */
    public function contactRecipient(int $businessId): string
    {
        $business = Business::find($businessId);
        $emailSettings = $this->emailSettingsForBusiness($business);
        $username = trim((string) ($emailSettings['mail_username'] ?? ''));

        if ($username !== '' && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            return $username;
        }

        return $this->resolveFrom($businessId, $emailSettings, $business)['address'];
    }

    /**
     * Email customer when a storefront order is marked shipped.
     */
    public function sendShippedNotification(Transaction $transaction): void
    {
        $email = $transaction->contact->email ?? null;
        if (empty($email) || empty($transaction->source) || $transaction->source !== 'storefront') {
            // Also allow POS sells with contact email.
            if (empty($email)) {
                return;
            }
        }

        $this->applyForBusiness((int) $transaction->business_id);

        $order = [
            'invoice_no' => $transaction->invoice_no,
            'storefront_order_id' => $transaction->storefront_order_id,
            'shipping_carrier' => $transaction->shipping_carrier,
            'shipping_tracking_number' => $transaction->shipping_tracking_number,
            'shipping_tracking_url' => $transaction->shipping_tracking_url,
        ];

        Mail::to($email)->queue(new StorefrontOrderShipped($order));

        if (! empty($transaction->contact_id)) {
            \App\Jobs\SendStorefrontOrderPush::dispatch((int) $transaction->id, 'shipped');
        }
    }

    /**
     * Email digital secrets after paid allocation (Fawry or COD marked paid).
     */
    public function sendPaidDigitalConfirmation(Transaction $transaction): void
    {
        $email = $transaction->contact->email ?? null;
        if (empty($email) || ($transaction->source ?? '') !== 'storefront') {
            return;
        }

        $deliveries = app(DigitalFulfillmentService::class)
            ->customerDeliveriesForTransaction($transaction);
        if ($deliveries === []) {
            return;
        }

        $this->applyForBusiness((int) $transaction->business_id);

        $order = [
            'invoice_no' => $transaction->invoice_no,
            'storefront_order_id' => $transaction->storefront_order_id,
            'final_total' => $transaction->final_total,
            'payment_status' => $transaction->payment_status,
            'digital_deliveries' => $deliveries,
        ];

        Mail::to($email)->queue(new \App\Mail\StorefrontOrderConfirmation($order));
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
