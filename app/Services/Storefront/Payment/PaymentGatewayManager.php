<?php

namespace App\Services\Storefront\Payment;

use App\Services\Storefront\StorefrontSettingService;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function __construct(
        private StorefrontSettingService $storefrontSettings,
    ) {
    }

    public function driver(string $provider): PaymentGatewayInterface
    {
        $class = config("storefront-payments.drivers.{$provider}");

        if (empty($class) || ! class_exists($class)) {
            throw new InvalidArgumentException("Unknown payment gateway driver [{$provider}].");
        }

        return app($class);
    }

    public function activeDriver(int $businessId): ?PaymentGatewayInterface
    {
        $settings = $this->storefrontSettings->get($businessId);
        $gateway = $settings['gateway'] ?? [];

        if (empty($gateway['enabled']) || empty($gateway['provider'])) {
            return null;
        }

        return $this->driver((string) $gateway['provider']);
    }

    public function gatewayConfig(int $businessId): array
    {
        return $this->storefrontSettings->get($businessId)['gateway'] ?? [];
    }

    public function isOnlinePaymentsEnabled(int $businessId): bool
    {
        $gateway = $this->gatewayConfig($businessId);

        return ! empty($gateway['enabled']) && ! empty($gateway['provider']);
    }

    public function providerLabel(string $provider): string
    {
        return (string) (config("storefront-payments.labels.{$provider}") ?? ucfirst($provider));
    }
}
