jQuery(document).ready(function($) {
    // Function to toggle variation options visibility
    function toggleVariationOptions() {
        var isBooking = $('#product-type').val() === 'booking';
        var hasVariations = $('#_variable_booking').is(':checked');
        
        // Show variations tab if booking with variations
        if (isBooking && hasVariations) {
            $('.show_if_variable.variations_tab').show();
            $('.variations_options').show();
        } else {
            $('.show_if_variable.variations_tab').hide();
            $('.variations_options').hide();
        }
        
        // Update product attributes section
        if (isBooking && hasVariations) {
            $('.product_attributes .checkbox_options .show_if_variable').show();
        } else {
            $('.product_attributes .checkbox_options .show_if_variable').hide();
        }
    }
    
    // Run on page load
    toggleVariationOptions();
    
    // Run when product type changes
    $('#product-type').on('change', toggleVariationOptions);
    
    // Run when variable booking option changes
    $('body').on('change', '#_variable_booking', toggleVariationOptions);
    
    // Run after attribute is added
    $('body').on('woocommerce_added_attribute', function() {
        setTimeout(toggleVariationOptions, 100);
    });
});
