/* ── Nextech — Checkout: factura, RUT, popup dirección, retiro ──────────── */

document.addEventListener( 'DOMContentLoaded', function () {

    /* ── Toggle formulario de facturación ── */
    var btn  = document.getElementById( 'lioren_factura_btn' );
    var form = document.getElementById( 'lioren_facturacion_field' );

    if ( btn && form ) {
        btn.addEventListener( 'click', function () {
            if ( form.style.display === 'none' || form.style.display === '' ) {
                form.style.display = 'block';
                btn.innerText = 'Cancelar Facturación';
                if ( ! document.getElementById( 'lioren_facturar' ) ) {
                    var hidden      = document.createElement( 'input' );
                    hidden.type     = 'hidden';
                    hidden.id       = 'lioren_facturar';
                    hidden.name     = 'lioren_facturar';
                    hidden.value    = '1';
                    form.appendChild( hidden );
                }
            } else {
                form.style.display = 'none';
                btn.innerText = 'Requiero Factura para Empresa';
                var hiddenField = document.getElementById( 'lioren_facturar' );
                if ( hiddenField ) hiddenField.parentNode.removeChild( hiddenField );
            }
        } );

        /* ── Inicializar Select2 en el formulario de facturación ── */
        jQuery( document ).ready( function ( $ ) {
            $( '.select2-enable select' ).select2( {
                placeholder: 'Seleccione una opción',
                allowClear: false,
            } ).on( 'select2:opening', function () {
                $( this ).find( 'option[value=""]' ).attr( 'disabled', 'disabled' );
            } );
        } );
    }

    /* ── Validación RUT en tiempo real ── */
    var rutInput = document.querySelector( '#billing_rut' );
    if ( rutInput ) {
        rutInput.addEventListener( 'input', function () {
            var rut = rutInput.value.replace( /[^0-9kK]/g, '' );

            if ( rut.length !== 9 && rut.length !== 10 ) {
                rutInput.setCustomValidity( 'El RUT debe tener exactamente 9 o 10 caracteres incluyendo el guion.' );
            } else {
                rutInput.setCustomValidity( '' );
            }

            if ( rut.length > 1 ) {
                var cuerpo = rut.slice( 0, -1 );
                var dv     = rut.slice( -1 ).toUpperCase();
                rut        = cuerpo + '-' + dv;
            }
            rutInput.value = rut;
        } );

        var checkoutForm = document.querySelector( 'form.checkout' );
        if ( checkoutForm ) {
            checkoutForm.addEventListener( 'submit', function ( e ) {
                var rut = rutInput.value;
                if ( rut.length !== 9 && rut.length !== 10 ) {
                    e.preventDefault();
                    alert( 'El RUT debe tener exactamente 9 o 10 caracteres, incluyendo el guion (Ej: 18784666-8).' );
                    return false;
                }
            } );
        }
    }

    /* ── Popup de formato de dirección ── */
    var billingField = document.querySelector( '#billing_address_1' );
    if ( billingField ) {
        var popup           = document.createElement( 'div' );
        popup.id            = 'billing-popup';
        popup.textContent   = 'Formato de dirección: Calle-avenida-pasaje + número + Departamento(Opcional).';
        document.body.appendChild( popup );

        billingField.addEventListener( 'focus', function () {
            var rect        = billingField.getBoundingClientRect();
            popup.style.top  = ( window.scrollY + rect.top - 40 ) + 'px';
            popup.style.left = rect.left + 'px';
            popup.style.display = 'block';
        } );
        billingField.addEventListener( 'blur', function () {
            popup.style.display = 'none';
        } );
    }

    /* ── Mensaje dirección de retiro ── */
    function updateRetiroMessage() {
        var retiroOption = document.querySelector( "input[name^='shipping_method'][value='local_pickup:7']" );
        var retiroLabel  = document.querySelector( "label[for='shipping_method_0_local_pickup7']" );
        if ( ! retiroLabel ) return;

        if ( ! document.querySelector( '.retiro-message' ) ) {
            var messageLink       = document.createElement( 'a' );
            messageLink.className = 'retiro-message';
            messageLink.textContent = '📍 Leopoldo Urrutia 1860, Ñuñoa, RM';
            messageLink.href      = 'https://www.google.com/maps/search/?api=1&query=Leopoldo+Urrutia+1860,+%C3%91u%C3%B1oa,+RM';
            messageLink.target    = '_blank';
            retiroLabel.appendChild( messageLink );
        }

        var retiroMessage = document.querySelector( '.retiro-message' );
        if ( retiroOption && retiroOption.checked ) {
            retiroMessage.style.display = 'inline-block';
        } else {
            retiroMessage.style.display = 'none';
        }
    }

    updateRetiroMessage();

    jQuery( document.body ).on( 'updated_checkout', updateRetiroMessage );

    document.addEventListener( 'change', function ( e ) {
        if ( e.target.matches( "input[name^='shipping_method']" ) ) {
            updateRetiroMessage();
        }
    } );

} );
