<?php


// Add custom Theme Functions here
function load_jquery_in_wordpress() {
    if (!is_admin()) { // Solo cargar en el frontend
        wp_enqueue_script('jquery'); // Cargar jQuery de WordPress
    }
}
add_action('wp_enqueue_scripts', 'load_jquery_in_wordpress');


/* SELECT2 — Agregado el 2026-04-23
   ANTES: Select2 se cargaba como <link> y <script> inline dentro de lioren_facturacion_field()
   PROBLEMA: Carga directa desde CDN externo sin pasar por WordPress:
     - Sin versioning ni cache control de WordPress
     - Si el CDN falla o está lento, el checkout se rompe visualmente
     - WordPress no puede minificar ni concatenar estos assets
     - Se cargaba en TODAS las visitas al checkout, incluso sin factura
   SOLUCIÓN: Registrado correctamente via wp_enqueue con is_checkout() para cargar
   SOLO en la página de checkout, no en todo el sitio.
   REVERTIR: Eliminar esta función y restaurar las líneas <link> y <script> en lioren_facturacion_field() */
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
        ['jquery'],
        '4.0.13',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'nextech_enqueue_select2' );


function remove_target_blank_with_js() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Remover el atributo target="_blank" de todos los enlaces, excepto los que tienen la clase .external
            document.querySelectorAll('a[target="_blank"]:not(.external)').forEach(function(link) {
                link.removeAttribute('target');
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'remove_target_blank_with_js');


add_action('woocommerce_account_content', 'custom_account_interface', 5);

/* BASE_URL — Corregido el 2026-04-23
   ANTES: URL hardcodeada a rstech.cl — al cerrar sesión en local redirigía
   a producción porque todos los links usaban esta constante fija.
   SOLUCIÓN: home_url() retorna automáticamente la URL correcta según el
   entorno — local en Local by Flywheel, rstech.cl en producción.
   REVERTIR: define('BASE_URL', 'https://rstech.cl'); */
define('BASE_URL', home_url());


/* ── Carrito: Quitar botones duplicados de los hooks de WooCommerce ──────────
   Flatsome inyecta su "Continue shopping" en woocommerce_cart_actions (p.10).
   El plugin de cotización inyecta su botón en el mismo hook (p.20).
   Ambos se renderizan directamente en cart.php (.nxt-cart-actions) para
   tener control total sobre su posición, por lo que los removemos aquí.
   ─────────────────────────────────────────────────────────────────────────── */
add_action( 'wp', function () {
    // Botón "Continue shopping" de Flatsome — se renderiza en .nxt-cart-actions
    remove_action( 'woocommerce_cart_actions', 'flatsome_continue_shopping', 10 );
    // Botón de cotización — ídem
    remove_action( 'woocommerce_cart_actions', 'ncc_boton_cotizacion', 20 );
} );

/* ── Footer: Logos Webpay + Linkify junto a íconos de pago ──────────────────
   Flatsome inyecta [ux_payment_icons] en flatsome_absolute_footer_secondary (p.10).
   Agregamos los logos personalizados a continuación (p.11).
   ─────────────────────────────────────────────────────────────────────────── */
add_action( 'flatsome_absolute_footer_secondary', function () {
    $uploads      = wp_upload_dir();
    $base         = $uploads['baseurl'];
    $webpay       = $base . '/2022/06/webpayyy.png';
    $linkify      = $base . '/2022/06/linkiii.png';
    $mercado_pago = $base . '/2026/05/mercado_pago.png';
    ?>
    <div class="nxt-footer-custom-logos">
        <img src="<?php echo esc_url( $webpay ); ?>"       alt="Webpay Plus"  class="nxt-footer-logo" />
        <img src="<?php echo esc_url( $linkify ); ?>"      alt="Linkify"      class="nxt-footer-logo" />
        <span class="nxt-footer-mp-wrap">
            <img src="<?php echo esc_url( $mercado_pago ); ?>" alt="Mercado Pago" class="nxt-footer-logo nxt-footer-logo--mp" />
            <span class="nxt-footer-mp-text">Mercado Pago</span>
        </span>
    </div>
    <?php
}, 11 );

/* ── Carrito: Auto-actualizar al cambiar cantidad + ocultar botón ────────────
   WooCommerce no auto-actualiza el carrito al cambiar el input qty.
   Detectamos el evento "change" en los inputs de cantidad y disparamos
   el click del botón update_cart con un debounce de 600ms.
   El botón se oculta con CSS (no se elimina del DOM para que el trigger funcione).
   ─────────────────────────────────────────────────────────────────────────── */
add_action( 'wp_footer', 'nextech_cart_scripts' );
function nextech_cart_scripts() {
    if ( ! is_cart() ) return;
    ?>
    <script>
    ( function ( $ ) {

        /* ── Auto-actualizar cantidad con debounce ── */
        var timer;
        $( document ).on( 'change', '.woocommerce-cart-form .qty', function () {
            clearTimeout( timer );
            timer = setTimeout( function () {
                $( '[name="update_cart"]' ).prop( 'disabled', false ).trigger( 'click' );
            }, 600 );
        } );

        /* ── Toggle cupón ── */
        document.addEventListener( 'DOMContentLoaded', function () {
            var btn  = document.getElementById( 'nxt-coupon-toggle' );
            var form = document.getElementById( 'nxt-coupon-form' );
            if ( ! btn || ! form ) return;

            btn.addEventListener( 'click', function () {
                var expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
                if ( expanded ) {
                    form.setAttribute( 'hidden', '' );
                    btn.setAttribute( 'aria-expanded', 'false' );
                } else {
                    form.removeAttribute( 'hidden' );
                    btn.setAttribute( 'aria-expanded', 'true' );
                    // Enfocar el input al abrir
                    var input = form.querySelector( 'input[name="coupon_code"]' );
                    if ( input ) setTimeout( function(){ input.focus(); }, 50 );
                }
            } );
        } );

    } )( jQuery );
    </script>
    <?php
}


/* ── Carrito: Suprimir widgets nativos de WooCommerce en tienda/categorías ───
   Los widgets WC_Widget_Price_Filter, WC_Widget_Product_Categories y
   WC_Widget_Layered_Nav aparecen en la barra lateral de la tienda aunque
   ya existe el nextech-product-filter. Este filtro los bloquea en
   is_shop() e is_product_category() sin necesidad de eliminarlos del
   panel de Appearance → Widgets.
   ─────────────────────────────────────────────────────────────────────────── */
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


/* ── Carrito: Auto-actualizar al cambiar cantidad + ocultar botón ────────────
   WooCommerce no auto-actualiza el carrito al cambiar el input qty.
   Detectamos el evento "change" en los inputs de cantidad y disparamos
   el click del botón update_cart con un debounce de 600ms.
   El botón se oculta con CSS (no se elimina del DOM para que el trigger funcione).
   ─────────────────────────────────────────────────────────────────────────── */
add_action( 'wp_footer', 'nextech_cart_auto_update' );
function nextech_cart_auto_update() {
    if ( ! is_cart() ) return;
    ?>
    <script>
    ( function ( $ ) {
        var timer;
        $( document ).on( 'change', '.woocommerce-cart-form .qty', function () {
            clearTimeout( timer );
            timer = setTimeout( function () {
                $( '[name="update_cart"]' ).prop( 'disabled', false ).trigger( 'click' );
            }, 600 );
        } );
    } )( jQuery );
    </script>
    <?php
}

function custom_account_interface() {
    // Mostrar la interfaz solo en la página principal de "Mi Cuenta"
    if (is_account_page() && !is_wc_endpoint_url()) {
        ?>
        <div class="my-account-container">
            <div class="account-header">
                <p>Accede a tu información y pedidos desde aquí.</p>
            </div>
            <div class="account-content">
                <div class="account-section">
                    <h3>Información de tu cuenta</h3>
                    <p>Gestiona tus datos personales y contraseñas.</p>
                    <p><a class="button" href="<?php echo BASE_URL; ?>/mi-cuenta/edit-account">Editar mi información</a></p>
                </div>
                <div class="account-section">
                    <h3>Historial de Pedidos</h3>
                    <p>Consulta el historial de tus pedidos</p>
                    <p><a class="button" href="<?php echo BASE_URL; ?>/mi-cuenta/orders">Ver mis pedidos</a></p>
                </div>
                <div class="account-section">
                    <h3>Direcciones Guardadas</h3>
                    <p>Actualiza tu dirección de envío o facturación.</p>
                    <p><a class="button" href="<?php echo BASE_URL; ?>/mi-cuenta/edit-address">Gestionar direcciones</a></p>
                </div>
                <div class="account-section">
                    <h3>¿Cerrar Sesión?</h3>
                    <p>Haz clic aquí si deseas cerrar tu sesión.</p>
                    <p><a class="button logout-button" href="<?php echo BASE_URL; ?>/mi-cuenta/customer-logout">Cerrar Sesión</a></p>
                </div>
            </div>
        </div>
        <?php
    }
}


add_action('woocommerce_after_order_notes', 'lioren_facturacion_field');

function lioren_facturacion_field($checkout) {
    // Select2 cargado via nextech_enqueue_select2() — ver comentario al inicio del archivo
    ?>

    <style>
        /* Estilo del botón */
        #lioren_factura_btn {
            display: inline-block;
            background-color: #0071a1;
            color: #fff;
            border: none;
            padding: 12px 20px;
            font-size: 16px;
            cursor: pointer;
            text-align: center;
            border-radius: 5px;
            transition: 0.3s;
            width: 100%;
            margin-bottom: 10px;
        }

        #lioren_factura_btn:hover {
            background-color: #005a87;
        }

        /* Estilo del formulario */
        #lioren_facturacion_field {
            display: none;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
            background: #f9f9f9;
        }

        /* Estilo de los campos */
        #lioren_facturacion_field input,
        #lioren_facturacion_field select {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-top: 5px;
        }

        #lioren_facturacion_field h2 {
            text-align: center;
            font-weight: bold;
            font-size: 22px;
            color: #333;
            margin-bottom: 15px;
        }

        /* Mejora de los selects */
        .select2-container .select2-selection--single {
            height: 42px;
            display: flex;
            align-items: center;
        }
    </style>

    <!-- Botón para mostrar formulario -->
    <button type="button" id="lioren_factura_btn">Requiero Factura para Empresa</button>

    <div id="lioren_facturacion_field">
        <h2>Datos de Facturación</h2>

        <?php
        woocommerce_form_field('lioren_rut', array(
            'type' => 'text',
            'class' => array('form-row-wide'),
            'label' => __('RUT'),
            'placeholder' => __('Ingrese su RUT'),
            'required' => true,
            'custom_attributes' => ['maxlength' => 10, 'minlength' => 8],
        ), $checkout->get_value('lioren_rut'));

        woocommerce_form_field('lioren_rs', array(
            'type' => 'text',
            'class' => array('form-row-wide'),
            'label' => __('Razón Social'),
            'placeholder' => __('Ingrese la razón social'),
            'required' => true,
            'custom_attributes' => ['maxlength' => 100, 'minlength' => 5],
        ), $checkout->get_value('lioren_rs'));

        woocommerce_form_field('lioren_giro', array(
            'type' => 'text',
            'class' => array('form-row-wide'),
            'label' => __('Giro'),
            'placeholder' => __('Ingrese el giro'),
            'required' => true,
            'custom_attributes' => ['maxlength' => 40, 'minlength' => 5],
        ), $checkout->get_value('lioren_giro'));

        woocommerce_form_field('lioren_comuna', array(
            'type' => 'select',
            'options' => [
                ''       => __('Selecciona una Comuna', 'lioren'), // Opción por defecto
                '68'    =>  __( 'Algarrobo', 'lioren'),
                '338'   =>  __( 'Alhué', 'lioren'),
                '178'   =>  __( 'Alto Biobío', 'lioren'),
                '27'    =>  __( 'Alto del Carmen', 'lioren'),
                '6' =>  __( 'Alto Hospicio', 'lioren'),
                '254'   =>  __( 'Ancud', 'lioren'),
                '32'    =>  __( 'Andacollo', 'lioren'),
                '221'   =>  __( 'Angol', 'lioren'),
                '289'   =>  __( 'Antártica', 'lioren'),
                '12'    =>  __( 'Antofagasta', 'lioren'),
                '166'   =>  __( 'Antuco', 'lioren'),
                '159'   =>  __( 'Arauco', 'lioren'),
                '348'   =>  __( 'Argentina', 'lioren'),
                '1' =>  __( 'Arica', 'lioren'),
                '276'   =>  __( 'Aysén', 'lioren'),
                '334'   =>  __( 'Buin', 'lioren'),
                '180'   =>  __( 'Bulnes', 'lioren'),
                '58'    =>  __( 'Cabildo', 'lioren'),
                '288'   =>  __( 'Cabo de Hornos', 'lioren'),
                '167'   =>  __( 'Cabrero', 'lioren'),
                '16'    =>  __( 'Calama', 'lioren'),
                '245'   =>  __( 'Calbuco', 'lioren'),
                '22'    =>  __( 'Caldera', 'lioren'),
                '335'   =>  __( 'Calera de Tango', 'lioren'),
                '54'    =>  __( 'Calle Larga', 'lioren'),
                '2' =>  __( 'Camarones', 'lioren'),
                '8' =>  __( 'Camiña', 'lioren'),
                '349'   =>  __( 'Canada', 'lioren'),
                '37'    =>  __( 'Canela', 'lioren'),
                '160'   =>  __( 'Cañete', 'lioren'),
                '201'   =>  __( 'Carahue', 'lioren'),
                '69'    =>  __( 'Cartagena', 'lioren'),
                '46'    =>  __( 'Casablanca', 'lioren'),
                '253'   =>  __( 'Castro', 'lioren'),
                '74'    =>  __( 'Catemu', 'lioren'),
                '126'   =>  __( 'Cauquenes', 'lioren'),
                '296'   =>  __( 'Cerrillos', 'lioren'),
                '297'   =>  __( 'Cerro Navia', 'lioren'),
                '270'   =>  __( 'Chaitén', 'lioren'),
                '24'    =>  __( 'Chañaral', 'lioren'),
                '127'   =>  __( 'Chanco', 'lioren'),
                '107'   =>  __( 'Chépica', 'lioren'),
                '148'   =>  __( 'Chiguayante', 'lioren'),
                '282'   =>  __( 'Chile Chico', 'lioren'),
                '179'   =>  __( 'Chillán', 'lioren'),
                '184'   =>  __( 'Chillán Viejo', 'lioren'),
                '108'   =>  __( 'Chimbarongo', 'lioren'),
                '220'   =>  __( 'Cholchol', 'lioren'),
                '255'   =>  __( 'Chonchi', 'lioren'),
                '277'   =>  __( 'Cisnes', 'lioren'),
                '181'   =>  __( 'Cobquecura', 'lioren'),
                '246'   =>  __( 'Cochamó', 'lioren'),
                '279'   =>  __( 'Cochrane', 'lioren'),
                '84'    =>  __( 'Codegua', 'lioren'),
                '182'   =>  __( 'Coelemu', 'lioren'),
                '183'   =>  __( 'Coihueco', 'lioren'),
                '85'    =>  __( 'Coinco', 'lioren'),
                '139'   =>  __( 'Colbún', 'lioren'),
                '9' =>  __( 'Colchane', 'lioren'),
                '330'   =>  __( 'Colina', 'lioren'),
                '222'   =>  __( 'Collipulli', 'lioren'),
                '352'   =>  __( 'COLOMBIA', 'lioren'),
                '86'    =>  __( 'Coltauco', 'lioren'),
                '41'    =>  __( 'Combarbalá', 'lioren'),
                '146'   =>  __( 'Concepción', 'lioren'),
                '298'   =>  __( 'Conchalí', 'lioren'),
                '47'    =>  __( 'Concón', 'lioren'),
                '117'   =>  __( 'Constitución', 'lioren'),
                '161'   =>  __( 'Contulmo', 'lioren'),
                '21'    =>  __( 'Copiapó', 'lioren'),
                '31'    =>  __( 'Coquimbo', 'lioren'),
                '147'   =>  __( 'Coronel', 'lioren'),
                '233'   =>  __( 'Corral', 'lioren'),
                '274'   =>  __( 'Coyhaique', 'lioren'),
                '202'   =>  __( 'Cunco', 'lioren'),
                '223'   =>  __( 'Curacautín', 'lioren'),
                '339'   =>  __( 'Curacaví', 'lioren'),
                '256'   =>  __( 'Curaco de Vélez', 'lioren'),
                '162'   =>  __( 'Curanilahue', 'lioren'),
                '203'   =>  __( 'Curarrehue', 'lioren'),
                '118'   =>  __( 'Curepto', 'lioren'),
                '129'   =>  __( 'Curicó', 'lioren'),
                '257'   =>  __( 'Dalcahue', 'lioren'),
                '25'    =>  __( 'Diego de Almagro', 'lioren'),
                '87'    =>  __( 'Doñihue', 'lioren'),
                '299'   =>  __( 'El Bosque', 'lioren'),
                '185'   =>  __( 'El Carmen', 'lioren'),
                '343'   =>  __( 'El Monte', 'lioren'),
                '70'    =>  __( 'El Quisco', 'lioren'),
                '71'    =>  __( 'El Tabo', 'lioren'),
                '119'   =>  __( 'Empedrado', 'lioren'),
                '224'   =>  __( 'Ercilla', 'lioren'),
                '300'   =>  __( 'Estación Central', 'lioren'),
                '149'   =>  __( 'Florida', 'lioren'),
                '350'   =>  __( 'Francia', 'lioren'),
                '204'   =>  __( 'Freire', 'lioren'),
                '28'    =>  __( 'Freirina', 'lioren'),
                '247'   =>  __( 'Fresia', 'lioren'),
                '248'   =>  __( 'Frutillar', 'lioren'),
                '271'   =>  __( 'Futaleufú', 'lioren'),
                '241'   =>  __( 'Futrono', 'lioren'),
                '205'   =>  __( 'Galvarino', 'lioren'),
                '4' =>  __( 'General Lagos', 'lioren'),
                '206'   =>  __( 'Gorbea', 'lioren'),
                '88'    =>  __( 'Graneros', 'lioren'),
                '278'   =>  __( 'Guaitecas', 'lioren'),
                '64'    =>  __( 'Hijuelas', 'lioren'),
                '272'   =>  __( 'Hualaihué', 'lioren'),
                '130'   =>  __( 'Hualañé', 'lioren'),
                '157'   =>  __( 'Hualpén', 'lioren'),
                '150'   =>  __( 'Hualqui', 'lioren'),
                '10'    =>  __( 'Huara', 'lioren'),
                '29'    =>  __( 'Huasco', 'lioren'),
                '301'   =>  __( 'Huechuraba', 'lioren'),
                '36'    =>  __( 'Illapel', 'lioren'),
                '302'   =>  __( 'Independencia', 'lioren'),
                '5' =>  __( 'Iquique', 'lioren'),
                '344'   =>  __( 'Isla de Maipo', 'lioren'),
                '52'    =>  __( 'Isla de Pascua', 'lioren'),
                '48'    =>  __( 'Juan Fernández', 'lioren'),
                '63'    =>  __( 'La Calera', 'lioren'),
                '303'   =>  __( 'La Cisterna', 'lioren'),
                '65'    =>  __( 'La Cruz', 'lioren'),
                '101'   =>  __( 'La Estrella', 'lioren'),
                '304'   =>  __( 'La Florida', 'lioren'),
                '305'   =>  __( 'La Granja', 'lioren'),
                '33'    =>  __( 'La Higuera', 'lioren'),
                '57'    =>  __( 'La Ligua', 'lioren'),
                '306'   =>  __( 'La Pintana', 'lioren'),
                '307'   =>  __( 'La Reina', 'lioren'),
                '30'    =>  __( 'La Serena', 'lioren'),
                '240'   =>  __( 'La Unión', 'lioren'),
                '242'   =>  __( 'Lago Ranco', 'lioren'),
                '275'   =>  __( 'Lago Verde', 'lioren'),
                '285'   =>  __( 'Laguna Blanca', 'lioren'),
                '168'   =>  __( 'Laja', 'lioren'),
                '331'   =>  __( 'Lampa', 'lioren'),
                '234'   =>  __( 'Lanco', 'lioren'),
                '89'    =>  __( 'Las Cabras', 'lioren'),
                '308'   =>  __( 'Las Condes', 'lioren'),
                '207'   =>  __( 'Lautaro', 'lioren'),
                '158'   =>  __( 'Lebu', 'lioren'),
                '131'   =>  __( 'Licantén', 'lioren'),
                '80'    =>  __( 'Limache', 'lioren'),
                '138'   =>  __( 'Linares', 'lioren'),
                '102'   =>  __( 'Litueche', 'lioren'),
                '75'    =>  __( 'Llaillay', 'lioren'),
                '250'   =>  __( 'Llanquihue', 'lioren'),
                '309'   =>  __( 'Lo Barnechea', 'lioren'),
                '310'   =>  __( 'Lo Espejo', 'lioren'),
                '311'   =>  __( 'Lo Prado', 'lioren'),
                '109'   =>  __( 'Lolol', 'lioren'),
                '208'   =>  __( 'Loncoche', 'lioren'),
                '140'   =>  __( 'Longaví', 'lioren'),
                '225'   =>  __( 'Lonquimay', 'lioren'),
                '163'   =>  __( 'Los Álamos', 'lioren'),
                '53'    =>  __( 'Los Andes', 'lioren'),
                '165'   =>  __( 'Los Ángeles', 'lioren'),
                '235'   =>  __( 'Los Lagos', 'lioren'),
                '249'   =>  __( 'Los Muermos', 'lioren'),
                '226'   =>  __( 'Los Sauces', 'lioren'),
                '38'    =>  __( 'Los Vilos', 'lioren'),
                '151'   =>  __( 'Lota', 'lioren'),
                '227'   =>  __( 'Lumaco', 'lioren'),
                '90'    =>  __( 'Machalí', 'lioren'),
                '312'   =>  __( 'Macul', 'lioren'),
                '236'   =>  __( 'Máfil', 'lioren'),
                '313'   =>  __( 'Maipú', 'lioren'),
                '91'    =>  __( 'Malloa', 'lioren'),
                '103'   =>  __( 'Marchihue', 'lioren'),
                '20'    =>  __( 'María Elena', 'lioren'),
                '340'   =>  __( 'María Pinto', 'lioren'),
                '237'   =>  __( 'Mariquina', 'lioren'),
                '120'   =>  __( 'Maule', 'lioren'),
                '251'   =>  __( 'Maullín', 'lioren'),
                '13'    =>  __( 'Mejillones', 'lioren'),
                '209'   =>  __( 'Melipeuco', 'lioren'),
                '337'   =>  __( 'Melipilla', 'lioren'),
                '132'   =>  __( 'Molina', 'lioren'),
                '42'    =>  __( 'Monte Patria', 'lioren'),
                '92'    =>  __( 'Mostazal', 'lioren'),
                '169'   =>  __( 'Mulchén', 'lioren'),
                '170'   =>  __( 'Nacimiento', 'lioren'),
                '110'   =>  __( 'Nancagua', 'lioren'),
                '293'   =>  __( 'Natales', 'lioren'),
                '104'   =>  __( 'Navidad', 'lioren'),
                '171'   =>  __( 'Negrete', 'lioren'),
                '186'   =>  __( 'Ninhue', 'lioren'),
                '187'   =>  __( 'Ñiquén', 'lioren'),
                '66'    =>  __( 'Nogales', 'lioren'),
                '210'   =>  __( 'Nueva Imperial', 'lioren'),
                '314'   =>  __( 'Ñuñoa', 'lioren'),
                '280'   =>  __( 'O\'Higgins', 'lioren'),
                '93'    =>  __( 'Olivar', 'lioren'),
                '17'    =>  __( 'Ollagüe', 'lioren'),
                '81'    =>  __( 'Olmué', 'lioren'),
                '263'   =>  __( 'Osorno', 'lioren'),
                '40'    =>  __( 'Ovalle', 'lioren'),
                '345'   =>  __( 'Padre Hurtado', 'lioren'),
                '211'   =>  __( 'Padre Las Casas', 'lioren'),
                '34'    =>  __( 'Paihuano', 'lioren'),
                '238'   =>  __( 'Paillaco', 'lioren'),
                '336'   =>  __( 'Paine', 'lioren'),
                '273'   =>  __( 'Palena', 'lioren'),
                '111'   =>  __( 'Palmilla', 'lioren'),
                '239'   =>  __( 'Panguipulli', 'lioren'),
                '76'    =>  __( 'Panquehue', 'lioren'),
                '59'    =>  __( 'Papudo', 'lioren'),
                '105'   =>  __( 'Paredones', 'lioren'),
                '141'   =>  __( 'Parral', 'lioren'),
                '315'   =>  __( 'Pedro Aguirre Cerda', 'lioren'),
                '121'   =>  __( 'Pelarco', 'lioren'),
                '128'   =>  __( 'Pelluhue', 'lioren'),
                '188'   =>  __( 'Pemuco', 'lioren'),
                '346'   =>  __( 'Peñaflor', 'lioren'),
                '316'   =>  __( 'Peñalolén', 'lioren'),
                '122'   =>  __( 'Pencahue', 'lioren'),
                '152'   =>  __( 'Penco', 'lioren'),
                '112'   =>  __( 'Peralillo', 'lioren'),
                '212'   =>  __( 'Perquenco', 'lioren'),
                '60'    =>  __( 'Petorca', 'lioren'),
                '94'    =>  __( 'Peumo', 'lioren'),
                '11'    =>  __( 'Pica', 'lioren'),
                '95'    =>  __( 'Pichidegua', 'lioren'),
                '100'   =>  __( 'Pichilemu', 'lioren'),
                '189'   =>  __( 'Pinto', 'lioren'),
                '328'   =>  __( 'Pirque', 'lioren'),
                '213'   =>  __( 'Pitrufquén', 'lioren'),
                '113'   =>  __( 'Placilla', 'lioren'),
                '190'   =>  __( 'Portezuelo', 'lioren'),
                '290'   =>  __( 'Porvenir', 'lioren'),
                '7' =>  __( 'Pozo Almonte', 'lioren'),
                '291'   =>  __( 'Primavera', 'lioren'),
                '317'   =>  __( 'Providencia', 'lioren'),
                '49'    =>  __( 'Puchuncaví', 'lioren'),
                '214'   =>  __( 'Pucón', 'lioren'),
                '318'   =>  __( 'Pudahuel', 'lioren'),
                '327'   =>  __( 'Puente Alto', 'lioren'),
                '244'   =>  __( 'Puerto Montt', 'lioren'),
                '264'   =>  __( 'Puerto Octay', 'lioren'),
                '252'   =>  __( 'Puerto Varas', 'lioren'),
                '114'   =>  __( 'Pumanque', 'lioren'),
                '43'    =>  __( 'Punitaqui', 'lioren'),
                '284'   =>  __( 'Punta Arenas', 'lioren'),
                '258'   =>  __( 'Puqueldón', 'lioren'),
                '228'   =>  __( 'Purén', 'lioren'),
                '265'   =>  __( 'Purranque', 'lioren'),
                '77'    =>  __( 'Putaendo', 'lioren'),
                '3' =>  __( 'Putre', 'lioren'),
                '266'   =>  __( 'Puyehue', 'lioren'),
                '259'   =>  __( 'Queilén', 'lioren'),
                '260'   =>  __( 'Quellón', 'lioren'),
                '261'   =>  __( 'Quemchi', 'lioren'),
                '172'   =>  __( 'Quilaco', 'lioren'),
                '319'   =>  __( 'Quilicura', 'lioren'),
                '173'   =>  __( 'Quilleco', 'lioren'),
                '191'   =>  __( 'Quillón', 'lioren'),
                '62'    =>  __( 'Quillota', 'lioren'),
                '79'    =>  __( 'Quilpué', 'lioren'),
                '262'   =>  __( 'Quinchao', 'lioren'),
                '96'    =>  __( 'Quinta de Tilcoco', 'lioren'),
                '320'   =>  __( 'Quinta Normal', 'lioren'),
                '50'    =>  __( 'Quintero', 'lioren'),
                '192'   =>  __( 'Quirihue', 'lioren'),
                '83'    =>  __( 'Rancagua', 'lioren'),
                '193'   =>  __( 'Ránquil', 'lioren'),
                '133'   =>  __( 'Rauco', 'lioren'),
                '321'   =>  __( 'Recoleta', 'lioren'),
                '229'   =>  __( 'Renaico', 'lioren'),
                '322'   =>  __( 'Renca', 'lioren'),
                '97'    =>  __( 'Rengo', 'lioren'),
                '98'    =>  __( 'Requínoa', 'lioren'),
                '142'   =>  __( 'Retiro', 'lioren'),
                '55'    =>  __( 'Rinconada', 'lioren'),
                '243'   =>  __( 'Río Bueno', 'lioren'),
                '123'   =>  __( 'Río Claro', 'lioren'),
                '44'    =>  __( 'Río Hurtado', 'lioren'),
                '283'   =>  __( 'Río Ibáñez', 'lioren'),
                '267'   =>  __( 'Río Negro', 'lioren'),
                '286'   =>  __( 'Río Verde', 'lioren'),
                '134'   =>  __( 'Romeral', 'lioren'),
                '215'   =>  __( 'Saavedra', 'lioren'),
                '135'   =>  __( 'Sagrada Familia', 'lioren'),
                '39'    =>  __( 'Salamanca', 'lioren'),
                '67'    =>  __( 'San Antonio', 'lioren'),
                '333'   =>  __( 'San Bernardo', 'lioren'),
                '194'   =>  __( 'San Carlos', 'lioren'),
                '124'   =>  __( 'San Clemente', 'lioren'),
                '56'    =>  __( 'San Esteban', 'lioren'),
                '195'   =>  __( 'San Fabián', 'lioren'),
                '73'    =>  __( 'San Felipe', 'lioren'),
                '106'   =>  __( 'San Fernando', 'lioren'),
                '287'   =>  __( 'San Gregorio', 'lioren'),
                '196'   =>  __( 'San Ignacio', 'lioren'),
                '143'   =>  __( 'San Javier', 'lioren'),
                '323'   =>  __( 'San Joaquín', 'lioren'),
                '329'   =>  __( 'San José de Maipo', 'lioren'),
                '268'   =>  __( 'San Juan de la Costa', 'lioren'),
                '324'   =>  __( 'San Miguel', 'lioren'),
                '197'   =>  __( 'San Nicolás', 'lioren'),
                '269'   =>  __( 'San Pablo', 'lioren'),
                '341'   =>  __( 'San Pedro', 'lioren'),
                '18'    =>  __( 'San Pedro de Atacama', 'lioren'),
                '153'   =>  __( 'San Pedro de La Paz', 'lioren'),
                '125'   =>  __( 'San Rafael', 'lioren'),
                '325'   =>  __( 'San Ramón', 'lioren'),
                '174'   =>  __( 'San Rosendo', 'lioren'),
                '99'    =>  __( 'San Vicente', 'lioren'),
                '175'   =>  __( 'Santa Bárbara', 'lioren'),
                '115'   =>  __( 'Santa Cruz', 'lioren'),
                '154'   =>  __( 'Santa Juana', 'lioren'),
                '78'    =>  __( 'Santa María', 'lioren'),
                '295'   =>  __( 'Santiago', 'lioren'),
                '72'    =>  __( 'Santo Domingo', 'lioren'),
                '14'    =>  __( 'Sierra Gorda', 'lioren'),
                '342'   =>  __( 'Talagante', 'lioren'),
                '116'   =>  __( 'Talca', 'lioren'),
                '155'   =>  __( 'Talcahuano', 'lioren'),
                '15'    =>  __( 'Taltal', 'lioren'),
                '200'   =>  __( 'Temuco', 'lioren'),
                '136'   =>  __( 'Teno', 'lioren'),
                '216'   =>  __( 'Teodoro Schmidt', 'lioren'),
                '23'    =>  __( 'Tierra Amarilla', 'lioren'),
                '332'   =>  __( 'Til Til', 'lioren'),
                '292'   =>  __( 'Timaukel', 'lioren'),
                '164'   =>  __( 'Tirúa', 'lioren'),
                '19'    =>  __( 'Tocopilla', 'lioren'),
                '217'   =>  __( 'Toltén', 'lioren'),
                '156'   =>  __( 'Tomé', 'lioren'),
                '294'   =>  __( 'Torres del Paine', 'lioren'),
                '281'   =>  __( 'Tortel', 'lioren'),
                '230'   =>  __( 'Traiguén', 'lioren'),
                '198'   =>  __( 'Treguaco', 'lioren'),
                '176'   =>  __( 'Tucapel', 'lioren'),
                '347'   =>  __( 'Uruguay', 'lioren'),
                '232'   =>  __( 'Valdivia', 'lioren'),
                '26'    =>  __( 'Vallenar', 'lioren'),
                '45'    =>  __( 'Valparaíso', 'lioren'),
                '137'   =>  __( 'Vichuquén', 'lioren'),
                '231'   =>  __( 'Victoria', 'lioren'),
                '35'    =>  __( 'Vicuña', 'lioren'),
                '218'   =>  __( 'Vilcún', 'lioren'),
                '144'   =>  __( 'Villa Alegre', 'lioren'),
                '82'    =>  __( 'Villa Alemana', 'lioren'),
                '219'   =>  __( 'Villarrica', 'lioren'),
                '51'    =>  __( 'Viña del Mar', 'lioren'),
                '326'   =>  __( 'Vitacura', 'lioren'),
                '145'   =>  __( 'Yerbas Buenas', 'lioren'),
                '177'   =>  __( 'Yumbel', 'lioren'),
                '199'   =>  __( 'Yungay', 'lioren'),
                '61'    =>  __( 'Zapallar', 'lioren'),
            ],
            'class' => array('form-row-wide select2-enable'),
            'label' => __('Comuna'),
            'required' => true,
        ), $checkout->get_value('lioren_comuna'));

        woocommerce_form_field('lioren_ciudad', array(
            'type' => 'select',
            'options' => [
                ''       => __('Selecciona una Ciudad', 'lioren'), // Opción por defecto
                '56'    =>  __( 'Algarrobo', 'lioren'),
                '3' =>  __( 'Alto Hospicio', 'lioren'),
                '183'   =>  __( 'Alto Jahuel', 'lioren'),
                '167'   =>  __( 'Ancud', 'lioren'),
                '21'    =>  __( 'Andacollo', 'lioren'),
                '143'   =>  __( 'Angol', 'lioren'),
                '5' =>  __( 'Antofagasta', 'lioren'),
                '111'   =>  __( 'Arauco', 'lioren'),
                '1' =>  __( 'Arica', 'lioren'),
                '184'   =>  __( 'Bajos de San Agustín', 'lioren'),
                '180'   =>  __( 'Batuco', 'lioren'),
                '202'   =>  __( 'BOGOTA', 'lioren'),
                '203'   =>  __( 'Buenos  Aires', 'lioren'),
                '195'   =>  __( 'Buenos Aires', 'lioren'),
                '182'   =>  __( 'Buin', 'lioren'),
                '123'   =>  __( 'Bulnes', 'lioren'),
                '51'    =>  __( 'Cabildo', 'lioren'),
                '115'   =>  __( 'Cabrero', 'lioren'),
                '6' =>  __( 'Calama', 'lioren'),
                '161'   =>  __( 'Calbuco', 'lioren'),
                '12'    =>  __( 'Caldera', 'lioren'),
                '47'    =>  __( 'Calle Larga', 'lioren'),
                '112'   =>  __( 'Cañete', 'lioren'),
                '133'   =>  __( 'Carahue', 'lioren'),
                '37'    =>  __( 'Cartagena', 'lioren'),
                '43'    =>  __( 'Casablanca', 'lioren'),
                '166'   =>  __( 'Castro', 'lioren'),
                '59'    =>  __( 'Catemu', 'lioren'),
                '88'    =>  __( 'Cauquenes', 'lioren'),
                '14'    =>  __( 'Chañaral', 'lioren'),
                '98'    =>  __( 'Chiguayante', 'lioren'),
                '106'   =>  __( 'Chillán', 'lioren'),
                '107'   =>  __( 'Chillán Viejo', 'lioren'),
                '79'    =>  __( 'Chimbarongo', 'lioren'),
                '66'    =>  __( 'Codegua', 'lioren'),
                '124'   =>  __( 'Coelemu', 'lioren'),
                '125'   =>  __( 'Coihueco', 'lioren'),
                '178'   =>  __( 'Colina', 'lioren'),
                '144'   =>  __( 'Collipulli', 'lioren'),
                '27'    =>  __( 'Combarbalá', 'lioren'),
                '96'    =>  __( 'Concepción', 'lioren'),
                '30'    =>  __( 'Concón', 'lioren'),
                '86'    =>  __( 'Constitución', 'lioren'),
                '117'   =>  __( 'Conurbación La Laja-San Rosendo', 'lioren'),
                '11'    =>  __( 'Copiapó', 'lioren'),
                '20'    =>  __( 'Coquimbo', 'lioren'),
                '196'   =>  __( 'Cordoba', 'lioren'),
                '99'    =>  __( 'Coronel', 'lioren'),
                '172'   =>  __( 'Coyhaique', 'lioren'),
                '134'   =>  __( 'Cunco', 'lioren'),
                '145'   =>  __( 'Curacautín', 'lioren'),
                '188'   =>  __( 'Curacaví', 'lioren'),
                '113'   =>  __( 'Curanilahue', 'lioren'),
                '84'    =>  __( 'Curicó', 'lioren'),
                '15'    =>  __( 'Diego de Almagro', 'lioren'),
                '67'    =>  __( 'Doñihue', 'lioren'),
                '54'    =>  __( 'El Melón', 'lioren'),
                '190'   =>  __( 'El Monte', 'lioren'),
                '57'    =>  __( 'El Quisco', 'lioren'),
                '16'    =>  __( 'El Salvador', 'lioren'),
                '58'    =>  __( 'El Tabo', 'lioren'),
                '135'   =>  __( 'Freire', 'lioren'),
                '162'   =>  __( 'Fresia', 'lioren'),
                '163'   =>  __( 'Frutillar', 'lioren'),
                '151'   =>  __( 'Futrono', 'lioren'),
                '136'   =>  __( 'Gorbea', 'lioren'),
                '69'    =>  __( 'Graneros', 'lioren'),
                '65'    =>  __( 'Gultro', 'lioren'),
                '39'    =>  __( 'Hijuelas', 'lioren'),
                '186'   =>  __( 'Hospital', 'lioren'),
                '89'    =>  __( 'Hualañé', 'lioren'),
                '104'   =>  __( 'Hualpén', 'lioren'),
                '100'   =>  __( 'Hualqui', 'lioren'),
                '18'    =>  __( 'Huasco', 'lioren'),
                '121'   =>  __( 'Huépil', 'lioren'),
                '23'    =>  __( 'Illapel', 'lioren'),
                '2' =>  __( 'Iquique', 'lioren'),
                '191'   =>  __( 'Isla de Maipo', 'lioren'),
                '40'    =>  __( 'La Calera', 'lioren'),
                '41'    =>  __( 'La Cruz', 'lioren'),
                '192'   =>  __( 'La Islita', 'lioren'),
                '50'    =>  __( 'La Ligua', 'lioren'),
                '19'    =>  __( 'La Serena', 'lioren'),
                '152'   =>  __( 'La Unión', 'lioren'),
                '132'   =>  __( 'Labranza', 'lioren'),
                '179'   =>  __( 'Lampa', 'lioren'),
                '153'   =>  __( 'Lanco', 'lioren'),
                '70'    =>  __( 'Las Cabras', 'lioren'),
                '44'    =>  __( 'Las Ventanas', 'lioren'),
                '137'   =>  __( 'Lautaro', 'lioren'),
                '110'   =>  __( 'Lebu', 'lioren'),
                '52'    =>  __( 'Limache', 'lioren'),
                '85'    =>  __( 'Linares', 'lioren'),
                '60'    =>  __( 'Llaillay', 'lioren'),
                '165'   =>  __( 'Llanquihue', 'lioren'),
                '68'    =>  __( 'Lo Miranda', 'lioren'),
                '138'   =>  __( 'Loncoche', 'lioren'),
                '92'    =>  __( 'Longaví', 'lioren'),
                '114'   =>  __( 'Los Álamos', 'lioren'),
                '46'    =>  __( 'Los Andes', 'lioren'),
                '108'   =>  __( 'Los Ángeles', 'lioren'),
                '154'   =>  __( 'Los Lagos', 'lioren'),
                '164'   =>  __( 'Los Muermos', 'lioren'),
                '24'    =>  __( 'Los Vilos', 'lioren'),
                '101'   =>  __( 'Lota', 'lioren'),
                '64'    =>  __( 'Machalí', 'lioren'),
                '10'    =>  __( 'María Elena', 'lioren'),
                '9' =>  __( 'Mejillones', 'lioren'),
                '187'   =>  __( 'Melipilla', 'lioren'),
                '90'    =>  __( 'Molina', 'lioren'),
                '116'   =>  __( 'Monte Águila', 'lioren'),
                '28'    =>  __( 'Monte Patria', 'lioren'),
                '194'   =>  __( 'Montevideo', 'lioren'),
                '198'   =>  __( 'Montreal', 'lioren'),
                '118'   =>  __( 'Mulchén', 'lioren'),
                '119'   =>  __( 'Nacimiento', 'lioren'),
                '80'    =>  __( 'Nancagua', 'lioren'),
                '201'   =>  __( 'Nancy', 'lioren'),
                '53'    =>  __( 'Nogales', 'lioren'),
                '139'   =>  __( 'Nueva Imperial', 'lioren'),
                '55'    =>  __( 'Olmué', 'lioren'),
                '169'   =>  __( 'Osorno', 'lioren'),
                '26'    =>  __( 'Ovalle', 'lioren'),
                '131'   =>  __( 'Padre Las Casas', 'lioren'),
                '156'   =>  __( 'Paillaco', 'lioren'),
                '185'   =>  __( 'Paine', 'lioren'),
                '81'    =>  __( 'Palmilla', 'lioren'),
                '197'   =>  __( 'Palpalá', 'lioren'),
                '157'   =>  __( 'Panguipulli', 'lioren'),
                '93'    =>  __( 'Parral', 'lioren'),
                '199'   =>  __( 'Pemuco', 'lioren'),
                '193'   =>  __( 'Peñaflor', 'lioren'),
                '102'   =>  __( 'Penco', 'lioren'),
                '72'    =>  __( 'Peumo', 'lioren'),
                '77'    =>  __( 'Pichilemu', 'lioren'),
                '140'   =>  __( 'Pitrufquén', 'lioren'),
                '34'    =>  __( 'Placilla de Peñuelas', 'lioren'),
                '4' =>  __( 'Pozo Almonte', 'lioren'),
                '141'   =>  __( 'Pucón', 'lioren'),
                '173'   =>  __( 'Puerto Aysén', 'lioren'),
                '159'   =>  __( 'Puerto Montt', 'lioren'),
                '175'   =>  __( 'Puerto Natales', 'lioren'),
                '160'   =>  __( 'Puerto Varas', 'lioren'),
                '174'   =>  __( 'Punta Arenas', 'lioren'),
                '146'   =>  __( 'Purén', 'lioren'),
                '170'   =>  __( 'Purranque', 'lioren'),
                '61'    =>  __( 'Putaendo', 'lioren'),
                '168'   =>  __( 'Quellón', 'lioren'),
                '126'   =>  __( 'Quillón', 'lioren'),
                '38'    =>  __( 'Quillota', 'lioren'),
                '33'    =>  __( 'Quilpué', 'lioren'),
                '73'    =>  __( 'Quinta de Tilcoco', 'lioren'),
                '45'    =>  __( 'Quintero', 'lioren'),
                '127'   =>  __( 'Quirihue', 'lioren'),
                '63'    =>  __( 'Rancagua', 'lioren'),
                '147'   =>  __( 'Renaico', 'lioren'),
                '74'    =>  __( 'Rengo', 'lioren'),
                '75'    =>  __( 'Requínoa', 'lioren'),
                '48'    =>  __( 'Rinconada', 'lioren'),
                '158'   =>  __( 'Río Bueno', 'lioren'),
                '171'   =>  __( 'Río Negro', 'lioren'),
                '200'   =>  __( 'Sainte Colombe', 'lioren'),
                '25'    =>  __( 'Salamanca', 'lioren'),
                '35'    =>  __( 'San Antonio', 'lioren'),
                '128'   =>  __( 'San Carlos', 'lioren'),
                '87'    =>  __( 'San Clemente', 'lioren'),
                '49'    =>  __( 'San Esteban', 'lioren'),
                '42'    =>  __( 'San Felipe', 'lioren'),
                '78'    =>  __( 'San Fernando', 'lioren'),
                '71'    =>  __( 'San Francisco de Mostazal', 'lioren'),
                '94'    =>  __( 'San Javier', 'lioren'),
                '155'   =>  __( 'San José de la Mariquina', 'lioren'),
                '177'   =>  __( 'San José de Maipo', 'lioren'),
                '105'   =>  __( 'San Pedro de la Paz', 'lioren'),
                '76'    =>  __( 'San Vicente de Tagua Tagua', 'lioren'),
                '120'   =>  __( 'Santa Bárbara', 'lioren'),
                '82'    =>  __( 'Santa Cruz', 'lioren'),
                '109'   =>  __( 'Santa Juana', 'lioren'),
                '62'    =>  __( 'Santa María', 'lioren'),
                '176'   =>  __( 'Santiago', 'lioren'),
                '36'    =>  __( 'Santo Domingo', 'lioren'),
                '189'   =>  __( 'Talagante', 'lioren'),
                '83'    =>  __( 'Talca', 'lioren'),
                '97'    =>  __( 'Talcahuano', 'lioren'),
                '8' =>  __( 'Taltal', 'lioren'),
                '130'   =>  __( 'Temuco', 'lioren'),
                '91'    =>  __( 'Teno', 'lioren'),
                '13'    =>  __( 'Tierra Amarilla', 'lioren'),
                '181'   =>  __( 'Tiltil', 'lioren'),
                '7' =>  __( 'Tocopilla', 'lioren'),
                '103'   =>  __( 'Tomé', 'lioren'),
                '148'   =>  __( 'Traiguén', 'lioren'),
                '150'   =>  __( 'Valdivia', 'lioren'),
                '17'    =>  __( 'Vallenar', 'lioren'),
                '29'    =>  __( 'Valparaíso', 'lioren'),
                '149'   =>  __( 'Victoria', 'lioren'),
                '22'    =>  __( 'Vicuña', 'lioren'),
                '95'    =>  __( 'Villa Alegre', 'lioren'),
                '32'    =>  __( 'Villa Alemana', 'lioren'),
                '142'   =>  __( 'Villarrica', 'lioren'),
                '31'    =>  __( 'Viña del Mar', 'lioren'),
                '122'   =>  __( 'Yumbel', 'lioren'),
                '129'   =>  __( 'Yungay', 'lioren'),
            ],
            'class' => array('form-row-wide select2-enable'),
            'label' => __('Ciudad'),
            'required' => true,
        ), $checkout->get_value('lioren_ciudad'));

        woocommerce_form_field('lioren_direccion', array(
            'type' => 'text',
            'class' => array('form-row-wide'),
            'label' => __('Dirección'),
            'placeholder' => __('Ingrese la dirección'),
            'required' => true,
            'custom_attributes' => ['maxlength' => 50, 'minlength' => 5],
        ), $checkout->get_value('lioren_direccion'));
        ?>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let btn = document.getElementById("lioren_factura_btn");
            let form = document.getElementById("lioren_facturacion_field");

            btn.addEventListener("click", function () {
                if (form.style.display === "none" || form.style.display === "") {
                    form.style.display = "block";
                    btn.innerText = "Cancelar Facturación";
                    // Add a hidden field to indicate factura is required
                    if (!document.getElementById('lioren_facturar')) {
                        let hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.id = 'lioren_facturar';
                        hiddenField.name = 'lioren_facturar';
                        hiddenField.value = '1';
                        form.appendChild(hiddenField);
                    }
                } else {
                    form.style.display = "none";
                    btn.innerText = "Requiero Factura para Empresa";
                    // Remove the hidden field if exists
                    let hiddenField = document.getElementById('lioren_facturar');
                    if (hiddenField) {
                        hiddenField.parentNode.removeChild(hiddenField);
                    }
                }
            });

            // Aplicar Select2 con la opción bloqueada
            jQuery(document).ready(function($) {
                $('.select2-enable select').select2({
                    placeholder: "Seleccione una opción",
                    allowClear: false // Evita que se elimine la opción seleccionada
                }).on('select2:opening', function (e) {
                    let defaultOption = $(this).find('option[value=""]');
                    if (defaultOption.length) {
                        defaultOption.attr('disabled', 'disabled'); // Bloquea la opción predeterminada
                    }
                });
            });
        });
    </script>
    <?php
}

