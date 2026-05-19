<?php
/* ── Nextech — Mi Cuenta: dashboard y botón volver ─────────────────────────
   ─────────────────────────────────────────────────────────────────────────── */

/* Panel principal de Mi Cuenta (solo en la raíz, no en sub-endpoints) */
add_action( 'woocommerce_account_content', 'custom_account_interface', 5 );
function custom_account_interface() {
    if ( is_account_page() && ! is_wc_endpoint_url() ) {
        get_template_part( 'template-parts/account/dashboard' );
    }
}

/* Botón "Regresar a mi cuenta" en cada sub-endpoint */
add_action( 'woocommerce_account_content', 'custom_return_to_account_button', 20 );
function custom_return_to_account_button() {
    $current_endpoint = WC()->query->get_current_endpoint();
    if ( $current_endpoint ) {
        ?>
        <div class="return-to-account">
            <a href="/mi-cuenta/" class="woocommerce-button button"><?php esc_html_e( 'Regresar a mi cuenta', 'woocommerce' ); ?></a>
        </div>
        <?php
    }
}
