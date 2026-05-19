<?php
/* ── Nextech — Tema hijo (Flatsome) ─────────────────────────────────────────
   Punto de entrada único. Toda la lógica está separada por dominio en inc/.
   ─────────────────────────────────────────────────────────────────────────── */

/* URL base del sitio — usada en enlaces de Mi Cuenta.
   home_url() resuelve correctamente en local y en producción. */
define( 'BASE_URL', home_url() );

$nextech_inc = get_stylesheet_directory() . '/inc/';

require_once $nextech_inc . 'data/comunas.php';   // Datos: arrays de comunas y ciudades
require_once $nextech_inc . 'enqueue.php';         // CSS / JS: enqueue de todos los assets
require_once $nextech_inc . 'woocommerce.php';     // WooCommerce: carrito, widgets, vaciar
require_once $nextech_inc . 'footer.php';          // Footer: logos de medios de pago
require_once $nextech_inc . 'checkout.php';        // Checkout: RUT, factura, campos
require_once $nextech_inc . 'account.php';         // Mi Cuenta: dashboard, botón volver
require_once $nextech_inc . 'registration.php';    // Registro: campo repetir contraseña
require_once $nextech_inc . 'pages.php';           // Páginas: Empresas, Términos y Condiciones
