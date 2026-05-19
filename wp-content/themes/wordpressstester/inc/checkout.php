<?php
/* ── Nextech — Checkout: campos, validación y facturación ───────────────────
   Incluye: campo RUT de cliente, formulario de factura empresa, comunas/ciudades,
   orden de campos, campos eliminados y ajustes de dirección.
   CSS → assets/css/nextech-checkout.css
   JS  → assets/js/nextech-checkout.js
   ─────────────────────────────────────────────────────────────────────────── */

/* ── Formulario de facturación empresa ────────────────────────────────────── */

add_action( 'woocommerce_after_order_notes', 'lioren_facturacion_field' );
function lioren_facturacion_field( $checkout ) {
    ?>
    <button type="button" id="lioren_factura_btn">Requiero Factura para Empresa</button>

    <div id="lioren_facturacion_field">
        <h2>Datos de Facturación</h2>

        <?php
        woocommerce_form_field( 'lioren_rut', [
            'type'               => 'text',
            'class'              => [ 'form-row-wide' ],
            'label'              => __( 'RUT' ),
            'placeholder'        => __( 'Ingrese su RUT' ),
            'required'           => true,
            'custom_attributes'  => [ 'maxlength' => 10, 'minlength' => 8 ],
        ], $checkout->get_value( 'lioren_rut' ) );

        woocommerce_form_field( 'lioren_rs', [
            'type'               => 'text',
            'class'              => [ 'form-row-wide' ],
            'label'              => __( 'Razón Social' ),
            'placeholder'        => __( 'Ingrese la razón social' ),
            'required'           => true,
            'custom_attributes'  => [ 'maxlength' => 100, 'minlength' => 5 ],
        ], $checkout->get_value( 'lioren_rs' ) );

        woocommerce_form_field( 'lioren_giro', [
            'type'               => 'text',
            'class'              => [ 'form-row-wide' ],
            'label'              => __( 'Giro' ),
            'placeholder'        => __( 'Ingrese el giro' ),
            'required'           => true,
            'custom_attributes'  => [ 'maxlength' => 40, 'minlength' => 5 ],
        ], $checkout->get_value( 'lioren_giro' ) );

        woocommerce_form_field( 'lioren_comuna', [
            'type'     => 'select',
            'options'  => nextech_comunas(),
            'class'    => [ 'form-row-wide select2-enable' ],
            'label'    => __( 'Comuna' ),
            'required' => true,
        ], $checkout->get_value( 'lioren_comuna' ) );

        woocommerce_form_field( 'lioren_ciudad', [
            'type'     => 'select',
            'options'  => nextech_ciudades(),
            'class'    => [ 'form-row-wide select2-enable' ],
            'label'    => __( 'Ciudad' ),
            'required' => true,
        ], $checkout->get_value( 'lioren_ciudad' ) );

        woocommerce_form_field( 'lioren_direccion', [
            'type'               => 'text',
            'class'              => [ 'form-row-wide' ],
            'label'              => __( 'Dirección' ),
            'placeholder'        => __( 'Ingrese la dirección' ),
            'required'           => true,
            'custom_attributes'  => [ 'maxlength' => 50, 'minlength' => 5 ],
        ], $checkout->get_value( 'lioren_direccion' ) );
        ?>
    </div>
    <?php
}

add_action( 'woocommerce_checkout_process', 'lioren_facturacion_field_process' );
function lioren_facturacion_field_process() {
    if ( ! isset( $_POST['lioren_facturar'] ) || ! $_POST['lioren_facturar'] ) return;

    if ( ! $_POST['lioren_rut'] ) {
        wc_add_notice( __( 'Debes ingresar el RUT del Contribuyente.' ), 'error' );
    } elseif ( ! checkRUTChile( $_POST['lioren_rut'] ) ) {
        wc_add_notice( __( 'El RUT ingresado no es válido.' ), 'error' );
    }
    if ( ! $_POST['lioren_rs'] || strlen( $_POST['lioren_rs'] ) < 5 )
        wc_add_notice( __( 'Debes ingresar la Razón Social del Contribuyente.' ), 'error' );
    if ( ! $_POST['lioren_giro'] || strlen( $_POST['lioren_giro'] ) < 5 )
        wc_add_notice( __( 'Debes ingresar el Giro del Contribuyente.' ), 'error' );
    if ( ! $_POST['lioren_comuna'] || $_POST['lioren_comuna'] === '' )
        wc_add_notice( __( 'Debes ingresar la Comuna del Contribuyente.' ), 'error' );
    if ( ! $_POST['lioren_ciudad'] || $_POST['lioren_ciudad'] === '' )
        wc_add_notice( __( 'Debes ingresar la Ciudad del Contribuyente.' ), 'error' );
    if ( ! $_POST['lioren_direccion'] || strlen( $_POST['lioren_direccion'] ) < 5 )
        wc_add_notice( __( 'Debes ingresar la Dirección del Contribuyente.' ), 'error' );
}

