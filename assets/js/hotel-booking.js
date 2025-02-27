(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Force hide booking form on page load
        $('.wc-bookings-booking-form, .booking-form-wrapper').hide().removeClass('show');
        
        // Helper function to update booking form
        function updateBookingForm() {
            if (typeof wc_bookings_booking_form !== 'undefined') {
                wc_bookings_booking_form.update();
            }
        }
        
        // Handle variation selection
        $('form.variations_form')
            .on('show_variation', function(event, variation) {
                $('.booking-form-wrapper, .wc-bookings-booking-form')
                    .addClass('show')
                    .slideDown();
                $('#wc_hotel_selected_variation').val(variation.variation_id);
                
                updateBookingForm();
            })
            .on('hide_variation reset_data', function() {
                $('.booking-form-wrapper, .wc-bookings-booking-form')
                    .removeClass('show')
                    .slideUp();
                $('#wc_hotel_selected_variation').val('');
            });
        
        // Prevent default WooCommerce Bookings behavior
        $(document).on('wc-booking-form-initialized', function() {
            if (!$('#wc_hotel_selected_variation').val()) {
                $('.wc-bookings-booking-form, .booking-form-wrapper').hide().removeClass('show');
            }
        });
        
        // Listen for variation changes
        $(document).on('found_variation', function(event, variation) {
            // Update hidden field with variation ID
            $('#wc_hotel_selected_variation').val(variation.variation_id);
            
            // Update booking form
            updateBookingForm();
        });
        
        // Clear variation selection when reset
        $(document).on('reset_data', function() {
            $('#wc_hotel_selected_variation').val('');
        });
    });
    
})(jQuery);
