<?php

use App\Http\Controllers\AccountsApi;
use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SellPosController;
use App\Http\Resources\ApiUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Machine/integration Passport routes. POST /api/login is allowlisted only
| (see config/api.php). Protected routes require auth:api plus a scope.
|
*/

Route::post('/login', [ApiTokenController::class, 'login'])
    ->middleware('throttle:api-login');

Route::middleware(['auth:api', 'scope:user-read'])->get('/user', function (Request $request) {
    return new ApiUserResource($request->user());
});

Route::middleware(['auth:api', 'scope:accounts-orders'])
    ->post('/accounts/orders/create/{business_id}', [AccountsApi::class, 'orderCreated'])
    ->whereNumber('business_id');

Route::middleware(['auth:api', 'scope:accounts-orders'])
    ->post('/accounts/orders/undone/{business_id}', [AccountsApi::class, 'orderUndone'])
    ->whereNumber('business_id');

Route::middleware(['auth:api', 'scope:accounts-catalog', 'throttle:accounts-catalog'])
    ->withoutMiddleware('throttle:api')
    ->post('/accounts/catalog/upsert/{business_id}', [AccountsApi::class, 'catalogUpsert'])
    ->whereNumber('business_id');

Route::middleware(['auth:api', 'scope:contacts-read', 'throttle:pii-lookup'])->get(
    '/contacts/{contact_id}/info',
    [ContactController::class, 'getContactInfoApi']
)->name('contacts.getInfo')->whereNumber('contact_id');

Route::middleware(['auth:api', 'scope:contacts-read', 'throttle:pii-lookup'])->get(
    '/contacts/{mobile}/details',
    [ContactController::class, 'getContactInfoByMobileApi']
)->name('contacts.getInfoByMobile');

Route::middleware(['auth:api', 'scope:rewards'])->post(
    '/rewards/validate',
    [SellPosController::class, 'validateRewardRedeem']
)->name('rewards.validate');

Route::middleware(['auth:api', 'scope:rewards'])->post(
    '/rewards/redeem',
    [SellPosController::class, 'redeemRewardPoints']
)->name('rewards.redeem');
