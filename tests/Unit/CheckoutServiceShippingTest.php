<?php

namespace Tests\Unit;

use App\Services\Storefront\CheckoutService;
use App\Transaction;
use Tests\TestCase;

class CheckoutServiceShippingTest extends TestCase
{
    public function test_shipping_address_payload_from_storefront_json(): void
    {
        $transaction = new Transaction();
        $transaction->order_addresses = json_encode([
            'shipping_address' => [
                'address_line_1' => '12 Nile Street',
                'city' => 'Cairo',
                'country' => 'Egypt',
            ],
        ]);

        $payload = app(CheckoutService::class)->shippingAddressPayload($transaction);

        $this->assertNotNull($payload);
        $this->assertSame('12 Nile Street', $payload['address_line_1']);
        $this->assertSame('Cairo', $payload['city']);
        $this->assertSame('Egypt', $payload['country']);
        $this->assertSame('12 Nile Street, Cairo, Egypt', $payload['formatted']);
    }

    public function test_shipping_address_payload_falls_back_to_shipping_address_column(): void
    {
        $transaction = new Transaction();
        $transaction->shipping_address = 'Warehouse pickup, Maadi';

        $payload = app(CheckoutService::class)->shippingAddressPayload($transaction);

        $this->assertNotNull($payload);
        $this->assertSame('Warehouse pickup, Maadi', $payload['formatted']);
    }
}