add_action( 'woocommerce_checkout_update_order_meta', 'lioren_facturacion_field_update_order_meta' );
function lioren_facturacion_field_update_order_meta( $order_id ) {
    if ( ! isset( $_POST['lioren_facturar'] ) || ! $_POST['lioren_facturar'] ) return;

    update_post_meta( $order_id, 'lioren_rut',       sanitize_text_field( strtoupper( preg_replace( '/[.,-]*/', '', $_POST['lioren_rut'] ) ) ) );
    update_post_meta( $order_id, 'lioren_rs',        sanitize_text_field( $_POST['lioren_rs'] ) );
    update_post_meta( $order_id, 'lioren_giro',      sanitize_text_field( $_POST['lioren_giro'] ) );
    update_post_meta( $order_id, 'lioren_comuna',    sanitize_text_field( $_POST['lioren_comuna'] ) );
    update_post_meta( $order_id, 'lioren_ciudad',    sanitize_text_field( $_POST['lioren_ciudad'] ) );
    update_post_meta( $order_id, 'lioren_direccion', sanitize_text_field( $_POST['lioren_direccion'] ) );
}

/* Validación RUT chileno (servidor) */
function checkRUTChile( $value ) {
    $value = strtoupper( preg_replace( '/[.,-]*/', '', $value ) );
    if ( strlen( $value ) === 0 ) return true;
    if ( strlen( $value ) < 7 || strlen( $value ) > 10 ) return false;
    if ( ! preg_match( '/[1-9]{1}[0-9]{5,7}[0-9k]{1}/is', $value ) ) return false;

    $numero      = substr( $value, 0, strlen( $value ) - 1 );
    $verificador = substr( $value, strlen( $value ) - 1, 1 );
    $total       = 0;
    $factor      = 2;

    for ( $i = strlen( $numero ); $i >= 1; $i-- ) {
        $total += intval( substr( $numero, $i - 1, 1 ) ) * $factor;
        $factor = ( $factor === 7 ) ? 2 : $factor + 1;
    }

    $resto = $total % 11;
    $ver   = 11 - $resto;
    if ( $ver === 11 ) $ver = '0';
    if ( $ver === 10 ) $ver = 'K';

    return (string) $ver === strtoupper( (string) $verificador );
}

/* ── RUT de cliente en el checkout ───────────────────────────────────────── */

add_filter( 'woocommerce_checkout_fields', 'personalizar_campos_checkout' );
function personalizar_campos_checkout( $fields ) {
    $fields['billing']['billing_rut'] = [
        'label'       => __( 'RUT', 'woocommerce' ),
        'placeholder' => _x( 'Ej: 12312321-1', 'placeholder', 'woocommerce' ),
        'required'    => true,
        'class'       => [ 'form-row-wide' ],
        'clear'       => true,
        'priority'    => 22,
        'type'        => 'text',
        'maxlength'   => 10,
    ];
    return $fields;
}

add_action( 'woocommerce_checkout_process', 'validar_rut_checkout' );
function validar_rut_checkout() {
    if ( ! isset( $_POST['billing_rut'] ) ) return;
    $rut             = sanitize_text_field( $_POST['billing_rut'] );
    $rut_sin_puntos  = preg_replace( '/[^0-9kK-]/', '', $rut );
    $rut_formateado  = formatear_rut( $rut_sin_puntos );

    if ( strlen( $rut_sin_puntos ) !== 9 && strlen( $rut_sin_puntos ) !== 10 ) {
        wc_add_notice( __( 'El RUT ingresado no es válido. Debe tener exactamente 9 o 10 caracteres (Ej: 18784666-8).' ), 'error' );
    } elseif ( ! validar_rut( $rut_sin_puntos ) ) {
        wc_add_notice( __( 'El RUT ingresado no es válido. Asegúrate de ingresarlo en el formato correcto.' ), 'error' );
    } else {
        $_POST['billing_rut'] = $rut_formateado;
    }
}

add_action( 'woocommerce_checkout_update_order_meta', 'guardar_rut_en_orden' );
function guardar_rut_en_orden( $order_id ) {
    if ( ! empty( $_POST['billing_rut'] ) ) {
        update_post_meta( $order_id, 'billing_rut', sanitize_text_field( $_POST['billing_rut'] ) );
    }
}

