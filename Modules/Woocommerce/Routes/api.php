<?php

/**
 * API Routes for WooCommerce Module
 * Protected with Laravel API authentication
 */

Route::middleware('auth:api')->post(
    '/update-order-custom-meta/{business_id}',
    [Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'updateOrderCustomMeta']
);

Route::middleware('auth:api')->post(
    '/bulk-send-orders/{business_id}',
    [Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'bulkSendOrders']
);

