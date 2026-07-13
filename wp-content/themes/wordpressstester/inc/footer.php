<?php
/* ── Nextech — Footer: logos de medios de pago ──────────────────────────────
   Flatsome inyecta [ux_payment_icons] en flatsome_absolute_footer_secondary (p.10).
   Los logos personalizados se agregan a continuación (p.11).
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
