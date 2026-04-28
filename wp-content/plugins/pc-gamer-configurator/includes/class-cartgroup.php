<?php
namespace PCGamer;

class CartGroup {

    public function __construct() {
        add_filter('woocommerce_cart_item_remove_link', [ $this, 'hide_remove_link_for_extras' ], 10, 2);
        add_action('woocommerce_cart_item_removed', [ $this, 'remove_child_items' ], 10, 2);
        
        // Add filter to disable quantity input for extras
        add_filter('woocommerce_cart_item_quantity', [ $this, 'disable_quantity_input_for_extras' ], 10, 3);
        
        // Also prevent quantity updates through AJAX/POST
        add_filter('woocommerce_before_cart_item_quantity_zero', [ $this, 'prevent_quantity_changes' ], 10, 2);
        add_filter('woocommerce_stock_amount_cart_item', [ $this, 'prevent_quantity_update' ], 10, 2);
        
        // Ensure the main product quantity changes affect the extras
        add_action('woocommerce_after_cart_item_quantity_update', [ $this, 'sync_extras_quantity' ], 10, 4);
    }

    public function hide_remove_link_for_extras($link, $cart_item_key) {
        $cart = WC()->cart->get_cart();

        if (!empty($cart[$cart_item_key]['is_extra'])) {
            $parent_key = $cart[$cart_item_key]['parent_cart_item_key'] ?? '';
            $parent = $cart[$parent_key] ?? null;

            if ($parent && !empty($parent['product_id'])) {
                $enabled = get_post_meta($parent['product_id'], '_pcgamer_enabled', true);
                if ($enabled === 'yes') {
                    return ''; // Oculta el botón de eliminar si el configurador está activo
                }
            }
        }

        return $link;
    }

    public function remove_child_items($removed_key, $cart) {
        $removed_item = WC()->cart->removed_cart_contents[$removed_key] ?? null;

        if (!$removed_item || empty($removed_item['product_id'])) return;

        $enabled = get_post_meta($removed_item['product_id'], '_pcgamer_enabled', true);
        if ($enabled !== 'yes') return;

        foreach ($cart->get_cart() as $key => $item) {
            if (isset($item['parent_cart_item_key']) && $item['parent_cart_item_key'] === $removed_key) {
                $cart->remove_cart_item($key);
            }
        }
    }
    
    /**
     * Disable quantity input for extras in cart
     */
    public function disable_quantity_input_for_extras($quantity_html, $cart_item_key, $cart_item) {
        // Check if this is an extra item from the configurator
        if (isset($cart_item['is_extra']) && $cart_item['is_extra'] === true) {
            // Replace the quantity input with plain text showing fixed quantity
            return sprintf('<span class="quantity">%s</span>', $cart_item['quantity']);
        }
        
        return $quantity_html;
    }
    
    /**
     * Prevent quantity changes via AJAX/direct request
     */
    public function prevent_quantity_changes($cart_item_key, $cart) {
        $cart_item = $cart->get_cart_item($cart_item_key);
        
        if (isset($cart_item['is_extra']) && $cart_item['is_extra'] === true) {
            wc_add_notice('No es posible modificar la cantidad de componentes configurados.', 'error');
            return false;
        }
        
        return $cart_item_key;
    }
    
    /**
     * Filter the quantity update for cart items
     */
    public function prevent_quantity_update($quantity, $cart_item_key) {
        $cart = WC()->cart;
        $cart_item = $cart->get_cart_item($cart_item_key);
        
        if (isset($cart_item['is_extra']) && $cart_item['is_extra'] === true) {
            // Return original quantity instead of updated quantity
            return $cart_item['quantity'];
        }
        
        return $quantity;
    }
    
    /**
     * When parent product quantity is updated, sync to child products
     */
    public function sync_extras_quantity($cart_item_key, $quantity, $old_quantity, $cart) {
        // Only process for PCGamer main products
        $cart_item = $cart->get_cart_item($cart_item_key);
        if (!$cart_item) return;
        
        $product_id = $cart_item['product_id'];
        $enabled = get_post_meta($product_id, '_pcgamer_enabled', true);
        
        if ($enabled === 'yes') {
            // Update quantity of all child items
            foreach ($cart->get_cart() as $key => $item) {
                if (isset($item['parent_cart_item_key']) && $item['parent_cart_item_key'] === $cart_item_key) {
                    $cart->set_quantity($key, $quantity, false);
                }
            }
        }
    }
}

