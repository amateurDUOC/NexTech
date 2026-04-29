<?php
/**
 * Debug temporal — acceder como:
 * http://nextech.local/wp-content/plugins/nextech-product-filter/debug-filtros.php?contexto=pc-gamer
 *
 * BORRAR ESTE ARCHIVO cuando termines de depurar.
 */
define( 'ABSPATH', true ); // evita "No direct access"
$wp_load = dirname( __FILE__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) die( 'wp-load.php no encontrado' );
require_once $wp_load;

header( 'Content-Type: application/json; charset=utf-8' );

$contexto = isset( $_GET['contexto'] ) ? sanitize_text_field( $_GET['contexto'] ) : '';
$ctx_term = $contexto ? get_term_by( 'slug', $contexto, 'product_cat' ) : null;

$result = [
    'contexto_slug'  => $contexto,
    'ctx_term_found' => $ctx_term ? true : false,
    'ctx_term_id'    => $ctx_term ? $ctx_term->term_id : null,
    'ctx_term_parent'=> $ctx_term ? $ctx_term->parent : null,
];

if ( $ctx_term ) {
    $subcats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => $ctx_term->term_id ] );
    $result['subcategorias_directas'] = array_map( fn($t) => [ 'slug' => $t->slug, 'nombre' => $t->name, 'count' => $t->count ], (array) $subcats );

    $siblings = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => $ctx_term->parent ] );
    $result['categorias_hermanas'] = array_map( fn($t) => [ 'slug' => $t->slug, 'nombre' => $t->name, 'count' => $t->count ], (array) $siblings );
}

$marcas = get_terms( [ 'taxonomy' => 'marca', 'hide_empty' => false ] );
$result['todas_las_marcas_existentes'] = array_map( fn($m) => [ 'nombre' => $m->name, 'count' => $m->count ], (array) $marcas );

// Caché activa?
$cache_key = 'nxf_filtros_' . ( $contexto ? md5( $contexto ) : 'global' ) . '_v1';
$result['transient_en_cache'] = get_transient( $cache_key ) !== false ? 'SÍ (borrarlo para ver datos frescos)' : 'NO';
$result['hint_borrar_cache'] = '?contexto=' . $contexto . '&clear=1';

if ( isset( $_GET['clear'] ) ) {
    delete_transient( $cache_key );
    delete_transient( 'nxf_filtros_global_v1' );
    $result['cache_borrada'] = true;
}

echo json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
