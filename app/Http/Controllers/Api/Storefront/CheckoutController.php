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
            'items.*.digital' => 'nullable|array',
            'items.*.digital.kind' => 'nullable|in:game,card',
            'items.*.digital.game_id' => 'nullable|integer|min:1',
            'items.*.digital.type' => 'nullable|in:primary,secondary',
            'items.*.digital.platform' => 'nullable|in:4,5',
            'items.*.digital.card_category_id' => 'nullable|integer|min:1',
            'items.*.digital.line_key' => 'nullable|string|max:191',
            'items.*.digital.title' => 'nullable|string|max:255',
            'items.*.digital.price' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'location_id' => 'required|integer',
            'payment_method' => 'required|in:cod,card,fawry,online',
            'customer' => 'nullable|array',
            'customer.first_name' => 'nullable|string|max:191',
            'customer.last_name' => 'nullable|string|max:191',
            'customer.email' => 'nullable|email',
            'customer.mobile' => 'nullable|string|max:20',
            'shipping_address' => 'nullable|array',
            'billing_address' => 'nullable|array',
            'shipping_method' => 'nullable|string|max:191',
            'shipping_rate_id' => 'required|string|max:255',
            'order_note' => 'nullable|string|max:1000',
            'reward_points' => 'nullable|integer|min:0',
            'coupon_code' => 'nullable|string|max:64',
            'coupon_codes' => 'nullable|array|max:10',
            'coupon_codes.*' => 'string|max:64',
        ]);

        // Re-merge raw digital/unit_price so nested catalog prices are never dropped.
        foreach ($request->input('items', []) as $index => $rawItem) {
            if (! is_array($rawItem) || ! isset($data['items'][$index])) {
                continue;
            }
            if (is_array($rawItem['digital'] ?? null)) {
                $data['items'][$index]['digital'] = array_merge(
                    $data['items'][$index]['digital'] ?? [],
                    $rawItem['digital']
                );
            }
            if (isset($rawItem['unit_price']) && is_numeric($rawItem['unit_price'])) {
                $data['items'][$index]['unit_price'] = (float) $rawItem['unit_price'];
            }
        }

        $data['storefront_order_id'] = $data['idempotency_key'];
        $data['locale'] = $request->header('X-Content-Locale', 'en');
        /** @var Contact|null $contact */
        $contact = Auth::guard('sanctum')->user();

        $order = $this->checkoutService->checkout($this->businessId($request), $data, $contact);

        $email = $contact?->email ?? ($data['customer']['email'] ?? null);
        if ($email && ($data['payment_method'] ?? 'cod') === 'cod') {
            try {
                Mail::to($email)->queue(new StorefrontOrderConfirmation($order));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->jsonSuccess($order, [], 201);
    }
}
