<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Contact;
use App\Services\Storefront\StorefrontPushService;
use Illuminate\Http\Request;

class DeviceController extends StorefrontController
{
    public function __construct(
        private StorefrontPushService $push
    ) {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'platform' => 'required|string|in:ios,android',
            'token' => 'required|string|max:512',
            'locale' => 'nullable|string|max:8',
        ]);

        /** @var Contact $contact */
        $contact = $request->user();

        $device = $this->push->register(
            $this->businessId($request),
            (int) $contact->id,
            $data['platform'],
            $data['token'],
            $data['locale'] ?? null
        );

        return $this->jsonSuccess([
            'id' => $device->id,
            'platform' => $device->platform,
            'locale' => $device->locale,
        ], [], 201);
    }

    public function destroy(Request $request, string $token)
    {
        /** @var Contact $contact */
        $contact = $request->user();
        $deleted = $this->push->unregister((int) $contact->id, urldecode($token));

        return $this->jsonSuccess(['deleted' => $deleted > 0]);
    }
}
