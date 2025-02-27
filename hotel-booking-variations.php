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

// Include template override class
require_once plugin_dir_path(__FILE__) . 'includes/template-hook-fixer.php';

class WC_Hotel_Booking_Variations {
    private static $processing_product_type = false;

    public function __construct() {
        // Add this at the very top of construct
        add_action('init', array($this, 'remove_default_booking_form'), 5);
        add_action('woocommerce_before_single_product', array($this, 'modify_booking_form_position'), 5);
        
        // Increase memory limit if needed
        $this->maybe_increase_memory_limit();
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('woocommerce_bookings_get_posted_data', [$this, 'modify_booking_data'], 10, 3);
        add_filter('woocommerce_booking_form_get_posted_data', [$this, 'modify_booking_data'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_room_availability'], 10, 5);
        add_action('woocommerce_before_booking_form', [$this, 'add_variation_field']);
        
        // Product type and variation support
        add_filter('woocommerce_product_supports', [$this, 'add_variable_support_to_bookings'], 20, 3);
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_variable_option']);
        add_action('woocommerce_process_product_meta_booking', [$this, 'save_variable_option']);
        add_filter('woocommerce_product_data_tabs', [$this, 'add_variations_tab'], 10, 1);
        add_filter('woocommerce_register_post_type_product_variation', [$this, 'modify_variation_post_type'], 10, 1);
        
        // Add attribute support
        add_action('woocommerce_product_write_panel_tabs', [$this, 'add_attribute_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_attribute_panel']);
        add_action('admin_footer', [$this, 'add_variation_scripts']);
        
        // Enhanced variation support
        add_filter('woocommerce_product_supports', [$this, 'enable_variations_for_booking_products'], 20, 3);
        add_action('woocommerce_product_options_attributes', [$this, 'force_show_variation_checkbox']);
        
        // Modify booking form position
        remove_action('woocommerce_before_add_to_cart_button', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_before_variations_form', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_after_variations_form', array('WC_Bookings_Cart', 'booking_form'), 10);
        
        // Add booking form after variations
        add_action('woocommerce_after_variations_table', array($this, 'render_booking_form'), 20);
        
        // Add availability check filter
        add_filter('wc_bookings_is_date_bookable', array($this, 'check_variation_availability'), 10, 4);
        
        // Load template-hook-fixer.php instead of our previous approach
        // (The class in template-hook-fixer.php will handle the booking form)
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
            
            // Add inline CSS for immediate effect
            $inline_css = "
                .wc-bookings-booking-form, 
                .wc-bookings-booking-form-wrapper,
                .booking-form-wrapper,
                .wc_bookings_field_duration,
                .wc_bookings_field_start_date {
                    display: none !important;
                    visibility: hidden !important;
                }
                
                .variations_form {
                    display: block !important;
                    visibility: visible !important;
                }
            ";
            wp_add_inline_style('wc-hotel-booking-style', $inline_css);
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

    public function add_variable_support_to_bookings($support, $feature, $product) {
        if (!$product || !$product->is_type('booking')) {
            return $support;
        }

        // Enable variation support for booking products
        if ($feature === 'ajax_add_to_cart') {
            return false;
        }

        if (in_array($feature, array('variations', 'variable'))) {
            return $product->get_meta('_has_variables') === 'yes';
        }

        return $support;
    }

    public function add_variable_option() {
        global $post;
        
        $product = wc_get_product($post->ID);
        if ($product && $product->is_type('booking')) {
            woocommerce_wp_checkbox([
                'id' => '_has_variables',
                'label' => __('Has variations', 'woocommerce'),
                'description' => __('Enable this to create different variations of this booking', 'woocommerce')
            ]);
        }
    }

    public function save_variable_option($post_id) {
        $has_variables = isset($_POST['_has_variables']) ? 'yes' : 'no';
        update_post_meta($post_id, '_has_variables', $has_variables);
    }

    public function add_variations_tab($tabs) {
        global $post;
        
        if (!$post) {
            return $tabs;
        }

        $product = wc_get_product($post->ID);
        if ($product && $product->is_type('booking') && $product->get_meta('_has_variables') === 'yes') {
            $tabs['variable_product'] = [
                'label'    => __('Variations', 'woocommerce'),
                'target'   => 'variable_product_options',
                'class'    => array('show_if_booking', 'variations_tab'),
                'priority' => 62
            ];
        }
        
        return $tabs;
    }

    // Add new method to check if product is variable booking
    private function is_variable_booking($product) {
        return $product 
            && $product->is_type('booking') 
            && $product->get_meta('_has_variables') === 'yes';
    }

    public function add_attribute_tab() {
        global $post;
        
        if (!$post || !$this->is_variable_booking(wc_get_product($post->ID))) {
            return;
        }
        ?>
        <li class="attributes_tab">
            <a href="#product_attributes"><span><?php _e('Attributes', 'woocommerce'); ?></span></a>
        </li>
        <?php
    }

    public function add_attribute_panel() {
        global $post;
        
        if (!$post || !$this->is_variable_booking(wc_get_product($post->ID))) {
            return;
        }
        ?>
        <div id="product_attributes" class="panel wc-metaboxes-wrapper hidden">
            <div class="toolbar toolbar-top">
                <span class="expand-close">
                    <a href="#" class="expand_all"><?php _e('Expand', 'woocommerce'); ?></a>
                    <a href="#" class="close_all"><?php _e('Close', 'woocommerce'); ?></a>
                </span>
                <button type="button" class="button add_attribute"><?php _e('Add', 'woocommerce'); ?></button>
            </div>
            <div class="product_attributes wc-metaboxes">
                <?php
                $attributes = get_post_meta($post->ID, '_product_attributes', true);
                // Initialize as empty array if false or not array
                $attributes = is_array($attributes) ? $attributes : array();
                
                if (!empty($attributes)) {
                    foreach ($attributes as $position => $attribute) {
                        // Ensure position is available for the attribute row
                        $attribute['position'] = $position;
                        $this->render_attribute_row($attribute);
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function add_variation_scripts() {
        global $post, $pagenow, $product_object;

        if (!$post || !in_array($pagenow, ['post.php', 'post-new.php']) || get_post_type($post->ID) !== 'product') {
            return;
        }

        $product = wc_get_product($post->ID);
        if (!$this->is_variable_booking($product)) {
            return;
        }

        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Always show variation options for booking products
                $('.enable_variation').show();
                $('.attribute_variation').show();
                
                // Enable variation handling for bookable products
                $('.product_attributes').on('change', 'input.checkbox', function() {
                    if ($(this).is(':checked')) {
                        $(this).closest('.woocommerce_attribute').find('.enable_variation').show();
                    }
                });

                // Show variation options for new attributes
                $('.product_attributes').on('click', 'button.add_attribute', function() {
                    setTimeout(function() {
                        $('.enable_variation').show();
                        $('.attribute_variation').show();
                    }, 100);
                });

                // Enable the variations panel
                $('#variable_product_options').removeClass('hide_if_booking').addClass('show_if_booking');
            });
        </script>
        <?php
    }

    private function render_attribute_row($attribute) {
        // Ensure position is set
        $position = isset($attribute['position']) ? $attribute['position'] : 0;
        
        // Ensure value is array
        $attribute['value'] = isset($attribute['value']) ? (array) $attribute['value'] : array();
        
        ?>
        <div data-taxonomy="<?php echo esc_attr($attribute['name']); ?>" class="woocommerce_attribute wc-metabox">
            <h3>
                <a href="#" class="remove_row delete"><?php _e('Remove', 'woocommerce'); ?></a>
                <div class="handlediv" title="<?php esc_attr_e('Click to toggle', 'woocommerce'); ?>"></div>
                <strong class="attribute_name"><?php echo esc_html($attribute['name']); ?></strong>
            </h3>
            <div class="woocommerce_attribute_data wc-metabox-content">
                <table>
                    <tbody>
                        <tr>
                            <td class="attribute_name">
                                <label><?php _e('Name', 'woocommerce'); ?>:</label>
                                <input type="text" class="attribute_name" name="attribute_names[]" value="<?php echo esc_attr($attribute['name']); ?>" />
                                <input type="hidden" name="attribute_position[]" class="attribute_position" value="<?php echo esc_attr($position); ?>" />
                                <input type="hidden" name="attribute_is_taxonomy[]" value="<?php echo $attribute['is_taxonomy'] ? 1 : 0; ?>" />
                            </td>
                            <td rowspan="3">
                                <label><?php _e('Value(s)', 'woocommerce'); ?>:</label>
                                <?php if ($attribute['is_taxonomy']) : ?>
                                    <?php if ('select' === $attribute['display']) : ?>
                                        <select multiple="multiple" data-placeholder="<?php esc_attr_e('Select terms', 'woocommerce'); ?>" class="multiselect attribute_values" name="attribute_values[]">
                                            <?php
                                            $args = array(
                                                'orderby'    => 'name',
                                                'hide_empty' => 0,
                                            );
                                            $terms = get_terms($attribute['name'], $args);
                                            if ($terms) {
                                                foreach ($terms as $term) {
                                                    echo '<option value="' . esc_attr($term->term_id) . '" ' . selected(in_array($term->term_id, $attribute['value']), true, false) . '>' . esc_html($term->name) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    <?php else : ?>
                                        <input type="text" name="attribute_values[]" value="<?php echo esc_attr(implode('|', $attribute['value'])); ?>" placeholder="<?php echo esc_attr__('Enter some text, or some attributes by "|" separating values.', 'woocommerce'); ?>" />
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label><input type="checkbox" class="checkbox" <?php checked($attribute['visible'], true); ?> name="attribute_visibility[]" value="1" /> <?php _e('Visible on the product page', 'woocommerce'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="enable_variation show_if_variable">
                                    <label><input type="checkbox" class="checkbox" <?php checked($attribute['variation'], true); ?> name="attribute_variation[]" value="1" /> <?php _e('Used for variations', 'woocommerce'); ?></label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function modify_variation_post_type($args) {
        if (!isset($args['supports'])) {
            $args['supports'] = array();
        }
        
        $args['supports'][] = 'booking';
        
        // Add necessary capabilities
        if (!isset($args['capabilities'])) {
            $args['capabilities'] = array();
        }
        
        $args['capabilities'] = array_merge($args['capabilities'], array(
            'publish_posts' => 'manage_woocommerce',
            'edit_posts' => 'manage_woocommerce',
            'delete_posts' => 'manage_woocommerce',
        ));
        
        return $args;
    }

    // Replace existing add_variable_support_to_bookings with this new method
    public function enable_variations_for_booking_products($supports, $feature, $product) {
        if ($product && $product->is_type('booking')) {
            if ($feature === 'ajax_add_to_cart') {
                return false;
            }
            if ($feature === 'variations' || $feature === 'variable') {
                return true;
            }
        }
        return $supports;
    }

    // Add new method to force show variation checkbox
    public function force_show_variation_checkbox() {
        global $post;
        $product = wc_get_product($post->ID);
        
        if ($product && $product->is_type('booking')) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $(".enable_variation").show();
                    $(".attribute_variation").show();
                    
                    // Also show for newly added attributes
                    $(document).on('woocommerce_attributes_added', function() {
                        $(".enable_variation").show();
                        $(".attribute_variation").show();
                    });
                });
            </script>
            <?php
        }
    }

    public function check_variation_availability($bookable, $date, $resource_id, $booking_form) {
        $variation_id = isset($_POST['variation_id']) ? (int)$_POST['variation_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
        
        if (!$variation_id) {
            return false;
        }
        
        // Get variation stock
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return false;
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

    public function remove_default_booking_form() {
        // Remove all default booking form positions
        remove_action('woocommerce_before_add_to_cart_button', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_before_add_to_cart_form', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_before_variations_form', array('WC_Bookings_Cart', 'booking_form'), 10);
        remove_action('woocommerce_after_variations_form', array('WC_Bookings_Cart', 'booking_form'), 10);
    }

    public function modify_booking_form_position() {
        add_action('woocommerce_after_variations_form', array($this, 'render_booking_form'), 20);
    }

    public function render_booking_form() {
        global $product;
        
        if (!$product || !$product->is_type('booking')) {
            return;
        }

        echo '<div class="booking-form-wrapper" style="display:none;">';
        echo '<div class="booking-form-inner">';
        do_action('woocommerce_before_booking_form');
        
        // Only show if variations exist
        if ($product->get_meta('_has_variables') === 'yes') {
            echo '<div class="variation-selection-notice">';
            echo esc_html__('Please select a room type above to see availability.', 'wc-hotel-booking');
            echo '</div>';
        }
        
        // Add the actual booking form
        if (class_exists('WC_Bookings_Cart')) {
            WC_Bookings_Cart::booking_form();
        }
        
        do_action('woocommerce_after_booking_form');
        echo '</div>';
        echo '</div>';
    }
}

new WC_Hotel_Booking_Variations();
