<?php
/* ── Nextech — Carga de assets (CSS y JS) ───────────────────────────────────
   Centraliza todos los wp_enqueue_style / wp_enqueue_script del tema hijo.
   ─────────────────────────────────────────────────────────────────────────── */

/* jQuery — requerido por WooCommerce y scripts propios */
function load_jquery_in_wordpress() {
    if ( ! is_admin() ) {
        wp_enqueue_script( 'jquery' );
    }
}
add_action( 'wp_enqueue_scripts', 'load_jquery_in_wordpress' );

/* Select2 — solo en checkout (factura empresa) */
function nextech_enqueue_select2() {
    if ( ! is_checkout() ) return;
    wp_enqueue_style(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css',
        [],
        '4.0.13'
    );
    wp_enqueue_script(
        'select2',
        'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js',
        [ 'jquery' ],
        '4.0.13',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'nextech_enqueue_select2' );

/* CSS del tema hijo con cache-busting */
function forzar_carga_css_tema_hijo() {
    wp_dequeue_style( 'parent-style' );
    wp_deregister_style( 'parent-style' );
    wp_enqueue_style(
        'wordpressstester-style',
        get_stylesheet_directory_uri() . '/style.css',
        [],
        filemtime( get_stylesheet_directory() . '/style.css' )
    );
}
add_action( 'wp_enqueue_scripts', 'forzar_carga_css_tema_hijo', 100 );

/* Assets propios del tema hijo — condicionales por página */
function nextech_enqueue_frontend_assets() {
    $uri = get_stylesheet_directory_uri() . '/assets';
    $dir = get_stylesheet_directory()     . '/assets';

    $v = function ( $rel ) use ( $dir ) {
        $path = $dir . '/' . $rel;
        return file_exists( $path ) ? filemtime( $path ) : '1.0.0';
    };

    // Todas las páginas
    wp_enqueue_style(
        'nextech-footer',
        $uri . '/css/nextech-footer.css',
        [],
        $v( 'css/nextech-footer.css' )
    );
    wp_enqueue_script(
        'nextech-links',
        $uri . '/js/nextech-links.js',
        [],
        $v( 'js/nextech-links.js' ),
        true
    );
    wp_enqueue_script(
        'nextech-footer',
        $uri . '/js/nextech-footer.js',
        [],
        $v( 'js/nextech-footer.js' ),
        true
    );

    // Solo carrito
    if ( is_cart() ) {
        wp_enqueue_script(
            'nextech-cart',
            $uri . '/js/nextech-cart.js',
            [ 'jquery' ],
            $v( 'js/nextech-cart.js' ),
            true
        );
    }

    // Solo checkout
    if ( is_checkout() ) {
        wp_enqueue_style(
            'nextech-checkout',
            $uri . '/css/nextech-checkout.css',
            [],
            $v( 'css/nextech-checkout.css' )
        );
        wp_enqueue_script(
            'nextech-checkout',
            $uri . '/js/nextech-checkout.js',
            [ 'jquery', 'select2' ],
            $v( 'js/nextech-checkout.js' ),
            true
        );
    }

    // Solo mi cuenta
    if ( is_account_page() ) {
        wp_enqueue_style(
            'nextech-account',
            $uri . '/css/nextech-account.css',
            [],
            $v( 'css/nextech-account.css' )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'nextech_enqueue_frontend_assets', 20 );
