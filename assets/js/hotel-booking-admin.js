/**
 * Hotel Booking Variations admin JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Toggle variation fields when product type changes
        $('select#product-type').on('change', function() {
            toggleVariableBookingFields();
        });
        
        // Initialize on page load
        toggleVariableBookingFields();
        
        function toggleVariableBookingFields() {
            const productType = $('select#product-type').val();
            
            if (productType === 'booking') {
                // Show variable booking option
                $('#_variable_booking').closest('label').show();
                
                // Show or hide variations tab based on variable booking setting
                if ($('#_variable_booking').is(':checked')) {
                    $('#inventory_product_data, #shipping_product_data').hide();
                    $('.show_if_variable').show();
                    $('ul.product_data_tabs li.general_options').addClass('show_if_variable_booking');
                    $('ul.product_data_tabs li.variations_options').addClass('show_if_variable_booking');
                    
                    // Hide person types if they exist
                    $('.woocommerce_bookings_persons').hide();
                }
            } else {
                $('#_variable_booking').closest('label').hide();
                $('ul.product_data_tabs li.general_options').removeClass('show_if_variable_booking');
                $('ul.product_data_tabs li.variations_options').removeClass('show_if_variable_booking');
            }
        }
        
        // Toggle variation booking settings when variable booking checkbox is toggled
        $('#_variable_booking').on('change', function() {
            if ($(this).is(':checked')) {
                $('.show_if_variable').show();
                $('ul.product_data_tabs li.variations_options').show();
                $('#inventory_product_data, #shipping_product_data').hide();
            } else {
                $('.show_if_variable:not(.show_if_booking)').hide();
                $('ul.product_data_tabs li.variations_options').hide();
            }
        });
    });
    
})(jQuery);
