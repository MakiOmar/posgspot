<?php

/**
 * API Routes for WooCommerce Module
 * These routes are accessible without web session/authentication
 * Authentication is handled via API keys in the controller
 */

Route::post(
    '/update-order-custom-meta/{business_id}',
    [Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'updateOrderCustomMeta']
);