/**
 * Checkout Process - Validate fields
 */
add_action('woocommerce_checkout_process', 'lioren_facturacion_field_process');

function lioren_facturacion_field_process()
{
    if (isset($_POST['lioren_facturar']) && $_POST['lioren_facturar'])
    {
        if(!$_POST['lioren_rut'])
        {
            wc_add_notice(__('Debes ingresar el RUT del Contribuyente.') , 'error');
        }
        else
        {
            if(!checkRUTChile($_POST['lioren_rut']))
            {
                wc_add_notice(__('El RUT ingresado no es válido.') , 'error');
            }
        }
        if (!$_POST['lioren_rs'] || strlen($_POST['lioren_rs']) < 5) wc_add_notice(__('Debes ingresar la Razón Social del Contribuyente.') , 'error');
        if (!$_POST['lioren_giro'] || strlen($_POST['lioren_giro']) < 5) wc_add_notice(__('Debes ingresar el Giro del Contribuyente.') , 'error');
        if (!$_POST['lioren_comuna'] || $_POST['lioren_comuna'] == '') wc_add_notice(__('Debes ingresar la Comuna del Contribuyente.') , 'error');
        if (!$_POST['lioren_ciudad'] || $_POST['lioren_ciudad'] == '') wc_add_notice(__('Debes ingresar la Ciudad del Contribuyente.') , 'error');
        if (!$_POST['lioren_direccion'] || strlen($_POST['lioren_direccion']) < 5) wc_add_notice(__('Debes ingresar la Dirección del Contribuyente.') , 'error');
    }
}

