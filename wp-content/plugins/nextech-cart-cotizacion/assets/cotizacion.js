/* Nextech Cart Cotización — Modal + ventana de impresión */
( function () {
    'use strict';

    var overlay   = document.getElementById( 'ncc-overlay' );
    var form      = document.getElementById( 'ncc-form' );
    var btnAbrir  = document.getElementById( 'ncc-btn-cotizacion' );
    var btnCerrar = document.getElementById( 'ncc-close' );
    var btnCancel = document.getElementById( 'ncc-cancel' );
    var btnEnviar = document.getElementById( 'ncc-submit' );
    var errGlobal = document.getElementById( 'ncc-error-global' );
    var inputNom  = document.getElementById( 'ncc-nombre' );

    if ( ! overlay || ! form || ! btnAbrir ) return;

    // ── Abrir / cerrar modal ──────────────────────────────────────────────────
    function abrir() {
        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
        inputNom.focus();
    }

    function cerrar() {
        overlay.hidden = true;
        document.body.style.overflow = '';
        ocultarError();
    }

    btnAbrir.addEventListener(  'click', abrir );
    btnCerrar.addEventListener( 'click', cerrar );
    btnCancel.addEventListener( 'click', cerrar );

    overlay.addEventListener( 'click', function ( e ) {
        if ( e.target === overlay ) cerrar();
    } );

    document.addEventListener( 'keydown', function ( e ) {
        if ( e.key === 'Escape' && ! overlay.hidden ) cerrar();
    } );

    // ── Validación ────────────────────────────────────────────────────────────
    function validar() {
        var nombre = inputNom.value.trim();
        var fieldErr = inputNom.closest( '.ncc-field' ).querySelector( '.ncc-field-error' );

        if ( ! nombre ) {
            inputNom.classList.add( 'ncc-invalid' );
            if ( fieldErr ) fieldErr.hidden = false;
            mostrarError( NCC.i18n.sin_nombre );
            return false;
        }

        inputNom.classList.remove( 'ncc-invalid' );
        if ( fieldErr ) fieldErr.hidden = true;
        return true;
    }

    inputNom.addEventListener( 'input', function () {
        if ( inputNom.classList.contains( 'ncc-invalid' ) ) validar();
    } );

    // ── Manejo de errores ─────────────────────────────────────────────────────
    function mostrarError( msg ) {
        errGlobal.textContent = msg;
        errGlobal.hidden = false;
    }

    function ocultarError() {
        errGlobal.hidden = true;
        errGlobal.textContent = '';
    }

    // ── Estado de carga ────────────────────────────────────────────────────────
    function setLoading( on ) {
        btnEnviar.disabled = on;
        btnEnviar.querySelector( '.ncc-submit-text' ).hidden    = on;
        btnEnviar.querySelector( '.ncc-submit-loading' ).hidden = ! on;
    }

    // ── Envío ─────────────────────────────────────────────────────────────────
    form.addEventListener( 'submit', function ( e ) {
        e.preventDefault();
        ocultarError();
        if ( ! validar() ) return;

        var data = new FormData( form );
        data.append( 'action', 'ncc_generar_pdf' );
        data.append( 'nonce',  NCC.nonce );

        setLoading( true );

        // Abrir la ventana ANTES del fetch (vinculado al click → no bloqueada por el navegador)
        var win = window.open( '', '_blank', 'width=900,height=700' );

        fetch( NCC.ajaxUrl, { method: 'POST', body: data } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( json ) {
                setLoading( false );

                if ( ! json.success ) {
                    if ( win ) win.close();
                    mostrarError( ( json.data && json.data.message ) || NCC.i18n.error );
                    return;
                }

                cerrar();

                var blob = new Blob( [ json.data.html ], { type: 'text/html;charset=utf-8' } );
                var url  = URL.createObjectURL( blob );
                if ( win ) {
                    win.location.href = url;
                } else {
                    window.open( url, '_blank' );
                }
                setTimeout( function () { URL.revokeObjectURL( url ); }, 30000 );
            } )
            .catch( function () {
                setLoading( false );
                if ( win ) win.close();
                mostrarError( NCC.i18n.error );
            } );
    } );

} )();
