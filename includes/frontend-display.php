<?php
/**
 * Frontend display handlers for hotel booking variations
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Hotel_Booking_Frontend_Display {
    
    public function __construct() {
        // Modify booking form display for variable booking products
        add_action('woocommerce_before_booking_form', array($this, 'add_variation_selection'), 10, 1);
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_quantity_selection'), 15);
        
        // Hide default quantity field for variable bookings
        add_filter('woocommerce_bookings_quantity_input_args', array($this, 'maybe_hide_quantity'), 10, 2);
        
        // Add variation data to booking form
        add_filter('woocommerce_bookings_booking_form_options', array($this, 'add_variation_to_booking_form'), 10, 2);
    }
    
    /**
     * Display variation selection for variable booking products
     */
    public function add_variation_selection($product) {
        // Fix for when product is passed as ID instead of object
        if (!is_object($product)) {
            $product_id = absint($product);
            $product = wc_get_product($product_id);
        }
        
        // Check if we have a valid product object
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }
        
        // Now check if it's a booking product with variables
        if (!$product->is_type('booking') || $product->get_meta('_has_variables') !== 'yes') {
            return;
        }
        
        // Get available variations
        $available_variations = $product->get_available_variations();
        if (empty($available_variations)) {
            return;
        }
        
        echo '<div class="wc-hotel-variation-select">';
        echo '<h3>' . esc_html__('Select Room Type', 'wc-hotel-booking') . '</h3>';
        echo '<select id="hotel-room-variation" name="variation_id">';
        echo '<option value="">' . esc_html__('Choose an option', 'wc-hotel-booking') . '</option>';
        
        foreach ($available_variations as $variation) {
            $variation_id = $variation['variation_id'];
            $variation_obj = wc_get_product($variation_id);
            
            if (!$variation_obj || !$variation_obj->is_in_stock()) {
                continue;
            }
            
            $attributes_html = array();
            foreach ($variation['attributes'] as $key => $value) {
                $attribute_name = str_replace('attribute_', '', $key);
                $attribute_label = wc_attribute_label($attribute_name);
                $attributes_html[] = $attribute_label . ': ' . $value;
            }
            
            $variation_title = implode(', ', $attributes_html);
            $stock_quantity = $variation_obj->get_stock_quantity();
            $stock_text = $stock_quantity ? sprintf(__(' (Available: %d)', 'wc-hotel-booking'), $stock_quantity) : '';
            
            echo '<option value="' . esc_attr($variation_id) . '" data-max="' . esc_attr($stock_quantity) . '">' 
                . esc_html($variation_title) . esc_html($stock_text) . '</option>';
        }
        
        echo '</select>';
        echo '</div>';
        
        // Add hidden input for storing the selected variation
        echo '<input type="hidden" name="wc_hotel_selected_variation" id="wc_hotel_selected_variation" value="">';
    }
    
    /**
     * Add quantity selection for multiple rooms
     */
    public function add_quantity_selection() {
        global $product;
        
        if (!is_a($product, 'WC_Product') || !$product->is_type('booking') || $product->get_meta('_has_variables') !== 'yes') {
            return;
        }
        
        echo '<div class="wc-hotel-room-quantity">';
        echo '<h3>' . esc_html__('Number of Rooms', 'wc-hotel-booking') . '</h3>';
        woocommerce_quantity_input(array(
            'min_value' => 1,
            'max_value' => 99,
            'input_id' => 'hotel_room_quantity',
            'input_name' => 'quantity',
            'step' => 1,
        ));
        echo '</div>';
    }
    
    /**
     * Hide default quantity input for variable booking products
     */
    public function maybe_hide_quantity($args, $product) {
        if ($product->is_type('booking') && $product->get_meta('_has_variables') === 'yes') {
            $args['class'] = isset($args['class']) ? $args['class'] . ' wc-hotel-hidden-quantity' : 'wc-hotel-hidden-quantity';
        }
        return $args;
    }
    
    /**
     * Add variation data to booking form options
     */
    public function add_variation_to_booking_form($booking_form_options, $product) {
        if ($product->is_type('booking') && $product->get_meta('_has_variables') === 'yes') {
            $booking_form_options['wc_hotel_booking_variations'] = true;
        }
        return $booking_form_options;
    }
}

// Initialize the class
new WC_Hotel_Booking_Frontend_Display();