/**
 * Update value of field in order meta
 */
add_action('woocommerce_checkout_update_order_meta', 'lioren_facturacion_field_update_order_meta');
 
function lioren_facturacion_field_update_order_meta($order_id)
{
    if (isset($_POST['lioren_facturar']) && $_POST['lioren_facturar']) {
        update_post_meta($order_id, 'lioren_rut', sanitize_text_field(strtoupper(preg_replace('/[.,-]*/', '', $_POST['lioren_rut']))));
        update_post_meta($order_id, 'lioren_rs', sanitize_text_field($_POST['lioren_rs']));
        update_post_meta($order_id, 'lioren_giro', sanitize_text_field($_POST['lioren_giro']));
        update_post_meta($order_id, 'lioren_comuna', sanitize_text_field($_POST['lioren_comuna']));
        update_post_meta($order_id, 'lioren_ciudad', sanitize_text_field($_POST['lioren_ciudad']));
        update_post_meta($order_id, 'lioren_direccion', sanitize_text_field($_POST['lioren_direccion']));
    }
}

/**
 * RUT validation function
 */
function checkRUTChile($value)
{
    $value = strtoupper(preg_replace('/[.,-]*/', '', $value));

    if(strlen($value) == 0)
    {
        return true;
    }
    if(strlen($value) < 7 || strlen($value) > 10)
    {
        return false;
    }
    else
    {
        if(!preg_match('/[1-9]{1}[0-9]{5,7}[0-9k]{1}/is', $value))
        {
            return false;
        }
        $numero = substr($value, 0, strlen($value) - 1);
        $verificador = substr($value, strlen($value) - 1, 1);

        $total = 0;
        $factor = 2;

        for ($i = strlen($numero); $i >= 1; $i--) {
            $total += intval(substr($numero, $i -1, 1)) * $factor;
            $factor++;

            if($factor == 8)
            {
                $factor = 2;
            }
        }    

        $resto = $total % 11;
        $ver = 11 - $resto;
        if($ver == 11)
        {
            $ver = '0';
        }
        if($ver == 10)
        {
            $ver = 'K';
        }

        if((string)$ver ==  strtoupper(((string)$verificador)))
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    return false;
}





#Agrega RUT a checkout
// Agregar RUT a checkout
add_filter( 'woocommerce_checkout_fields', 'personalizar_campos_checkout' );

function personalizar_campos_checkout( $fields ) {
    $fields['billing']['billing_rut'] = array(
        'label'       => __('RUT', 'woocommerce'),
        'placeholder' => _x('Ej: 12312321-1', 'placeholder', 'woocommerce'),
        'required'    => true,
        'class'       => array('form-row-wide'),
        'clear'       => true,
        'priority'    => 22,
        'type'        => 'text',
        'maxlength'   => 10
    );

    return $fields;
}

// Validar y formatear el RUT antes de procesar el checkout
add_action( 'woocommerce_checkout_process', 'validar_rut_checkout' );

function validar_rut_checkout() {
    if ( isset($_POST['billing_rut']) ) {
        $rut = sanitize_text_field($_POST['billing_rut']);
        $rut_sin_puntos = preg_replace('/[^0-9kK-]/', '', $rut); // Eliminar puntos, solo dejar números, K y guion
        $rut_formateado = formatear_rut($rut_sin_puntos);

        // Validar solo la longitud de la versión sin puntos
        if ( strlen($rut_sin_puntos) !== 9 && strlen($rut_sin_puntos) !== 10 ) {
            wc_add_notice(__('El RUT ingresado no es válido. Debe tener exactamente 9 o 10 caracteres (Ej: 18784666-8).'), 'error');
        } elseif ( !validar_rut($rut_sin_puntos) ) {
            wc_add_notice(__('El RUT ingresado no es válido. Asegúrate de ingresarlo en el formato correcto.'), 'error');
        } else {
            $_POST['billing_rut'] = $rut_formateado; // Guardar RUT formateado correctamente
        }
    }
}

// Guardar el RUT en la orden
add_action( 'woocommerce_checkout_update_order_meta', 'guardar_rut_en_orden' );

function guardar_rut_en_orden( $order_id ) {
    if ( !empty($_POST['billing_rut']) ) {
        update_post_meta( $order_id, 'billing_rut', sanitize_text_field($_POST['billing_rut']) );
    }
}

// Mostrar el RUT en el backend de WooCommerce
add_filter( 'woocommerce_admin_billing_fields', 'mostrar_rut_en_backend' );

function mostrar_rut_en_backend( $fields ) {
    $fields['billing_rut'] = array(
        'label' => __('RUT', 'woocommerce'),
        'show'  => true
    );
    return $fields;
}

// Mostrar el RUT en los correos electrónicos de WooCommerce
add_filter( 'woocommerce_email_order_meta_fields', 'mostrar_rut_en_email' );

function mostrar_rut_en_email( $fields ) {
    $fields['billing_rut'] = array(
        'label' => __('RUT', 'woocommerce'),
        'value' => get_post_meta( get_the_ID(), 'billing_rut', true ),
    );
    return $fields;
}

// Función para formatear el RUT y agregar el guion si falta
function formatear_rut($rut) {
    $rut = preg_replace('/[^0-9kK]/', '', $rut); // Eliminar caracteres no permitidos
    if (strlen($rut) < 2) return $rut; // Evitar errores con RUT muy cortos

    $cuerpo = substr($rut, 0, -1);
    $dv = strtoupper(substr($rut, -1)); // Obtener el dígito verificador

    return $cuerpo . '-' . $dv; // Solo agregar el guion, sin puntos
}

// Función para validar el RUT chileno
function validar_rut($rut) {
    $rut = preg_replace('/[^0-9kK]/', '', $rut); // Limpiar el RUT de caracteres inválidos

    if (!preg_match('/^(\d{7,8})([kK0-9])$/', $rut, $matches)) {
        return false;
    }

    $cuerpo = $matches[1];
    $dv = strtoupper($matches[2]);

    $suma = 0;
    $multiplo = 2;

    for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
        $suma += $multiplo * intval($cuerpo[$i]);
        $multiplo = $multiplo < 7 ? $multiplo + 1 : 2;
    }

    $dvEsperado = 11 - ($suma % 11);
    $dvEsperado = $dvEsperado == 11 ? '0' : ($dvEsperado == 10 ? 'K' : (string)$dvEsperado);

    return $dv == $dvEsperado;
}

