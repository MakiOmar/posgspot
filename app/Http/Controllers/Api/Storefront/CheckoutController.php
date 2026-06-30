<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Contact;
use App\Mail\StorefrontOrderConfirmation;
use App\Services\Storefront\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends StorefrontController
{
    public function __construct(private CheckoutService $checkoutService)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'idempotency_key' => 'required|string|max:191',
            'items' => 'required|array|min:1',
            'items.*.variation_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'location_id' => 'required|integer',
            'payment_method' => 'required|in:cod,card',
            'customer' => 'nullable|array',
            'customer.first_name' => 'nullable|string|max:191',
            'customer.last_name' => 'nullable|string|max:191',
            'customer.email' => 'nullable|email',
            'customer.mobile' => 'nullable|string|max:20',
            'shipping_address' => 'required|array',
            'billing_address' => 'nullable|array',
            'shipping_method' => 'nullable|string|max:191',
            'order_note' => 'nullable|string|max:1000',
        ]);

        $data['storefront_order_id'] = $data['idempotency_key'];
        /** @var Contact|null $contact */
        $contact = Auth::guard('sanctum')->user();

        $order = $this->checkoutService->checkout($this->businessId($request), $data, $contact);

        $email = $contact?->email ?? ($data['customer']['email'] ?? null);
        if ($email) {
            Mail::to($email)->queue(new StorefrontOrderConfirmation($order));
        }

        return $this->jsonSuccess($order, [], 201);
    }
}
