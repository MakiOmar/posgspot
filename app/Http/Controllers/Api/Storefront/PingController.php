<?php

namespace App\Http\Controllers\Api\Storefront;

use Illuminate\Http\Request;

class PingController extends StorefrontController
{
    public function __invoke(Request $request)
    {
        return $this->jsonSuccess([
            'status' => 'ok',
            'service' => 'storefront-api',
            'version' => 'v1',
            'time' => now()->toIso8601String(),
        ]);
    }
}