// Agregar validación en tiempo real con JavaScript en el checkout
add_action('woocommerce_after_checkout_form', 'agregar_script_validacion_rut');

function agregar_script_validacion_rut() {
    ?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            const rutInput = document.querySelector("#billing_rut");

            if (rutInput) {
                rutInput.addEventListener("input", function() {
                    let rut = rutInput.value.replace(/[^0-9kK]/g, ''); // Eliminar caracteres no válidos
                    
                    // Permitir solo 9 o 10 caracteres
                    if (rut.length !== 9 && rut.length !== 10) {
                        rutInput.setCustomValidity("El RUT debe tener exactamente 9 o 10 caracteres incluyendo el guion.");
                    } else {
                        rutInput.setCustomValidity("");
                    }

                    // Formatear RUT con guion antes del último número
                    if (rut.length > 1) {
                        let cuerpo = rut.slice(0, -1);
                        let dv = rut.slice(-1).toUpperCase();
                        rut = cuerpo + '-' + dv;
                    }

                    rutInput.value = rut;
                });

                // Validación en tiempo real cuando el usuario intente enviar el formulario
                document.querySelector("form.checkout").addEventListener("submit", function(event) {
                    let rut = rutInput.value;

                    if (rut.length !== 9 && rut.length !== 10) {
                        event.preventDefault();
                        alert("El RUT debe tener exactamente 9 o 10 caracteres, incluyendo el guion (Ej: 18784666-8).");
                        return false;
                    }
                });
            }
        });
    </script>
    <?php
}





