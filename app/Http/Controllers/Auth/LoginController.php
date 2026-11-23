<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Rules\ReCaptcha;
use App\Services\StaffLoginOtpService;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $moduleUtil;

    public function __construct(
        BusinessUtil $businessUtil,
        ModuleUtil $moduleUtil,
        private StaffLoginOtpService $otpService
    ) {
        $this->middleware('guest')->except('logout');
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
    }

    public function showLoginForm(Request $request)
    {
        $loginStep = $this->resolveLoginStep($request);
        $otpEmail = old('email', session('login_otp_email'));

        $resendAvailableIn = 0;
        if ($loginStep === 'code' && is_string($otpEmail) && $otpEmail !== '') {
            $resendAvailableIn = $this->otpService->secondsUntilResendAllowedForEmail($otpEmail);
        }

        return response()
            ->view('auth.login', [
                'loginStep' => $loginStep,
                'otpEmail' => $otpEmail,
                'loginMethod' => $this->loginMethod(),
                'passwordLoginEnabled' => $this->passwordLoginEnabled(),
                'otpLoginEnabled' => $this->otpLoginEnabled(),
                'resendAvailableIn' => $resendAvailableIn,
                'resendCooldownSeconds' => max(1, (int) config('auth_otp.resend_cooldown_seconds', 60)),
            ])
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Username + password staff login (when STAFF_LOGIN_METHOD is password or both).
     */
    public function login(Request $request)
    {
        $this->ensurePasswordLoginAllowed();
        $this->validatePasswordLogin($request);

        if ($this->hasTooManyLoginAttempts($request)) {
            return $this->sendLockoutResponse($request);
        }

        if (Auth::attempt($this->credentials($request), false)) {
            $request->session()->put('auth.password_confirmed_at', time());
            $this->clearLoginAttempts($request);

            $user = Auth::user();
            if ($response = $this->authenticated($request, $user)) {
                return $response;
            }

            $request->session()->regenerate();

            return redirect()->intended($this->redirectTo());
        }

        $this->incrementLoginAttempts($request);

        throw ValidationException::withMessages([
            'username' => [trans('auth.failed')],
        ]);
    }

    /**
     * Email a 6-digit login code when the address matches an eligible staff user.
     */
    public function requestOtp(Request $request)
    {
        $this->ensureOtpLoginAllowed();

        $rules = ['email' => 'required|email'];
        if (config('constants.enable_recaptcha')) {
            $rules['g-recaptcha-response'] = ['required', new ReCaptcha];
        }
        $data = $request->validate($rules);

        $email = strtolower(trim($data['email']));
        $result = $this->otpService->requestCode($email);

        if (! $result['ok']) {
            $retryAfter = isset($result['retry_after']) ? (int) $result['retry_after'] : null;
            $message = StaffLoginOtpService::failureMessage($result['reason'], $retryAfter);

            if ($result['reason'] === StaffLoginOtpService::RESULT_COOLDOWN) {
                return redirect()
                    ->route('login', $this->otpRouteParams(['step' => 'code']))
                    ->with('login_otp_email', $email)
                    ->with('status', [
                        'success' => 0,
                        'msg' => $message,
                    ]);
            }

            return redirect()
                ->route('login', $this->otpRouteParams())
                ->withInput(['email' => $email])
                ->withErrors(['email' => $message])
                ->with('status', [
                    'success' => 0,
                    'msg' => $message,
                ]);
        }

        $msg = ! empty($result['reused'])
            ? __('lang_v1.login_code_resent_same')
            : __('lang_v1.login_code_sent');

        return redirect()
            ->route('login', $this->otpRouteParams(['step' => 'code']))
            ->with('login_otp_email', $email)
            ->with('status', [
                'success' => 1,
                'msg' => $msg,
            ]);
    }

    /**
     * Verify the emailed OTP and start a web session.
     */
    public function verifyOtp(Request $request)
    {
        $this->ensureOtpLoginAllowed();

        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $email = strtolower(trim($data['email']));

        try {
            $user = $this->otpService->verifyCode($email, $data['code']);
        } catch (ValidationException $e) {
            return redirect()
                ->route('login', $this->otpRouteParams(['step' => 'code']))
                ->with('login_otp_email', $email)
                ->withInput($request->only('email'))
                ->withErrors($e->errors());
        }

        Auth::login($user);
        $request->session()->forget('login_otp_email');
        $request->session()->regenerate();
        $request->session()->put('auth.password_confirmed_at', time());

        if ($response = $this->authenticated($request, $user)) {
            return $response;
        }

        return redirect()->intended($this->redirectTo());
    }

    public function logout()
    {
        $this->businessUtil->activityLog(auth()->user(), 'logout');

        request()->session()->flush();
        Auth::logout();

        return redirect('/login');
    }

    /**
     * After Auth::login: enforce business/user eligibility, else log out.
     *
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        $this->businessUtil->activityLog($user, 'login', null, [], false, $user->business_id);

        if (! $user->business || ! $user->business->is_active) {
            Auth::logout();

            return redirect('/login')
                ->with(
                    'status',
                    ['success' => 0, 'msg' => __('lang_v1.business_inactive')]
                );
        } elseif ($user->status != 'active') {
            Auth::logout();

            return redirect('/login')
                ->with(
                    'status',
                    ['success' => 0, 'msg' => __('lang_v1.user_inactive')]
                );
        } elseif (! $user->allow_login) {
            Auth::logout();

            return redirect('/login')
                ->with(
                    'status',
                    ['success' => 0, 'msg' => __('lang_v1.login_not_allowed')]
                );
        } elseif (($user->user_type == 'user_customer') && ! $this->moduleUtil->hasThePermissionInSubscription($user->business_id, 'crm_module')) {
            Auth::logout();

            return redirect('/login')
                ->with(
                    'status',
                    ['success' => 0, 'msg' => __('lang_v1.business_dont_have_crm_subscription')]
                );
        }
    }

    protected function redirectTo()
    {
        $user = Auth::user();
        if (! $user->can('dashboard.data') && $user->can('sell.create')) {
            return '/pos/create';
        }

        if ($user->user_type == 'user_customer') {
            return 'contact/contact-dashboard';
        }

        return '/home';
    }

    private function loginMethod(): string
    {
        $method = strtolower((string) config('auth_otp.login_method', 'otp'));

        return in_array($method, ['otp', 'password', 'both'], true) ? $method : 'otp';
    }

    private function passwordLoginEnabled(): bool
    {
        return in_array($this->loginMethod(), ['password', 'both'], true);
    }

    private function otpLoginEnabled(): bool
    {
        return in_array($this->loginMethod(), ['otp', 'both'], true);
    }

    private function ensurePasswordLoginAllowed(): void
    {
        if (! $this->passwordLoginEnabled()) {
            abort(404);
        }
    }

    private function ensureOtpLoginAllowed(): void
    {
        if (! $this->otpLoginEnabled()) {
            abort(404);
        }
    }

    /**
     * Keep OTP redirects on the OTP path when dual-mode would otherwise default to password.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function otpRouteParams(array $extra = []): array
    {
        if ($this->loginMethod() === 'both') {
            $extra['method'] = 'otp';
        }

        return $extra;
    }

    /**
     * Resolve which panel to show: password | email | code.
     */
    private function resolveLoginStep(Request $request): string
    {
        $method = $this->loginMethod();

        if ($method === 'password') {
            return 'password';
        }

        $wantsOtp = $method === 'otp'
            || $request->query('method') === 'otp'
            || $request->query('step') === 'code'
            || session()->has('login_otp_email')
            || old('code');

        if (! $wantsOtp && $this->passwordLoginEnabled()) {
            return 'password';
        }

        if ($request->query('step') === 'code' || session('login_otp_email') || old('code')) {
            return 'code';
        }

        return 'email';
    }

    private function validatePasswordLogin(Request $request): void
    {
        $rules = [
            'username' => 'required|string',
            'password' => 'required|string',
        ];
        if (config('constants.enable_recaptcha')) {
            $rules['g-recaptcha-response'] = ['required', new ReCaptcha];
        }
        $request->validate($rules);
    }

    /**
     * @return array{username: string, password: string}
     */
    private function credentials(Request $request): array
    {
        return $request->only('username', 'password');
    }

    private function hasTooManyLoginAttempts(Request $request): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->throttleKey($request),
            max(1, (int) config('auth_otp.password_max_attempts', 5))
        );
    }

    private function incrementLoginAttempts(Request $request): void
    {
        RateLimiter::hit(
            $this->throttleKey($request),
            max(1, (int) config('auth_otp.password_decay_minutes', 1)) * 60
        );
    }

    private function clearLoginAttempts(Request $request): void
    {
        RateLimiter::clear($this->throttleKey($request));
    }

    private function sendLockoutResponse(Request $request)
    {
        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'username' => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ])->status(429);
    }

    private function throttleKey(Request $request): string
    {
        return strtolower((string) $request->input('username')).'|'.$request->ip();
    }
}
