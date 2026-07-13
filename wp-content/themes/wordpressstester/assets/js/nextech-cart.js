/* ── Nextech — Carrito: auto-actualizar cantidad + toggle cupón ─────────── */

( function ( $ ) {

    /* ── Auto-actualizar cantidad con debounce 600ms ── */
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
                var input = form.querySelector( 'input[name="coupon_code"]' );
                if ( input ) setTimeout( function () { input.focus(); }, 50 );
            }
        } );
    } );

} )( jQuery );
