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
        // If Authorization header is missing but exists in $_SERVER, add it
        if (!$request->hasHeader('Authorization') && !$request->bearerToken()) {
            // Check various $_SERVER keys where Authorization might be stored
            $authHeader = null;
            
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                if (isset($headers['Authorization'])) {
                    $authHeader = $headers['Authorization'];
                } elseif (isset($headers['authorization'])) {
                    $authHeader = $headers['authorization'];
                }
            }
            
            // If we found the Authorization header, set it on the request
            if ($authHeader) {
                $request->headers->set('Authorization', $authHeader);
            }
        }
        
        return $next($request);
    }
}

