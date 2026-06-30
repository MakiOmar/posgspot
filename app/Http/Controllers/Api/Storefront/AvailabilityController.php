<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\AvailabilityService;
use Illuminate\Http\Request;

class AvailabilityController extends StorefrontController
{
    public function __construct(private AvailabilityService $availability)
    {
    }

    public function show(Request $request, int $productId)
    {
        $variationId = $request->query('variation_id') ? (int) $request->query('variation_id') : null;
        $data = $this->availability->getAvailability($this->businessId($request), $productId, $variationId);

        if ($data === null) {
            return $this->jsonError('Product not found.', 404);
        }

        return $this->jsonSuccess($data);
    }
}
