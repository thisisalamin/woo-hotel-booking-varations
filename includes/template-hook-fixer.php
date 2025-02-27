<?php
if (!defined('ABSPATH')) {
    exit;
}

class HBV_Template_Hook_Fixer {
    public function __construct() {
        // Remove default booking form hooks
        add_action('init', array($this, 'remove_all_booking_form_hooks'), 1);
        add_action('template_redirect', array($this, 'remove_late_hooks'), 1);
        
        // Add our custom placement
        add_action('woocommerce_after_single_product', array($this, 'add_booking_form_placeholder'), 99);
        add_filter('wc_get_template', array($this, 'override_booking_templates'), 10, 5);
        
        // Add front-end DOM manipulation
        add_action('wp_footer', array($this, 'add_dom_manipulation_script'), 99);
    }
    
    public function remove_all_booking_form_hooks() {
        // Remove standard booking form placements
        remove_action('woocommerce_before_add_to_cart_button', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_before_add_to_cart_form', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_before_variations_form', array('WC_Bookings_Cart', 'booking_form'), 10); 
        remove_action('woocommerce_after_variations_form', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_before_single_product', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_after_single_product', array('WC_Bookings_Cart', 'booking_form'), 10);
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
            // Hide booking form elements initially
            $('.wc-bookings-booking-form-wrapper, .wc-bookings-booking-form, form.cart .wc-bookings-booking-form').css({
                'display': 'none',
                'visibility': 'hidden',
                'opacity': '0'
            });
            
            // Show variations
            $('.variations_form, #product-variations').css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
            });
            
            // Create booking form container if it doesn't exist
            if ($('#booking-form-container').length === 0) {
                $('.variations_form').after('<div id="booking-form-container" style="display:none; margin-top:30px;"></div>');
            }
            
            // Show booking form when variation is selected
            $(document).on('show_variation', function(event, variation) {
                $('.wc-bookings-booking-form').appendTo('#booking-form-container');
                $('#booking-form-container').slideDown();
                $('.wc-bookings-booking-form').css({
                    'display': 'block',
                    'visibility': 'visible',
                    'opacity': '1'
                });
                
                $('#wc_hotel_selected_variation').val(variation.variation_id);
                
                // Update booking form
                if (typeof wc_bookings_booking_form !== 'undefined') {
                    wc_bookings_booking_form.update();
                }
            });
            
            // Hide booking form when variation is hidden
            $(document).on('hide_variation', function() {
                $('#booking-form-container').slideUp();
                $('#wc_hotel_selected_variation').val('');
            });
        });
        </script>
        <?php
    }
}

new HBV_Template_Hook_Fixer();
