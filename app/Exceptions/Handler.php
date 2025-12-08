<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // For API routes, always return JSON for authentication exceptions
        if ($request->is('api/*')) {
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error' => 'Authentication required'
                ], 401);
            }
            
            // Handle OAuth Server Exceptions with more details
            if ($e instanceof \League\OAuth2\Server\Exception\OAuthServerException) {
                $token = $request->bearerToken();
                $clientId = null;
                
                // Try to decode token to get client_id
                if ($token) {
                    try {
                        $parts = explode('.', $token);
                        if (count($parts) === 3) {
                            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
                            $clientId = $payload['aud'] ?? null;
                        }
                    } catch (\Exception $decodeException) {
                        // Ignore decode errors
                    }
                }
                
                // Check if client exists
                $clientExists = $clientId ? \Laravel\Passport\Client::find($clientId) !== null : false;
                $personalClients = \Laravel\Passport\Client::where('personal_access_client', true)->pluck('id')->toArray();
                
                return response()->json([
                    'message' => 'The resource owner or authorization server denied the request.',
                    'error' => 'Token validation failed',
                    'details' => [
                        'error_code' => $e->getCode(),
                        'hint' => $e->getHint(),
                        'token_client_id' => $clientId,
                        'client_exists' => $clientExists,
                        'available_personal_client_ids' => $personalClients,
                        'suggestion' => $clientId && !$clientExists ? 
                            'Token was created with client_id ' . $clientId . ' which does not exist. Generate a new token using /api/login.' :
                            ($clientId && !in_array($clientId, $personalClients) ?
                                'Token was created with client_id ' . $clientId . ' which is not a personal access client.' :
                                'Generate a new token using /api/login endpoint.')
                    ]
                ], 401);
            }
        }

        return parent::render($request, $e);
    }
}
