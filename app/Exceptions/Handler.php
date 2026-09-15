<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpFoundation\Response;
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
                return $this->corsify($request, response()->json([
                    'message' => 'Unauthenticated.',
                    'error' => 'Authentication required',
                ], 401));
            }

            // Handle OAuth Server Exceptions
            if ($e instanceof \League\OAuth2\Server\Exception\OAuthServerException) {
                return $this->corsify($request, response()->json([
                    'message' => 'The resource owner or authorization server denied the request.',
                    'error' => 'Token validation failed',
                ], 401));
            }

            // Transient DB / connectivity — browsers need CORS on the error body.
            if ($e instanceof \Illuminate\Database\QueryException
                || $e instanceof \PDOException
            ) {
                $msg = $e->getMessage();
                $isConnectivity = str_contains($msg, '2002')
                    || str_contains($msg, '2006')
                    || str_contains($msg, 'Operation not permitted')
                    || str_contains($msg, 'Connection refused')
                    || str_contains($msg, 'server has gone away');

                if ($isConnectivity) {
                    return $this->corsify($request, response()->json([
                        'success' => false,
                        'message' => 'Store temporarily unavailable. Please try again.',
                        'error' => 'database_unavailable',
                    ], 503));
                }
            }
        }

        $response = parent::render($request, $e);

        if ($request->is('api/*') || $request->is('sanctum/csrf-cookie')) {
            return $this->corsify($request, $response);
        }

        return $response;
    }

    /**
     * HandleCors does not run when $next() throws — attach ACAO so SPA can read API errors.
     */
    private function corsify($request, Response $response): Response
    {
        $origin = $request->headers->get('Origin');
        if (! $origin) {
            return $response;
        }

        $allowed = config('cors.allowed_origins', []);
        if (! in_array($origin, $allowed, true) && ! in_array('*', $allowed, true)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        if (config('cors.supports_credentials')) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
