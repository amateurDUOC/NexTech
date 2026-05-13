<?php
/**
 * Product Loop Start — Override del child theme
 *
 * Archivo original: flatsome/woocommerce/loop/loop-start.php
 * Flatsome version: 3.16.0 | WooCommerce version: 3.3.0
 *
 * ¿POR QUÉ EXISTE ESTE ARCHIVO?
 * WordPress carga primero los templates del child theme antes que el tema padre.
 * Al copiar este archivo aquí, cualquier cambio que hagamos queda en el child
 * theme y no se pierde cuando Flatsome se actualiza.
 *
 * ¿QUÉ CONTROLA ESTE ARCHIVO?
 * Renderiza el <div> contenedor que envuelve TODAS las tarjetas de producto
 * en páginas de tienda, categorías y resultados de búsqueda.
 * Las clases de columnas (large-columns-3, medium-columns-2, etc.) las genera
 * flatsome_product_row_classes() leyendo los ajustes del Customizer de WordPress.
 *
 * CLASE PERSONALIZADA AÑADIDA: nextech-product-grid
 * Permite apuntar específicamente a este grid desde style.css sin interferir
 * con otros componentes de Flatsome que también usen la clase .products
 *
 * PARA ACTUALIZAR FLATSOME:
 * Comparar este archivo con el nuevo flatsome/woocommerce/loop/loop-start.php
 * y aplicar los cambios relevantes manteniendo las personalizaciones.
 *
 * @package    Wordpressstester (child theme de Flatsome)
 * @since      2026-04-25
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Bloquear acceso directo al archivo
}

// Obtener el número de columnas configurado (viene de WooCommerce o del loop actual)
$cols = esc_attr( wc_get_loop_prop( 'columns' ) );

/*
 * flatsome_product_row_classes( $cols ) devuelve clases como:
 *   row row-small large-columns-3 medium-columns-2 small-columns-2
 *
 * Estas columnas se configuran en:
 *   WP Admin → Apariencia → Personalizar → WooCommerce → Catálogo
 */
?>
<div class="products nextech-product-grid <?php echo flatsome_product_row_classes( $cols ); ?>">
<?php
