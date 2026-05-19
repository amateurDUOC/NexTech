<?php
/* ── Nextech — Registro: campo "Repetir contraseña" ─────────────────────────
   WooCommerce por defecto solo tiene un campo de contraseña en el registro.
   Se agregan dos hooks para mostrar el campo y validar que coincidan.
   ─────────────────────────────────────────────────────────────────────────── */

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
