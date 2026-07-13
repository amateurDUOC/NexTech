<?php
/**
 * Plugin Name:  Nextech Product Filter
 * Plugin URI:   https://rstech.cl
 * Description:  Filtro de productos personalizado con REST API y Vanilla JS. Reemplaza Husky/YITH para mejorar el rendimiento en catálogos grandes (+1000 productos).
 * Version:      1.0.1
 * Author:       Nextech
 * Text Domain:  nextech-filter
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * WC requires at least: 7.0
 * WC tested up to: 9.8
 */

defined( 'ABSPATH' ) || exit;

define( 'NEXTECH_FILTER_VERSION', '1.0.1' );
define( 'NEXTECH_FILTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEXTECH_FILTER_URL', plugin_dir_url( __FILE__ ) );

require_once NEXTECH_FILTER_DIR . 'includes/class-rest-endpoint.php';
require_once NEXTECH_FILTER_DIR . 'includes/class-filter-widget.php';
require_once NEXTECH_FILTER_DIR . 'includes/class-admin-page.php';

// ── Índices de BD al activar el plugin ───────────────────────────────────────
register_activation_hook( __FILE__, 'nextech_filter_create_indexes' );

function nextech_filter_create_indexes(): void {
    global $wpdb;

    /*
     * ÍNDICE 1: wp_postmeta — búsquedas de meta_key + meta_value
     * ─────────────────────────────────────────────────────────────
     * El índice por defecto de WordPress solo cubre meta_key(191).
     * Agregar meta_value(20) mejora las consultas directas al postmeta
     * que aún hace WooCommerce internamente para atributos de producto,
     * variaciones y búsquedas de SKU.
     *
     * meta_value es longtext → solo se puede indexar con prefijo.
     * 20 caracteres cubre: 'instock', 'outofstock', precios en CLP, SKUs cortos.
     */
    $existing = $wpdb->get_var(
        "SELECT COUNT(1)
         FROM information_schema.STATISTICS
         WHERE table_schema = DATABASE()
           AND table_name   = '{$wpdb->postmeta}'
           AND index_name   = 'nxf_key_value'"
    );
    if ( ! $existing ) {
        $wpdb->query(
            "ALTER TABLE {$wpdb->postmeta}
             ADD INDEX nxf_key_value (meta_key(20), meta_value(20))"
        );
    }

    /*
     * ÍNDICE 2: wc_product_meta_lookup — stock + rango de precio combinados
     * ────────────────────────────────────────────────────────────────────────
     * WooCommerce crea índices separados para (min_price, max_price) y
     * para (stock_status). Cuando ambos filtros se usan juntos, MySQL puede
     * usar solo uno. Este índice compuesto cubre la consulta más frecuente:
     * "productos en stock entre X e Y pesos".
     *
     * Solo se crea si la tabla existe (WC 3.7+).
     */
    $lookup = $wpdb->prefix . 'wc_product_meta_lookup';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$lookup}'" );

    if ( $table_exists ) {
        $existing2 = $wpdb->get_var(
            "SELECT COUNT(1)
             FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name   = '{$lookup}'
               AND index_name   = 'nxf_stock_price'"
        );
        if ( ! $existing2 ) {
            $wpdb->query(
                "ALTER TABLE {$lookup}
                 ADD INDEX nxf_stock_price (stock_status, min_price, max_price)"
            );
        }
    }
}

// ── Admin: botón para aplicar índices manualmente ────────────────────────────
add_action( 'admin_notices', 'nextech_filter_index_notice' );
add_action( 'admin_init',    'nextech_filter_apply_indexes_action' );

function nextech_filter_index_notice(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;
    // Mostrar siempre el botón de limpiar caché, y el de índices si no se aplicaron
    $indexes_ok = get_option( 'nxf_indexes_applied' );
    ?>
    <div class="notice notice-info" style="display:flex;align-items:center;gap:12px;padding:10px 12px;">
        <strong>Nextech Filter</strong>
        <?php if ( ! $indexes_ok ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'nxf_apply_indexes', '1' ) ); ?>"
               class="button button-primary">Aplicar índices BD</a>
        <?php endif; ?>
        <a href="<?php echo esc_url( add_query_arg( 'nxf_clear_cache', '1' ) ); ?>"
           class="button">Limpiar caché del filtro</a>
    </div>
    <?php
}

