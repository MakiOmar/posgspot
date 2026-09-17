<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\TrackOrderService;
use Illuminate\Http\Request;

class TrackOrderController extends StorefrontController
{
    public function __construct(private TrackOrderService $trackOrder)
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_no' => 'required|string|max:191',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:191',
        ]);

        $phone = trim((string) ($validated['phone'] ?? ''));
        $email = trim((string) ($validated['email'] ?? ''));
        if ($phone === '' && $email === '') {
            return $this->jsonError('Phone or email is required.', 422, [
                'phone' => ['Provide the phone or email used on the order.'],
            ]);
        }

        $order = $this->trackOrder->lookup(
            $this->businessId($request),
            $validated['invoice_no'],
            $phone !== '' ? $phone : null,
            $email !== '' ? $email : null
        );

        if ($order === null) {
            return $this->jsonError('Order not found.', 404);
        }

        return $this->jsonSuccess($order);
    }
}
