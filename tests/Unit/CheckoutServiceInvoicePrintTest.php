<?php

namespace Tests\Unit;

use App\Contact;
use App\Services\Storefront\CheckoutService;
use App\Transaction;
use Tests\TestCase;

class CheckoutServiceInvoicePrintTest extends TestCase
{
    protected int $businessId = 1;

    public function test_paid_order_includes_invoice_print_url(): void
    {
        $contact = Contact::create([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'name' => 'Invoice Print Test',
            'email' => 'invoice_print_'.uniqid().'@example.com',
            'mobile' => '01'.random_int(100000000, 999999999),
            'created_by' => 1,
        ]);

        $transaction = Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => 1,
            'contact_id' => $contact->id,
            'type' => 'sell',
            'status' => 'final',
            'payment_status' => 'paid',
            'source' => 'storefront',
            'storefront_order_id' => 'SF-'.uniqid(),
            'invoice_no' => 'INV-'.uniqid(),
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'final_total' => 150,
            'created_by' => 1,
        ]);

        $order = app(CheckoutService::class)->getOrderForContact(
            $this->businessId,
            $contact->id,
            $transaction->id
        );

        $this->assertNotNull($order);
        $this->assertNotNull($order['invoice_print_url']);
        $this->assertStringContainsString('print_on_load=true', $order['invoice_print_url']);
        $this->assertStringContainsString('/invoice/', $order['invoice_print_url']);
    }

    public function test_unpaid_order_has_no_invoice_print_url(): void
    {
        $contact = Contact::create([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'name' => 'Unpaid Order Test',
            'email' => 'unpaid_'.uniqid().'@example.com',
            'mobile' => '01'.random_int(100000000, 999999999),
            'created_by' => 1,
        ]);

        $transaction = Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => 1,
            'contact_id' => $contact->id,
            'type' => 'sell',
            'status' => 'final',
            'payment_status' => 'due',
            'source' => 'storefront',
            'storefront_order_id' => 'SF-'.uniqid(),
            'invoice_no' => 'INV-'.uniqid(),
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'final_total' => 75,
            'created_by' => 1,
        ]);

        $order = app(CheckoutService::class)->getOrderForContact(
            $this->businessId,
            $contact->id,
            $transaction->id
        );

        $this->assertNotNull($order);
        $this->assertNull($order['invoice_print_url']);
    }

    public function test_paid_pos_order_without_storefront_source_includes_invoice_print_url(): void
    {
        $contact = Contact::create([
            'business_id' => $this->businessId,
            'type' => 'customer',
            'name' => 'POS Customer',
            'email' => 'pos_print_'.uniqid().'@example.com',
            'mobile' => '01'.random_int(100000000, 999999999),
            'created_by' => 1,
        ]);

        $transaction = Transaction::create([
            'business_id' => $this->businessId,
            'location_id' => 1,
            'contact_id' => $contact->id,
            'type' => 'sell',
            'status' => 'final',
            'payment_status' => 'paid',
            'source' => null,
            'invoice_no' => 'INV-'.uniqid(),
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'final_total' => 1299,
            'created_by' => 1,
        ]);

        $url = app(CheckoutService::class)->invoicePrintUrlForContact(
            $this->businessId,
            $contact->id,
            $transaction->id
        );

        $this->assertNotNull($url);
        $this->assertStringContainsString('/invoice/', $url);
    }
}
