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
        
        // Normalize authHeader - Laravel might return array or string
        $authHeaderValue = is_array($authHeader) ? ($authHeader[0] ?? null) : $authHeader;
        
        // If bearer token is null (even if header exists but value is null), extract from $_SERVER
        if (!$bearerToken || empty($authHeaderValue)) {
            // Check various $_SERVER keys where Authorization might be stored
            $serverAuthHeader = null;
            $source = null;
            
            if (isset($_SERVER['HTTP_AUTHORIZATION']) && !empty(trim($_SERVER['HTTP_AUTHORIZATION']))) {
                $serverAuthHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
                $source = 'HTTP_AUTHORIZATION';
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && !empty(trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))) {
                $serverAuthHeader = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
                $source = 'REDIRECT_HTTP_AUTHORIZATION';
            } elseif (function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                if (isset($headers['Authorization']) && !empty(trim($headers['Authorization']))) {
                    $serverAuthHeader = trim($headers['Authorization']);
                    $source = 'apache_request_headers[Authorization]';
                } elseif (isset($headers['authorization']) && !empty(trim($headers['authorization']))) {
                    $serverAuthHeader = trim($headers['authorization']);
                    $source = 'apache_request_headers[authorization]';
                }
            }
            
            // If we found the Authorization header in $_SERVER, set it on the request
            if ($serverAuthHeader) {
                // Remove any existing authorization header (case-insensitive)
                $request->headers->remove('authorization');
                $request->headers->remove('Authorization');
                // Set it with proper capitalization
                $request->headers->set('Authorization', $serverAuthHeader);
                
                // Also set it in the server parameters to ensure it's available
                $request->server->set('HTTP_AUTHORIZATION', $serverAuthHeader);
            }
        }
        
        return $next($request);
    }
}

