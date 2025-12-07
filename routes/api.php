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

        // Generate a Passport access token
        $tokenResult = $user->createToken('API Token');
        $token = $tokenResult->accessToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer'
        ], 200);
    } catch (\Exception $e) {
        \Log::error('Token generation failed: ' . $e->getMessage());
        return response()->json([
            'message' => 'Failed to generate token',
            'error' => config('app.debug') ? $e->getMessage() : 'Please ensure Passport keys are generated'
        ], 500);
    }
});

Route::get('/user', function (Request $request) {
    return $request->user();
});

// Test endpoint to debug authentication
Route::get('/test-auth', function (Request $request) {
    return response()->json([
        'authenticated' => false,
        'message' => 'API is currently public (no auth required)',
        'token_info' => [
            'has_token' => $request->bearerToken() ? true : false,
            'token_preview' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : null
        ]
    ]);
});

Route::post('/accounts/orders/create/{business_id}', [AccountsApi::class, 'orderCreated']);
Route::post('/woo/create-contact', [AccountsApi::class, 'createContact']);//create contact
Route::post('/woo/get-orders', [AccountsApi::class, 'getOrdersByPhone']);//get orders by phone

Route::get('/possells/{transaction_id}/print', [SellPosController::class, 'printWooInvoice'])->name('possell.printInvoice');