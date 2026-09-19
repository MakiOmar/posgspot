<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\DeviceTrackLookupService;
use App\Services\Storefront\TurnstileService;
use Illuminate\Http\Request;

/**
 * Public + account console/device service tracking (Accounts Device Track API proxy).
 */
class DeviceTrackController extends StorefrontController
{
    public function __construct(
        private DeviceTrackLookupService $lookup,
        private TurnstileService $turnstile,
    ) {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'phone_number' => 'required_without:phone|nullable|string|max:20',
            'phone' => 'required_without:phone_number|nullable|string|max:20',
            'turnstile_token' => 'nullable|string',
        ]);

        $businessId = $this->businessId($request);
        $turnstileError = $this->turnstile->validate($businessId, $data['turnstile_token'] ?? null, $request->ip());
        if ($turnstileError !== null) {
            return $this->jsonError($turnstileError, 422, ['turnstile_token' => [$turnstileError]]);
        }

        $phone = trim((string) ($data['phone_number'] ?? $data['phone'] ?? ''));
        $result = $this->lookup->trackByPhone($phone);

        if (! $result['ok']) {
            return $this->jsonError($result['message'], $result['status'], $result['errors']);
        }

        return $this->jsonSuccess([
            'count' => count($result['services']),
            'services' => $result['services'],
        ]);
    }
}
