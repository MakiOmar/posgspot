<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FixAuthorizationHeader
{
    /**
     * Handle an incoming request.
     * Fixes Authorization header when it's not properly forwarded by proxy/load balancer.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if bearer token is missing or null, even if header exists
        $bearerToken = $request->bearerToken();
        $authHeader = $request->header('Authorization');
        
        // If bearer token is null (even if header exists but value is null), extract from $_SERVER
        if (!$bearerToken || empty($authHeader)) {
            // Check various $_SERVER keys where Authorization might be stored
            $serverAuthHeader = null;
            
            if (isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $serverAuthHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $serverAuthHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                if (isset($headers['Authorization']) && !empty($headers['Authorization'])) {
                    $serverAuthHeader = $headers['Authorization'];
                } elseif (isset($headers['authorization']) && !empty($headers['authorization'])) {
                    $serverAuthHeader = $headers['authorization'];
                }
            }
            
            // If we found the Authorization header in $_SERVER, set it on the request
            if ($serverAuthHeader) {
                // Remove any existing authorization header (case-insensitive)
                $request->headers->remove('authorization');
                $request->headers->remove('Authorization');
                // Set it with proper capitalization
                $request->headers->set('Authorization', $serverAuthHeader);
                
                \Log::info('Fixed Authorization header from $_SERVER', [
                    'token_preview' => substr($serverAuthHeader, 0, 30) . '...',
                    'source' => isset($_SERVER['HTTP_AUTHORIZATION']) ? 'HTTP_AUTHORIZATION' : 
                                (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? 'REDIRECT_HTTP_AUTHORIZATION' : 'apache_request_headers')
                ]);
            }
        }
        
        return $next($request);
    }
}

