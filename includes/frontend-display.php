<?php
if (!defined('ABSPATH')) {
    exit;
}

class HBV_Frontend_Display {
    public function __construct() {
        add_filter('mphb_room_type_variations', array($this, 'modify_variation_display'), 10, 2);
    }

    public function modify_variation_display($variations, $room_type_id) {
        // Get all variations for this room type
        $room_variations = get_post_meta($room_type_id, '_mphb_room_variations', true);
        
        if (!empty($room_variations)) {
            foreach ($room_variations as $variation) {
                // Format variation data for frontend display
                $variations[] = array(
                    'variation_id' => $variation['id'],
                    'attributes'   => $this->format_attributes($variation['attributes']),
                    'price'        => $variation['price'],
                    'description'  => $variation['description']
                );
            }
        }
        
        return $variations;
    }

    private function format_attributes($attributes) {
        $formatted = array();
        if (!empty($attributes)) {
            foreach ($attributes as $key => $value) {
                $formatted[$key] = array(
                    'label' => wc_attribute_label($key),
                    'value' => $value
                );
            }
        }
        return $formatted;
    }
}

new HBV_Frontend_Display();
