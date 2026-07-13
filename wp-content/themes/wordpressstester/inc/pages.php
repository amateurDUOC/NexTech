<?php
/* ── Nextech — Customizaciones de páginas estáticas ─────────────────────────
   Banner hero, Empresas layout, Controladores redesign, Términos y Condiciones.
   ─────────────────────────────────────────────────────────────────────────── */

// ══════════════════════════════════════════════════════════════════════════════
// 1. BANNER HERO — todas las páginas estáticas + tienda
// ══════════════════════════════════════════════════════════════════════════════

add_action( 'flatsome_before_page', 'nextech_static_page_banner', 5 );
function nextech_static_page_banner(): void {
    // Excluimos la portada (Inicio)
    if ( is_front_page() || is_home() ) return;
    // Excluimos páginas especiales de WooCommerce (carrito, checkout, mi cuenta)
    if ( is_cart() || is_checkout() || is_account_page() ) return;
    // Solo en páginas estáticas normales
    if ( ! is_page() ) return;
    ?>
    <div class="nxt-page-banner">
        <div class="nxt-page-banner__inner">
            <h1 class="nxt-page-banner__title"><?php the_title(); ?></h1>
            <nav class="nxt-page-banner__breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Inicio</a>
                <span aria-hidden="true">&nbsp;/&nbsp;</span>
                <span><?php the_title(); ?></span>
            </nav>
        </div>
    </div>
    <?php
}

// ── Eliminar el banner nativo de Flatsome (está en flatsome_after_header) ───
// Necesitamos usar una closure en init para que la función flatsome_category_header
// ya esté definida cuando removemos el hook.
add_action( 'init', function() {
    remove_action( 'flatsome_after_header', 'flatsome_category_header' );
} );