#Elimina "Nombre de la empresa"
add_filter( 'woocommerce_checkout_fields', 'eliminar_nombre_empresa_checkout' );
function eliminar_nombre_empresa_checkout( $fields ) {
    unset($fields['billing']['billing_company']); // Elimina el campo "Nombre de la empresa"
    return $fields;
}


#Elimina código postal
add_filter( 'woocommerce_checkout_fields', 'eliminar_codigo_postal_checkout' );

function eliminar_codigo_postal_checkout( $fields ) {
    unset($fields['billing']['billing_postcode']); // Elimina el campo Código Postal
    return $fields;
}

##Orden de checkout prioridades
add_filter( 'woocommerce_default_address_fields', 'personalizar_orden_campos_direccion' );

function personalizar_orden_campos_direccion( $fields ) {
    // Establecer prioridades para los campos
    $fields['state']['priority'] = 23;      // Región
    $fields['city']['priority'] = 25;       // Comuna / Ciudad
    $fields['address_1']['priority'] = 30;  // Dirección de la calle
    $fields['address_2']['priority'] = 40;  // Número de calle y/o departamento

    return $fields;
}


#Ocultar pais para funcionamiento
add_filter( 'woocommerce_checkout_fields', 'ocultar_pais_checkout' );

function ocultar_pais_checkout( $fields ) {
    // Ocultar el campo visualmente pero mantenerlo funcional
    $fields['billing']['billing_country']['class'] = array('hidden-field');
    return $fields;
}