function nextech_filter_apply_indexes_action(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( isset( $_GET['nxf_apply_indexes'] ) ) {
        nextech_filter_create_indexes();
        update_option( 'nxf_indexes_applied', true );
        wp_safe_redirect( remove_query_arg( 'nxf_apply_indexes' ) );
        exit;
    }

    if ( isset( $_GET['nxf_clear_cache'] ) ) {
        nextech_filter_invalidate_cache();
        nextech_filter_invalidate_filters_cache();
        // Borrar TODOS los filtros cacheados (todas las categorías)
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_nxf_filtros_%'
                OR option_name LIKE '_transient_timeout_nxf_filtros_%'"
        );
        wp_safe_redirect( remove_query_arg( 'nxf_clear_cache' ) );
        exit;
    }
}

// ── Admin Page ────────────────────────────────────────────────────────────────
if ( is_admin() ) {
    add_action( 'init', [ 'Nextech_Admin_Page', 'init' ] );
}

// ── REST API ──────────────────────────────────────────────────────────────────
add_action( 'rest_api_init', [ 'Nextech_Rest_Endpoint', 'register_routes' ] );

// ── Widget ────────────────────────────────────────────────────────────────────
add_action( 'widgets_init', function () {
    register_widget( 'Nextech_Filter_Widget' );
} );

// ── Assets ────────────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'nextech_filter_enqueue_assets' );

function nextech_filter_enqueue_assets(): void {
    if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) return;

    wp_enqueue_style(
        'nextech-filter',
        NEXTECH_FILTER_URL . 'assets/css/nextech-filter.css',
        [],
        NEXTECH_FILTER_VERSION
    );

    wp_enqueue_script(
        'nextech-filter',
        NEXTECH_FILTER_URL . 'assets/js/nextech-filter.js',
        [],
        NEXTECH_FILTER_VERSION,
        true
    );

    // Pasar la categoría actual al JS para filtrado contextual
    $categoria_actual = '';
    if ( is_product_category() ) {
        $queried = get_queried_object();
        if ( $queried instanceof WP_Term ) {
            $categoria_actual = $queried->slug;
        }
    }

    wp_localize_script( 'nextech-filter', 'NxtFilter', [
        'apiUrl'          => esc_url( rest_url( 'nextech/v1' ) ),
        'nonce'           => wp_create_nonce( 'wp_rest' ),
        'perPage'         => 24,
        'categoriaActual' => $categoria_actual,
        'isShop'          => is_shop(),
        'i18n'            => [
            'sin_resultados' => __( 'No se encontraron productos con esos filtros.', 'nextech-filter' ),
            'error'          => __( 'Error al cargar productos. Intenta de nuevo.', 'nextech-filter' ),
        ],
    ] );
}

// ── Quitar el ordering y paginación nativos de WooCommerce ────────────────────
// El plugin ya provee su propio selector de orden y paginación — los nativos
// son redundantes y generan duplicados visuales en páginas con muchos productos.
add_action( 'init', function () {
    remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
    remove_action( 'woocommerce_after_shop_loop',  'woocommerce_pagination',        10 );
} );

// ── Redirigir "Volver a la tienda" a la última URL con filtros ────────────────
add_filter( 'woocommerce_return_to_shop_redirect',      'nextech_filter_return_url' );
add_filter( 'woocommerce_continue_shopping_redirect',   'nextech_filter_return_url' );

function nextech_filter_return_url( string $default ): string {
    if ( empty( $_COOKIE['nxf_last_url'] ) ) return $default;

    $url = esc_url_raw( urldecode( $_COOKIE['nxf_last_url'] ) );

    // Seguridad: solo aceptar URLs del mismo sitio
    if ( strpos( $url, home_url() ) === 0 || str_starts_with( $url, '/' ) ) {
        return $url;
    }

    return $default;
}

// ── Invalidar caché cuando cambia un producto ─────────────────────────────────
add_action( 'save_post_product',              'nextech_filter_invalidate_cache' );
add_action( 'woocommerce_product_set_stock',  'nextech_filter_invalidate_cache' );
add_action( 'created_product_cat',            'nextech_filter_invalidate_filters_cache' );
add_action( 'edited_product_cat',             'nextech_filter_invalidate_filters_cache' );
add_action( 'created_marca',                  'nextech_filter_invalidate_filters_cache' );
add_action( 'edited_marca',                   'nextech_filter_invalidate_filters_cache' );

function nextech_filter_invalidate_cache(): void {
    global $wpdb;
    // Elimina todos los transients de productos del filtro
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_nxf_%'
            OR option_name LIKE '_transient_timeout_nxf_%'"
    );
}

function nextech_filter_invalidate_filters_cache(): void {
    delete_transient( 'nxf_filtros_v1' );
}
