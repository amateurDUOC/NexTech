/* PC Gamer Configurator — Agrupación visual del carrito */
( function () {
    'use strict';

    function init() {
        var mainRows = document.querySelectorAll( 'tr.pcgamer-main-item' );
        mainRows.forEach( setupGroup );
    }

    function setupGroup( mainRow ) {
        // Evitar doble inicialización en actualizaciones AJAX del carrito
        if ( mainRow.dataset.pcgamerReady === '1' ) return;
        mainRow.dataset.pcgamerReady = '1';

        // Obtener el key del PC principal desde su clase CSS
        var keyClass = Array.from( mainRow.classList ).find( function ( c ) {
            return c.startsWith( 'pcgamer-key-' );
        } );
        if ( ! keyClass ) return;

        var key    = keyClass.replace( 'pcgamer-key-', '' );
        var extras = Array.from( document.querySelectorAll( '.pcgamer-parent-' + key ) );
        if ( ! extras.length ) return;

        var count = extras.length;

        // Detectar dinámicamente el número de columnas reales de la tabla
        var colCount = mainRow.querySelectorAll( 'td' ).length || 6;

        // ── Botón toggle en la celda de nombre del PC ─────────────────────────
        var nameCell = mainRow.querySelector( '.product-name' );
        if ( ! nameCell ) return;

        var toggle = document.createElement( 'button' );
        toggle.className = 'pcgamer-toggle';
        toggle.type      = 'button';
        toggle.setAttribute( 'aria-expanded', 'true' );
        toggle.innerHTML =
            '<span class="pcgamer-toggle-icon">▾</span> Componentes incluidos (' + count + ')';
        nameCell.appendChild( toggle );

        // ── Fila cabecera azul antes del primer componente ────────────────────
        var separator = document.createElement( 'tr' );
        separator.className = 'pcgamer-extras-header pcgamer-separator-' + key;
        separator.innerHTML =
            '<td colspan="' + colCount + '" class="pcgamer-extras-label">' +
            '<span>⚙ Componentes del armado</span></td>';
        extras[ 0 ].parentNode.insertBefore( separator, extras[ 0 ] );

        // ── Fila de cierre con degradado después del último componente ────────
        extras[ extras.length - 1 ].classList.add( 'pcgamer-last-extra' );
        var closer = document.createElement( 'tr' );
        closer.className = 'pcgamer-extras-closer pcgamer-closer-' + key;
        closer.innerHTML = '<td colspan="' + colCount + '"></td>';
        extras[ extras.length - 1 ].insertAdjacentElement( 'afterend', closer );

        // ── Toggle de visibilidad ─────────────────────────────────────────────
        var allToggleable = [ separator ].concat( extras ).concat( [ closer ] );
        var isOpen        = true;

        toggle.addEventListener( 'click', function () {
            isOpen = ! isOpen;
            toggle.setAttribute( 'aria-expanded', String( isOpen ) );
            toggle.querySelector( '.pcgamer-toggle-icon' ).textContent = isOpen ? '▾' : '▸';
            allToggleable.forEach( function ( row ) {
                row.classList.toggle( 'pcgamer-hidden', ! isOpen );
            } );
        } );
    }

    // Inicializar al cargar y también tras actualizaciones AJAX del carrito
    document.addEventListener( 'DOMContentLoaded', init );
    document.body.addEventListener( 'updated_cart_totals', init );
    document.body.addEventListener( 'wc_fragments_loaded',  init );
} )();
