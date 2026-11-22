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
        $otpEmail = old('email', session('login_otp_email'));
        $step = ($request->query('step') === 'code' || session('login_otp_email') || old('code'))
            ? 'code'
            : 'email';

        $resendAvailableIn = 0;
        if ($step === 'code' && is_string($otpEmail) && $otpEmail !== '') {
            $resendAvailableIn = $this->otpService->secondsUntilResendAllowedForEmail($otpEmail);
        }

        return response()
            ->view('auth.login', [
                'loginStep' => $step,
                'otpEmail' => $otpEmail,
                'resendAvailableIn' => $resendAvailableIn,
                'resendCooldownSeconds' => max(1, (int) config('auth_otp.resend_cooldown_seconds', 60)),
            ])
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Email a 6-digit login code when the address matches an eligible staff user.
     */
    public function requestOtp(Request $request)
    {
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
                    ->route('login', ['step' => 'code'])
                    ->with('login_otp_email', $email)
                    ->with('status', [
                        'success' => 0,
                        'msg' => $message,
                    ]);
            }

            return redirect()
                ->route('login')
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
            ->route('login', ['step' => 'code'])
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
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'remember' => 'sometimes|boolean',
        ]);

        $email = strtolower(trim($data['email']));

        try {
            $user = $this->otpService->verifyCode($email, $data['code']);
        } catch (ValidationException $e) {
            return redirect()
                ->route('login', ['step' => 'code'])
                ->with('login_otp_email', $email)
                ->withInput($request->only('email', 'remember'))
                ->withErrors($e->errors());
        }

        Auth::login($user, (bool) $request->boolean('remember'));
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
}
