/* ── Nextech — Footer: star review + panels colapsables ─────────────────── */

document.addEventListener( 'DOMContentLoaded', function () {

    /* ── Ícono estrella de calificaciones (SoloTodo) ── */
    var container = document.querySelector(
        '.header-social-icons, .follow-icons, [class*="social-icons"], [class*="follow"]'
    );
    if ( container ) {
        var link       = document.createElement( 'a' );
        link.href      = 'https://www.solotodo.cl/stores/2009/ratings';
        link.target    = '_blank';
        link.rel       = 'noopener';
        link.title     = 'Ver nuestras calificaciones';
        link.className = 'nextech-star-review';
        link.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"' +
            ' fill="#ffffff" stroke="#ffffff" stroke-width="0.3" stroke-linejoin="round">' +
            '<polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>' +
            '</svg>';
        container.appendChild( link );
    }

    /* ── Panels colapsables del footer ── */
    var allBtns   = document.querySelectorAll( '.nextech-footer-toggle' );
    var allPanels = document.querySelectorAll( '.nextech-panel-body' );

    allBtns.forEach( function ( btn ) {
        btn.addEventListener( 'click', function ( e ) {
            e.stopPropagation();
            var targetId = btn.dataset.target;
            var panel    = document.getElementById( targetId );
            if ( ! panel ) return;

            var isOpen = panel.classList.contains( 'open' );

            allPanels.forEach( function ( p ) { p.classList.remove( 'open' ); } );
            allBtns.forEach( function ( b ) {
                b.classList.remove( 'open' );
                b.setAttribute( 'aria-expanded', 'false' );
            } );

            if ( ! isOpen ) {
                panel.classList.add( 'open' );
                btn.classList.add( 'open' );
                btn.setAttribute( 'aria-expanded', 'true' );
            }
        } );
    } );

    document.addEventListener( 'click', function ( e ) {
        if ( ! e.target.closest( '.nextech-panel-wrap' ) ) {
            allPanels.forEach( function ( p ) { p.classList.remove( 'open' ); } );
            allBtns.forEach( function ( b ) {
                b.classList.remove( 'open' );
                b.setAttribute( 'aria-expanded', 'false' );
            } );
        }
    } );

} );
