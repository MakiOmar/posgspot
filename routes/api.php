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