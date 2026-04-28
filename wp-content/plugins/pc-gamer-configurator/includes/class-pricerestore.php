<?php
namespace PCGamer;

/**
 * This class ensures product prices are restored to their original values
 * when viewed outside of the configurator context
 */
class PriceRestore {
    private $modified_products = [];
    
    public function __construct() {
        // Remove cart item key filter to avoid duplicate extras
        // add_filter('woocommerce_cart_item_key', [$this, 'ensure_unique_cart_keys'], 10, 2);
        
        // Add filter to prevent cart item merging
        add_filter('woocommerce_add_to_cart_sold_individually_found_in_cart', [$this, 'prevent_merging_configurator_items'], 10, 5);
    }
    
    /**
     * Prevent WooCommerce from merging identical products if one is from configurator
     */
    public function prevent_merging_configurator_items($found_item, $product_id, $variation_id, $cart_item_data, $cart_id) {
        // If this is a configurator product, it should never merge with existing items
        if (isset($cart_item_data['is_extra']) && $cart_item_data['is_extra'] === true) {
            return false;
        }
        
        // For regular products being added, check if found item is a configurator product
        if ($found_item) {
            $cart = WC()->cart;
            if ($cart && isset($cart->cart_contents[$found_item])) {
                $found_cart_item = $cart->cart_contents[$found_item];
                
                // If the found item is a configurator product, don't consider it a match
                if (isset($found_cart_item['is_extra']) && $found_cart_item['is_extra'] === true) {
                    return false;
                }
            }
        }
        
        return $found_item;
    }
}
