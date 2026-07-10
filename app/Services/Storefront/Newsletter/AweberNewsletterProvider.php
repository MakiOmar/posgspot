<?php

namespace App\Services\Storefront\Newsletter;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AWeber API 1.0 — add subscriber to a list (OAuth access token from AWeber app).
 *
 * Double opt-in is controlled in the AWeber list settings; we always POST the email.
 *
 * @see https://api.aweber.com/#tag/Subscribers
 */
class AweberNewsletterProvider implements NewsletterProviderInterface
{
    public function subscribe(string $email, array $config, array $meta = []): NewsletterResult
    {
        $block = $config['aweber'] ?? [];
        $token = trim((string) ($block['access_token'] ?? ''));
        $accountId = trim((string) ($block['account_id'] ?? ''));
        $listId = trim((string) ($block['list_id'] ?? ''));
        $doubleOptIn = ! empty($config['double_opt_in']);

        if ($token === '' || $accountId === '' || $listId === '') {
            return new NewsletterResult(NewsletterResult::STATUS_FAILED, 'AWeber is not configured.');
        }

        $url = "https://api.aweber.com/1.0/accounts/{$accountId}/lists/{$listId}/subscribers";

        $payload = [
            'email' => $email,
            'strict_custom_fields' => 'false',
        ];

        try {
            $response = Http::withToken($token)
                ->asForm()
                ->acceptJson()
                ->timeout(15)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('AWeber newsletter subscribe failed.', ['error' => $e->getMessage()]);

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

        if ($response->status() === 400) {
            $error = strtolower((string) ($response->json('error.message') ?? $response->body()));
            if (str_contains($error, 'already subscribed') || str_contains($error, 'already exists')) {
                return new NewsletterResult(
                    NewsletterResult::STATUS_ALREADY,
                    'You are already subscribed to our newsletter.'
                );
            }
        }

        Log::warning('AWeber newsletter subscribe rejected.', [
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ]);

        return new NewsletterResult(NewsletterResult::STATUS_FAILED, 'Could not subscribe right now. Please try again later.');
    }
}