// Agregar CSS para ocultar el campo en el checkout
add_action('wp_head', 'ocultar_pais_con_css');

function ocultar_pais_con_css() {
    if (is_checkout()) {
        echo '<style>.hidden-field { display: none !important; }</style>';
    }
}





#Modificar tamaño billing_address_1
add_filter( 'woocommerce_checkout_fields', 'custom_modify_billing_address_1' );
function custom_modify_billing_address_1( $fields ) {
    // Agregar atributos personalizados para modificar el tamaño
    $fields['billing']['billing_address_1']['custom_attributes'] = array(
        'style' => 'width:100% !important; height:50px !important; font-size:16px !important; padding:10px !important;'
    );

    return $fields;
}

#Modificar tamaño billing_address_1
add_filter( 'woocommerce_checkout_fields', 'custom_full_width_billing_address_1' );
function custom_full_width_billing_address_1( $fields ) {
    // Asegurar que el campo dirección ocupe toda la fila como teléfono y correo
    $fields['billing']['billing_address_1']['class'] = array('form-row-wide');
    return $fields;
}


add_action('wp_footer', 'custom_popup_billing_address_1');

function custom_popup_billing_address_1() {
    if (is_checkout()) {
        ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                // Crear el elemento del popup
                var popup = document.createElement("div");
                popup.id = "billing-popup";
                popup.innerHTML = "Formato de dirección: Calle-avenida-pasaje + número + Departamento(Opcional).";
                document.body.appendChild(popup);

                // Estilizar el popup
                var style = document.createElement("style");
                style.innerHTML = `
                    #billing-popup {
                        position: absolute;
                        background: #ffcc00;
                        color: #000;
                        padding: 10px;
                        border-radius: 5px;
                        font-size: 14px;
                        font-weight: bold;
                        display: none;
                        z-index: 1000;
                        box-shadow: 2px 2px 10px rgba(0,0,0,0.2);
                    }
                `;
                document.head.appendChild(style);

                // Mostrar el popup cerca del campo billing_address_1
                var billingField = document.querySelector("#billing_address_1");
                if (billingField) {
                    billingField.addEventListener("focus", function() {
                        var rect = billingField.getBoundingClientRect();
                        popup.style.top = (window.scrollY + rect.top - 40) + "px";
                        popup.style.left = (rect.left) + "px";
                        popup.style.display = "block";
                    });

                    // Ocultar el popup cuando el usuario deja de enfocarse en el campo
                    billingField.addEventListener("blur", function() {
                        popup.style.display = "none";
                    });
                }
            });
        </script>
        <?php
    }
}

