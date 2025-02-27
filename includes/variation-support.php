<?php
/**
 * Adds variation support for WooCommerce Bookings products
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Hotel_Booking_Variation_Support {
    
    public function __construct() {
        // Add variation support to booking products
        add_filter('product_type_selector', array($this, 'add_variable_booking_product_type'));
        add_filter('woocommerce_product_class', array($this, 'get_product_class'), 10, 4);
        
        // Add booking tab to variation data
        add_action('woocommerce_product_after_variable_attributes', array($this, 'add_booking_fields_to_variations'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_booking_data'), 10, 2);
        
        // Add availability check for variations
        add_filter('woocommerce_booking_get_availability_args', array($this, 'filter_availability_args'), 10, 3);
    }
    
    /**
     * Add variable booking product type
     */
    public function add_variable_booking_product_type($types) {
        $types['variable_booking'] = __('Variable Booking', 'wc-hotel-booking');
        return $types;
    }
    
    /**
     * Get appropriate product class for variable booking
     */
    public function get_product_class($classname, $product_type, $post_type, $product_id) {
        if ($product_type === 'variable_booking') {
            return 'WC_Product_Booking';
        }
        return $classname;
    }
    
    /**
     * Add booking fields to variations for capacity and availability
     */
    public function add_booking_fields_to_variations($loop, $variation_data, $variation) {
        $variation_id = $variation->ID;
        $booking_capacity = get_post_meta($variation_id, '_wc_booking_max_per_block', true);
        
        echo '<div class="booking_variation_data">';
        echo '<p class="form-row form-row-first">';
        echo '<label>' . esc_html__('Room Capacity', 'wc-hotel-booking') . '</label>';
        echo '<input type="number" name="variation_booking_capacity[' . esc_attr($loop) . ']" ' . 
             'value="' . esc_attr($booking_capacity ? $booking_capacity : 1) . '" ' .
             'placeholder="' . esc_attr__('Room capacity', 'wc-hotel-booking') . '" ' .
             'min="1" step="1">';
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Save variation booking data
     */
    public function save_variation_booking_data($variation_id, $loop) {
        if (isset($_POST['variation_booking_capacity'][$loop])) {
            $capacity = absint($_POST['variation_booking_capacity'][$loop]);
            update_post_meta($variation_id, '_wc_booking_max_per_block', $capacity);
        }
    }
    
    /**
     * Filter availability arguments to respect variation stock
     */
    public function filter_availability_args($args, $product, $resource_id) {
        if (isset($_POST['wc_hotel_selected_variation']) && $_POST['wc_hotel_selected_variation']) {
            $variation_id = absint($_POST['wc_hotel_selected_variation']);
            $variation = wc_get_product($variation_id);
            
            if ($variation) {
                // Adjust availability based on variation stock
                $stock_quantity = $variation->get_stock_quantity();
                if ($stock_quantity !== null) {
                    $args['qty'] = absint(isset($_POST['quantity']) ? $_POST['quantity'] : 1);
                    $args['variation_id'] = $variation_id;
                }
            }
        }
        
        return $args;
    }
}

// Initialize the class
new WC_Hotel_Booking_Variation_Support();
