<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\Newsletter\NewsletterProviderManager;
use App\Services\Storefront\TurnstileService;
use Illuminate\Http\Request;

class NewsletterController extends StorefrontController
{
    public function __construct(
        private NewsletterProviderManager $newsletters,
        private TurnstileService $turnstile
    ) {
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:191',
            'turnstile_token' => 'nullable|string',
        ]);

        $businessId = $this->businessId($request);

        if (! $this->newsletters->isEnabled($businessId)) {
            return $this->jsonError('Newsletter signup is not available.', 503);
        }

        $turnstileError = $this->turnstile->validate($businessId, $data['turnstile_token'] ?? null, $request->ip());
        if ($turnstileError !== null) {
            return $this->jsonError($turnstileError, 422, ['turnstile_token' => [$turnstileError]]);
        }

        $config = $this->newsletters->resolvedConfig($businessId);
        if ($config === null) {
            return $this->jsonError('Newsletter signup is not available.', 503);
        }

        $provider = (string) $config['provider'];
        $result = $this->newsletters->driver($provider)->subscribe(
            strtolower(trim($data['email'])),
            $config,
            [
                'ip' => $request->ip(),
                'locale' => $request->header('X-Content-Locale'),
            ]
        );

        if (! $result->ok()) {
            return $this->jsonError($result->message, 502);
        }

        return $this->jsonSuccess([
            'status' => $result->status,
            'message' => $result->message,
        ]);
    }
}
