<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\RepairStatusLookupService;
use App\Support\StorefrontLocale;
use Illuminate\Http\Request;

/**
 * Public repair status lookup for the Qwik storefront.
 */
class RepairStatusController extends StorefrontController
{
    public function __construct(private RepairStatusLookupService $lookup)
    {
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);

        if (! $this->lookup->isAvailable($businessId)) {
            return $this->jsonError('Repair tracking is not available.', 503);
        }

        $allowed = ['job_sheet_no', 'invoice_no'];
        if ($this->lookup->lookupByMobileEnabled()) {
            $allowed[] = 'mobile_num';
        }

        $data = $request->validate([
            'search_type' => 'required|string|in:'.implode(',', $allowed),
            'search_number' => 'required|string|max:100',
            'serial_no' => 'nullable|string|max:100',
        ]);

        $locale = StorefrontLocale::fromRequest($request);
        $repairs = $this->lookup->lookup(
            $businessId,
            $data['search_type'],
            trim($data['search_number']),
            isset($data['serial_no']) ? trim((string) $data['serial_no']) : null,
            $locale
        );

        if ($repairs === []) {
            return $this->jsonError(
                __('repair::lang.invalid_repair_details'),
                404,
                ['search_number' => [__('repair::lang.invalid_repair_details')]]
            );
        }

        return $this->jsonSuccess([
            'repairs' => $repairs,
        ]);
    }
}
