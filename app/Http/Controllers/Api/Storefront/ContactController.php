<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Mail\StorefrontContactMessage;
use App\Services\Storefront\PhoneValidationService;
use App\Services\Storefront\StorefrontMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends StorefrontController
{
    public function __construct(private PhoneValidationService $phoneValidation)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'phone' => 'required|string|max:30',
            'message' => 'required|string|max:5000',
            'dial_code' => 'nullable|string|max:6',
        ]);

        $dialCode = $data['dial_code'] ?? $this->inferDialCode($data['phone']);
        $phoneCheck = $this->phoneValidation->validate($data['phone'], $dialCode);
        if (! $phoneCheck['valid']) {
            return $this->jsonError($phoneCheck['message'], 422, ['phone' => [$phoneCheck['message']]]);
        }

        $businessId = $this->businessId($request);
        $mailService = app(StorefrontMailService::class);
        $mailService->applyForBusiness($businessId);
        $recipient = $mailService->contactRecipient($businessId);

        try {
            Mail::to($recipient)->queue(new StorefrontContactMessage($data));
        } catch (\Throwable $e) {
            Log::warning('Storefront contact form email failed.', [
                'business_id' => $businessId,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return $this->jsonError('Could not send your message. Please try again later.', 503);
        }

        return $this->jsonSuccess([
            'message' => 'Thank you. We received your message and will get back to you soon.',
        ]);
    }

    private function inferDialCode(string $phone): string
    {
        foreach ($this->phoneValidation->getCountriesData() as $country) {
            $dial = $country['dial_code'] ?? '';
            if ($dial !== '' && str_starts_with($phone, $dial)) {
                return $dial;
            }
        }

        return '+20';
    }
}
