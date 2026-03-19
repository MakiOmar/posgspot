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
 * Custom product class for POS Combo products.
 */
if (! class_exists('WC_Product_Pos_Combo') && class_exists('WC_Product_Simple')) {
    class WC_Product_Pos_Combo extends WC_Product_Simple
    {
        /**
         * Product type identifier.
         *
         * @return string
         */
        public function get_type()
        {
            return 'pos_combo';
        }
    }
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
        return WC_Product_Pos_Combo::class;
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
 * Render combo items list on single product page for pos_combo products.
 */
add_action('woocommerce_single_product_summary', function () {
    global $product;

    if (! $product instanceof WC_Product) {
        return;
    }

    if ($product->get_type() !== 'pos_combo') {
        return;
    }

    $items = get_post_meta($product->get_id(), '_pos_combo_items', true);
    if (empty($items) || ! is_array($items)) {
        // Optional: log missing items for pos_combo products
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info(
                'POS Combo: no _pos_combo_items meta found when rendering product page',
                [
                    'source' => 'pos-combo',
                    'product_id' => $product->get_id(),
                ]
            );
        }

        return;
    }

    echo '<div class="pos-combo-items">';
    echo '<h3>' . esc_html__('Combo contents', 'pos-combo') . '</h3>';
    echo '<ul>';

    foreach ($items as $item) {
        $component_product_id = isset($item['woocommerce_product_id']) ? (int) $item['woocommerce_product_id'] : 0;
        $component_variation_id = isset($item['woocommerce_variation_id']) ? (int) $item['woocommerce_variation_id'] : 0;
        $quantity = isset($item['quantity']) ? (float) $item['quantity'] : 0;

        if ($component_variation_id > 0) {
            $component = wc_get_product($component_variation_id);
        } else {
            $component = $component_product_id > 0 ? wc_get_product($component_product_id) : null;
        }

        if (! $component) {
            continue;
        }

        $title = $component->get_name();
        $qty_display = $quantity > 0 ? $quantity : 1;

        echo '<li>';
        echo esc_html($title) . ' &times; ' . esc_html(wc_format_decimal($qty_display, 2));
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
},40);

/**
 * Persist _pos_combo_items meta from REST API payload.
 */
add_action('woocommerce_rest_insert_product', function ($product, $request, $creating) {
    $logger = function_exists('wc_get_logger') ? wc_get_logger() : null;

    // Log basic context for debugging POS sync
    if ($logger) {
        $logger->info(
            'POS Combo: REST insert product called',
            [
                'source' => 'pos-combo',
                'product_id' => $product->get_id(),
                'creating' => (bool) $creating,
                'product_type' => $product->get_type(),
            ]
        );
    }

    $meta_data = $request->get_param('meta_data');
    if (empty($meta_data) || ! is_array($meta_data)) {
        if ($logger && $product->get_type() === 'pos_combo') {
            $logger->warning(
                'POS Combo: no meta_data received for pos_combo product via REST',
                [
                    'source' => 'pos-combo',
                    'product_id' => $product->get_id(),
                ]
            );
        }

        return;
    }

    if ($logger && $product->get_type() === 'pos_combo') {
        $logger->info(
            'POS Combo: raw meta_data received from POS',
            [
                'source' => 'pos-combo',
                'product_id' => $product->get_id(),
                // Do not log full payload to avoid huge logs; log keys only
                'meta_keys' => array_map(function ($meta) {
                    return isset($meta['key']) ? $meta['key'] : null;
                }, $meta_data),
            ]
        );
    }

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
                    'has_items' => is_array($normalized) && ! empty($normalized),
                ]
            );
        }
    }
}, 10, 3);

