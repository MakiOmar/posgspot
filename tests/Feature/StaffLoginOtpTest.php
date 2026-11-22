<?php

namespace Tests\Feature;

use App\Business;
use App\Mail\StaffLoginOtpMail;
use App\Services\OneTimeCodeAttempts;
use App\Services\StaffLoginOtpService;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Staff web login at /login uses email OTP only.
 */
class StaffLoginOtpTest extends TestCase
{
    use DatabaseTransactions;

    private int $businessId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        if (! Business::whereKey($this->businessId)->exists()) {
            $this->markTestSkipped('No business in database.');
        }
    }

    private function staff(array $overrides = []): User
    {
        $user = User::where('business_id', $this->businessId)
            ->where('allow_login', 1)
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->first();

        if (! $user) {
            $this->markTestSkipped('No eligible staff user for business 1.');
        }

        $email = 'staff_otp_'.uniqid().'@example.com';
        $user->fill(array_merge([
            'email' => $email,
            'status' => 'active',
            'allow_login' => 1,
            'login_otp_hash' => null,
            'login_otp_expires_at' => null,
        ], $overrides));
        $user->save();

        return $user->fresh();
    }

    public function test_request_otp_sends_mail_for_active_staff(): void
    {
        $user = $this->staff();

        $this->post(route('login.otp.request'), ['email' => $user->email])
            ->assertRedirect(route('login', ['step' => 'code']))
            ->assertSessionHas('status.success', 1);

        Mail::assertSent(StaffLoginOtpMail::class, function (StaffLoginOtpMail $mail) use ($user) {
            return $mail->user->is($user) && strlen($mail->code) === 6;
        });

        $user->refresh();
        $this->assertNotEmpty($user->login_otp_hash);
        $this->assertNotNull($user->login_otp_expires_at);
    }

    public function test_request_otp_for_unknown_email_shows_not_found_and_sends_nothing(): void
    {
        $this->post(route('login.otp.request'), ['email' => 'nobody_'.uniqid().'@example.com'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionHas('status.success', 0);

        Mail::assertNothingSent();
    }

    public function test_request_otp_rejects_inactive_users_with_cause(): void
    {
        $user = $this->staff(['status' => 'inactive']);

        $this->post(route('login.otp.request'), ['email' => $user->email])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionHas('status.success', 0);

        Mail::assertNothingSent();
        $this->assertNull($user->fresh()->login_otp_hash);
    }

    public function test_request_otp_rejects_login_disabled_with_cause(): void
    {
        $user = $this->staff(['allow_login' => 0]);

        $this->post(route('login.otp.request'), ['email' => $user->email])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionHas('status.success', 0);

        Mail::assertNothingSent();
        $this->assertNull($user->fresh()->login_otp_hash);
    }

    public function test_verify_otp_logs_in_and_clears_code(): void
    {
        $user = $this->staff();
        $code = '123456';
        $user->login_otp_hash = Hash::make($code);
        $user->login_otp_expires_at = now()->addMinutes(10);
        $user->save();

        $this->post(route('login.otp.verify'), [
            'email' => $user->email,
            'code' => $code,
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertNull($user->login_otp_hash);
        $this->assertNull($user->login_otp_expires_at);
    }

    public function test_verify_otp_rejects_wrong_code(): void
    {
        $user = $this->staff();
        $user->login_otp_hash = Hash::make('123456');
        $user->login_otp_expires_at = now()->addMinutes(10);
        $user->save();

        $this->from(route('login', ['step' => 'code']))
            ->post(route('login.otp.verify'), [
                'email' => $user->email,
                'code' => '000000',
            ])
            ->assertRedirect(route('login', ['step' => 'code']))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertNotNull($user->fresh()->login_otp_hash);
    }

    public function test_verify_otp_rejects_expired_code(): void
    {
        $user = $this->staff();
        $user->login_otp_hash = Hash::make('123456');
        $user->login_otp_expires_at = now()->subMinute();
        $user->save();

        $this->post(route('login.otp.verify'), [
            'email' => $user->email,
            'code' => '123456',
        ])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_too_many_wrong_guesses_burn_the_code(): void
    {
        $user = $this->staff();
        $user->login_otp_hash = Hash::make('123456');
        $user->login_otp_expires_at = now()->addMinutes(10);
        $user->save();

        $service = app(StaffLoginOtpService::class);

        for ($i = 0; $i < OneTimeCodeAttempts::MAX_FAILURES; $i++) {
            try {
                $service->verifyCode($user->email, '000000');
                $this->fail('Expected ValidationException');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey('code', $e->errors());
            }
        }

        $this->assertNull($user->fresh()->login_otp_hash);
    }

    public function test_password_login_route_is_gone(): void
    {
        $this->post('/login', [
            'username' => 'admin',
            'password' => 'secret',
        ])->assertMethodNotAllowed();
    }

    public function test_login_page_is_email_otp_only(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('lang_v1.send_login_code'), false)
            ->assertSee(route('login.otp.request'), false)
            ->assertDontSee('name="username"', false)
            ->assertDontSee('name="password"', false);
    }
}
