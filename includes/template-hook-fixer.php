<?php
/**
 * Fixes template hooks and compatibility issues
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Hotel_Booking_Template_Hook_Fixer {
    
    public function __construct() {
        // Modify add to cart button text
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'change_button_text'), 20, 2);
        
        // Filter booking form templates
        add_filter('wc_get_template', array($this, 'maybe_override_template'), 10, 5);
        
        // Add AJAX endpoint for checking variation availability
        add_action('wp_ajax_check_variation_availability', array($this, 'check_variation_availability'));
        add_action('wp_ajax_nopriv_check_variation_availability', array($this, 'check_variation_availability'));
    }
    
    /**
     * Change the Add to Cart button text for bookable products
     */
    public function change_button_text($text, $product) {
        if ($product && $product->is_type('booking') && $product->get_meta('_has_variables') === 'yes') {
            return __('Book Now', 'wc-hotel-booking');
        }
        return $text;
    }
    
    /**
     * Override templates if needed for variable bookings
     */
    public function maybe_override_template($template, $template_name, $args, $template_path, $default_path) {
        if ($template_path !== 'woocommerce-bookings') {
            return $template;
        }
        
        // Check if we need to override any WooCommerce Bookings templates
        $override_templates = array(
            'booking-form/date-picker.php',
            'booking-form/number-of-persons.php'
        );
        
        if (in_array($template_name, $override_templates)) {
            $custom_template = plugin_dir_path(dirname(__FILE__)) . 'templates/' . $template_name;
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }
    
    /**
     * AJAX endpoint for checking variation availability
     */
    public function check_variation_availability() {
        if (!isset($_POST['product_id']) || !isset($_POST['variation_id']) || !isset($_POST['quantity'])) {
            wp_send_json_error();
        }
        
        $product_id = absint($_POST['product_id']);
        $variation_id = absint($_POST['variation_id']);
        $quantity = absint($_POST['quantity']);
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('booking')) {
            wp_send_json_error();
        }
        
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            wp_send_json_error();
        }
        
        // Check stock availability
        $stock_quantity = $variation->get_stock_quantity();
        if ($stock_quantity !== null && $quantity > $stock_quantity) {
            wp_send_json_error(array(
                'message' => sprintf(__('Only %d room(s) available', 'wc-hotel-booking'), $stock_quantity)
            ));
        }
        
        // If dates are provided, check booking availability
        if ($start_date && $end_date) {
            $booking_form = new WC_Booking_Form($product);
            
            // Convert dates to timestamps
            $start = strtotime($start_date);
            $end = strtotime($end_date);
            
            // Check each day in the range
            $current = $start;
            while ($current <= $end) {
                $date_to_check = new WC_DateTime("@$current");
                
                // Manually check if this date is bookable
                $is_bookable = apply_filters(
                    'wc_bookings_is_date_bookable', 
                    true, 
                    $date_to_check, 
                    0, 
                    $booking_form
                );
                
                if (!$is_bookable) {
                    wp_send_json_error(array(
                        'message' => __('Selected dates are not available for the requested quantity', 'wc-hotel-booking')
                    ));
                }
                
                // Move to next day
                $current = strtotime('+1 day', $current);
            }
        }
        
        wp_send_json_success(array(
            'message' => __('Rooms available!', 'wc-hotel-booking')
        ));
    }
}

// Initialize the class
new WC_Hotel_Booking_Template_Hook_Fixer();
