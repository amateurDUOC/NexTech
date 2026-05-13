<?php
/**
 * Header Main — Barra negra superior del header (logo + navegación principal)
 * Override del child theme
 *
 * Archivo original: flatsome/template-parts/header/header-main.php
 * Flatsome version: 3.16.0
 *
 * ¿QUÉ ES ESTE ARCHIVO?
 * Controla la barra superior del header donde vive el logo de Rs Tech.
 * En desktop también muestra los elementos izquierda/derecha configurados
 * en el Header Builder. En móvil muestra los elementos del menú hamburguesa.
 *
 * ZONAS DEL HEADER MAIN:
 *  - Logo        → siempre visible, configurado en Personalizar → Identidad del sitio
 *  - Left  (desktop) → header_elements_left   en Header Builder
 *  - Right (desktop) → header_elements_right  en Header Builder
 *  - Left  (móvil)   → header_mobile_elements_left
 *  - Right (móvil)   → header_mobile_elements_right
 *
 * PARA ACTUALIZAR FLATSOME:
 * Comparar con flatsome/template-parts/header/header-main.php y aplicar
 * los cambios relevantes manteniendo las personalizaciones de este archivo.
 *
 * @package Wordpressstester (child theme de Flatsome)
 * @since   2026-04-25
 */
?>
<div id="masthead" class="header-main <?php header_inner_class('main'); ?>">
    <nav class="header-inner flex-row container <?php flatsome_logo_position(); ?>" aria-label="Navegación principal">

        <!-- =============================================
             LOGO
             Cargado desde template-parts/header/partials/element-logo.php
             Se configura en: Personalizar → Identidad del sitio
             ============================================= -->
        <div id="logo" class="flex-col logo">
            <?php get_template_part('template-parts/header/partials/element', 'logo'); ?>
        </div>

        <!-- =============================================
             MÓVIL — Elementos izquierda
             show-for-medium: visible solo en tablet/móvil
             Suele contener el botón de menú hamburguesa
             ============================================= -->
        <div class="flex-col show-for-medium flex-left">
            <ul class="mobile-nav nav nav-left <?php flatsome_nav_classes('main-mobile'); ?>">
                <?php flatsome_header_elements('header_mobile_elements_left', 'mobile'); ?>
            </ul>
        </div>

        <!-- =============================================
             DESKTOP — Elementos izquierda
             hide-for-medium: visible solo en pantallas grandes
             flex-grow cuando el logo está a la izquierda para
             que el menú ocupe el espacio disponible
             ============================================= -->
        <div class="flex-col hide-for-medium flex-left
            <?php if ( get_theme_mod('logo_position', 'left') == 'left' ) echo 'flex-grow'; ?>">
            <ul class="header-nav header-nav-main nav nav-left <?php flatsome_nav_classes('main'); ?>">
                <?php flatsome_header_elements('header_elements_left'); ?>
            </ul>
        </div>

        <!-- =============================================
             DESKTOP — Elementos derecha
             hide-for-medium: visible solo en pantallas grandes
             ============================================= -->
        <div class="flex-col hide-for-medium flex-right">
            <ul class="header-nav header-nav-main nav nav-right <?php flatsome_nav_classes('main'); ?>">
                <?php flatsome_header_elements('header_elements_right'); ?>
            </ul>
        </div>

        <!-- =============================================
             MÓVIL — Elementos derecha
             show-for-medium: visible solo en tablet/móvil
             Suele contener ícono de carrito y búsqueda
             ============================================= -->
        <div class="flex-col show-for-medium flex-right">
            <ul class="mobile-nav nav nav-right <?php flatsome_nav_classes('main-mobile'); ?>">
                <?php flatsome_header_elements('header_mobile_elements_right', 'mobile'); ?>
            </ul>
        </div>

    </nav>

    <?php
    /*
     * Línea divisoria horizontal debajo del header main.
     * Se activa/desactiva en: Personalizar → Header → Divider
     */
    if ( get_theme_mod('header_divider', 1) ) { ?>
    <div class="container"><div class="top-divider full-width"></div></div>
    <?php } ?>
</div>
