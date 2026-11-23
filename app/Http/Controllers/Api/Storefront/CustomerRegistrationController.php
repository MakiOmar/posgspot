<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\AddCustomerService;
use App\Services\Storefront\TurnstileService;
use Illuminate\Http\Request;

class CustomerRegistrationController extends StorefrontController
{
    public function __construct(
        private AddCustomerService $addCustomer,
        private TurnstileService $turnstile
    ) {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:191',
            'last_name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'birth_date' => 'required|date',
            'country' => 'required|string|size:2',
            'state' => 'required|string|max:191',
            'mobile' => 'required|string|max:20',
            'dial_code' => 'nullable|string|max:6',
            'turnstile_token' => 'nullable|string',
        ]);

        $businessId = $this->businessId($request);
        $turnstileError = $this->turnstile->validate($businessId, $data['turnstile_token'] ?? null, $request->ip());
        if ($turnstileError !== null) {
            return $this->jsonError($turnstileError, 422, ['turnstile_token' => [$turnstileError]]);
        }

        $result = $this->addCustomer->register($businessId, $data);

        return $this->jsonSuccess($result, [], $result['created'] ? 201 : 200);
    }
}
