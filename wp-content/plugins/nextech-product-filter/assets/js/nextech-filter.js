/* global NxtFilter */
( function () {
    'use strict';

    // Categoría de la página actual — viene de PHP, solo lectura
    const contexto = ( NxtFilter.categoriaActual || '' ).trim();

    // ── Estado ────────────────────────────────────────────────────────────────
    const state = {
        categoria:  '',
        marca:      '',
        min_precio: 0,
        max_precio: 0,
        pagina:     1,
        atributos:  {},   // { 'pa_procesador': 'amd-ryzen-5', 'pa_vram': '8gb' }
    };

    // Datos de atributos cargados desde /filtros (para labels en chips)
    let atributoData = [];

    let debounceTimer = null;
    let abortCtrl     = null;

    // ── Helpers DOM ───────────────────────────────────────────────────────────
    const $ = ( sel, ctx = document ) => ctx.querySelector( sel );

    function grid() {
        return $( '.nextech-product-grid' )
            || $( '.products.woocommerce-products-grid' )
            || $( 'ul.products' );
    }

    function esc( str ) {
        return String( str ).replace( /[&<>"']/g, c => (
            { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]
        ) );
    }

    // Sanitiza slug para usarlo como ID de elemento DOM
    function nxfId( slug ) {
        return ( slug || '' ).replace( /[^a-z0-9]/gi, '-' ).toLowerCase();
    }

    // ── Inicialización ────────────────────────────────────────────────────────
    function init() {
        if ( ! $( '#nextech-filter' ) ) return;

        readUrlParams();
        loadFilterOptions();
        bindControls();
        bindAccordions();

        if ( hasActiveFilters() ) fetchProducts();
    }

    // ── URL params ────────────────────────────────────────────────────────────
    function readUrlParams() {
        const p = new URLSearchParams( window.location.search );

        for ( const key of [ 'categoria', 'marca', 'pagina', 'min_precio', 'max_precio' ] ) {
            if ( ! p.has( key ) ) continue;
            const val = p.get( key );
            state[ key ] = [ 'min_precio', 'max_precio', 'pagina' ].includes( key )
                ? ( parseInt( val, 10 ) || 0 )
                : val;
        }

        // Atributos pa_*
        for ( const [ key, val ] of p.entries() ) {
            if ( key.startsWith( 'pa_' ) && val ) {
                state.atributos[ key ] = val;
            }
        }
    }

    function writeUrlParams() {
        const p = new URLSearchParams();

        if ( state.categoria   ) p.set( 'categoria',  state.categoria );
        if ( state.marca       ) p.set( 'marca',       state.marca );
        if ( state.min_precio  ) p.set( 'min_precio',  state.min_precio );
        if ( state.max_precio  ) p.set( 'max_precio',  state.max_precio );
        if ( state.pagina > 1  ) p.set( 'pagina',      state.pagina );

        for ( const [ slug, val ] of Object.entries( state.atributos ) ) {
            if ( val ) p.set( slug, val );
        }

        const qs = p.toString();
        history.pushState( { ...state }, '', qs ? '?' + qs : window.location.pathname );
    }

    function hasActiveFilters() {
        return state.categoria !== '' || state.marca !== ''
            || state.min_precio !== 0 || state.max_precio !== 0
            || Object.values( state.atributos ).some( v => v !== '' );
    }

    // ── Acordeón (delegado — captura acordeones dinámicos también) ────────────
    function bindAccordions() {
        $( '#nextech-filter' ).addEventListener( 'click', e => {
            const btn = e.target.closest( '.nxf-accordion-header' );
            if ( ! btn ) return;
            const accordion = btn.closest( '.nxf-accordion' );
            const isOpen    = accordion.dataset.open === 'true';
            accordion.dataset.open = isOpen ? 'false' : 'true';
            btn.setAttribute( 'aria-expanded', String( ! isOpen ) );
        } );
    }

    // ── Carga opciones desde /filtros ─────────────────────────────────────────
    async function loadFilterOptions() {
        try {
            const qs  = contexto ? `?contexto=${ encodeURIComponent( contexto ) }` : '';
            const res = await fetch( NxtFilter.apiUrl + '/filtros' + qs, {
                headers: { 'X-WP-Nonce': NxtFilter.nonce },
            } );
            if ( ! res.ok ) return;
            const data = await res.json();

            buildMarcas( data.marcas     || [] );
            buildCategorias( data.categorias || [] );
            buildAtributos( data.atributos   || [] );
            restoreFromState();

        } catch ( e ) {
            console.warn( '[NxtFilter] No se pudo cargar /filtros:', e );
        }
    }

    // ── Marcas ────────────────────────────────────────────────────────────────
    function buildMarcas( marcas ) {
        const list    = $( '#nxf-marcas-list' );
        const section = $( '#nxf-marcas-section' );
        if ( ! list || ! marcas.length ) return;

        section.removeAttribute( 'hidden' );

        list.innerHTML = marcas.map( m => `
            <label class="nxf-term">
                <input type="checkbox" name="marca" value="${ esc( m.slug ) }" />
                <span class="nxf-term-name">${ esc( m.nombre ) }</span>
                <span class="nxf-term-count">${ m.count }</span>
            </label>
        ` ).join( '' );

        list.querySelectorAll( 'input[type=checkbox]' ).forEach( cb =>
            cb.addEventListener( 'change', () => onTermChange( 'marca' ) )
        );

        const search = $( '#nxf-marcas-search' );
        if ( search ) {
            search.addEventListener( 'input', () => {
                const q = search.value.trim().toLowerCase();
                list.querySelectorAll( '.nxf-term' ).forEach( l => {
                    const name = l.querySelector( '.nxf-term-name' )?.textContent.toLowerCase() || '';
                    l.style.display = name.includes( q ) ? '' : 'none';
                } );
            } );
        }
    }

    // ── Categorías ────────────────────────────────────────────────────────────
    function buildCategorias( categorias ) {
        const list    = $( '#nxf-categorias-list' );
        const section = $( '#nxf-categorias-section' );
        if ( ! list || ! categorias.length ) return;

        section.removeAttribute( 'hidden' );

        const esSiblings = contexto && categorias.every( c => ! c.hijos?.length );
        if ( esSiblings ) {
            const titulo = section.querySelector( '.nxf-accordion-header span' );
            if ( titulo ) titulo.textContent = 'Otras categorías';
        }

        list.innerHTML = categorias.map( cat => `
            <div class="nxf-term-group">
                <label class="nxf-term">
                    <input type="checkbox" name="categoria" value="${ esc( cat.slug ) }" />
                    <span class="nxf-term-name">${ esc( cat.nombre ) }</span>
                    <span class="nxf-term-count">${ cat.count }</span>
                </label>
                ${ cat.hijos?.length ? `
                    <div class="nxf-term-children">
                        ${ cat.hijos.map( h => `
                            <label class="nxf-term nxf-term--child">
                                <input type="checkbox" name="categoria" value="${ esc( h.slug ) }" />
                                <span class="nxf-term-name">${ esc( h.nombre ) }</span>
                                <span class="nxf-term-count">${ h.count }</span>
                            </label>
                        ` ).join( '' ) }
                    </div>
                ` : '' }
            </div>
        ` ).join( '' );

        list.querySelectorAll( 'input[type=checkbox]' ).forEach( cb =>
            cb.addEventListener( 'change', () => onTermChange( 'categoria' ) )
        );
    }

    // ── Atributos ─────────────────────────────────────────────────────────────
    // Cada ítem puede ser:
    //   hijos = []  → atributo simple (acordeón directo con checkboxes)
    //   hijos ≠ []  → grupo (acordeón padre con sub-acordeones anidados)

    function buildAtributos( atributos ) {
        if ( ! atributos.length ) return;
        atributoData = atributos;
        const filter   = $( '#nextech-filter' );
        const resetBtn = $( '#nxf-reset' );
        atributos.forEach( attr => {
            if ( attr.hijos && attr.hijos.length ) {
                buildAtributoGrupo( attr, filter, resetBtn );
            } else {
                buildAtributoSimple( attr, filter, resetBtn );
            }
        } );
    }

    /* Acordeón padre que agrupa varios sub-atributos */
    function buildAtributoGrupo( grupo, filter, resetBtn ) {
        const id = 'nxf-grupo-' + nxfId( grupo.slug );
        if ( $( '#' + id ) ) return;

        const section = document.createElement( 'div' );
        section.className    = 'nxf-accordion nxf-attr-grupo';
        section.id           = id;
        section.dataset.open = 'true';

        const hijosHtml = grupo.hijos.map( hijo => {
            const hid = 'nxf-attr-' + nxfId( hijo.slug );
            return `
                <div class="nxf-accordion nxf-attr-hijo" id="${ hid }" data-open="true">
                    <button class="nxf-accordion-header nxf-accordion-header--sub" type="button" aria-expanded="true">
                        <span>${ esc( hijo.nombre ) }</span>
                        <svg class="nxf-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M0 0l5 6 5-6z"/></svg>
                    </button>
                    <div class="nxf-accordion-body">
                        <div class="nxf-term-list">
                            ${ hijo.valores.map( v => `
                                <label class="nxf-term">
                                    <input type="checkbox" name="${ esc( hijo.slug ) }" value="${ esc( v.slug ) }" data-tax="${ esc( hijo.slug ) }" />
                                    <span class="nxf-term-name">${ esc( v.nombre ) }</span>
                                    <span class="nxf-term-count">${ v.count }</span>
                                </label>` ).join( '' ) }
                        </div>
                    </div>
                </div>`;
        } ).join( '' );

        section.innerHTML = `
            <button class="nxf-accordion-header" type="button" aria-expanded="true">
                <span>${ esc( grupo.nombre ) }</span>
                <svg class="nxf-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M0 0l5 6 5-6z"/></svg>
            </button>
            <div class="nxf-accordion-body nxf-accordion-body--grupo">
                ${ hijosHtml }
            </div>`;

        if ( resetBtn ) filter.insertBefore( section, resetBtn );
        else filter.appendChild( section );

        grupo.hijos.forEach( hijo =>
            section.querySelectorAll( `input[name="${ hijo.slug }"]` )
                .forEach( cb => cb.addEventListener( 'change', () => onAttrChange( hijo.slug ) ) )
        );
    }

    /* Acordeón simple (atributo sin agrupación) */
    function buildAtributoSimple( attr, filter, resetBtn ) {
        const id = 'nxf-attr-' + nxfId( attr.slug );
        if ( $( '#' + id ) ) return;

        const section = document.createElement( 'div' );
        section.className       = 'nxf-accordion nxf-attr-section';
        section.id              = id;
        section.dataset.open    = 'true';
        section.dataset.taxSlug = attr.slug;

        section.innerHTML = `
            <button class="nxf-accordion-header" type="button" aria-expanded="true">
                <span>${ esc( attr.nombre ) }</span>
                <svg class="nxf-chevron" viewBox="0 0 10 6" aria-hidden="true"><path d="M0 0l5 6 5-6z"/></svg>
            </button>
            <div class="nxf-accordion-body">
                <div class="nxf-term-list" id="${ id }-list">
                    ${ attr.valores.map( v => `
                        <label class="nxf-term">
                            <input type="checkbox" name="${ esc( attr.slug ) }" value="${ esc( v.slug ) }" data-tax="${ esc( attr.slug ) }" />
                            <span class="nxf-term-name">${ esc( v.nombre ) }</span>
                            <span class="nxf-term-count">${ v.count }</span>
                        </label>` ).join( '' ) }
                </div>
            </div>`;

        if ( resetBtn ) filter.insertBefore( section, resetBtn );
        else filter.appendChild( section );

        section.querySelectorAll( 'input[type=checkbox]' )
            .forEach( cb => cb.addEventListener( 'change', () => onAttrChange( attr.slug ) ) );
    }

    /* Busca la definición de un atributo por slug — soporta grupos anidados */
    function findAttrDef( slug ) {
        for ( const item of atributoData ) {
            if ( item.hijos && item.hijos.length ) {
                const hijo = item.hijos.find( h => h.slug === slug );
                if ( hijo ) return hijo;
            } else if ( item.slug === slug ) {
                return item;
            }
        }
        return null;
    }

    // ── Restaurar estado visual desde state ───────────────────────────────────
    function restoreFromState() {
        const marcasActivas = state.marca     ? state.marca.split( ',' )     : [];
        const catsActivas   = state.categoria ? state.categoria.split( ',' ) : [];

        document.querySelectorAll( 'input[name=marca]' ).forEach(
            cb => ( cb.checked = marcasActivas.includes( cb.value ) )
        );
        document.querySelectorAll( 'input[name=categoria]' ).forEach(
            cb => ( cb.checked = catsActivas.includes( cb.value ) )
        );

        for ( const [ slug, val ] of Object.entries( state.atributos ) ) {
            if ( ! val ) continue;
            const activos = val.split( ',' );
            document.querySelectorAll( `input[name="${ slug }"]` ).forEach(
                cb => ( cb.checked = activos.includes( cb.value ) )
            );
        }

        const minEl = $( '#nxf-min-precio' );
        const maxEl = $( '#nxf-max-precio' );
        if ( minEl ) minEl.value = state.min_precio || '';
        if ( maxEl ) maxEl.value = state.max_precio || '';

        renderActiveChips();
    }

    // ── Chips de filtros activos ──────────────────────────────────────────────
    function renderActiveChips() {
        const container = $( '#nxf-active-filters' );
        const resetBtn  = $( '#nxf-reset' );
        if ( ! container ) return;

        const chips = [];

        if ( state.marca ) {
            state.marca.split( ',' ).forEach( slug => {
                const label = document.querySelector( `input[name=marca][value="${ slug }"]` )
                    ?.closest( '.nxf-term' )?.querySelector( '.nxf-term-name' )?.textContent || slug;
                chips.push( { key: 'marca', value: slug, label: `Marca: ${ label }` } );
            } );
        }

        if ( state.categoria ) {
            state.categoria.split( ',' ).forEach( slug => {
                const label = document.querySelector( `input[name=categoria][value="${ slug }"]` )
                    ?.closest( '.nxf-term' )?.querySelector( '.nxf-term-name' )?.textContent || slug;
                chips.push( { key: 'categoria', value: slug, label: `Cat: ${ label }` } );
            } );
        }

        // Chips de atributos pa_* — busca label en grupos y atributos simples
        for ( const [ slug, val ] of Object.entries( state.atributos ) ) {
            if ( ! val ) continue;
            const attrDef   = findAttrDef( slug );
            const attrLabel = attrDef ? attrDef.nombre : slug.replace( 'pa_', '' );

            val.split( ',' ).forEach( termSlug => {
                const termLabel = attrDef?.valores?.find( v => v.slug === termSlug )?.nombre || termSlug;
                chips.push( { key: slug, value: termSlug, label: `${ attrLabel }: ${ termLabel }` } );
            } );
        }

        if ( state.min_precio ) chips.push( { key: 'min_precio', value: state.min_precio, label: `Desde ${ formatCLP( state.min_precio ) }` } );
        if ( state.max_precio ) chips.push( { key: 'max_precio', value: state.max_precio, label: `Hasta ${ formatCLP( state.max_precio ) }` } );

        const hasChips = chips.length > 0;
        container.hidden = ! hasChips;
        if ( resetBtn ) resetBtn.hidden = ! hasChips;

        container.innerHTML = chips.map( c => `
            <button class="nxf-chip" type="button"
                    data-chip-key="${ esc( c.key ) }"
                    data-chip-value="${ esc( String( c.value ) ) }"
                    aria-label="Quitar: ${ esc( c.label ) }">
                ${ esc( c.label ) } <span class="nxf-chip-x" aria-hidden="true">×</span>
            </button>
        ` ).join( '' );
    }

    // ── Binding de controles ──────────────────────────────────────────────────
    function bindControls() {
        [ [ '#nxf-min-precio', 'min_precio' ], [ '#nxf-max-precio', 'max_precio' ] ].forEach(
            ( [ sel, key ] ) => {
                const el = $( sel );
                if ( ! el ) return;
                el.addEventListener( 'input', () => {
                    state[ key ] = parseInt( el.value, 10 ) || 0;
                    state.pagina = 1;
                    clearTimeout( debounceTimer );
                    debounceTimer = setTimeout( triggerFetch, 700 );
                } );
            }
        );

        $( '#nxf-active-filters' )?.addEventListener( 'click', e => {
            const chip = e.target.closest( '.nxf-chip' );
            if ( ! chip ) return;
            removeChipFilter( chip.dataset.chipKey, chip.dataset.chipValue );
        } );

        $( '#nxf-reset' )?.addEventListener( 'click', resetFilters );

        document.addEventListener( 'click', e => {
            const btn = e.target.closest( '[data-nxf-page]' );
            if ( ! btn ) return;
            e.preventDefault();
            state.pagina = parseInt( btn.dataset.nxfPage, 10 );
            triggerFetch();
            grid()?.scrollIntoView( { behavior: 'smooth', block: 'start' } );
        } );

        window.addEventListener( 'popstate', e => {
            if ( e.state ) Object.assign( state, e.state );
            restoreFromState();
            fetchProducts();
        } );
    }

    function onTermChange( filterKey ) {
        const checked = [ ...document.querySelectorAll( `input[name=${ filterKey }]:checked` ) ]
            .map( cb => cb.value );
        state[ filterKey ] = checked.join( ',' );
        state.pagina       = 1;
        triggerFetch();
    }

    function onAttrChange( taxSlug ) {
        const checked = [ ...document.querySelectorAll( `input[name="${ taxSlug }"]:checked` ) ]
            .map( cb => cb.value );
        if ( checked.length ) {
            state.atributos[ taxSlug ] = checked.join( ',' );
        } else {
            delete state.atributos[ taxSlug ];
        }
        state.pagina = 1;
        triggerFetch();
    }

    function removeChipFilter( key, value ) {
        if ( key === 'min_precio' || key === 'max_precio' ) {
            state[ key ] = 0;
            const el = $( key === 'min_precio' ? '#nxf-min-precio' : '#nxf-max-precio' );
            if ( el ) el.value = '';
        } else if ( key.startsWith( 'pa_' ) ) {
            const current = ( state.atributos[ key ] || '' ).split( ',' ).filter( v => v !== value );
            if ( current.length ) {
                state.atributos[ key ] = current.join( ',' );
            } else {
                delete state.atributos[ key ];
            }
            const cb = document.querySelector( `input[name="${ key }"][value="${ value }"]` );
            if ( cb ) cb.checked = false;
        } else {
            const cb = document.querySelector( `input[name=${ key }][value="${ value }"]` );
            if ( cb ) cb.checked = false;
            const values = [ ...document.querySelectorAll( `input[name=${ key }]:checked` ) ].map( c => c.value );
            state[ key ] = values.join( ',' );
        }
        state.pagina = 1;
        triggerFetch();
    }

    // ── Fetch ─────────────────────────────────────────────────────────────────
    function triggerFetch() {
        writeUrlParams();
        renderActiveChips();
        fetchProducts();
    }

    async function fetchProducts() {
        const g = grid();
        if ( ! g ) return;

        if ( abortCtrl ) abortCtrl.abort();
        abortCtrl = new AbortController();

        setLoading( true );

        const categoriaParam = state.categoria || contexto;

        const params = new URLSearchParams( {
            categoria:  categoriaParam,
            marca:      state.marca,
            min_precio: state.min_precio,
            max_precio: state.max_precio,
            en_stock:   '1',
            pagina:     state.pagina,
        } );

        for ( const [ slug, val ] of Object.entries( state.atributos ) ) {
            if ( val ) params.set( slug, val );
        }

        try {
            const res = await fetch( `${ NxtFilter.apiUrl }/productos?${ params }`, {
                signal:  abortCtrl.signal,
                headers: { 'X-WP-Nonce': NxtFilter.nonce },
            } );
            if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
            const data = await res.json();
            renderGrid( data );
            renderPagination( data );
            renderResultCount( data.total );
        } catch ( err ) {
            if ( err.name !== 'AbortError' ) {
                showError();
                console.error( '[NxtFilter]', err );
            }
        } finally {
            setLoading( false );
        }
    }

    // ── Render ────────────────────────────────────────────────────────────────
    function renderGrid( data ) {
        const g = grid();
        if ( ! g ) return;

        if ( ! data.html || ! data.total ) {
            g.innerHTML = `<p class="nxf-no-results">${ NxtFilter.i18n.sin_resultados }</p>`;
            return;
        }

        g.innerHTML = data.html;
        document.dispatchEvent( new CustomEvent( 'nextech_grid_updated', { detail: { data, grid: g } } ) );
    }

    function renderPagination( data ) {
        let pag = $( '#nxf-pagination' );
        if ( ! pag ) {
            pag = document.createElement( 'nav' );
            pag.id        = 'nxf-pagination';
            pag.className = 'nxf-pagination';
            grid()?.after( pag );
        }

        if ( ! data.paginas || data.paginas <= 1 ) { pag.innerHTML = ''; return; }

        const current = data.pagina_actual;
        const total   = data.paginas;

        pag.innerHTML = [
            current > 1 ? `<button class="nxf-page-btn nxf-page-nav" data-nxf-page="${ current - 1 }" type="button">← Anterior</button>` : '',
            ...buildPageRange( current, total ).map( p =>
                p === '…'
                    ? `<span class="nxf-page-ellipsis">…</span>`
                    : `<button class="nxf-page-btn${ p === current ? ' is-active' : '' }" data-nxf-page="${ p }" type="button" ${ p === current ? 'aria-current="page"' : '' }>${ p }</button>`
            ),
            current < total ? `<button class="nxf-page-btn nxf-page-nav" data-nxf-page="${ current + 1 }" type="button">Siguiente →</button>` : '',
        ].join( '' );
    }

    function buildPageRange( current, total ) {
        const delta = 2;
        const result = [];
        let last;
        for ( let i = 1; i <= total; i++ ) {
            if ( i === 1 || i === total || ( i >= current - delta && i <= current + delta ) ) {
                if ( last !== undefined && i - last > 1 ) result.push( '…' );
                result.push( i );
                last = i;
            }
        }
        return result;
    }

    function renderResultCount( total ) {
        let counter = $( '#nxf-result-count' );
        if ( ! counter ) {
            counter = document.createElement( 'p' );
            counter.id        = 'nxf-result-count';
            counter.className = 'nxf-result-count woocommerce-result-count';
            grid()?.before( counter );
        }
        counter.textContent = total === 1 ? '1 producto encontrado' : `${ total } productos encontrados`;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function setLoading( on ) {
        $( '#nextech-filter' )?.setAttribute( 'data-loading', on ? 'true' : 'false' );
        grid()?.classList.toggle( 'nxf-grid-loading', on );
    }

    function showError() {
        const g = grid();
        if ( g ) g.innerHTML = `<p class="nxf-error">${ NxtFilter.i18n.error }</p>`;
    }

    function resetFilters() {
        state.categoria  = '';
        state.marca      = '';
        state.min_precio = 0;
        state.max_precio = 0;
        state.pagina     = 1;
        state.atributos  = {};

        document.querySelectorAll( '#nextech-filter input[type=checkbox]' )
            .forEach( cb => ( cb.checked = false ) );

        [ '#nxf-min-precio', '#nxf-max-precio', '#nxf-marcas-search' ].forEach( sel => {
            const el = $( sel );
            if ( el ) el.value = '';
        } );

        $( '#nxf-marcas-list' )?.querySelectorAll( '.nxf-term' )
            .forEach( l => ( l.style.display = '' ) );

        history.pushState( { ...state }, '', window.location.pathname );
        renderActiveChips();
        fetchProducts();
    }

    function formatCLP( num ) {
        return '$' + Number( num ).toLocaleString( 'es-CL' );
    }

    // ── Boot ──────────────────────────────────────────────────────────────────
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
