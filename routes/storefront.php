<?php

use App\Http\Controllers\Api\Storefront\AccountController;
use App\Http\Controllers\Api\Storefront\AuthController;
use App\Http\Controllers\Api\Storefront\AvailabilityController;
use App\Http\Controllers\Api\Storefront\BostaDistrictController;
use App\Http\Controllers\Api\Storefront\BrandController;
use App\Http\Controllers\Api\Storefront\CartController;
use App\Http\Controllers\Api\Storefront\CategoryController;
use App\Http\Controllers\Api\Storefront\CheckoutController;
use App\Http\Controllers\Api\Storefront\ContactController;
use App\Http\Controllers\Api\Storefront\CouponController;
use App\Http\Controllers\Api\Storefront\CustomerRegistrationController;
use App\Http\Controllers\Api\Storefront\DeviceController;
use App\Http\Controllers\Api\Storefront\DigitalCatalogController;
use App\Http\Controllers\Api\Storefront\GeoController;
use App\Http\Controllers\Api\Storefront\HomepageController;
use App\Http\Controllers\Api\Storefront\LocationController;
use App\Http\Controllers\Api\Storefront\PhoneCountryController;
use App\Http\Controllers\Api\Storefront\NewsletterController;
use App\Http\Controllers\Api\Storefront\PaymentReturnController;
use App\Http\Controllers\Api\Storefront\PaymentWebhookController;
use App\Http\Controllers\Api\Storefront\PingController;
use App\Http\Controllers\Api\Storefront\ProductController;
use App\Http\Controllers\Api\Storefront\ProductReviewController;
use App\Http\Controllers\Api\Storefront\RepairStatusController;
use App\Http\Controllers\Api\Storefront\SearchController;
use App\Http\Controllers\Api\Storefront\SettingsController;
use App\Http\Controllers\Api\Storefront\WishlistController;
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
    Route::get('/homepage', [HomepageController::class, 'show']);
    Route::get('/phone-countries', [PhoneCountryController::class, 'index']);
    Route::get('/geo/countries', [GeoController::class, 'countries']);
    Route::get('/geo/states/{countryCode}', [GeoController::class, 'states']);
    Route::get('/geo/bosta-districts', [BostaDistrictController::class, 'index']);
    Route::post('/customers/add', [CustomerRegistrationController::class, 'store']);
    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/homepage-shelves', [CategoryController::class, 'homepageShelves']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brands/{slug}', [BrandController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{idOrSlug}/reviews', [ProductReviewController::class, 'index']);
    Route::get('/products/{idOrSlug}', [ProductController::class, 'show']);
    Route::get('/products/{productId}/availability', [AvailabilityController::class, 'show']);
    Route::get('/search', [SearchController::class, 'index']);

    Route::post('/contact', [ContactController::class, 'store']);
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
    Route::post('/repair/status', [RepairStatusController::class, 'store']);

    Route::get('/digital/games', [DigitalCatalogController::class, 'games']);
    Route::get('/digital/games/{id}', [DigitalCatalogController::class, 'game'])->whereNumber('id');
    Route::get('/digital/card-categories', [DigitalCatalogController::class, 'cardCategories']);
    Route::post('/digital/check-stock', [DigitalCatalogController::class, 'checkGameStock']);
    Route::post('/digital/check-card-stock', [DigitalCatalogController::class, 'checkCardStock']);

    Route::post('/coupons/validate', [CouponController::class, 'validateCode']);
    Route::post('/coupons/available', [CouponController::class, 'available']);
    Route::post('/cart/validate', [CartController::class, 'validateCart']);
    Route::post('/checkout', [CheckoutController::class, 'store']);

    Route::post('/payments/{provider}/webhook', [PaymentWebhookController::class, 'handle']);
    Route::post('/payments/{provider}/return', [PaymentReturnController::class, 'confirm']);
    Route::post('/payments/{provider}/session', [PaymentReturnController::class, 'session']);

    Route::prefix('auth')->middleware('throttle:storefront-auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
        Route::post('/email/resend', [AuthController::class, 'resendEmailVerification']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist/merge', [WishlistController::class, 'merge']);
        Route::post('/wishlist', [WishlistController::class, 'store']);
        Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);

        Route::get('/products/{idOrSlug}/reviews/eligibility', [ProductReviewController::class, 'eligibility']);
        Route::post('/products/{idOrSlug}/reviews', [ProductReviewController::class, 'store']);
    });

    Route::middleware('auth:sanctum')->prefix('account')->group(function () {
        Route::get('/profile', [AccountController::class, 'profile']);
        Route::put('/profile', [AccountController::class, 'updateProfile']);
        Route::put('/password', [AccountController::class, 'updatePassword']);
        Route::post('/delete-request', [AccountController::class, 'requestDeletion']);
        Route::put('/address', [AccountController::class, 'updateAddress']);
        Route::get('/orders', [AccountController::class, 'orders']);
        Route::get('/orders/{orderId}', [AccountController::class, 'orderDetail']);
        Route::get('/orders/{orderId}/invoice', [AccountController::class, 'orderInvoice']);
        Route::get('/reward-points', [AccountController::class, 'rewardPoints']);
        Route::post('/reward-points/validate', [AccountController::class, 'validateRewardRedeem']);
        Route::post('/devices', [DeviceController::class, 'store']);
        Route::delete('/devices/{token}', [DeviceController::class, 'destroy'])->where('token', '.*');
    });
});
