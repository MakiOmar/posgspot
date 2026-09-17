<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\PhoneValidationService;
use App\Services\Storefront\RequestProductService;
use App\Services\Storefront\TurnstileService;
use App\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequestProductController extends StorefrontController
{
    public function __construct(
        private RequestProductService $requestProduct,
        private PhoneValidationService $phoneValidation,
        private TurnstileService $turnstile
    ) {
    }

    public function meta()
    {
        if (! $this->requestProduct->isEnabled()) {
            return $this->jsonError('Request a product is not available.', 404);
        }

        return $this->jsonSuccess($this->requestProduct->meta());
    }

    public function store(Request $request)
    {
        if (! $this->requestProduct->isEnabled()) {
            return $this->jsonError('Request a product is not available.', 404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'phone' => 'nullable|string|max:50',
            'dial_code' => 'nullable|string|max:6',
            'product_name' => 'required|string|max:191',
            'platform' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:5000',
            'turnstile_token' => 'nullable|string',
        ]);

        $businessId = $this->businessId($request);
        $turnstileError = $this->turnstile->validate($businessId, $data['turnstile_token'] ?? null, $request->ip());
        if ($turnstileError !== null) {
            return $this->jsonError($turnstileError, 422, ['turnstile_token' => [$turnstileError]]);
        }

        if (! empty($data['phone'])) {
            $dialCode = $data['dial_code'] ?? '+20';
            $phoneCheck = $this->phoneValidation->validate($data['phone'], $dialCode);
            if (! $phoneCheck['valid']) {
                return $this->jsonError($phoneCheck['message'], 422, ['phone' => [$phoneCheck['message']]]);
            }
        }

        // Optional Sanctum — guests allowed; link contact when Bearer token present.
        /** @var Contact|null $contact */
        $contact = Auth::guard('sanctum')->user();
        $row = $this->requestProduct->createRequest($businessId, $contact, $data);

        return $this->jsonSuccess($this->requestProduct->present($row), [], 201);
    }
}
