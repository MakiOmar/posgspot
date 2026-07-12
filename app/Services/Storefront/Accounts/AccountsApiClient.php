<?php

namespace App\Services\Storefront\Accounts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the Games Spot Accounts API (digital inventory).
 */
class AccountsApiClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.accounts.base'), '/');
    }

    public function token(bool $forceRefresh = false): ?string
    {
        if (! $forceRefresh) {
            $cached = Cache::get('accounts_auth_token');
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        try {
            $response = Http::timeout(20)->post($this->baseUrl().'/api/login', [
                'phone' => config('services.accounts.phone'),
                'password' => config('services.accounts.password'),
            ]);
            if ($response->successful() && ! empty($response->json('token'))) {
                $token = (string) $response->json('token');
                Cache::put('accounts_auth_token', $token, now()->addDays(7));

                return $token;
            }
            Log::warning('Accounts API login failed', ['body' => $response->body()]);
        } catch (\Throwable $e) {
            Log::warning('Accounts API login exception: '.$e->getMessage());
        }

        return null;
    }

    /**
     * @return array{success:bool,status:int,body:?array,error:?string}
     */
    public function request(string $method, string $path, ?array $body = null, bool $auth = true): array
    {
        $url = $this->baseUrl().'/'.ltrim($path, '/');
        $headers = ['Accept' => 'application/json'];
        if ($auth) {
            $token = $this->token();
            if (! $token) {
                return ['success' => false, 'status' => 0, 'body' => null, 'error' => 'Accounts auth failed'];
            }
            $headers['Authorization'] = 'Bearer '.$token;
        }

        try {
            $pending = Http::withHeaders($headers)->timeout(30)->acceptJson();
            $response = match (strtoupper($method)) {
                'GET' => $pending->get($url, $body ?? []),
                'POST' => $pending->asJson()->post($url, $body ?? []),
                default => throw new \InvalidArgumentException('Unsupported method '.$method),
            };

            if ($response->status() === 401 && $auth) {
                $token = $this->token(true);
                if ($token) {
                    $headers['Authorization'] = 'Bearer '.$token;
                    $pending = Http::withHeaders($headers)->timeout(30)->acceptJson();
                    $response = strtoupper($method) === 'GET'
                        ? $pending->get($url, $body ?? [])
                        : $pending->asJson()->post($url, $body ?? []);
                }
            }

            $json = $response->json();

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'body' => is_array($json) ? $json : null,
                'error' => $response->successful()
                    ? null
                    : (string) (is_array($json) ? ($json['message'] ?? $response->body()) : $response->body()),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'status' => 0, 'body' => null, 'error' => $e->getMessage()];
        }
    }

    public function getGamesByPlatform(string $platform, int $page = 1): array
    {
        return $this->request('GET', 'api/games/platform/'.$platform, ['page' => $page], false);
    }

    public function getGame(int $id): array
    {
        return $this->request('GET', 'api/games/'.$id, null, false);
    }

    public function getCardCategories(): array
    {
        return $this->request('GET', 'api/card-ctegories/list', null, false);
    }

    public function checkStock(array $payload): array
    {
        return $this->request('POST', 'api/orders/check_stock', $payload, true);
    }

    public function checkCardStock(array $payload): array
    {
        return $this->request('POST', 'api/orders/check_card_stock', $payload, true);
    }

    public function receiveOrder(array $payload): array
    {
        return $this->request('POST', 'api/orders/receive', $payload, true);
    }

    public function stampPosOrder(string $storefrontOrderId, int $posTransactionId, ?int $accountsOrderId = null): array
    {
        $payload = [
            'woocommerce_order_id' => $posTransactionId,
            'created' => $posTransactionId,
        ];
        $storefrontOrderId = trim($storefrontOrderId);
        if ($storefrontOrderId !== '') {
            $payload['storefront_order_id'] = $storefrontOrderId;
        }
        if ($accountsOrderId !== null && $accountsOrderId > 0) {
            $payload['order_id'] = $accountsOrderId;
        }

        return $this->request('POST', 'api/pos/receive-order', $payload, true);
    }
}
