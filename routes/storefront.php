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
use App\Http\Controllers\Api\Storefront\CustomBundleController;
use App\Http\Controllers\Api\Storefront\CustomerRegistrationController;
use App\Http\Controllers\Api\Storefront\DeviceController;
use App\Http\Controllers\Api\Storefront\DeviceTrackController;
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
use App\Http\Controllers\Api\Storefront\CommunityPostController;
use App\Http\Controllers\Api\Storefront\RequestProductController;
use App\Http\Controllers\Api\Storefront\SellToUsController;
use App\Http\Controllers\Api\Storefront\SettingsController;
use App\Http\Controllers\Api\Storefront\SocialAuthController;
use App\Http\Controllers\Api\Storefront\SupportChatController;
use App\Http\Controllers\Api\Storefront\TrackOrderController;
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

    Route::get('/custom-bundle/meta', [CustomBundleController::class, 'meta']);
    Route::get('/custom-bundle/products', [CustomBundleController::class, 'products']);

    Route::get('/sell-to-us/meta', [SellToUsController::class, 'meta']);

    Route::get('/community/posts', [CommunityPostController::class, 'index']);
    Route::get('/community/posts/{slug}', [CommunityPostController::class, 'show']);
    Route::post('/community/posts/{slug}/applications', [CommunityPostController::class, 'apply'])
        ->middleware('throttle:storefront-community-apply');

    Route::get('/request-product/meta', [RequestProductController::class, 'meta']);
    Route::post('/request-product/requests', [RequestProductController::class, 'store']);

    Route::post('/contact', [ContactController::class, 'store']);
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
    Route::post('/track-order', [TrackOrderController::class, 'store'])
        ->middleware('throttle:20,1');

    Route::prefix('support')->middleware('throttle:storefront-support-chat')->group(function () {
        Route::get('/conversations', [SupportChatController::class, 'index']);
        Route::post('/conversations', [SupportChatController::class, 'store']);
        Route::post('/conversations/claim', [SupportChatController::class, 'claim']);
        Route::get('/conversations/{uuid}', [SupportChatController::class, 'show'])
            ->where('uuid', '[0-9a-fA-F-]{36}');
        Route::post('/conversations/{uuid}/messages', [SupportChatController::class, 'storeMessage'])
            ->where('uuid', '[0-9a-fA-F-]{36}');
        Route::post('/conversations/{uuid}/escalate', [SupportChatController::class, 'escalate'])
            ->where('uuid', '[0-9a-fA-F-]{36}');
    });
    Route::post('/repair/status', [RepairStatusController::class, 'store'])
        ->middleware('throttle:storefront-pii-lookup');
    Route::post('/device/track', [DeviceTrackController::class, 'store'])
        ->middleware('throttle:storefront-pii-lookup');

    Route::get('/digital/games', [DigitalCatalogController::class, 'games']);
    Route::get('/digital/games/{id}', [DigitalCatalogController::class, 'game'])->whereNumber('id');
    Route::get('/digital/card-categories', [DigitalCatalogController::class, 'cardCategories']);
    Route::post('/digital/check-stock', [DigitalCatalogController::class, 'checkGameStock']);
    Route::post('/digital/check-card-stock', [DigitalCatalogController::class, 'checkCardStock']);

    Route::post('/coupons/validate', [CouponController::class, 'validateCode']);
    Route::post('/coupons/available', [CouponController::class, 'available']);
    Route::post('/cart/validate', [CartController::class, 'validateCart']);
    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:storefront-checkout');

    Route::post('/payments/{provider}/webhook', [PaymentWebhookController::class, 'handle']);
    Route::post('/payments/{provider}/return', [PaymentReturnController::class, 'confirm']);
    Route::post('/payments/{provider}/session', [PaymentReturnController::class, 'session']);

    Route::prefix('auth')->middleware('throttle:storefront-auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:storefront-otp');
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
        Route::post('/email/resend', [AuthController::class, 'resendEmailVerification'])
            ->middleware('throttle:storefront-otp');

        Route::get('/social/{provider}/redirect', [SocialAuthController::class, 'redirect'])
            ->where('provider', 'google|facebook');
        Route::get('/social/{provider}/callback', [SocialAuthController::class, 'callback'])
            ->where('provider', 'google|facebook');
        Route::post('/social/exchange', [SocialAuthController::class, 'exchange']);
        Route::post('/social/{provider}/token', [SocialAuthController::class, 'token'])
            ->where('provider', 'google|facebook');

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

        Route::post('/sell-to-us/verify-invoice', [SellToUsController::class, 'verifyInvoice']);
        Route::post('/sell-to-us/requests', [SellToUsController::class, 'store']);
    });

    Route::middleware('auth:sanctum')->prefix('account')->group(function () {
        Route::get('/profile', [AccountController::class, 'profile']);
        Route::put('/profile', [AccountController::class, 'updateProfile']);
        Route::post('/profile/avatar', [AccountController::class, 'updateAvatar']);
        Route::delete('/profile/avatar', [AccountController::class, 'deleteAvatar']);
        Route::put('/password', [AccountController::class, 'updatePassword']);
        Route::post('/delete-request', [AccountController::class, 'requestDeletion']);
        Route::put('/address', [AccountController::class, 'updateAddress']);
        Route::get('/orders', [AccountController::class, 'orders']);
        Route::get('/orders/{orderId}', [AccountController::class, 'orderDetail']);
        Route::get('/orders/{orderId}/invoice', [AccountController::class, 'orderInvoice']);
        Route::get('/repairs', [AccountController::class, 'repairs']);
        Route::get('/device-services', [AccountController::class, 'deviceServices']);
        Route::get('/reward-points', [AccountController::class, 'rewardPoints']);
        Route::post('/reward-points/validate', [AccountController::class, 'validateRewardRedeem']);
        Route::get('/coupons/used', [AccountController::class, 'usedCoupons']);
        Route::get('/coupons', [AccountController::class, 'coupons']);
        Route::post('/coupons', [AccountController::class, 'saveCoupon']);
        Route::get('/social', [SocialAuthController::class, 'index']);
        Route::delete('/social/{provider}', [SocialAuthController::class, 'destroy'])
            ->where('provider', 'google|facebook');
        Route::post('/devices', [DeviceController::class, 'store']);
        Route::delete('/devices/{token}', [DeviceController::class, 'destroy'])->where('token', '.*');
    });
});
