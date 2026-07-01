<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Contact;
use App\Services\Storefront\CheckoutService;
use App\Services\Storefront\CustomerAuthService;
use Illuminate\Http\Request;

class AccountController extends StorefrontController
{
    public function __construct(
        private CustomerAuthService $authService,
        private CheckoutService $checkoutService
    ) {
    }

    public function profile(Request $request)
    {
        return $this->jsonSuccess($this->authService->formatContact($request->user()));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'nullable|string|max:191',
            'last_name' => 'nullable|string|max:191',
            'email' => 'nullable|email|max:191',
            'mobile' => 'nullable|string|max:20',
        ]);

        /** @var Contact $contact */
        $contact = $request->user();
        $contact->fill($data);
        if (! empty($data['first_name']) || ! empty($data['last_name'])) {
            $contact->name = trim(($data['first_name'] ?? $contact->first_name).' '.($data['last_name'] ?? $contact->last_name));
        }
        $contact->save();

        return $this->jsonSuccess($this->authService->formatContact($contact));
    }

    public function orders(Request $request)
    {
        /** @var Contact $contact */
        $contact = $request->user();

        return $this->jsonSuccess($this->checkoutService->listOrdersForContact($this->businessId($request), $contact->id));
    }

    public function orderDetail(Request $request, int $orderId)
    {
        /** @var Contact $contact */
        $contact = $request->user();
        $order = $this->checkoutService->getOrderForContact($this->businessId($request), $contact->id, $orderId);

        if (empty($order)) {
            return $this->jsonError('Order not found.', 404);
        }

        return $this->jsonSuccess($order);
    }

    public function orderInvoice(Request $request, int $orderId)
    {
        /** @var Contact $contact */
        $contact = $request->user();
        $url = $this->checkoutService->invoicePrintUrlForContact(
            $this->businessId($request),
            $contact->id,
            $orderId
        );

        if (empty($url)) {
            return $this->jsonError('Invoice is not available for this order.', 404);
        }

        return $this->jsonSuccess(['invoice_print_url' => $url]);
    }

    public function updateAddress(Request $request)
    {
        $data = $request->validate([
            'address_line_1' => 'nullable|string|max:500',
            'address_line_2' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:191',
            'state' => 'nullable|string|max:191',
            'country' => 'nullable|string|max:191',
            'zip_code' => 'nullable|string|max:20',
        ]);

        /** @var Contact $contact */
        $contact = $request->user();
        $contact->fill($data);
        $contact->save();

        return $this->jsonSuccess($this->authService->formatContact($contact));
    }
}