add_action('wp_footer', 'custom_inline_retiro_message');

function custom_inline_retiro_message() {
    ?>
    <style>
        /* Estilo del mensaje de dirección */
        .retiro-message {
            display: none;
            background-color: #007BFF;
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            margin-left: 10px;
            white-space: nowrap;
            cursor: pointer;
            text-decoration: underline;
        }

        .retiro-message:hover {
            background-color: #0056b3;
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            function updateRetiroMessage() {
                let retiroOption = document.querySelector("input[name^='shipping_method'][value='local_pickup:7']");
                let retiroLabel = document.querySelector("label[for='shipping_method_0_local_pickup7']");

                // Si el label no existe, no hacer nada
                if (!retiroLabel) return;

                // Buscar si ya existe el mensaje y evitar duplicaciones
                let existingMessage = document.querySelector(".retiro-message");
                if (!existingMessage) {
                    let messageLink = document.createElement("a");
                    messageLink.classList.add("retiro-message");
                    messageLink.textContent = "📍 Leopoldo Urrutia 1860, Ñuñoa, RM";
                    messageLink.href = "https://www.google.com/maps/search/?api=1&query=Leopoldo+Urrutia+1860,+Ñuñoa,+RM";
                    messageLink.target = "_blank"; // Abrir en nueva ventana
                    retiroLabel.appendChild(messageLink);
                }

                let retiroMessage = document.querySelector(".retiro-message");

                // Mostrar u ocultar el mensaje dependiendo de si la opción está seleccionada
                if (retiroOption && retiroOption.checked) {
                    retiroMessage.style.display = "inline-block";
                } else {
                    retiroMessage.style.display = "none";
                }
            }

            updateRetiroMessage(); // Ejecutar al cargar la página

            // Detectar cambios en la selección del método de envío
            jQuery(document.body).on('updated_checkout', function () {
                updateRetiroMessage();
            });

            document.addEventListener("change", function (event) {
                if (event.target.matches("input[name^='shipping_method']")) {
                    updateRetiroMessage();
                }
            });
        });
    </script>
    <?php
}


function vaciar_carrito_woocommerce() {
    if (WC()->cart) {
        WC()->cart->empty_cart();
    }
}

// Ejecutar automáticamente si está logueado y en una URL especial
add_action('init', function () {
    if (isset($_GET['vaciar_carrito']) && is_user_logged_in()) {
        vaciar_carrito_woocommerce();
    }
});

/* ============================================================
   HEADER — Ícono estrella de calificaciones junto a Follow Icons
   Inyecta una estrella dorada al lado de los iconos sociales.
   Actualizado: 2026-05-06
   ============================================================ */
add_action('wp_footer', function () {
    $reviews_url = 'https://www.solotodo.cl/stores/2009/ratings';
    ?>
    <style>
    .nextech-star-review {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        margin-left: 6px;
        opacity: 0.85;
        transition: opacity 0.2s, transform 0.18s;
        vertical-align: middle;
        line-height: 1;
    }
    .nextech-star-review:hover {
        opacity: 1;
        transform: scale(1.15);
    }
    .nextech-star-review svg {
        display: block;
    }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.querySelector(
            '.header-social-icons, .follow-icons, [class*="social-icons"], [class*="follow"]'
        );
        if (!container) return;

        var link = document.createElement('a');
        link.href      = <?php echo json_encode(esc_url($reviews_url)); ?>;
        link.target    = '_blank';
        link.rel       = 'noopener';
        link.title     = 'Ver nuestras calificaciones';
        link.className = 'nextech-star-review';
        link.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"' +
            ' fill="#ffffff" stroke="#ffffff" stroke-width="0.3" stroke-linejoin="round">' +
            '<polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>' +
            '</svg>';

        container.appendChild(link);
    });
    </script>
    <?php
}, 20);


/* REGISTRO — Campo "Repetir contraseña" — Agregado el 2026-04-23
   ANTES: El formulario de registro de WooCommerce solo tenía un campo de contraseña.
   PROBLEMA: Sin confirmación de contraseña, el usuario puede cometer errores al escribirla
   y no darse cuenta hasta intentar iniciar sesión.
   SOLUCIÓN: Se agregan tres hooks:
     1. woocommerce_register_form — agrega el campo visual al formulario
     2. woocommerce_registration_errors — valida que ambas contraseñas coincidan
     3. woocommerce_new_customer_data — no es necesario guardar, WC ya guarda la contraseña
   REVERTIR: Eliminar las tres funciones y sus add_action/add_filter. */

