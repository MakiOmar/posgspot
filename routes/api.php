<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\User;
use App\Http\Controllers\AccountsApi;
use Laravel\Passport\Passport;
use App\Http\Controllers\SellPosController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Debug endpoint to decode token (no auth required)
Route::post('/debug-token', function (Request $request) {
    $token = $request->bearerToken() ?? $request->input('token');
    
    if (!$token) {
        return response()->json(['error' => 'No token provided'], 400);
    }
    
    try {
        // Decode JWT token (just the payload, no signature verification)
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return response()->json(['error' => 'Invalid token format'], 400);
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        // Check if client exists
        $clientId = $payload['aud'] ?? null;
        $client = $clientId ? \Laravel\Passport\Client::find($clientId) : null;
        
        // Check if token is revoked in database (JTI = JWT ID = token ID in oauth_access_tokens table)
        $jti = $payload['jti'] ?? null;
        $tokenRecord = $jti ? \DB::table('oauth_access_tokens')->where('id', $jti)->first() : null;
        $tokenRevoked = $tokenRecord ? (bool) $tokenRecord->revoked : null;
        $tokenExpired = $tokenRecord && $tokenRecord->expires_at ? 
            (strtotime($tokenRecord->expires_at) < time()) : null;
        
        // Check available personal access clients
        $personalClients = \Laravel\Passport\Client::where('personal_access_client', true)->get(['id', 'name', 'revoked']);
        
        // Determine the issue
        $issue = null;
        if (!$client) {
            $issue = 'Client ID ' . $clientId . ' does NOT exist in database';
        } elseif ($client->revoked) {
            $issue = 'Client exists but is REVOKED';
        } elseif (!$client->personal_access_client) {
            $issue = 'Client exists but is NOT a personal access client';
        } elseif ($tokenRevoked === true) {
            $issue = 'Token is REVOKED in database (oauth_access_tokens table)';
        } elseif ($tokenRevoked === null) {
            $issue = 'Token record NOT found in database (oauth_access_tokens table) - token may be invalid or signed with different keys';
        } elseif ($tokenExpired) {
            $issue = 'Token has EXPIRED';
        } else {
            $issue = 'Client exists and is valid, token exists and is not revoked';
        }
        
        return response()->json([
            'token_info' => [
                'client_id' => $clientId,
                'user_id' => $payload['sub'] ?? null,
                'jti' => $jti,
                'expires_at' => isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : null,
                'issued_at' => isset($payload['iat']) ? date('Y-m-d H:i:s', $payload['iat']) : null,
            ],
            'client_exists' => $client ? true : false,
            'client_info' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'revoked' => $client->revoked,
                'personal_access_client' => $client->personal_access_client,
            ] : null,
            'token_in_database' => [
                'found' => $tokenRecord !== null,
                'revoked' => $tokenRevoked,
                'expired' => $tokenExpired,
                'expires_at' => $tokenRecord && $tokenRecord->expires_at ? $tokenRecord->expires_at : null,
            ],
            'available_personal_clients' => $personalClients->map(function($c) {
                return ['id' => $c->id, 'name' => $c->name, 'revoked' => $c->revoked];
            }),
            'message' => $issue
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to decode token: ' . $e->getMessage()], 500);
    }
});

Route::post('/login', function (Request $request) {
    try {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Ensure personal access client exists
        $personalClient = \Laravel\Passport\Client::where('personal_access_client', true)->first();
        if (!$personalClient) {
            \Log::warning('Personal access client not found, attempting to create one');
            // Try to create personal access client if it doesn't exist
            try {
                \Artisan::call('passport:client', ['--personal' => true, '--name' => 'Personal Access Client']);
                // Refresh after creation
                $personalClient = \Laravel\Passport\Client::where('personal_access_client', true)->first();
            } catch (\Exception $e) {
                \Log::error('Failed to create personal access client: ' . $e->getMessage());
            }
        }

        if (!$personalClient) {
            return response()->json([
                'message' => 'Personal access client not configured. Please contact support.',
                'error' => 'Passport client missing'
            ], 500);
        }

        // Generate a Passport access token
        $tokenResult = $user->createToken('API Token');
        $token = $tokenResult->accessToken;
        
        \Log::info('Token generated successfully', [
            'user_id' => $user->id,
            'client_id' => $personalClient->id,
            'token_preview' => substr($token, 0, 20) . '...'
        ]);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer'
        ], 200);
    } catch (\League\OAuth2\Server\Exception\OAuthServerException $e) {
        \Log::error('OAuth Server Error: ' . $e->getMessage(), [
            'error_code' => $e->getCode(),
            'hint' => $e->getHint()
        ]);
        return response()->json([
            'message' => 'Failed to generate token',
            'error' => 'OAuth server error. Please ensure Passport is properly configured.',
            'hint' => config('app.debug') ? $e->getHint() : 'Check if personal access client exists'
        ], 500);
    } catch (\Exception $e) {
        \Log::error('Token generation failed: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'message' => 'Failed to generate token',
            'error' => config('app.debug') ? $e->getMessage() : 'Please ensure Passport keys are generated and personal access client exists'
        ], 500);
    }
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Test endpoint to debug authentication
Route::middleware('auth:api')->get('/test-auth', function (Request $request) {
    return response()->json([
        'authenticated' => true,
        'user' => $request->user(),
        'token_info' => [
            'has_token' => $request->bearerToken() ? true : false,
            'token_preview' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : null
        ]
    ]);
});

Route::middleware('auth:api')->post('/accounts/orders/create/{business_id}', [AccountsApi::class, 'orderCreated']);
Route::middleware('auth:api')->post('/woo/create-contact', [AccountsApi::class, 'createContact']);//create contact
Route::middleware('auth:api')->post('/woo/get-orders', [AccountsApi::class, 'getOrdersByPhone']);//get orders by phone

Route::get('/possells/{transaction_id}/print', [SellPosController::class, 'printWooInvoice'])->name('possell.printInvoice');