add_filter( 'woocommerce_admin_billing_fields', 'mostrar_rut_en_backend' );
function mostrar_rut_en_backend( $fields ) {
    $fields['billing_rut'] = [
        'label' => __( 'RUT', 'woocommerce' ),
        'show'  => true,
    ];
    return $fields;
}

add_filter( 'woocommerce_email_order_meta_fields', 'mostrar_rut_en_email' );
function mostrar_rut_en_email( $fields ) {
    $fields['billing_rut'] = [
        'label' => __( 'RUT', 'woocommerce' ),
        'value' => get_post_meta( get_the_ID(), 'billing_rut', true ),
    ];
    return $fields;
}

function formatear_rut( $rut ) {
    $rut = preg_replace( '/[^0-9kK]/', '', $rut );
    if ( strlen( $rut ) < 2 ) return $rut;
    return substr( $rut, 0, -1 ) . '-' . strtoupper( substr( $rut, -1 ) );
}

function validar_rut( $rut ) {
    $rut = preg_replace( '/[^0-9kK]/', '', $rut );
    if ( ! preg_match( '/^(\d{7,8})([kK0-9])$/', $rut, $matches ) ) return false;

    $cuerpo    = $matches[1];
    $dv        = strtoupper( $matches[2] );
    $suma      = 0;
    $multiplo  = 2;

    for ( $i = strlen( $cuerpo ) - 1; $i >= 0; $i-- ) {
        $suma    += $multiplo * intval( $cuerpo[ $i ] );
        $multiplo = $multiplo < 7 ? $multiplo + 1 : 2;
    }

    $dvEsperado = 11 - ( $suma % 11 );
    $dvEsperado = $dvEsperado === 11 ? '0' : ( $dvEsperado === 10 ? 'K' : (string) $dvEsperado );

    return $dv === $dvEsperado;
}

/* ── Ajustes de campos del checkout ──────────────────────────────────────── */

add_filter( 'woocommerce_checkout_fields', 'eliminar_nombre_empresa_checkout' );
function eliminar_nombre_empresa_checkout( $fields ) {
    unset( $fields['billing']['billing_company'] );
    return $fields;
}

add_filter( 'woocommerce_checkout_fields', 'eliminar_codigo_postal_checkout' );
function eliminar_codigo_postal_checkout( $fields ) {
    unset( $fields['billing']['billing_postcode'] );
    return $fields;
}

add_filter( 'woocommerce_default_address_fields', 'personalizar_orden_campos_direccion' );
function personalizar_orden_campos_direccion( $fields ) {
    $fields['state']['priority']    = 23;
    $fields['city']['priority']     = 25;
    $fields['address_1']['priority'] = 30;
    $fields['address_2']['priority'] = 40;
    return $fields;
}

add_filter( 'woocommerce_checkout_fields', 'ocultar_pais_checkout' );
function ocultar_pais_checkout( $fields ) {
    $fields['billing']['billing_country']['class'] = [ 'hidden-field' ];
    return $fields;
}

/* Dirección 1 — ancho completo y altura consistente con dirección 2.
   Estilos visuales en assets/css/nextech-checkout.css (#billing_address_1). */
add_filter( 'woocommerce_checkout_fields', 'custom_modify_billing_address_1' );
function custom_modify_billing_address_1( $fields ) {
    $fields['billing']['billing_address_1']['class'] = [ 'form-row-wide' ];
    return $fields;
}

/* Dirección 2 — etiqueta oculta, placeholder descriptivo, ancho completo.
   Estilos visuales en assets/css/nextech-checkout.css (#billing_address_2). */
add_filter( 'woocommerce_billing_fields', 'custom_modify_billing_address_2' );
function custom_modify_billing_address_2( $fields ) {
    if ( isset( $fields['billing_address_2'] ) ) {
        $fields['billing_address_2']['label']       = '';
        $fields['billing_address_2']['placeholder'] = 'Observaciones o información adicional';
        $fields['billing_address_2']['class']       = [ 'form-row-wide' ];
        $fields['billing_address_2']['label_class'] = [ 'screen-reader-text' ];
    }
    return $fields;
}

add_filter( 'woocommerce_default_address_fields', 'custom_address_fields_override' );
function custom_address_fields_override( $fields ) {
    if ( isset( $fields['address_2'] ) ) {
        $fields['address_2']['label']       = '';
        $fields['address_2']['placeholder'] = 'Información adicional (opcional) Ej: Sucursal starken/chilexpress';
        $fields['address_2']['label_class'] = [ 'screen-reader-text' ];
        $fields['address_2']['class']       = [ 'form-row-wide' ];
    }
    return $fields;
}

add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
