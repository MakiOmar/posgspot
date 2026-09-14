<?php

namespace Tests\Unit\Storefront\Payment;

use App\Services\Storefront\Payment\GeideaPaymentGateway;
use App\Services\Storefront\Payment\GeideaSignature;
use Carbon\Carbon;
use Tests\TestCase;

class GeideaSignatureTest extends TestCase
{
    private GeideaSignature $signature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signature = new GeideaSignature;
    }

    public function test_timestamp_matches_vendor_php_date_format(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 14, 15, 17, 5));

        $this->assertSame('9/14/2026 3:17:05 PM', $this->signature->timestamp());
    }

    public function test_session_signature_matches_vendor_field_order(): void
    {
        $expected = base64_encode(hash_hmac(
            'sha256',
            'pk-test10.50EGPSF-1109/14/2026 3:17:05 PM',
            'api-password',
            true
        ));

        $this->assertSame(
            $expected,
            $this->signature->session(
                'pk-test',
                10.5,
                'EGP',
                'SF-110',
                '9/14/2026 3:17:05 PM',
                'api-password'
            )
        );
    }

    public function test_callback_signature_includes_order_id_and_status(): void
    {
        $expected = base64_encode(hash_hmac(
            'sha256',
            'pk-test10.50EGPgid-1SuccessSF-1109/14/2026 3:17:05 PM',
            'api-password',
            true
        ));

        $this->assertSame(
            $expected,
            $this->signature->callback(
                'pk-test',
                '10.50',
                'EGP',
                'gid-1',
                'Success',
                'SF-110',
                '9/14/2026 3:17:05 PM',
                'api-password'
            )
        );
    }

    public function test_session_and_callback_recipes_do_not_validate_each_other(): void
    {
        $session = $this->signature->session('pk', 10, 'EGP', 'SF-1', '9/14/2026 3:17:05 PM', 'secret');
        $callback = $this->signature->callback('pk', 10, 'EGP', 'oid', 'Success', 'SF-1', '9/14/2026 3:17:05 PM', 'secret');

        $this->assertNotSame($session, $callback);
        $this->assertFalse(hash_equals($session, $callback));
    }

    public function test_resolve_credentials_uses_live_pair_only_in_live_mode(): void
    {
        $gateway = app(GeideaPaymentGateway::class);
        $config = [
            'geidea' => [
                'mode' => 'live',
                'region' => 'EGY-PROD',
                'test_public_key' => 'test-pk',
                'test_api_password' => 'test-pw',
                'live_public_key' => 'live-pk',
                'live_api_password' => 'live-pw',
            ],
        ];

        $resolved = $gateway->resolveCredentials($config);

        $this->assertSame('live', $resolved['mode']);
        $this->assertSame('live-pk', $resolved['public_key']);
        $this->assertSame('live-pw', $resolved['api_password']);
    }

    public function test_resolve_credentials_defaults_to_test_pair(): void
    {
        $gateway = app(GeideaPaymentGateway::class);
        $resolved = $gateway->resolveCredentials([
            'geidea' => [
                'test_public_key' => 'test-pk',
                'test_api_password' => 'test-pw',
                'live_public_key' => 'live-pk',
                'live_api_password' => 'live-pw',
            ],
        ]);

        $this->assertSame('test', $resolved['mode']);
        $this->assertSame('test-pk', $resolved['public_key']);
        $this->assertSame('test-pw', $resolved['api_password']);
    }

    public function test_webhook_verification_uses_only_the_configured_mode_password(): void
    {
        $gateway = app(GeideaPaymentGateway::class);
        $payload = $this->callbackPayload('test-pk', 'test-pw');

        $this->assertTrue($gateway->verifyWebhookPayload($payload, [
            'geidea' => [
                'mode' => 'test',
                'test_public_key' => 'test-pk',
                'test_api_password' => 'test-pw',
                'live_public_key' => 'live-pk',
                'live_api_password' => 'live-pw',
            ],
        ]));

        $this->assertFalse($gateway->verifyWebhookPayload($payload, [
            'geidea' => [
                'mode' => 'live',
                'test_public_key' => 'test-pk',
                'test_api_password' => 'test-pw',
                'live_public_key' => 'live-pk',
                'live_api_password' => 'live-pw',
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function callbackPayload(string $publicKey, string $password): array
    {
        $order = [
            'amount' => 10.5,
            'currency' => 'EGP',
            'orderId' => 'gid-1',
            'status' => 'Success',
            'merchantReferenceId' => 'SF-110',
            'detailedStatus' => 'Paid',
        ];
        $timeStamp = '9/14/2026 3:17:05 PM';

        return [
            'order' => $order,
            'timeStamp' => $timeStamp,
            'signature' => $this->signature->callback(
                $publicKey,
                $order['amount'],
                $order['currency'],
                $order['orderId'],
                $order['status'],
                $order['merchantReferenceId'],
                $timeStamp,
                $password
            ),
        ];
    }
}
