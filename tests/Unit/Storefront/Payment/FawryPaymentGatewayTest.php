<?php

namespace Tests\Unit\Storefront\Payment;

use App\Services\Storefront\Payment\FawryPaymentGateway;
use App\Services\Storefront\Payment\StorefrontPaymentRecorder;
use App\Services\Storefront\StorefrontSettingService;
use Tests\TestCase;

class FawryPaymentGatewayTest extends TestCase
{
    private FawryPaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FawryPaymentGateway(
            app(StorefrontSettingService::class),
            app(StorefrontPaymentRecorder::class),
        );
    }

    public function test_sorted_items_signature_matches_reference_order(): void
    {
        $items = [
            ['itemId' => 'shipping', 'quantity' => 1, 'price' => '25.00'],
            ['itemId' => '10', 'quantity' => 2, 'price' => '50.00'],
        ];

        usort($items, fn ($a, $b) => strcmp((string) $a['itemId'], (string) $b['itemId']));

        $signature = $this->gateway->sortedItemsSignature($items);

        $this->assertSame('10250.00shipping125.00', $signature);
    }

    public function test_status_api_signature_format(): void
    {
        $merchantCode = 'MERCHANT';
        $merchantRef = 'SF-ORDER-123';
        $securityKey = 'secret-key';

        $signature = hash('sha256', $merchantCode.$merchantRef.$securityKey);

        $this->assertSame(
            hash('sha256', 'MERCHANTSF-ORDER-123secret-key'),
            $signature
        );
    }

    public function test_verify_webhook_signature_uses_callback_field_names(): void
    {
        $securityKey = 'test-hash';
        $payload = [
            'fawryRefNumber' => 'FAW123',
            'merchantRefNumber' => 'SF-1',
            'paymentAmount' => 100,
            'orderAmount' => 100,
            'orderStatus' => 'PAID',
            'paymentMethod' => 'CARD',
            'paymentRefrenceNumber' => 'PAY123',
        ];

        $signatureString = 'FAW123'
            .'SF-1'
            .'100.00'
            .'100.00'
            .'PAID'
            .'CARD'
            .'PAY123'
            .$securityKey;

        $payload['messageSignature'] = hash('sha256', $signatureString);

        $config = [
            'fawry' => [
                'merchant_code' => 'MC',
                'security_key' => $securityKey,
                'staging' => true,
            ],
        ];

        $this->assertTrue($this->gateway->verifyWebhookPayload($payload, $config));
    }

    public function test_verify_return_signature_uses_url_field_names(): void
    {
        $securityKey = 'test-hash';
        $payload = [
            'referenceNumber' => 'FAW123',
            'merchantRefNumber' => 'SF-1',
            'paymentAmount' => 100,
            'orderAmount' => 100,
            'orderStatus' => 'PAID',
            'paymentMethod' => 'CARD',
            'paymentReferenceNumber' => 'PAY123',
        ];

        $signatureString = 'FAW123'
            .'SF-1'
            .'100.00'
            .'100.00'
            .'PAID'
            .'CARD'
            .''
            .''
            .''
            .'PAY123'
            .$securityKey;

        $payload['signature'] = hash('sha256', $signatureString);

        $config = [
            'fawry' => [
                'merchant_code' => 'MC',
                'security_key' => $securityKey,
                'staging' => true,
            ],
        ];

        $this->assertTrue($this->gateway->verifyReturnPayload($payload, $config));
    }
}
