<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Contact;
use App\Services\Storefront\CheckoutService;
use App\Services\Storefront\StorefrontSettingService;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Geidea HPP shows card inputs only; checkout contact + address details travel on the Create Session body.
 */
class GeideaSessionCustomerTest extends TestCase
{
    use DatabaseTransactions;

    protected int $businessId = 1;

    public function test_session_hides_contact_fields_and_sends_checkout_customer_details(): void
    {
        $locationId = BusinessLocation::where('business_id', $this->businessId)->where('is_active', 1)->value('id');
        $contactId = Contact::where('business_id', $this->businessId)->where('type', 'customer')->value('id');
        if (empty($locationId) || empty($contactId)) {
            $this->markTestSkipped('No active location or customer contact in database.');
        }

        app(StorefrontSettingService::class)->save($this->businessId, [
            'gateway' => [
                'provider' => 'geidea',
                'enabled' => true,
                'geidea' => [
                    'mode' => 'test',
                    'region' => 'EGY-PROD',
                    'currency' => 'EGP',
                    'test_public_key' => 'test-pk',
                    'test_api_password' => 'test-pw',
                ],
            ],
        ]);
        Cache::flush();

        $orderRef = 'SF-GEIDEA-CUST-'.uniqid();
        $transaction = Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $contactId,
            'transaction_date' => now(),
            'total_before_tax' => 150,
            'final_total' => 150,
            'payment_status' => 'due',
            'invoice_no' => 'GEIDEA-CUST-'.uniqid(),
            'storefront_order_id' => $orderRef,
            'created_by' => 1,
            'source' => 'storefront',
            'order_addresses' => json_encode([
                'shipping_address' => [
                    'address_line_1' => '5 Tahrir Square',
                    'city' => 'Cairo',
                    'state' => 'C',
                    'country' => 'EG',
                ],
                'billing_address' => [],
                'customer' => [
                    'first_name' => 'Checkout',
                    'last_name' => 'Buyer',
                    'email' => 'checkout.buyer@example.com',
                    'mobile' => '+201001234567',
                ],
            ]),
        ]);
        $token = app(CheckoutService::class)->issueOrderAccessToken($transaction);

        Http::fake([
            'https://api.merchant.geidea.net/payment-intent/api/v2/direct/session' => Http::response([
                'session' => ['id' => 'sess-cust'],
            ], 200),
        ]);

        $this->postJson('/api/storefront/v1/payments/geidea/session', [
            'storefront_order_id' => $orderRef,
            'order_access_token' => $token,
            'locale' => 'en',
        ])->assertOk()->assertJsonPath('data.session_id', 'sess-cust');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/payment-intent/api/v2/direct/session')) {
                return false;
            }
            $body = $request->data();
            $appearance = $body['appearance'] ?? [];
            $customer = $body['customer'] ?? [];

            return ($appearance['showEmail'] ?? null) === false
                && ($appearance['showPhone'] ?? null) === false
                && ($appearance['showAddress'] ?? null) === false
                && ($customer['firstName'] ?? null) === 'Checkout'
                && ($customer['lastName'] ?? null) === 'Buyer'
                && ($customer['email'] ?? null) === 'checkout.buyer@example.com'
                && ($customer['phoneNumber'] ?? null) === '+201001234567'
                && ($customer['address']['billing']['street'] ?? null) === '5 Tahrir Square'
                && ($customer['address']['shipping']['city'] ?? null) === 'Cairo'
                && ($customer['address']['shipping']['country'] ?? null) === 'EGY';
        });
    }
}