// ── Nuestro banner unificado en flatsome_after_header ────────────────────────
// Se ejecuta ANTES del row sidebar+columna, por lo que es full-width como el nativo.
add_action( 'flatsome_after_header', 'nextech_woo_page_banner', 5 );
function nextech_woo_page_banner(): void {
    if ( is_shop() ) {
        $shop_title = get_the_title( wc_get_page_id( 'shop' ) );
        ?>
        <div class="nxt-page-banner">
            <div class="nxt-page-banner__inner">
                <h1 class="nxt-page-banner__title"><?php echo esc_html( $shop_title ); ?></h1>
                <nav class="nxt-page-banner__breadcrumb" aria-label="Breadcrumb">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Inicio</a>
                    <span aria-hidden="true">&nbsp;/&nbsp;</span>
                    <span><?php echo esc_html( $shop_title ); ?></span>
                </nav>
            </div>
        </div>
        <?php
    } elseif ( is_product_category() ) {
        $term      = get_queried_object();
        $cat_name  = $term ? $term->name : '';
        $shop_url  = get_permalink( wc_get_page_id( 'shop' ) );
        $shop_name = get_the_title( wc_get_page_id( 'shop' ) );
        ?>
        <div class="nxt-page-banner">
            <div class="nxt-page-banner__inner">
                <h1 class="nxt-page-banner__title"><?php echo esc_html( $cat_name ); ?></h1>
                <nav class="nxt-page-banner__breadcrumb" aria-label="Breadcrumb">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Inicio</a>
                    <span aria-hidden="true">&nbsp;/&nbsp;</span>
                    <a href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( $shop_name ); ?></a>
                    <span aria-hidden="true">&nbsp;/&nbsp;</span>
                    <span><?php echo esc_html( $cat_name ); ?></span>
                </nav>
            </div>
        </div>
        <?php
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// 2. EMPRESAS (ID 35683) — reestructurar contenido
// ══════════════════════════════════════════════════════════════════════════════

add_filter( 'the_content', 'nextech_empresas_content', 20 );
function nextech_empresas_content( string $content ): string {
    if ( ! is_page( 35683 ) ) return $content;

    // ── 2a. Eliminar imágenes decorativas (mantener solo banner-marcas-empr) ──
    // Hay 3 imágenes que no aportan:
    //   · logos-emp-azul   → bloque azul con industrias (repetitivo con los chips)
    //   · logos-emp-blanco → bloque blanco con beneficios (repetitivo con nuestro grid)
    //   · marcas-celu      → versión duplicada del banner de marcas (peor calidad)
    $content = preg_replace(
        '/<img[^>]+(?:logos-emp-azul|logos-emp-blanco|marcas-celu)[^>]+>/s',
        '',
        $content
    );

    // ── 2b. Productos Sugeridos — 4 PC Gamer con stock (dinámico) ──────────────
    // Busca la categoría PC Gamer por nombre para obtener su slug real.
    $pc_gamer_term = get_term_by( 'name', 'PC Gamer', 'product_cat' );
    $cat_slug      = $pc_gamer_term ? $pc_gamer_term->slug : 'pc-gamer';

    $wc_products = wc_get_products( [
        'status'       => 'publish',
        'stock_status' => 'instock',
        'category'     => [ $cat_slug ],
        'limit'        => 4,
        'orderby'      => 'date',
        'order'        => 'DESC',
    ] );

    $cards = '';
    foreach ( $wc_products as $product ) {
        $cards .= sprintf(
            '<a href="%s" class="nxt-emp-product-card">
                <span class="nxt-emp-product-card__name">%s</span>
                <span class="nxt-emp-product-card__price">%s</span>
                <span class="nxt-emp-product-card__cta">Ver producto →</span>
            </a>',
            esc_url( get_permalink( $product->get_id() ) ),
            esc_html( $product->get_name() ),
            $product->get_price_html()
        );
    }

    // Si no hay productos en stock en PC Gamer, dejamos la sección vacía con aviso
    if ( ! $cards ) {
        $cards = '<p class="nxt-emp-products-empty">Próximamente nuevos modelos disponibles.</p>';
    }

    $content = preg_replace(
        '/<h2>Productos Sugeridos<\/h2>.+$/s',
        '<h2 class="nxt-section-title">Productos Sugeridos</h2><div class="nxt-emp-products-grid">' . $cards . '</div>',
        $content
    );

    // ── 2c. Wrap industry chips en contenedor flex ───────────────────────────
    $content = preg_replace_callback(
        '/(<h3[^>]*>Dise[^<]+industria<\/h3>)((?:\s*<h3[^>]*>[^<]+<\/h3>){6})/s',
        function( $m ) {
            $label = '<p class="nxt-emp-industries__label">' . strip_tags( $m[1] ) . '</p>';
            // Trim el whitespace interno de cada chip (el contenido tiene tabs/newlines)
            $chips = preg_replace_callback(
                '/<h3[^>]*>\s*(.+?)\s*<\/h3>/s',
                fn( $h ) => '<span class="nxt-emp-chip">' . trim( strip_tags( $h[1] ) ) . '</span>',
                $m[2]
            );
            return '<div class="nxt-emp-industries">' . $label . $chips . '</div>';
        },
        $content
    );

    // ── 2d. Wrap cada workstation (figure + H3 + P) como card ────────────────
    $content = preg_replace_callback(
        '/(<figure>.*?<\/figure>)\s*(<h3[^>]*>[^<]+<\/h3>)\s*(<p[^>]*>.*?<\/p>)/s',
        fn( $m ) => '<div class="nxt-emp-ws-card">' . $m[1] . $m[2] . $m[3] . '</div>',
        $content
    );
    $content = preg_replace(
        '/(<div class="nxt-emp-ws-card">.*?<\/div>)(\s*<div class="nxt-emp-ws-card">.*?<\/div>)(\s*<div class="nxt-emp-ws-card">.*?<\/div>)/s',
        '<div class="nxt-emp-workstations">$1$2$3</div>',
        $content
    );

    // ── 2e. Wrap benefits (H3s después de "¿Por qué elegirnos?") ────────────
    $content = preg_replace_callback(
        '/(<h3[^>]*>[^<]*Por qu[^<]+elegirnos[^<]*<\/h3>)((?:\s*<h3[^>]*>[^<]+<\/h3>){5})/s',
        function( $m ) {
            $title = '<h2 class="nxt-section-title">' . strip_tags( $m[1] ) . '</h2>';
            $items = preg_replace_callback(
                '/<h3[^>]*>\s*(.+?)\s*<\/h3>/s',
                fn( $h ) => '<div class="nxt-emp-benefit"><span class="nxt-emp-benefit__icon">✓</span><span>' . trim( strip_tags( $h[1] ) ) . '</span></div>',
                $m[2]
            );
            return $title . '<div class="nxt-emp-benefits">' . $items . '</div>';
        },
        $content
    );

    // ── 2f. Título "COTIZA CON NOSOTROS" ─────────────────────────────────────
    $content = preg_replace(
        '/<h3[^>]*>COTIZA CON NOSOTROS<\/h3>/i',
        '<h2 class="nxt-section-title">Cotiza con Nosotros</h2>',
        $content
    );

    return $content;
}


// ══════════════════════════════════════════════════════════════════════════════
// 3. CONTROLADORES (ID 21928) — redesign completo
// ══════════════════════════════════════════════════════════════════════════════

add_filter( 'the_content', 'nextech_controladores_content', 20 );
function nextech_controladores_content( string $content ): string {
    if ( ! is_page( 21928 ) ) return $content;

    // Extraer los pares imagen+link del contenido original
    // Patrón: <img .../> <h3><a href="URL">Texto</a></h3>
    preg_match_all(
        '/<h3[^>]*><a\s+href="([^"]+)"[^>]*>([^<]+)<\/a><\/h3>/s',
        $content,
        $links
    );

    // Construir las tarjetas de descarga con brand colors en lugar de imágenes rotas
    $drivers = [
        [
            'brand'   => 'NVIDIA',
            'color'   => '#76b900',
            'icon'    => 'N',
            'label'   => 'Tarjetas de Video NVIDIA',
            'url'     => $links[1][0] ?? '#',
            'version' => 'GeForce Game Ready Driver',
        ],
        [
            'brand'   => 'GALAX',
            'color'   => '#1e73be',
            'icon'    => 'G',
            'label'   => 'Xtreme Tuner Plus (GALAX)',
            'url'     => $links[1][1] ?? '#',
            'version' => 'Software de control de tarjetas GALAX',
        ],
        [
            'brand'   => 'AMD',
            'color'   => '#ed1c24',
            'icon'    => 'A',
            'label'   => 'Tarjetas de Video AMD',
            'url'     => $links[1][2] ?? '#',
            'version' => 'AMD Software: Adrenalin Edition',
        ],
    ];

    $cards_html = '';
    foreach ( $drivers as $d ) {
        $cards_html .= sprintf(
            '<a href="%s" class="nxt-driver-card" target="_blank" rel="noopener noreferrer">
                <div class="nxt-driver-card__logo" style="background:%s;">%s</div>
                <div class="nxt-driver-card__body">
                    <strong class="nxt-driver-card__brand">%s</strong>
                    <span class="nxt-driver-card__name">%s</span>
                    <span class="nxt-driver-card__version">%s</span>
                </div>
                <div class="nxt-driver-card__cta">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Descargar
                </div>
            </a>',
            esc_url( $d['url'] ),
            esc_attr( $d['color'] ),
            esc_html( $d['icon'] ),
            esc_html( $d['brand'] ),
            esc_html( $d['label'] ),
            esc_html( $d['version'] )
        );
    }

    return '<div class="nxt-drivers-page">
        <p class="nxt-drivers-page__intro">
            Descargá los drivers oficiales para tus componentes RS Tech. Hacé clic en la tarjeta correspondiente para iniciar la descarga directa.
        </p>
        <div class="nxt-drivers-grid">' . $cards_html . '</div>
        <p class="nxt-drivers-page__note">
            ⚠️ Los links apuntan a la versión disponible al momento de publicar esta página. Te recomendamos verificar la versión más reciente en el sitio oficial del fabricante.
        </p>
    </div>';
}


// ══════════════════════════════════════════════════════════════════════════════
// 4. TIENDA (/tienda) — suprimir título nativo de WC (evita duplicación con banner)
// ══════════════════════════════════════════════════════════════════════════════

// WooCommerce renderiza el título H1 de la tienda/categoría via woocommerce_page_title().
// Como ya tenemos nuestro nxt-page-banner con el título, ocultamos el nativo.
// El CSS también oculta .shop-page-title (banner nativo de Flatsome).
add_filter( 'woocommerce_show_page_title', function( bool $show ): bool {
    return ( is_shop() || is_product_category() ) ? false : $show;
} );
