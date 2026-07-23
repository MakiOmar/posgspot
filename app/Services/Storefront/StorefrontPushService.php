<?php

namespace App\Services\Storefront;

use App\StorefrontDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Registers mobile push tokens and sends FCM HTTP v1 notifications.
 */
class StorefrontPushService
{
    public function register(int $businessId, int $contactId, string $platform, string $token, ?string $locale = null): StorefrontDeviceToken
    {
        $platform = strtolower($platform) === 'ios' ? 'ios' : 'android';
        $token = trim($token);

        return StorefrontDeviceToken::updateOrCreate(
            [
                'contact_id' => $contactId,
                'token' => $token,
            ],
            [
                'business_id' => $businessId,
                'platform' => $platform,
                'locale' => $locale ? substr($locale, 0, 8) : null,
            ]
        );
    }

    public function unregister(int $contactId, string $token): int
    {
        return StorefrontDeviceToken::where('contact_id', $contactId)
            ->where('token', trim($token))
            ->delete();
    }

    /**
     * @param  array{title: string, body: string, data?: array<string, string>}  $payload
     */
    public function notifyContact(int $contactId, array $payload): void
    {
        $tokens = StorefrontDeviceToken::where('contact_id', $contactId)->get();
        foreach ($tokens as $device) {
            $this->sendToToken($device, $payload);
        }
    }

    /**
     * @param  array{title: string, body: string, data?: array<string, string>}  $payload
     */
    public function sendToToken(StorefrontDeviceToken $device, array $payload): void
    {
        $projectId = (string) config('storefront.fcm_project_id', '');
        $accessToken = $this->fcmAccessToken();

        if ($projectId === '' || $accessToken === null) {
            Log::info('storefront.push.skipped', [
                'reason' => 'fcm_not_configured',
                'contact_id' => $device->contact_id,
                'platform' => $device->platform,
            ]);

            return;
        }

        $message = [
            'message' => [
                'token' => $device->token,
                'notification' => [
                    'title' => $payload['title'],
                    'body' => $payload['body'],
                ],
                'data' => $payload['data'] ?? [],
            ],
        ];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $message);

        if ($response->status() === 404 || $response->status() === 410) {
            $device->delete();

            return;
        }

        if (! $response->successful()) {
            Log::warning('storefront.push.failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'contact_id' => $device->contact_id,
            ]);
        }
    }

    /**
     * OAuth access token for FCM HTTP v1 via service account JSON path.
     */
    private function fcmAccessToken(): ?string
    {
        $path = (string) config('storefront.fcm_credentials_path', '');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        try {
            $json = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $clientEmail = $json['client_email'] ?? null;
            $privateKey = $json['private_key'] ?? null;
            if (! is_string($clientEmail) || ! is_string($privateKey)) {
                return null;
            }

            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = $this->base64Url(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $unsigned = $header.'.'.$claim;
            $signature = '';
            if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                return null;
            }
            $jwt = $unsigned.'.'.$this->base64Url($signature);

            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $tokenResponse->successful()) {
                return null;
            }

            $access = $tokenResponse->json('access_token');

            return is_string($access) ? $access : null;
        } catch (\Throwable $e) {
            Log::warning('storefront.push.auth_failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
