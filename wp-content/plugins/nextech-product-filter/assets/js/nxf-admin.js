/* global NxfAdmin, jQuery */
jQuery( function ( $ ) {
    'use strict';

    // ── State ─────────────────────────────────────────────────────────────────
    let currentSlug   = '';
    let currentConfig = [];   // array of attr items (same format as PHP attr_config)

    // ── Tab switching ─────────────────────────────────────────────────────────
    $( '.nxf-tabs .nav-tab' ).on( 'click', function ( e ) {
        e.preventDefault();
        const tab = $( this ).data( 'tab' );
        $( '.nxf-tabs .nav-tab' ).removeClass( 'nav-tab-active' );
        $( this ).addClass( 'nav-tab-active' );
        $( '.nxf-tab-content' ).hide();
        $( '#nxf-tab-' + tab ).show();
    } );

    // ── Category search ───────────────────────────────────────────────────────
    $( '#nxf-cat-search' ).on( 'input', function () {
        const q = $( this ).val().trim().toLowerCase();
        $( '#nxf-cat-list .nxf-cat-item' ).each( function () {
            const name = $( this ).data( 'name' ).toLowerCase();
            $( this ).toggle( name.includes( q ) );
        } );
    } );

    // ── Click on category ─────────────────────────────────────────────────────
    $( '#nxf-cat-list' ).on( 'click', '.nxf-cat-item', function () {
        const slug = $( this ).data( 'slug' );
        const name = $( this ).data( 'name' );
        if ( slug === currentSlug ) return;

        $( '.nxf-cat-item' ).removeClass( 'is-active' );
        $( this ).addClass( 'is-active' );
        loadCategory( slug, name );
    } );

    function loadCategory( slug, name ) {
        currentSlug = slug;

        $( '#nxf-empty-state' ).hide();
        $( '#nxf-panel' ).show();
        $( '#nxf-panel-title' ).text( name );
        $( '#nxf-panel-slug' ).text( slug );
        $( '#nxf-save-msg' ).text( '' ).removeClass( 'success error' );

        $.post( NxfAdmin.ajaxUrl, {
            action: 'nxf_load_category',
            nonce:  NxfAdmin.nonce,
            slug:   slug,
        }, function ( res ) {
            if ( ! res.success ) return;
            currentConfig = res.data.config || [];

            // Source badge
            const badges = {
                admin: [ '✅ Guardado en Admin',       'from-admin' ],
                php:   [ '📄 Config PHP (editable)', 'from-php'   ],
                none:  [ '➕ Sin configuración',  'from-none'  ],
            };
            const [ label, cls ] = badges[ res.data.source ] || badges.none;
            $( '#nxf-source-badge' ).text( label ).attr( 'class', 'nxf-source-badge ' + cls );

            renderAttrList();
        } );
    }

    // ── Render attribute list ─────────────────────────────────────────────────
    function renderAttrList() {
        const $list = $( '#nxf-attrs-list' );
        $list.empty();

        if ( ! currentConfig.length ) {
            $list.append( '<li class="nxf-no-attrs" id="nxf-no-attrs">Sin filtros configurados — agrega uno abajo.</li>' );
            return;
        }

        currentConfig.forEach( ( item, idx ) => {
            $list.append( buildAttrItem( item, idx ) );
        } );

        // Make sortable
        $list.sortable( {
            handle:      '.nxf-drag-handle',
            placeholder: 'nxf-sortable-placeholder',
            update: function () {
                const newOrder = [];
                $list.children( '.nxf-attr-item' ).each( function () {
                    const i = parseInt( $( this ).data( 'index' ), 10 );
                    newOrder.push( currentConfig[ i ] );
                } );
                currentConfig = newOrder;
                renderAttrList();
            },
        } );
    }

    function buildAttrItem( item, idx ) {
        const isGroup = !! item.grupo;
        const $li = $( '<li>' )
            .addClass( 'nxf-attr-item' + ( isGroup ? ' is-group' : '' ) )
            .attr( 'data-index', idx );

        // Drag handle
        $li.append( '<span class="nxf-drag-handle" title="Arrastra para reordenar">⠇</span>' );

        // Info
        const $info = $( '<div class="nxf-attr-info">' );
        let nombreHtml = '<div class="nxf-attr-nombre">' + escHtml( item.nombre );
        if ( isGroup ) nombreHtml += ' <span class="nxf-attr-group-badge">GRUPO</span>';
        nombreHtml += '</div>';
        $info.append( nombreHtml );

        if ( isGroup && item.hijos && item.hijos.length ) {
            const childNames = item.hijos.map( h => escHtml( h.nombre ) ).join( ', ' );
            $info.append( '<div class="nxf-attr-children">→ ' + childNames + '</div>' );
        } else if ( ! isGroup ) {
            $info.append( '<div class="nxf-attr-slug">' + escHtml( item.slug ) + '</div>' );
        }
        $li.append( $info );

        // Remove button
        const $rm = $( '<button class="button nxf-btn-remove-attr" type="button">✕</button>' );
        $rm.on( 'click', function () {
            currentConfig.splice( idx, 1 );
            renderAttrList();
        } );
        $li.append( $rm );

        return $li;
    }

    // ── Populate attribute selects ────────────────────────────────────────────
    function buildAttrOptions( $select ) {
        $select.empty().append( '<option value="">— Atributo —</option>' );
        NxfAdmin.attributes.forEach( a => {
            $select.append(
                '<option value="' + escAttr( a.slug ) + '">' +
                escHtml( a.nombre ) + ' (' + escHtml( a.slug ) + ')' +
                '</option>'
            );
        } );
    }

    // Populate #nxf-new-slug
    buildAttrOptions( $( '#nxf-new-slug' ) );

    // Auto-fill nombre when slug selected
    $( '#nxf-new-slug' ).on( 'change', function () {
        const slug  = $( this ).val();
        const match = NxfAdmin.attributes.find( a => a.slug === slug );
        if ( match && ! $( '#nxf-new-nombre' ).val() ) {
            $( '#nxf-new-nombre' ).val( match.nombre );
        }
    } );

    // ── Add simple attribute ──────────────────────────────────────────────────
    $( '#nxf-btn-add-simple' ).on( 'click', function () {
        const slug   = $( '#nxf-new-slug' ).val().trim();
        const nombre = $( '#nxf-new-nombre' ).val().trim();
        if ( ! slug || ! nombre ) {
            alert( 'Selecciona un atributo e ingresa un nombre visible.' );
            return;
        }

        currentConfig.push( { nombre: nombre, slug: slug, grupo: false, hijos: [] } );
        renderAttrList();
        $( '#nxf-new-slug' ).val( '' );
        $( '#nxf-new-nombre' ).val( '' );
    } );

    // ── Group builder — add child row ─────────────────────────────────────────
    function addChildRow() {
        const $row    = $( '<div class="nxf-child-row">' );
        const $sel    = $( '<select class="nxf-select nxf-child-slug">' );
        buildAttrOptions( $sel );
        const $nombre = $( '<input type="text" class="nxf-input nxf-child-nombre" placeholder="Nombre visible">' );
        const $rm     = $( '<button class="button" type="button">✕</button>' );
        $rm.on( 'click', function () { $row.remove(); } );

        // Auto-fill nombre
        $sel.on( 'change', function () {
            const match = NxfAdmin.attributes.find( a => a.slug === $sel.val() );
            if ( match && ! $nombre.val() ) $nombre.val( match.nombre );
        } );

        $row.append( $sel, $nombre, $rm );
        $( '#nxf-group-children' ).append( $row );
    }

    addChildRow(); // Start with one child row

    $( '#nxf-btn-add-child' ).on( 'click', addChildRow );

    $( '#nxf-btn-add-group' ).on( 'click', function () {
        const nombre = $( '#nxf-group-nombre' ).val().trim();
        if ( ! nombre ) {
            alert( 'Ingresa el nombre del grupo.' );
            return;
        }

        const hijos = [];
        $( '#nxf-group-children .nxf-child-row' ).each( function () {
            const s = $( this ).find( '.nxf-child-slug' ).val();
            const n = $( this ).find( '.nxf-child-nombre' ).val().trim();
            if ( s && n ) hijos.push( { nombre: n, slug: s } );
        } );

        if ( ! hijos.length ) {
            alert( 'Agrega al menos un sub-atributo al grupo.' );
            return;
        }

        currentConfig.push( {
            nombre: nombre,
            slug:   '_grupo_' + nombre.toLowerCase().replace( /[^a-z0-9]/g, '-' ),
            grupo:  true,
            hijos:  hijos,
        } );
        renderAttrList();

        // Reset group builder
        $( '#nxf-group-nombre' ).val( '' );
        $( '#nxf-group-children' ).empty();
        addChildRow();
    } );

    // ── Save ──────────────────────────────────────────────────────────────────
    $( '#nxf-btn-save' ).on( 'click', function () {
        if ( ! currentSlug ) return;
        const $msg = $( '#nxf-save-msg' ).text( 'Guardando...' ).removeClass( 'success error' );

        $.post( NxfAdmin.ajaxUrl, {
            action: 'nxf_save_category',
            nonce:  NxfAdmin.nonce,
            slug:   currentSlug,
            config: JSON.stringify( currentConfig ),
        }, function ( res ) {
            if ( res.success ) {
                $msg.text( '✅ Guardado correctamente' ).addClass( 'success' );
                // Mark as configured in sidebar
                $( '.nxf-cat-item[data-slug="' + currentSlug + '"]' )
                    .addClass( 'is-configured' )
                    .find( '.nxf-configured-badge' ).show();
                $( '#nxf-source-badge' ).text( '✅ Guardado en Admin' ).attr( 'class', 'nxf-source-badge from-admin' );
                setTimeout( function () {
                    $msg.text( '' ).removeClass( 'success' );
                }, 3000 );
            } else {
                $msg.text( '❌ Error al guardar' ).addClass( 'error' );
            }
        } );
    } );

    // ── Delete config ─────────────────────────────────────────────────────────
    $( '#nxf-btn-delete' ).on( 'click', function () {
        if ( ! currentSlug ) return;
        if ( ! confirm( '¿Eliminar la configuración de admin para esta categoría?\n\nVolverá a usar la configuración PHP.' ) ) return;

        $.post( NxfAdmin.ajaxUrl, {
            action: 'nxf_delete_category',
            nonce:  NxfAdmin.nonce,
            slug:   currentSlug,
        }, function ( res ) {
            if ( res.success ) {
                $( '.nxf-cat-item[data-slug="' + currentSlug + '"]' ).removeClass( 'is-configured' );
                loadCategory( currentSlug, $( '#nxf-panel-title' ).text() );
            }
        } );
    } );

    // ── Helpers ───────────────────────────────────────────────────────────────
    function escHtml( str ) {
        return String( str ).replace( /[&<>"']/g, function ( c ) {
            return ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ c ];
        } );
    }

    function escAttr( str ) {
        return escHtml( str );
    }
} );
