<?php
/**
 * Handles attribute variation support for booking products
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Hotel_Booking_Variation_Support {
    public function __construct() {
        // Enable "Used for variations" checkbox for attributes
        add_filter('woocommerce_attribute_show_in_variation_options', array($this, 'show_in_variation_options'), 10, 2);
        
        // Ensure variations panel shows for booking products
        add_filter('woocommerce_product_data_tabs', array($this, 'modify_product_data_tabs'), 20);
        add_action('admin_footer', array($this, 'product_type_selector_script'));
        
        // Support saving variation data for booking products
        add_filter('woocommerce_product_after_variable_attributes', array($this, 'variation_options_booking'), 10, 3);
        add_filter('woocommerce_available_variation', array($this, 'available_variation_booking'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_booking'), 10, 2);
    }
    
    /**
     * Show "Used for variations" checkbox for booking products
     */
    public function show_in_variation_options($show_option, $product_type) {
        if ($product_type === 'booking') {
            // Check if product has the variable booking option enabled
            global $post;
            if ($post && get_post_meta($post->ID, '_variable_booking', true) === 'yes') {
                return true;
            }
        }
        return $show_option;
    }
    
    /**
     * Ensure variations tab shows for booking products when needed
     */
    public function modify_product_data_tabs($tabs) {
        // Add a custom class to show variations tab for booking products
        if (isset($tabs['variations'])) {
            // Add our custom class that will be used by our JavaScript
            $tabs['variations']['class'][] = 'show_if_booking_has_variations';
        }
        return $tabs;
    }
    
    /**
     * Add JavaScript to dynamically show/hide variation tab based on product settings
     */
    public function product_type_selector_script() {
        if (!function_exists('get_current_screen')) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || ($screen->id !== 'product' && $screen->id !== 'edit-product')) {
            return;
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Function to update variations visibility
                function updateVariationVisibility() {
                    var is_booking = $('#product-type').val() === 'booking';
                    var has_variations = $('#_variable_booking').is(':checked');
                    
                    // Show/hide variations tab
                    if (is_booking && has_variations) {
                        $('.show_if_booking_has_variations').show();
                        $('.variations_options').show();
                    } else if (is_booking) {
                        $('.show_if_booking_has_variations').hide();
                        $('.variations_options').hide();
                    }
                    
                    // Show/hide "Used for variations" checkbox in attributes
                    $('.attribute_options .show_if_variable').toggleClass('show_if_booking', is_booking && has_variations);
                }
                
                // Run when document loads
                updateVariationVisibility();
                
                // Run when product type changes
                $('#product-type').on('change', updateVariationVisibility);
                
                // Run when variable booking checkbox changes
                $('#_variable_booking').on('change', updateVariationVisibility);
                
                // After attribute is added, update the checkboxes visibility
                $('body').on('woocommerce_added_attribute', updateVariationVisibility);
            });
        </script>
        <?php
    }
    
    /**
     * Add booking-specific options to variations
     */
    public function variation_options_booking($loop, $variation_data, $variation) {
        // You can add booking-specific variation options here if needed
    }
    
    /**
     * Modify variation data for booking products
     */
    public function available_variation_booking($variation_data, $product, $variation) {
        return $variation_data;
    }
    
    /**
     * Save booking-specific variation data
     */
    public function save_variation_booking($variation_id, $i) {
        // Save any booking-specific variation data here
    }
}

// Initialize the variation support
new WC_Hotel_Booking_Variation_Support();
