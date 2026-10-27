<?php

namespace App\Services\Storefront;

use App\Business;
use App\Mail\StorefrontOrderShipped;
use App\Mail\StorefrontTestEmail;
use App\System;
use App\Transaction;
use App\Utils\BusinessUtil;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Applies business or system mail settings and resolves a valid From header for storefront mail.
 */
class StorefrontMailService
{
    /** Storefront inboxes that receive staff notifications (see recipientFor()). */
    public const NOTIFY_TYPES = ['contact', 'orders', 'sell_to_us', 'request_product'];

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
     * Send immediately with the business mail settings.
     *
     * Settings must be applied before the mailer is resolved: Mail::to() binds the transport
     * at call time, so applying them later inside Mailable::build() silently keeps the .env mailer.
     */
    public function sendNowForBusiness(int $businessId, string $to, Mailable $mailable): void
    {
        $this->applyForBusiness($businessId);
        $mailer = (string) config('mail.default');
        Mail::purge($mailer);
        Mail::mailer($mailer)->to($to)->sendNow($mailable);
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
     * Inbox for Sell to us trade-in requests (settings override, else contact inbox).
     */
    public function sellToUsRecipient(int $businessId): string
    {
        $settings = $this->storefrontSettings->get($businessId);
        $email = trim((string) ($settings['sell_to_us']['notify_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return $this->contactRecipient($businessId);
    }

    /**
     * Inbox for new website order alerts (settings override, else contact inbox).
     */
    public function ordersRecipient(int $businessId): string
    {
        $settings = $this->storefrontSettings->get($businessId);
        $email = trim((string) ($settings['orders']['notify_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return $this->contactRecipient($businessId);
    }

    /**
     * Inbox for Request a product submissions (settings override, else contact inbox).
     */
    public function requestProductRecipient(int $businessId): string
    {
        $settings = $this->storefrontSettings->get($businessId);
        $email = trim((string) ($settings['request_product']['notify_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return $this->contactRecipient($businessId);
    }

    /**
     * Inbox that live mail of this notification type is delivered to.
     */
    public function recipientFor(int $businessId, string $type): string
    {
        return match ($type) {
            'orders' => $this->ordersRecipient($businessId),
            'sell_to_us' => $this->sellToUsRecipient($businessId),
            'request_product' => $this->requestProductRecipient($businessId),
            default => $this->contactRecipient($businessId),
        };
    }

    /**
     * @return array<string, string> notify type => current inbox
     */
    public function recipients(int $businessId): array
    {
        $out = [];
        foreach (self::NOTIFY_TYPES as $type) {
            $out[$type] = $this->recipientFor($businessId, $type);
        }

        return $out;
    }

    /** Address typed in the settings form, else the inbox the saved settings resolve to. */
    public function testRecipient(int $businessId, string $type, ?string $email = null): string
    {
        $email = trim((string) $email);

        return $email !== '' ? $email : $this->recipientFor($businessId, $type);
    }

    /**
     * Send a test message right now through the same transport as new-order alerts, so transport errors surface.
     * An empty $email tests the inbox the saved settings resolve to.
     *
     * @return array{recipient: string, from: string, mailer: string}
     *
     * @throws \Throwable when the mail transport rejects the message
     */
    public function sendTestEmail(int $businessId, string $type, ?string $email = null): array
    {
        $recipient = $this->testRecipient($businessId, $type, $email);
        $from = $this->resolveFrom($businessId);
        $business = Business::find($businessId);

        $this->sendNowForBusiness($businessId, $recipient, new StorefrontTestEmail(
            $type,
            (string) ($business->name ?? $from['name']),
            $recipient
        ));

        return ['recipient' => $recipient, 'from' => $from['address'], 'mailer' => (string) config('mail.default')];
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
