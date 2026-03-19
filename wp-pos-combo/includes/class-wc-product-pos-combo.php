<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WC_Product_Pos_Combo') && class_exists('WC_Product_Simple')) {
    /**
     * POS Combo product type.
     *
     * Behaves like a simple product but uses the custom type identifier "pos_combo".
     */
    class WC_Product_Pos_Combo extends WC_Product_Simple
    {
        /**
         * Get internal type.
         *
         * @return string
         */
        public function get_type()
        {
            return 'pos_combo';
        }
    }
}

