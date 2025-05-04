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
    $request->validate([
        'username' => 'required|string',
        'password' => 'required',
    ]);

    $user = User::where('username', $request->username)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // Generate a Passport access token
    $token = $user->createToken('API Token')->accessToken;

    return response()->json(['token' => $token], 200);
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:api')->post('/accounts/orders/create/{business_id}', [AccountsApi::class, 'orderCreated']);
Route::middleware('auth:api')->post('/woo/create-contact', [AccountsApi::class, 'createContact']);
Route::middleware('auth:api')->post('/woo/create-contact', [AccountsApi::class, 'getOrdersByPhone']);

Route::get('/possells/{transaction_id}/print', [SellPosController::class, 'printWooInvoice'])->name('possell.printInvoice');