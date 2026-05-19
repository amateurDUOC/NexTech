<?php
/* ── Nextech — Customizaciones WooCommerce (carrito y widgets) ──────────────
   ─────────────────────────────────────────────────────────────────────────── */

/* Quitar botones duplicados del hook woocommerce_cart_actions.
   Flatsome inyecta "Continue shopping" (p.10) y el plugin de cotización
   su botón (p.20). Ambos se renderizan en cart.php directamente. */
add_action( 'wp', function () {
    remove_action( 'woocommerce_cart_actions', 'flatsome_continue_shopping', 10 );
    remove_action( 'woocommerce_cart_actions', 'ncc_boton_cotizacion', 20 );
} );

/* Suprimir widgets nativos de WooCommerce en tienda/categorías.
   El nextech-product-filter ya cubre ese rol. */
add_filter( 'widget_display_callback', 'nextech_suppress_wc_filter_widgets', 10, 3 );
function nextech_suppress_wc_filter_widgets( $instance, $widget, $args ) {
    if ( ! is_shop() && ! is_product_category() ) {
        return $instance;
    }
    $blocked = [
        'WC_Widget_Price_Filter',
        'WC_Widget_Product_Categories',
        'WC_Widget_Layered_Nav',
        'WC_Widget_Layered_Nav_Filters',
        'WC_Widget_Product_Tag_Cloud',
    ];
    if ( in_array( get_class( $widget ), $blocked, true ) ) {
        return false;
    }
    return $instance;
}

/* Mostrar acceso rápido a /tienda en páginas de categoría de producto.
   Aparece antes del grid, como breadcrumb visual, permitiendo que el usuario
   navegue al catálogo completo sin depender del menú principal. */
add_action( 'woocommerce_before_main_content', function () {
    if ( ! is_product_category() ) return;
    $shop_url = get_permalink( wc_get_page_id( 'shop' ) );
    ?>
    <a href="<?php echo esc_url( $shop_url ); ?>" class="nxt-shop-back-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        Ver catálogo completo
    </a>
    <?php
}, 5 );

/* Vaciar carrito vía parámetro URL (solo usuarios logueados) */
function vaciar_carrito_woocommerce() {
    if ( WC()->cart ) {
        WC()->cart->empty_cart();
    }
}
add_action( 'init', function () {
    if ( isset( $_GET['vaciar_carrito'] ) && is_user_logged_in() ) {
        vaciar_carrito_woocommerce();
    }
} );
