<?php

namespace App\Services\Storefront\Newsletter;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MailerLite API (connect.mailerlite.com) — create/update subscriber.
 *
 * @see https://developers.mailerlite.com/docs/subscribers
 */
class MailerLiteNewsletterProvider implements NewsletterProviderInterface
{
    public function subscribe(string $email, array $config, array $meta = []): NewsletterResult
    {
        $block = $config['mailerlite'] ?? [];
        $token = trim((string) ($block['api_token'] ?? ''));
        $groupId = trim((string) ($block['group_id'] ?? ''));
        $doubleOptIn = ! empty($config['double_opt_in']);

        if ($token === '') {
            return new NewsletterResult(NewsletterResult::STATUS_FAILED, 'MailerLite is not configured.');
        }

        $payload = [
            'email' => $email,
            'status' => $doubleOptIn ? 'unconfirmed' : 'active',
        ];

        if ($groupId !== '') {
            $payload['groups'] = [$groupId];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post('https://connect.mailerlite.com/api/subscribers', $payload);
        } catch (\Throwable $e) {
            Log::warning('MailerLite newsletter subscribe failed.', ['error' => $e->getMessage()]);

            return new NewsletterResult(NewsletterResult::STATUS_FAILED, 'Could not subscribe right now. Please try again later.');
        }

        if ($response->successful() || $response->status() === 201) {
            if ($doubleOptIn) {
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

        // Already subscribed / conflict
        if (in_array($response->status(), [409, 422], true)) {
            $message = strtolower((string) ($response->json('message') ?? ''));
            if (str_contains($message, 'already') || str_contains($message, 'exist')) {
                return new NewsletterResult(
                    NewsletterResult::STATUS_ALREADY,
                    'You are already subscribed to our newsletter.'
                );
            }
        }

        Log::warning('MailerLite newsletter subscribe rejected.', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return new NewsletterResult(NewsletterResult::STATUS_FAILED, 'Could not subscribe right now. Please try again later.');
    }
}
