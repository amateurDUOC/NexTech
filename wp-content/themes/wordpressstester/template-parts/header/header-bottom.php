<?php
/**
 * Header Bottom — Barra azul inferior del header
 * Override del child theme
 *
 * Archivo original: flatsome/template-parts/header/header-bottom.php
 * Flatsome version: 3.16.0
 *
 * ¿QUÉ ES ESTE ARCHIVO?
 * Controla la barra inferior del header (la azul en Rs Tech).
 * Contiene: menú de navegación principal, iconos de contacto y botones.
 * WordPress carga este archivo antes que el del tema padre, por eso
 * cualquier cambio aquí no se pierde al actualizar Flatsome.
 *
 * ¿CÓMO FUNCIONA EL CONTENIDO?
 * flatsome_header_elements('header_elements_bottom_left')  → columna izquierda
 * flatsome_header_elements('header_elements_bottom_center')→ columna central
 * flatsome_header_elements('header_elements_bottom_right') → columna derecha
 * Cada una lee los elementos configurados en Header Builder (WP Admin → Apariencia
 * → Header Builder). Los elementos (menús, botones, iconos) viven en la DB.
 *
 * PARA ACTUALIZAR FLATSOME:
 * Comparar con flatsome/template-parts/header/header-bottom.php y aplicar
 * los cambios relevantes manteniendo las personalizaciones de este archivo.
 *
 * @package Wordpressstester (child theme de Flatsome)
 * @since   2026-04-25
 */

// flatsome_has_bottom_bar() verifica si la barra está activa en el Header Builder.
// Si no hay elementos configurados, no renderiza el div para no dejar HTML vacío.
if ( flatsome_has_bottom_bar()['large_or_mobile'] ) {
?>
<div id="wide-nav" class="header-bottom wide-nav <?php header_inner_class('bottom'); ?>">
    <div class="flex-row container">

        <?php
        /*
         * COLUMNA IZQUIERDA — Desktop
         * Visible solo en pantallas grandes (hide-for-medium).
         * Renderiza si hay elementos en left O right (ambas condiciones
         * se revisan juntas para que las columnas balanceen el layout).
         */
        if ( get_theme_mod('header_elements_bottom_left') || get_theme_mod('header_elements_bottom_right') ) { ?>
        <div class="flex-col hide-for-medium flex-left">
            <ul class="nav header-nav header-bottom-nav nav-left <?php flatsome_nav_classes('bottom'); ?>">
                <?php flatsome_header_elements('header_elements_bottom_left', 'nav_position_text'); ?>
            </ul>
        </div>
        <?php } ?>

        <?php
        /*
         * COLUMNA CENTRAL — Desktop
         * Visible solo en pantallas grandes (hide-for-medium).
         * Aquí suele ir el menú de navegación principal.
         */
        if ( get_theme_mod('header_elements_bottom_center') ) { ?>
        <div class="flex-col hide-for-medium flex-center">
            <ul class="nav header-nav header-bottom-nav nav-center <?php flatsome_nav_classes('bottom'); ?>">
                <?php flatsome_header_elements('header_elements_bottom_center', 'nav_position_text'); ?>
            </ul>
        </div>
        <?php } ?>

        <?php
        /*
         * COLUMNA DERECHA — Desktop
         * Visible solo en pantallas grandes (hide-for-medium).
         * flex-grow hace que ocupe el espacio restante empujando a la derecha.
         */
        if ( get_theme_mod('header_elements_bottom_right') || get_theme_mod('header_elements_bottom_left') ) { ?>
        <div class="flex-col hide-for-medium flex-right flex-grow">
            <ul class="nav header-nav header-bottom-nav nav-right <?php flatsome_nav_classes('bottom'); ?>">
                <?php flatsome_header_elements('header_elements_bottom_right', 'nav_position_text'); ?>
            </ul>
        </div>
        <?php } ?>

        <?php
        /*
         * MÓVIL — Reemplaza las tres columnas anteriores en pantallas pequeñas.
         * show-for-medium lo hace visible solo en tablet/móvil.
         * Usa header_mobile_elements_bottom configurado en el Header Builder móvil.
         */
        if ( get_theme_mod('header_mobile_elements_bottom') ) { ?>
        <div class="flex-col show-for-medium flex-grow">
            <ul class="nav header-bottom-nav nav-center mobile-nav <?php flatsome_nav_classes('bottom'); ?>">
                <?php flatsome_header_elements('header_mobile_elements_bottom'); ?>
            </ul>
        </div>
        <?php } ?>

    </div>
</div>
<?php } ?>

<?php
/*
 * Hook para agregar contenido después de la barra inferior.
 * Útil para añadir banners, alertas o elementos extra sin modificar
 * este archivo. Ejemplo en functions.php:
 *   add_action('flatsome_after_header_bottom', 'mi_funcion');
 */
do_action('flatsome_after_header_bottom');
?>
