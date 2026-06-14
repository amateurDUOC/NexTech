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

        // Clases CSS para agrupar visualmente el PC y sus componentes
        add_filter('woocommerce_cart_item_class', [ $this, 'add_cart_item_classes' ], 10, 3);

        // Restaurar componentes hijos cuando el usuario deshace la eliminación
        add_action('woocommerce_cart_item_restored', [ $this, 'restore_child_items' ], 10, 2);

        // Assets del carrito
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_cart_assets' ]);
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

    /**
     * Cuando el usuario deshace la eliminación de una PC principal,
     * restaura también los componentes hijos que fueron eliminados automáticamente.
     */
    public function restore_child_items($restored_key, $cart) {
        $restored_item = $cart->get_cart_item($restored_key);
        if (!$restored_item) return;

        $enabled = get_post_meta($restored_item['product_id'], '_pcgamer_enabled', true);
        if ($enabled !== 'yes') return;

        $restored_any = false;
        foreach ($cart->removed_cart_contents as $key => $item) {
            if (isset($item['parent_cart_item_key']) && $item['parent_cart_item_key'] === $restored_key) {
                $cart->cart_contents[$key] = $item;
                unset($cart->removed_cart_contents[$key]);
                $restored_any = true;
            }
        }

        if ($restored_any) {
            $cart->calculate_totals();
        }
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
     * Agrega clases CSS a las filas del carrito para agrupar el PC y sus componentes.
     * - PC principal → pcgamer-main-item pcgamer-key-{hash}
     * - Componente   → pcgamer-extra-item pcgamer-parent-{hash}
     */
    public function add_cart_item_classes( $class, $cart_item, $cart_item_key ) {
        if ( ! empty( $cart_item['is_extra'] ) ) {
            $parent_key = sanitize_html_class( $cart_item['parent_cart_item_key'] ?? '' );
            $class .= ' pcgamer-extra-item pcgamer-parent-' . $parent_key;
        } else {
            $enabled = get_post_meta( $cart_item['product_id'] ?? 0, '_pcgamer_enabled', true );
            if ( $enabled === 'yes' ) {
                $class .= ' pcgamer-main-item pcgamer-key-' . sanitize_html_class( $cart_item_key );
            }
        }
        return $class;
    }

    /**
     * Encola CSS y JS de mejoras visuales del carrito.
     */
    public function enqueue_cart_assets() {
        if ( ! is_cart() ) return;
        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
        wp_enqueue_style(
            'pcgamer-cart',
            $plugin_url . 'assets/cart.css',
            [],
            '1.0.0'
        );
        wp_enqueue_script(
            'pcgamer-cart',
            $plugin_url . 'assets/cart.js',
            [],
            '1.0.0',
            true
        );
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

