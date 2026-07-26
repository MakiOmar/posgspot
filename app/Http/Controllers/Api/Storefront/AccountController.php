<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Contact;
use App\Mail\StorefrontAccountDeleteRequest;
use App\Services\Storefront\CheckoutService;
use App\Services\Storefront\CustomerAuthService;
use App\Services\Storefront\ContactDuplicateService;
use App\Services\Storefront\PhoneValidationService;
use App\Services\Storefront\RewardPointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccountController extends StorefrontController
{
    public function __construct(
        private CustomerAuthService $authService,
        private CheckoutService $checkoutService,
        private RewardPointsService $rewardPointsService,
        private PhoneValidationService $phoneValidation,
        private ContactDuplicateService $duplicates
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
            'dial_code' => 'nullable|string|max:6',
        ]);

        if (! empty($data['mobile'])) {
            $dialCode = $data['dial_code'] ?? $this->inferDialCode($data['mobile']);
            $phoneCheck = $this->phoneValidation->validate($data['mobile'], $dialCode);
            if (! $phoneCheck['valid']) {
                return $this->jsonError($phoneCheck['message'], 422, ['mobile' => [$phoneCheck['message']]]);
            }
        }

        /** @var Contact $contact */
        $contact = $request->user();
        $businessId = $this->businessId($request);
        $emailChanged = false;

        if (! empty($data['email']) && strcasecmp((string) $data['email'], (string) $contact->email) !== 0) {
            if ($this->duplicates->findCustomerByEmail($businessId, $data['email'])) {
                return $this->jsonError('Email already registered.', 422, ['email' => ['Email already registered.']]);
            }
            $emailChanged = true;
        }

        $contact->fill($data);
        if (! empty($data['first_name']) || ! empty($data['last_name'])) {
            $contact->name = trim(($data['first_name'] ?? $contact->first_name).' '.($data['last_name'] ?? $contact->last_name));
        }
        $contact->save();

        if ($emailChanged) {
            $this->authService->issueEmailVerificationCode($contact->fresh(), true);
        }

        return $this->jsonSuccess($this->authService->formatContact($contact->fresh()));
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        /** @var Contact $contact */
        $contact = $request->user();
        $token = $this->authService->changePasswordAndIssueToken(
            $contact,
            $data['current_password'],
            $data['password']
        );

        return $this->jsonSuccess([
            'message' => 'Password updated.',
            'token' => $token,
            'token_type' => 'Bearer',
            'contact' => $this->authService->formatContact($contact->fresh()),
        ]);
    }

    public function requestDeletion(Request $request)
    {
        /** @var Contact $contact */
        $contact = $request->user();

        if (empty($contact->storefront_delete_requested_at)) {
            $contact->storefront_delete_requested_at = now();
            $contact->save();

            try {
                $to = config('mail.from.address');
                if ($to) {
                    Mail::to($to)->queue(new StorefrontAccountDeleteRequest($contact));
                }
            } catch (\Throwable $e) {
                Log::warning('Storefront delete-request email failed.', [
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        }

        return $this->jsonSuccess([
            'message' => 'Deletion request recorded. Our team will process it.',
            'contact' => $this->authService->formatContact($contact->fresh()),
        ]);
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

    public function rewardPoints(Request $request)
    {
        /** @var Contact $contact */
        $contact = $request->user();

        return $this->jsonSuccess($this->rewardPointsService->balancePayload($this->businessId($request), $contact));
    }

    public function validateRewardRedeem(Request $request)
    {
        $data = $request->validate([
            'requested_points' => 'required|integer|min:0',
            'order_total' => 'required|numeric|min:0',
        ]);

        /** @var Contact $contact */
        $contact = $request->user();

        return $this->jsonSuccess($this->rewardPointsService->validateRedemption(
            $this->businessId($request),
            $contact,
            (int) $data['requested_points'],
            (float) $data['order_total']
        ));
    }

    private function inferDialCode(string $mobile): string
    {
        foreach ($this->phoneValidation->getCountriesData() as $country) {
            $dial = $country['dial_code'] ?? '';
            if ($dial !== '' && str_starts_with($mobile, $dial)) {
                return $dial;
            }
        }

        return '+20';
    }
}
