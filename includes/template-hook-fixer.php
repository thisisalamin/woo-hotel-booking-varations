<?php
if (!defined('ABSPATH')) {
    exit;
}

class HBV_Template_Hook_Fixer {
    public function __construct() {
        // Run at priority 1 to be as early as possible
        add_action('init', array($this, 'remove_all_booking_form_hooks'), 1);
        add_action('wp_loaded', array($this, 'register_template_overrides'), 1);
        add_action('template_redirect', array($this, 'remove_late_hooks'), 1);
        
        // Add our hooks
        add_action('woocommerce_after_single_product', array($this, 'add_booking_form_placeholder'), 99);
        add_filter('wc_get_template', array($this, 'override_booking_templates'), 10, 5);
        
        // Add front-end manipulation
        add_action('wp_footer', array($this, 'add_dom_manipulation_script'), 99);
    }
    
    public function remove_all_booking_form_hooks() {
        global $wp_filter;
        
        // Try to remove ALL variations of booking form hooks
        foreach ($wp_filter as $tag => $hook) {
            if (strpos($tag, 'booking_form') !== false || strpos($tag, 'bookings') !== false) {
                if (isset($wp_filter[$tag])) {
                    foreach ($wp_filter[$tag] as $priority => $callbacks) {
                        foreach ($callbacks as $callback_key => $callback_data) {
                            if (is_array($callback_data['function'])) {
                                $class = $callback_data['function'][0];
                                if (is_object($class) && get_class($class) === 'WC_Bookings_Cart') {
                                    remove_action($tag, $callback_data['function'], $priority);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Specific removals
        remove_action('woocommerce_before_add_to_cart_button', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_before_add_to_cart_form', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_before_variations_form', array('WC_Bookings_Cart', 'booking_form'), 10); 
        remove_action('woocommerce_after_variations_form', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_before_single_product', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_after_single_product', array('WC_Bookings_Cart', 'booking_form'), 10);
    }
    
    public function register_template_overrides() {
        if (class_exists('WC_Bookings')) {
            add_filter('woocommerce_locate_template', array($this, 'override_booking_template_path'), 10, 3);
        }
    }
    
    public function remove_late_hooks() {
        // Run late removals again to catch anything added after init
        $this->remove_all_booking_form_hooks();
    }
    
    public function add_booking_form_placeholder() {
        echo '<div id="booking-form-destination" style="display:none;"></div>';
    }
    
    public function override_booking_templates($template, $template_name, $args, $template_path, $default_path) {
        // Intercept booking form templates
        if (strpos($template_name, 'booking-form') !== false) {
            // Return our placeholder template
            $custom_template = plugin_dir_path(dirname(__FILE__)) . 'templates/booking-form-placeholder.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
    
    public function override_booking_template_path($template, $template_name, $template_path) {
        if (strpos($template_name, 'booking-form') !== false || strpos($template_name, 'bookings') !== false) {
            $plugin_path = plugin_dir_path(dirname(__FILE__)) . 'templates/';
            $template_file = $plugin_path . $template_name;
            
            if (file_exists($template_file)) {
                return $template_file;
            }
        }
        return $template;
    }
    
    public function add_dom_manipulation_script() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        if (!$product || !$product->is_type('booking')) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Completely hide ALL booking form elements initially
            $('.wc-bookings-booking-form-wrapper, .wc-bookings-booking-form, form.cart .wc-bookings-booking-form').css({
                'display': 'none',
                'visibility': 'hidden',
                'opacity': '0',
                'height': '0',
                'overflow': 'hidden'
            });
            
            // Force variations to appear
            $('.variations_form, #product-variations').css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
            });
            
            // Create a placeholder for the booking form
            if ($('#booking-form-container').length === 0) {
                $('.variations_form').after('<div id="booking-form-container" style="display:none; margin-top:30px;"></div>');
            }
            
            // When variation is selected
            $(document).on('show_variation', function(event, variation) {
                // Move the booking form to our container and show it
                $('.wc-bookings-booking-form').appendTo('#booking-form-container');
                $('#booking-form-container').slideDown();
                $('.wc-bookings-booking-form').css({
                    'display': 'block',
                    'visibility': 'visible',
                    'opacity': '1',
                    'height': 'auto',
                    'overflow': 'visible'
                });
                
                $('#wc_hotel_selected_variation').val(variation.variation_id);
                
                // Update booking form data
                if (typeof wc_bookings_booking_form !== 'undefined') {
                    wc_bookings_booking_form.update();
                }
            });
            
            // When variation is hidden
            $(document).on('hide_variation', function() {
                $('#booking-form-container').slideUp();
                $('#wc_hotel_selected_variation').val('');
            });
            
            // Wait for late-loading elements
            setTimeout(function() {
                // Hide booking form one more time, for elements loaded after page init
                $('.wc-bookings-booking-form').not('#booking-form-container .wc-bookings-booking-form').css({
                    'display': 'none',
                    'visibility': 'hidden'
                });
            }, 500);
        });
        </script>
        <?php
    }
}

new HBV_Template_Hook_Fixer();
