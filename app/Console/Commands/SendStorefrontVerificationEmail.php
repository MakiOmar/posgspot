<?php

namespace App\Console\Commands;

use App\Contact;
use App\Mail\StorefrontEmailVerification;
use App\Services\Storefront\CustomerAuthService;
use App\Services\Storefront\StorefrontMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Diagnostic: send a storefront email-verification OTP and print sent/failed status.
 *
 * Sends synchronously (sendNow) so SMTP errors surface immediately — unlike the
 * queued path used by the public API.
 */
class SendStorefrontVerificationEmail extends Command
{
    protected $signature = 'storefront:send-verification
        {email : Customer email address}
        {--business_id= : Business id (default: config storefront.business_id)}
        {--force : Re-send even if already verified}
        {--show-code : Print the OTP in the console (local/debug only)}';

    protected $description = 'Send a storefront email verification OTP and report sent status';

    public function handle(StorefrontMailService $mailService): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $businessId = (int) ($this->option('business_id') ?: config('storefront.business_id', 1));
        $force = (bool) $this->option('force');

        $contact = Contact::where('business_id', $businessId)
            ->whereIn('type', ['customer', 'both'])
            ->where('email', $email)
            ->first();

        if (! $contact) {
            $this->error("No customer contact found for {$email} (business_id={$businessId}).");

            return self::FAILURE;
        }

        if (! empty($contact->email_verified_at) && ! $force) {
            $this->warn("Contact #{$contact->id} is already verified (email_verified_at={$contact->email_verified_at}).");
            $this->line('Re-run with --force to clear verification and send a new code.');

            return self::FAILURE;
        }

        $from = $mailService->applyForBusiness($businessId);

        $this->table(
            ['Key', 'Value'],
            [
                ['contact_id', (string) $contact->id],
                ['email', (string) $contact->email],
                ['business_id', (string) $businessId],
                ['mailer', (string) Config::get('mail.default')],
                ['smtp_host', (string) Config::get('mail.mailers.smtp.host')],
                ['smtp_port', (string) Config::get('mail.mailers.smtp.port')],
                ['smtp_username', (string) Config::get('mail.mailers.smtp.username')],
                ['from_address', $from['address']],
                ['from_name', $from['name']],
                ['queue_connection', (string) Config::get('queue.default')],
            ]
        );

        $code = (string) random_int(100000, 999999);
        $contact->email_verify_code_hash = Hash::make($code);
        $contact->email_verify_expires_at = now()->addMinutes(CustomerAuthService::VERIFY_CODE_TTL_MINUTES);
        if ($force) {
            $contact->email_verified_at = null;
        }
        $contact->save();

        if ($this->option('show-code')) {
            $this->warn("OTP (debug): {$code}");
        }

        try {
            // Force synchronous send so SMTP auth failures are visible here.
            Mail::to($contact->email)->sendNow(new StorefrontEmailVerification($contact, $code));
        } catch (\Throwable $e) {
            $this->error('FAILED to send verification email.');
            $this->line($e::class.': '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("SENT verification email to {$contact->email} (contact #{$contact->id}).");
        $this->line('Code expires in '.CustomerAuthService::VERIFY_CODE_TTL_MINUTES.' minutes.');

        return self::SUCCESS;
    }
}
