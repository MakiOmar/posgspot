<?php
/**
 * Plugin Name: POS Combo Product Type
 * Description: Adds a dedicated pos_combo product type for WooCommerce and accepts combo structure from an external POS.
 * Version: 0.1.0
 * Author: POS Integration
 * Requires Plugins: woocommerce
 */

if (! defined('ABSPATH')) {
    exit;
}

// Ensure WooCommerce is active.
if (! function_exists('WC')) {
    return;
}

// Define constants.
if (! defined('POS_COMBO_PLUGIN_PATH')) {
    define('POS_COMBO_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

// Load classes.
require_once POS_COMBO_PLUGIN_PATH . 'includes/class-wc-product-pos-combo.php';
require_once POS_COMBO_PLUGIN_PATH . 'includes/class-pos-combo.php';

// Bootstrap.
add_action('plugins_loaded', ['POS_Combo', 'init']);

