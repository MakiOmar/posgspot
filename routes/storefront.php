<?php

use App\Http\Controllers\Api\Storefront\AccountController;
use App\Http\Controllers\Api\Storefront\AuthController;
use App\Http\Controllers\Api\Storefront\AvailabilityController;
use App\Http\Controllers\Api\Storefront\CartController;
use App\Http\Controllers\Api\Storefront\CategoryController;
use App\Http\Controllers\Api\Storefront\CheckoutController;
use App\Http\Controllers\Api\Storefront\LocationController;
use App\Http\Controllers\Api\Storefront\PaymentWebhookController;
use App\Http\Controllers\Api\Storefront\PingController;
use App\Http\Controllers\Api\Storefront\ProductController;
use App\Http\Controllers\Api\Storefront\SearchController;
use App\Http\Controllers\Api\Storefront\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront API (public Qwik website)
|--------------------------------------------------------------------------
| Versioned endpoints consumed by the Qwik storefront and future mobile app.
| Completely independent of the WooCommerce module.
*/

Route::prefix('storefront/v1')->group(function () {
    Route::get('/ping', PingController::class)->name('storefront.ping');

    Route::get('/settings', [SettingsController::class, 'show']);
    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{idOrSlug}', [ProductController::class, 'show']);
    Route::get('/products/{productId}/availability', [AvailabilityController::class, 'show']);
    Route::get('/search', [SearchController::class, 'index']);

    Route::post('/cart/validate', [CartController::class, 'validateCart']);
    Route::post('/checkout', [CheckoutController::class, 'store']);

    Route::post('/payments/{provider}/webhook', [PaymentWebhookController::class, 'handle']);

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->prefix('account')->group(function () {
        Route::get('/profile', [AccountController::class, 'profile']);
        Route::put('/profile', [AccountController::class, 'updateProfile']);
        Route::put('/address', [AccountController::class, 'updateAddress']);
        Route::get('/orders', [AccountController::class, 'orders']);
        Route::get('/orders/{orderId}', [AccountController::class, 'orderDetail']);
    });
});
