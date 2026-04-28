<?php
namespace PCGamer;

class CustomPrice {
    public function __construct() {
        // These filters adjust the displayed price in cart, checkout, and order pages
        add_filter('woocommerce_product_get_price', [$this, 'apply_custom_price'], 99, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'apply_custom_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_price', [$this, 'apply_custom_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_regular_price', [$this, 'apply_custom_price'], 99, 2);
        
        // Critical filter to modify the actual price in the cart - Apply with higher priority (10 instead of 99)
        add_filter('woocommerce_before_calculate_totals', [$this, 'adjust_cart_prices'], 10, 1);
        
        // Add filter to ensure prices are set correctly after adding products to cart
        add_action('woocommerce_add_to_cart', [$this, 'ensure_correct_prices_after_add'], 10, 6);
        add_filter('woocommerce_get_cart_contents', [$this, 'ensure_correct_prices_in_cart'], 10, 1);
        
        // Add filter to modify the cart item price directly
        add_filter('woocommerce_cart_item_price', [$this, 'filter_cart_item_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'filter_cart_item_subtotal'], 10, 3);
        
        // Remove "Sale!" badge for items with custom prices
        add_filter('woocommerce_sale_flash', [$this, 'remove_sale_flash'], 10, 3);
        
        // Override price html display to remove strikethrough prices
        add_filter('woocommerce_get_price_html', [$this, 'override_price_html'], 99, 2);
        
        // Add filter to ensure we use updated WooCommerce prices for synced categories
        add_filter('pcgamer_get_component_price', [$this, 'maybe_use_woo_price'], 10, 2);
    }
    
    /**
     * Filter to get the correct price for a component based on sync settings
     */
    public function maybe_use_woo_price($price, $product_id) {
        if (empty($product_id)) return $price;
        
        // Get the product
        $product = wc_get_product($product_id);
        if (!$product) return $price;
        
        // Check if this product belongs to a synced category
        $synced_categories = get_option('pcgamer_synced_categories', []);
        if (empty($synced_categories)) return $price;
        
        // Get product categories
        $categories = get_the_terms($product_id, 'product_cat');
        if (!$categories || is_wp_error($categories)) return $price;
        
        foreach ($categories as $category) {
            if (isset($synced_categories[$category->slug]) && $synced_categories[$category->slug]) {
                // This product is in a synced category, use WooCommerce price
                $woo_price = $product->get_sale_price();
                if (!$woo_price || empty($woo_price)) {
                    $woo_price = $product->get_regular_price();
                }
                
                if (!empty($woo_price)) {
                    return (float) $woo_price;
                }
            }
        }
        
        return $price;
    }
    
    /**
     * Apply custom price to cart items if they are extras
     */
    public function apply_custom_price($price, $product) {
        // Only modify price in cart/checkout context
        if (!is_cart() && !is_checkout()) {
            return $price;
        }
        
        $cart = WC()->cart;
        if (empty($cart)) {
            return $price;
        }
        
        // Get cart item based on product ID and item key
        // Important: We need to find the exact cart item this product belongs to
        $product_id = $product->get_id();
        $found_item = null;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Check if this is the exact cart item we need to modify
            if ($cart_item['product_id'] == $product_id && 
                isset($cart_item['is_extra']) && 
                $cart_item['is_extra'] === true &&
                isset($cart_item['pcgamer_custom_price'])) {
                
                // Check if this product object belongs to this cart item
                if ($cart_item['data'] === $product) {
                    return $cart_item['pcgamer_custom_price'];
                }
                
                // Store for fallback check
                $found_item = $cart_item;
            }
        }
        
        // If we found a matching item but couldn't determine object identity
        // This is a fallback for older WooCommerce versions
        if ($found_item && isset($found_item['pcgamer_custom_price'])) {
            return $found_item['pcgamer_custom_price'];
        }
        
        return $price;
    }
    
    /**
     * Ensure correct prices are applied immediately after adding to cart
     */
    public function ensure_correct_prices_after_add($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        if (isset($cart_item_data['is_extra']) && $cart_item_data['is_extra'] === true) {
            $cart = WC()->cart->get_cart();
            if (isset($cart[$cart_item_key]) && isset($cart_item_data['pcgamer_custom_price'])) {
                $product = $cart[$cart_item_key]['data'];
                if ($product) {
                    $product->set_price($cart_item_data['pcgamer_custom_price']);
                    $product->set_regular_price($cart_item_data['pcgamer_custom_price']);
                    if (method_exists($product, 'set_sale_price')) {
                        $product->set_sale_price('');
                    }
                }
            }
        }
    }
    
    /**
     * Ensure correct prices are set when retrieving cart contents
     */
    public function ensure_correct_prices_in_cart($cart_contents) {
        foreach ($cart_contents as $cart_item_key => $cart_item) {
            if (isset($cart_item['is_extra']) && $cart_item['is_extra'] === true && isset($cart_item['pcgamer_custom_price'])) {
                $product = $cart_item['data'];
                if ($product) {
                    $product->set_price($cart_item['pcgamer_custom_price']);
                    $product->set_regular_price($cart_item['pcgamer_custom_price']);
                    if (method_exists($product, 'set_sale_price')) {
                        $product->set_sale_price('');
                    }
                }
            }
        }
        return $cart_contents;
    }
    
    /**
     * Adjust prices in the cart for configurator items only
     */
    public function adjust_cart_prices($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Check if this is an extra with a custom price
            if (isset($cart_item['is_extra']) && 
                $cart_item['is_extra'] === true && 
                isset($cart_item['pcgamer_custom_price'])) {
                
                // Get the product object from the cart item
                $product = $cart_item['data'];
                
                // Store original price if not already stored
                if (!isset($cart_item['original_price'])) {
                    $cart->cart_contents[$cart_item_key]['original_price'] = $product->get_price();
                }
                
                // Set the custom price for this specific cart item only
                $price = $cart_item['pcgamer_custom_price'];
                $product->set_price($price);
                
                // Also set regular price to avoid showing as on sale
                if (method_exists($product, 'set_regular_price')) {
                    $product->set_regular_price($price);
                }
                
                // Ensure it's not shown as a sale product
                if (method_exists($product, 'set_sale_price')) {
                    $product->set_sale_price('');
                }
            }
        }
    }
    
    /**
     * Filter to modify the displayed price in cart
     */
    public function filter_cart_item_price($price, $cart_item, $cart_item_key) {
        if (isset($cart_item['is_extra']) && $cart_item['is_extra'] === true && isset($cart_item['pcgamer_custom_price'])) {
            return wc_price($cart_item['pcgamer_custom_price']);
        }
        return $price;
    }
    
    /**
     * Filter to modify the displayed subtotal in cart
     */
    public function filter_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        if (isset($cart_item['is_extra']) && $cart_item['is_extra'] === true && isset($cart_item['pcgamer_custom_price'])) {
            $quantity = $cart_item['quantity'];
            return wc_price($cart_item['pcgamer_custom_price'] * $quantity);
        }
        return $subtotal;
    }
    
    /**
     * Remove sale flash for items with custom prices
     */
    public function remove_sale_flash($html, $post, $product) {
        $cart = WC()->cart;
        if (!empty($cart)) {
            foreach ($cart->get_cart() as $cart_item) {
                if ($cart_item['product_id'] == $product->get_id()) {
                    if (isset($cart_item['is_extra']) && isset($cart_item['pcgamer_custom_price'])) {
                        return ''; // No mostrar etiqueta de oferta
                    }
                }
            }
        }
        return $html;
    }
    
    /**
     * Override price HTML to remove strikethrough prices
     */
    public function override_price_html($price_html, $product) {
        $cart = WC()->cart;
        if (!empty($cart)) {
            foreach ($cart->get_cart() as $cart_item) {
                if ($cart_item['product_id'] == $product->get_id()) {
                    if (isset($cart_item['is_extra']) && isset($cart_item['pcgamer_custom_price'])) {
                        return wc_price($cart_item['pcgamer_custom_price']);
                    }
                }
            }
        }
        return $price_html;
    }
}
