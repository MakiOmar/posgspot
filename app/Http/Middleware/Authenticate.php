<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Log;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // For API routes, always return null to prevent redirect (will return JSON 401)
        if ($request->is('api/*')) {
            // Log authentication attempt for debugging
            $allHeaders = $request->headers->all();
            Log::info('API Authentication attempt', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'has_authorization_header' => $request->hasHeader('Authorization'),
                'authorization_header' => $request->header('Authorization') ? substr($request->header('Authorization'), 0, 30) . '...' : null,
                'bearer_token' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : null,
                'all_headers' => array_keys($allHeaders),
                'authorization_from_server' => $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null),
            ]);
            return null;
        }
        
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
