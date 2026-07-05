<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Contact;
use App\Services\Storefront\CustomerAuthService;
use App\Services\Storefront\PhoneValidationService;
use App\Services\Storefront\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\StorefrontPasswordReset;
use Illuminate\Support\Facades\Log;

class AuthController extends StorefrontController
{
    public function __construct(
        private CustomerAuthService $authService,
        private PhoneValidationService $phoneValidation,
        private TurnstileService $turnstile
    ) {
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:191',
            'last_name' => 'nullable|string|max:191',
            'email' => 'required|email|max:191',
            'mobile' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'dial_code' => 'nullable|string|max:6',
            'turnstile_token' => 'nullable|string',
        ]);

        $businessId = $this->businessId($request);
        $turnstileError = $this->turnstile->validate($businessId, $data['turnstile_token'] ?? null, $request->ip());
        if ($turnstileError !== null) {
            return $this->jsonError($turnstileError, 422, ['turnstile_token' => [$turnstileError]]);
        }

        $dialCode = $data['dial_code'] ?? $this->inferDialCode($data['mobile']);
        $phoneCheck = $this->phoneValidation->validate($data['mobile'], $dialCode);
        if (! $phoneCheck['valid']) {
            return $this->jsonError($phoneCheck['message'], 422, ['mobile' => [$phoneCheck['message']]]);
        }

        $result = $this->authService->register($businessId, $data);

        return $this->jsonSuccess($result, [], 201);
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

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $result = $this->authService->login($this->businessId($request), $data['login'], $data['password']);

        return $this->jsonSuccess($result);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->jsonSuccess(['message' => 'Logged out.']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $businessId = $this->businessId($request);

        $contact = Contact::where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->where('email', $data['email'])
            ->first();

        if ($contact) {
            $token = Str::random(64);
            DB::table('password_resets_contacts')->updateOrInsert(
                ['email' => $contact->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );
            try {
                Mail::to($contact->email)->queue(new StorefrontPasswordReset($contact, $token));
            } catch (\Throwable $e) {
                // Never fail the public response (no account enumeration); log for operators.
                Log::warning('Storefront password reset email failed.', [
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        }

        return $this->jsonSuccess(['message' => 'If the email exists, a reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $businessId = $this->businessId($request);
        $row = DB::table('password_resets_contacts')->where('email', $data['email'])->first();

        if (empty($row) || ! Hash::check($data['token'], $row->token)) {
            return $this->jsonError('Invalid or expired reset token.', 422);
        }

        $expireMinutes = max(1, (int) config('storefront.password_reset_expire_minutes', 60));
        $createdAt = $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at) : null;
        if (empty($createdAt) || $createdAt->lt(now()->subMinutes($expireMinutes))) {
            DB::table('password_resets_contacts')->where('email', $data['email'])->delete();

            return $this->jsonError('Invalid or expired reset token.', 422);
        }

        $contact = Contact::where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->where('email', $data['email'])
            ->first();

        if (empty($contact)) {
            return $this->jsonError('Contact not found.', 404);
        }

        $contact->password = Hash::make($data['password']);
        $contact->save();
        $contact->tokens()->delete();
        DB::table('password_resets_contacts')->where('email', $data['email'])->delete();

        return $this->jsonSuccess(['message' => 'Password updated successfully.']);
    }
}
