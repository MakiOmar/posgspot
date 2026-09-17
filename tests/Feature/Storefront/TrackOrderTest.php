<?php

namespace Tests\Feature\Storefront;

use App\BusinessLocation;
use App\Contact;
use App\Transaction;
use Tests\TestCase;

class TrackOrderTest extends TestCase
{
    protected int $businessId = 1;

    private function createContactWithOrder(string $email, string $mobile, string $invoiceNo): Transaction
    {
        $contact = Contact::create([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'contact_status' => 'active',
            'name' => 'Track Tester',
            'first_name' => 'Track',
            'last_name' => 'Tester',
            'email' => $email,
            'mobile' => $mobile,
            'created_by' => 1,
            'is_default' => 0,
        ]);

        $locationId = BusinessLocation::where('business_id', $this->businessId)
            ->where('is_active', 1)
            ->value('id');

        return Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $contact->id,
            'invoice_no' => $invoiceNo,
            'storefront_order_id' => 'web-track-'.uniqid(),
            'transaction_date' => now(),
            'total_before_tax' => 100,
            'final_total' => 100,
            'created_by' => 1,
            'source' => 'storefront',
            'shipping_status' => 'ordered',
            'payment_status' => 'paid',
        ]);
    }

    public function test_guest_can_track_order_by_invoice_and_email(): void
    {
        $invoice = 'TRK-'.uniqid();
        $email = 'track_'.uniqid().'@example.com';
        $this->createContactWithOrder($email, '+201011122233', $invoice);

        $this->postJson('/api/storefront/v1/track-order', [
            'invoice_no' => $invoice,
            'email' => $email,
        ])
            ->assertOk()
            ->assertJsonPath('data.invoice_no', $invoice)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'payment_status',
                    'shipping_status',
                    'lines',
                ],
            ]);
    }

    public function test_guest_can_track_order_by_invoice_and_phone(): void
    {
        $invoice = 'TRK-'.uniqid();
        $mobile = '+2010'.random_int(10000000, 99999999);
        $this->createContactWithOrder('phone_'.uniqid().'@example.com', $mobile, $invoice);

        $this->postJson('/api/storefront/v1/track-order', [
            'invoice_no' => $invoice,
            'phone' => $mobile,
        ])
            ->assertOk()
            ->assertJsonPath('data.invoice_no', $invoice);
    }

    public function test_track_order_returns_404_on_mismatch(): void
    {
        $invoice = 'TRK-'.uniqid();
        $this->createContactWithOrder('ok_'.uniqid().'@example.com', '+201055566677', $invoice);

        $this->postJson('/api/storefront/v1/track-order', [
            'invoice_no' => $invoice,
            'email' => 'wrong@example.com',
        ])->assertStatus(404);

        $this->postJson('/api/storefront/v1/track-order', [
            'invoice_no' => 'DOES-NOT-EXIST',
            'email' => 'ok@example.com',
        ])->assertStatus(404);
    }

    public function test_track_order_requires_phone_or_email(): void
    {
        $this->postJson('/api/storefront/v1/track-order', [
            'invoice_no' => 'X',
        ])->assertStatus(422);
    }
}