// 1. Agregar el campo al formulario
add_action( 'woocommerce_register_form', 'nextech_registro_campo_repetir_password' );
function nextech_registro_campo_repetir_password() {
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="password2"><?php esc_html_e( 'Repetir contraseña', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="password"
               class="woocommerce-Input woocommerce-Input--password input-text"
               name="password2"
               id="password2"
               autocomplete="new-password"
               value="" />
    </p>
    <?php
}

// 2. Validar que ambas contraseñas coincidan
add_filter( 'woocommerce_registration_errors', 'nextech_validar_repetir_password', 10, 3 );
function nextech_validar_repetir_password( $errors, $username, $email ) {
    $password1 = isset( $_POST['password'] )  ? $_POST['password']  : '';
    $password2 = isset( $_POST['password2'] ) ? $_POST['password2'] : '';

    if ( empty( $password2 ) ) {
        $errors->add( 'password2_error', __( 'Por favor repite tu contraseña.', 'woocommerce' ) );
    } elseif ( $password1 !== $password2 ) {
        $errors->add( 'password2_error', __( 'Las contraseñas no coinciden.', 'woocommerce' ) );
    }

    return $errors;
}

/* ============================================================
   FOOTER — Widgets "Horarios" y "¿Quiénes somos?" colapsables
   - Ambos tienen botón toggle con flecha
   - "¿Quiénes somos?" expande el texto hacia la DERECHA
     como un panel flotante anclado al botón
   - "Horarios" expande hacia abajo (es info corta y directa)
   Actualizado: 2026-05-06
   ============================================================ */
add_action('wp_footer', function () { ?>
<style>

/* ══════════════════════════════════════════════════════════════
   TOGGLE GENÉRICO — botón compartido por Horarios y ¿Quiénes?
   ══════════════════════════════════════════════════════════════ */
.nextech-footer-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: none;
    border: 1px solid rgba(255,255,255,0.30);
    border-radius: 4px;
    color: #fff;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 6px 14px;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    white-space: nowrap;
}
.nextech-footer-toggle:hover {
    background: rgba(255,255,255,0.07);
    border-color: rgba(255,255,255,0.55);
}
.nextech-footer-toggle::after {
    content: '▾';
    font-size: 1rem;
    transition: transform 0.25s ease;
    display: inline-block;
}
.nextech-footer-toggle.open::after {
    transform: rotate(180deg);
}

/* ══════════════════════════════════════════════════════════════
   ¿QUIÉNES SOMOS? — expande hacia la DERECHA como panel flotante
   ══════════════════════════════════════════════════════════════ */
.nextech-quienes-wrap {
    position: relative;
    display: inline-block;
}
/* Panel flotante que aparece a la derecha del botón */
.nextech-quienes-body {
    position: absolute;
    left: calc(100% + 14px);
    top: 50%;
    transform: translateY(-50%) translateX(-8px);
    width: 280px;
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 6px;
    padding: 14px 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.55);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.28s ease, transform 0.28s ease;
    z-index: 999;
    text-align: left;
}
/* Triángulo decorativo apuntando al botón (izquierda del panel) */
.nextech-quienes-body::before {
    content: '';
    position: absolute;
    left: -7px;
    top: 50%;
    transform: translateY(-50%);
    border-width: 7px 7px 7px 0;
    border-style: solid;
    border-color: transparent rgba(255,255,255,0.15) transparent transparent;
}
.nextech-quienes-body::after {
    content: '';
    position: absolute;
    left: -5px;
    top: 50%;
    transform: translateY(-50%);
    border-width: 6px 6px 6px 0;
    border-style: solid;
    border-color: transparent #1a1a1a transparent transparent;
}
.nextech-quienes-body.open {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(-50%) translateX(0);
}
.nextech-quienes-body p {
    margin: 0 0 8px;
    line-height: 1.6;
    font-size: 0.88rem;
    color: #ddd;
}
.nextech-quienes-body p:last-child { margin-bottom: 0; }

/* En móvil el panel cae hacia abajo para no salirse de pantalla */
@media (max-width: 768px) {
    .nextech-quienes-body {
        position: static !important;
        width: 100% !important;
        transform: none !important;
        margin-top: 10px;
        box-shadow: none;
        border-color: rgba(255,255,255,0.12);
        /* Ocultar completamente en lugar de solo opacity para no ocupar espacio */
        display: none;
        opacity: 1 !important;
        pointer-events: auto !important;
    }
    .nextech-quienes-body.open {
        display: block !important;
        transform: none !important;
    }
    .nextech-quienes-body::before,
    .nextech-quienes-body::after { display: none !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* Toggle genérico: funciona para Horarios y ¿Quiénes somos? */
    document.querySelectorAll('.nextech-footer-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var body = document.getElementById(btn.dataset.target);
            if (!body) return;
            var isOpen = body.classList.contains('open');
            body.classList.toggle('open', !isOpen);
            btn.classList.toggle('open', !isOpen);
            btn.setAttribute('aria-expanded', String(!isOpen));
        });
    });

    /* Cierra el panel de ¿Quiénes somos? al hacer click fuera */
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.nextech-quienes-wrap')) {
            var body = document.getElementById('quienes-body');
            var btn  = document.querySelector('[data-target="quienes-body"]');
            if (body)  body.classList.remove('open');
            if (btn)   { btn.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }
        }
    });
});
</script>
<?php }, 20);


add_action( 'woocommerce_account_content', 'custom_return_to_account_button', 20 );

function custom_return_to_account_button() {
    // Detectar si estamos dentro de una subpágina (excepto "mi cuenta" raíz)
    $current_endpoint = WC()->query->get_current_endpoint();

    if ( $current_endpoint ) {
        ?>
        <!-- Botón Regresar a Mi Cuenta -->
        <div class="return-to-account">
            <a href="/mi-cuenta/" class="woocommerce-button button"><?php esc_html_e( 'Regresar a mi cuenta', 'woocommerce' ); ?></a>
        </div>

        <style>
            .return-to-account {
                text-align: center;
                margin-top: 20px;
            }
            .return-to-account .woocommerce-button {
                display: inline-block;
                padding: 8px 16px;
                font-size: 14px;
                background-color: #0073aa;
                color: #fff;
                text-decoration: none;
                border-radius: 5px;
                transition: background-color 0.3s ease;
            }
            .return-to-account .woocommerce-button:hover {
                background-color: #005177;
            }
            @media (max-width: 768px) {
                .return-to-account .woocommerce-button {
                    width: 80%;
                    font-size: 13px;
                    padding: 10px;
                }
            }
        </style>
        <?php
    }
}



function forzar_carga_css_tema_hijo() {
    // Opcional: eliminar el CSS del tema padre si lo necesitas
    wp_dequeue_style('parent-style');
    wp_deregister_style('parent-style');

    // Registrar y encolar el CSS del tema hijo con versión dinámica
    wp_enqueue_style(
        'wordpressstester-style', // Puedes usar el nombre que quieras
        get_stylesheet_directory_uri() . '/style.css',
        [], // Dependencias (vacío si no depende del padre)
        filemtime(get_stylesheet_directory() . '/style.css')
    );
}
add_action('wp_enqueue_scripts', 'forzar_carga_css_tema_hijo', 100); // Prioridad alta

// Fix the billing_address_2 field label and width - now hiding the label
add_filter('woocommerce_billing_fields', 'custom_modify_billing_address_2');
add_filter('woocommerce_default_address_fields', 'custom_address_fields_override');

function custom_modify_billing_address_2($fields) {
    // Change the label and placeholder for billing_address_2
    if (isset($fields['billing_address_2'])) {
        $fields['billing_address_2']['label'] = ''; // Empty label to hide it
        $fields['billing_address_2']['placeholder'] = 'Observaciones o información adicional';
        
        // Make sure it takes full width
        $fields['billing_address_2']['class'] = array('form-row-wide');
        
        // Match the styling of billing_address_1
        $fields['billing_address_2']['custom_attributes'] = array(
            'style' => 'width:100% !important; height:50px !important; font-size:16px !important; padding:10px !important;'
        );
        
        // Hide the label by adding screen-reader-text class
        $fields['billing_address_2']['label_class'] = array('screen-reader-text'); 
    }
    return $fields;
}

// Override default address fields (this is needed to properly change address_2 label)
function custom_address_fields_override($fields) {
    // Override the address_2 field specifically
    if (isset($fields['address_2'])) {
        $fields['address_2']['label'] = ''; // Empty label to hide it
        $fields['address_2']['placeholder'] = 'Información adicional (opcional) Ej: Sucursal starken/chilexpress';
        $fields['address_2']['label_class'] = array('screen-reader-text'); // Add screen-reader-text class to hide label
        $fields['address_2']['class'] = array('form-row-wide'); // Ensure full width
    }
    return $fields;
}

// Add custom CSS to ensure billing_address_2 has the correct width and hidden label
add_action('wp_head', 'ensure_billing_address_2_width');

function ensure_billing_address_2_width() {
    if (is_checkout()) {
        ?>
        <style>
            #billing_address_2_field {
                width: 100% !important;
                max-width: 100% !important;
                margin-top: -10px; /* Reduce space above the field since label is hidden */
            }
            
            #billing_address_2 {
                width: 100% !important;
                max-width: 100% !important;
                height: 50px !important;
                font-size: 16px !important;
                padding: 10px !important;
                box-sizing: border-box; /* Ensure padding doesn't affect width */
            }
            
            /* Hide the label completely */
            #billing_address_2_field label {
                display: none !important;
            }
        </style>
        <?php
    }
}

add_filter('woocommerce_enable_order_notes_field', '__return_false');
