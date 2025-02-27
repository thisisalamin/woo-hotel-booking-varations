<?php
/**
 * Plugin Name: WooCommerce Hotel Booking Variations
 * Description: Enables booking multiple rooms of the same type while respecting WooCommerce Bookings availability per variation.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/template-hook-fixer.php';
require_once plugin_dir_path(__FILE__) . 'includes/frontend-display.php';
require_once plugin_dir_path(__FILE__) . 'includes/variation-support.php';

class WC_Hotel_Booking_Variations {
    public function __construct() {
        // Initialize with priority to ensure it runs early
        add_action('init', array($this, 'setup'), 5);
        
        // Core functionality
        add_filter('woocommerce_bookings_get_posted_data', [$this, 'modify_booking_data'], 10, 3);
        add_filter('woocommerce_booking_form_get_posted_data', [$this, 'modify_booking_data'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_room_availability'], 10, 5);
        add_action('woocommerce_before_booking_form', [$this, 'add_variation_field']);
        
        // Admin product management
        add_filter('product_type_options', [$this, 'add_variable_product_type_option']);
        add_filter('woocommerce_product_data_tabs', [$this, 'add_variations_tab_for_booking']);
        add_action('woocommerce_process_product_meta_booking', [$this, 'save_variable_option']);
        
        // Availability checking
        add_filter('wc_bookings_is_date_bookable', array($this, 'check_variation_availability'), 10, 4);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }
    
    public function setup() {
        $this->maybe_increase_memory_limit();
    }

    private function maybe_increase_memory_limit() {
        $current_limit = ini_get('memory_limit');
        $current_limit_int = wp_convert_hr_to_bytes($current_limit);
        
        if ($current_limit_int < 256 * MB_IN_BYTES) {
            @ini_set('memory_limit', '256M');
        }
    }

    public function enqueue_scripts() {
        if (is_product()) {
            wp_enqueue_style(
                'wc-hotel-booking-style',
                plugins_url('assets/css/hotel-booking.css', __FILE__),
                array(),
                '1.0'
            );
            
            wp_enqueue_script(
                'wc-hotel-booking',
                plugins_url('assets/js/hotel-booking.js', __FILE__),
                array('jquery'),
                '1.0',
                true
            );
        }
    }

    public function enqueue_admin_scripts() {
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'product' || $screen->id === 'edit-product')) {
            wp_enqueue_script(
                'wc-hotel-booking-admin',
                plugins_url('assets/js/hotel-booking-admin.js', __FILE__),
                array('jquery'),
                '1.0',
                true
            );
        }
    }

    public function add_variation_field() {
        echo '<input type="hidden" id="wc_hotel_selected_variation" name="wc_hotel_selected_variation" value="">';
    }

    public function modify_booking_data($posted, $product, $total_duration = 0) {
        if (isset($_POST['wc_hotel_selected_variation'])) {
            $posted['variation_id'] = (int) $_POST['wc_hotel_selected_variation'];
        }
        return $posted;
    }

    public function validate_room_availability($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
        if (!class_exists('WC_Bookings_Controller')) {
            return $passed;
        }

        $booking_product = wc_get_product($product_id);
        if (!$booking_product || !$booking_product->is_type('booking')) {
            return $passed;
        }

        // Only check availability if product has variations
        if ($booking_product->get_meta('_has_variables') !== 'yes') {
            return $passed;
        }

        // If no variation_id is provided, try to get it from POST data
        if (!$variation_id && isset($_POST['variation_id'])) {
            $variation_id = (int) $_POST['variation_id'];
        }

        // If still no variation_id, return true as we can't validate
        if (!$variation_id) {
            return $passed;
        }

        // Get total rooms requested including cart
        $total_requested = $quantity;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if ($cart_item['variation_id'] == $variation_id) {
                $total_requested += $cart_item['quantity'];
            }
        }

        // Check against variation stock
        $variation = wc_get_product($variation_id);
        $available_rooms = $variation ? $variation->get_stock_quantity() : 0;

        if ($available_rooms !== null && $total_requested > $available_rooms) {
            wc_add_notice(
                sprintf(__('Sorry, only %d room(s) available for this type.', 'wc-hotel-booking'), 
                $available_rooms),
                'error'
            );
            return false;
        }

        return $passed;
    }

    public function add_variable_product_type_option($options) {
        $options['variable_booking'] = array(
            'id'            => '_variable_booking',
            'wrapper_class' => 'show_if_booking',
            'label'         => __('Variable booking', 'woocommerce-bookings'),
            'description'   => __('Enable this to create booking product variations.', 'woocommerce-bookings'),
            'default'       => 'no'
        );
        
        return $options;
    }
    
    public function add_variations_tab_for_booking($tabs) {
        // Make sure variations tab shows for booking products with variable option enabled
        $tabs['variations']['class'][] = 'show_if_variable_booking';
        
        return $tabs;
    }

    public function add_variation_support_to_booking($support, $feature, $product) {
        if (!$product || !$product->is_type('booking')) {
            return $support;
        }

        // Only if the product is set to be variable
        if ($product->get_meta('_variable_booking') === 'yes') {
            // Enable variation support for booking products
            if (in_array($feature, array('variations', 'variable'))) {
                return true;
            }
            
            // Disable ajax_add_to_cart for variable booking products
            if ($feature === 'ajax_add_to_cart') {
                return false;
            }
        }

        return $support;
    }

    public function save_variable_option($post_id) {
        $variable_booking = isset($_POST['_variable_booking']) ? 'yes' : 'no';
        update_post_meta($post_id, '_variable_booking', $variable_booking);
        
        // Ensure backwards compatibility with the old option
        update_post_meta($post_id, '_has_variables', $variable_booking);
    }

    public function check_variation_availability($bookable, $date, $resource_id, $booking_form) {
        $variation_id = isset($_POST['variation_id']) ? (int)$_POST['variation_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        
        if (!$variation_id) {
            return $bookable;
        }
        
        // Get variation stock
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return $bookable;
        }
        
        $max_rooms = $variation->get_stock_quantity();
        if ($max_rooms === null) {
            return $bookable;
        }
        
        // Count existing bookings for this date and variation
        $existing_bookings = $this->count_bookings_for_date($date, $variation_id);
        
        // Check if requested quantity is available
        return ($existing_bookings + $quantity) <= $max_rooms;
    }

    private function count_bookings_for_date($date, $variation_id) {
        global $wpdb;
        
        $bookings_table = $wpdb->prefix . 'wc_booking';
        $date_string = $date->format('Y-m-d');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(qty) FROM {$bookings_table}
            WHERE start_date <= %s 
            AND end_date >= %s
            AND variation_id = %d
            AND status NOT IN ('cancelled', 'trash')",
            $date_string,
            $date_string,
            $variation_id
        ));
        
        return (int)$count;
    }
}

// Initialize the plugin
$wc_hotel_booking_variations = new WC_Hotel_Booking_Variations();

// Add product supports filter with high priority to override WC Bookings
add_filter('woocommerce_product_supports', array($wc_hotel_booking_variations, 'add_variation_support_to_booking'), 99, 3);
