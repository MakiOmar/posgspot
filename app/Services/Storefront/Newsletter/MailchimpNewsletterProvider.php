<?php

namespace App\Services\Storefront\Newsletter;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mailchimp Marketing API — add/update audience member.
 *
 * @see https://mailchimp.com/developer/marketing/api/list-members/
 */
class MailchimpNewsletterProvider implements NewsletterProviderInterface
{
    public function subscribe(string $email, array $config, array $meta = []): NewsletterResult
    {
        $block = $config['mailchimp'] ?? [];
        $apiKey = trim((string) ($block['api_key'] ?? ''));
        $audienceId = trim((string) ($block['audience_id'] ?? ''));
        $doubleOptIn = ! empty($config['double_opt_in']);

        if ($apiKey === '' || $audienceId === '' || ! str_contains($apiKey, '-')) {
            return new NewsletterResult(NewsletterResult::STATUS_FAILED, 'Mailchimp is not configured.');
        }

        $dc = substr($apiKey, strrpos($apiKey, '-') + 1);
        $hash = md5(strtolower($email));
        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$audienceId}/members/{$hash}";

        $payload = [
            'email_address' => $email,
            'status_if_new' => $doubleOptIn ? 'pending' : 'subscribed',
        ];

        try {
            $response = Http::withBasicAuth('anystring', $apiKey)
                ->acceptJson()
                ->timeout(15)
                ->put($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('Mailchimp newsletter subscribe failed.', ['error' => $e->getMessage()]);

            return new NewsletterResult(NewsletterResult::STATUS_FAILED, 'Could not subscribe right now. Please try again later.');
        }

        if ($response->successful()) {
            $status = (string) $response->json('status');
            if ($status === 'pending') {
                return new NewsletterResult(
                    NewsletterResult::STATUS_PENDING,
                    'Please check your email to confirm your subscription.'
                );
            }

            return new NewsletterResult(
                NewsletterResult::STATUS_SUBSCRIBED,
                'Thanks — you are subscribed to our newsletter.'
            );
        }

        // Member already exists with same status, or title conflict — treat as success.
        $title = (string) $response->json('title');
        if ($response->status() === 400 && str_contains(strtolower($title), 'member exists')) {
            return new NewsletterResult(
                NewsletterResult::STATUS_ALREADY,
                'You are already subscribed to our newsletter.'
            );
        }

        Log::warning('Mailchimp newsletter subscribe rejected.', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return new NewsletterResult(NewsletterResult::STATUS_FAILED, 'Could not subscribe right now. Please try again later.');
    }
}
