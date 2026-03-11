<?php
/**
 * Plugin Name: POS Combo Product Type
 * Description: Registers the pos_combo product type and accepts combo meta data (_pos_combo_items) from the POS for WooCommerce.
 * Version: 0.1.0
 * Author: POS Integration
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register custom product type: pos_combo.
 */
add_filter('product_type_selector', function ($types) {
    $types['pos_combo'] = __('POS Combo', 'pos-combo');

    return $types;
});

add_filter('woocommerce_product_class', function ($classname, $product_type) {
    if ($product_type === 'pos_combo') {
        return WC_Product_Simple::class;
    }

    return $classname;
}, 10, 2);

/**
 * Ensure REST API accepts pos_combo as a valid product type.
 */
add_filter('woocommerce_rest_product_schema', function ($schema) {
    if (isset($schema['properties']['type']['enum']) && is_array($schema['properties']['type']['enum'])) {
        if (! in_array('pos_combo', $schema['properties']['type']['enum'], true)) {
            $schema['properties']['type']['enum'][] = 'pos_combo';
        }
    }

    return $schema;
});

/**
 * Persist _pos_combo_items meta from REST API payload.
 */
add_action('woocommerce_rest_insert_product', function ($product, $request, $creating) {
    $meta_data = $request->get_param('meta_data');
    if (empty($meta_data) || ! is_array($meta_data)) {
        return;
    }

    $logger = function_exists('wc_get_logger') ? wc_get_logger() : null;

    foreach ($meta_data as $meta) {
        if (! isset($meta['key'], $meta['value'])) {
            continue;
        }

        if ($meta['key'] !== '_pos_combo_items') {
            continue;
        }

        $raw_value = $meta['value'];
        $normalized = $raw_value;

        // If value is JSON string, try to decode.
        if (is_string($raw_value)) {
            $decoded = json_decode($raw_value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $normalized = $decoded;
            } elseif ($logger) {
                $logger->warning(
                    'POS Combo: invalid JSON payload for _pos_combo_items',
                    [
                        'source' => 'pos-combo',
                        'product_id' => $product->get_id(),
                    ]
                );
            }
        }

        update_post_meta($product->get_id(), '_pos_combo_items', $normalized);

        if ($logger) {
            $logger->info(
                'POS Combo: _pos_combo_items meta updated from REST sync',
                [
                    'source' => 'pos-combo',
                    'product_id' => $product->get_id(),
                ]
            );
        }
    }
}, 10, 3);

