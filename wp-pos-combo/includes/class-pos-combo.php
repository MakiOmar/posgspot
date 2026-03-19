<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Main hooks for POS Combo integration with WooCommerce.
 */
class POS_Combo
{
    /**
     * Register WordPress / WooCommerce hooks.
     *
     * @return void
     */
    public static function init()
    {
        // Register product type for admin UI.
        add_filter('product_type_selector', [__CLASS__, 'register_product_type']);

        // Map product type to custom product class.
        add_filter('woocommerce_product_class', [__CLASS__, 'map_product_class'], 10, 2);

        // Extend REST product schema to allow pos_combo type.
        add_filter('woocommerce_rest_product_schema', [__CLASS__, 'extend_rest_schema']);

        // Capture combo meta from REST payloads.
        add_action('woocommerce_rest_insert_product', [__CLASS__, 'handle_rest_insert_product'], 10, 3);

        // Render combo contents on single product page.
        add_action('woocommerce_single_product_summary', [__CLASS__, 'render_combo_contents'], 25);

        // Reuse simple product add-to-cart template for pos_combo products.
        add_action('woocommerce_pos_combo_add_to_cart', 'woocommerce_simple_add_to_cart', 30);
    }

    /**
     * Add "POS Combo" to the product type selector.
     *
     * @param array $types
     * @return array
     */
    public static function register_product_type($types)
    {
        $types['pos_combo'] = __('POS Combo', 'pos-combo');

        return $types;
    }

    /**
     * Map product type to custom class.
     *
     * @param string $classname
     * @param string $product_type
     * @return string
     */
    public static function map_product_class($classname, $product_type)
    {
        if ($product_type === 'pos_combo' && class_exists('WC_Product_Pos_Combo')) {
            return 'WC_Product_Pos_Combo';
        }

        return $classname;
    }

    /**
     * Allow pos_combo as a valid product type in REST API schema.
     *
     * @param array $schema
     * @return array
     */
    public static function extend_rest_schema($schema)
    {
        if (isset($schema['properties']['type']['enum']) && is_array($schema['properties']['type']['enum'])) {
            if (! in_array('pos_combo', $schema['properties']['type']['enum'], true)) {
                $schema['properties']['type']['enum'][] = 'pos_combo';
            }
        }

        return $schema;
    }

    /**
     * Persist _pos_combo_items meta from REST API payload and log for debugging.
     *
     * @param WC_Product $product
     * @param WP_REST_Request $request
     * @param bool $creating
     * @return void
     */
    public static function handle_rest_insert_product($product, $request, $creating)
    {
        if (! $product instanceof WC_Product) {
            return;
        }

        $logger = function_exists('wc_get_logger') ? wc_get_logger() : null;

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
                    'meta_keys' => array_map(
                        function ($meta) {
                            return isset($meta['key']) ? $meta['key'] : null;
                        },
                        $meta_data
                    ),
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
    }

    /**
     * Render combo items as a list on the single product page.
     *
     * @return void
     */
    public static function render_combo_contents()
    {
        global $product;

        if (! $product instanceof WC_Product) {
            return;
        }

        if ($product->get_type() !== 'pos_combo') {
            return;
        }

        $items = get_post_meta($product->get_id(), '_pos_combo_items', true);
        if (empty($items) || ! is_array($items)) {
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
    }
}

