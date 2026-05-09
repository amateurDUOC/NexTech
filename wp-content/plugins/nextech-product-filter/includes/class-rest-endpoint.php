<?php
defined( 'ABSPATH' ) || exit;

class Nextech_Rest_Endpoint {

    // ── Registro de rutas ─────────────────────────────────────────────────────

    public static function register_routes(): void {
        register_rest_route( 'nextech/v1', '/productos', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_productos' ],
            'permission_callback' => '__return_true',
            'args'                => self::productos_args(),
        ] );

        register_rest_route( 'nextech/v1', '/filtros', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'get_filtros' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'contexto' => [ 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ],
            ],
        ] );
    }

    private static function productos_args(): array {
        return [
            'categoria'  => [ 'sanitize_callback' => 'sanitize_text_field',   'default' => '' ],
            'marca'      => [ 'sanitize_callback' => 'sanitize_text_field',   'default' => '' ],
            'min_precio' => [ 'sanitize_callback' => 'absint',                'default' => 0 ],
            'max_precio' => [ 'sanitize_callback' => 'absint',                'default' => 0 ],
            'en_stock'   => [ 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ],
            'orden'      => [ 'sanitize_callback' => 'sanitize_text_field',   'default' => 'menu_order' ],
            'pagina'     => [ 'sanitize_callback' => 'absint',                'default' => 1 ],
            // Los parámetros pa_* (atributos) se leen sin declarar — ver get_filtered_ids()
        ];
    }

    // ── GET /nextech/v1/productos ─────────────────────────────────────────────

    public static function get_productos( WP_REST_Request $request ): WP_REST_Response {
        $params    = $request->get_params();

        // Leer también los pa_* del querystring (no declarados en args)
        foreach ( $request->get_query_params() as $key => $val ) {
            if ( str_starts_with( $key, 'pa_' ) ) {
                $params[ $key ] = sanitize_text_field( $val );
            }
        }

        $cache_key = 'nxf_' . md5( serialize( $params ) );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return rest_ensure_response( $cached );

        $per_page = 24;
        $pagina   = max( 1, (int) $params['pagina'] );

        $all_ids = self::get_filtered_ids( $params );
        $total   = count( $all_ids );
        $paginas = $total > 0 ? (int) ceil( $total / $per_page ) : 0;
        $page_ids = array_slice( $all_ids, ( $pagina - 1 ) * $per_page, $per_page );

        $html = '';
        if ( ! empty( $page_ids ) ) {
            $render_query = new WP_Query( [
                'post_type'           => 'product',
                'post_status'         => 'publish',
                'post__in'            => $page_ids,
                'posts_per_page'      => $per_page,
                'orderby'             => 'post__in',
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
            ] );

            ob_start();
            wc_set_loop_prop( 'columns', wc_get_default_products_per_row() );
            while ( $render_query->have_posts() ) {
                $render_query->the_post();
                wc_get_template_part( 'content', 'product' );
            }
            wp_reset_postdata();
            $html = ob_get_clean();
        }

        $response = [
            'html'          => $html,
            'total'         => $total,
            'paginas'       => $paginas,
            'pagina_actual' => $pagina,
            'por_pagina'    => $per_page,
        ];

        set_transient( $cache_key, $response, 30 * MINUTE_IN_SECONDS );
        return rest_ensure_response( $response );
    }

    /**
     * Obtiene todos los IDs filtrados usando wc_product_meta_lookup para
     * precio/stock y taxonomy queries para categoría, marca y atributos pa_*.
     */
    /**
     * Obtiene IDs de productos aplicando todos los filtros mediante SQL puro.
     * No usa wc_get_products() para evitar conflictos entre category + tax_query.
     */
    private static function get_filtered_ids( array $p ): array {
        global $wpdb;
        $lookup = $wpdb->prefix . 'wc_product_meta_lookup';

        $wheres = [
            "p.post_status   = 'publish'",
            "p.post_password = ''",
            "p.post_type     = 'product'",
            // Excluir productos con visibilidad "Oculto" del catálogo
            "p.ID NOT IN (
                SELECT tr_v.object_id
                FROM {$wpdb->term_relationships} tr_v
                INNER JOIN {$wpdb->term_taxonomy} tt_v ON tt_v.term_taxonomy_id = tr_v.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t_v          ON t_v.term_id = tt_v.term_id
                WHERE tt_v.taxonomy = 'product_visibility'
                  AND t_v.slug      = 'exclude-from-catalog'
            )",
        ];

        // ── Stock y precio via wc_product_meta_lookup (indexado) ──────────
        $join_wml = "LEFT JOIN $lookup wml ON wml.product_id = p.ID";

        if ( ! empty( $p['en_stock'] ) ) {
            $wheres[] = "wml.stock_status = 'instock'";
        }
        if ( ! empty( $p['min_precio'] ) ) {
            $wheres[] = $wpdb->prepare( 'wml.min_price >= %f', (float) $p['min_precio'] );
        }
        if ( ! empty( $p['max_precio'] ) ) {
            $wheres[] = $wpdb->prepare( 'wml.max_price <= %f', (float) $p['max_precio'] );
        }

        // ── JOINs de taxonomía (reemplaza subconsultas IN() correlacionadas)
        //    Cada filtro activo agrega un INNER JOIN independiente con alias
        //    único, de modo que MySQL puede usar índices en lugar de evaluar
        //    subqueries por cada fila de wp_posts.
        $tax_joins   = [];
        $join_index  = 0;

        // ── Categoría — resuelve subcategorías recursivamente ──────────────
        if ( ! empty( $p['categoria'] ) ) {
            $slugs = array_filter( array_map( 'sanitize_title', explode( ',', $p['categoria'] ) ) );
            if ( $slugs ) {
                $all_term_ids = [];
                foreach ( $slugs as $slug ) {
                    $term = get_term_by( 'slug', $slug, 'product_cat' );
                    if ( ! $term ) continue;
                    $children     = get_term_children( $term->term_id, 'product_cat' );
                    $all_term_ids = array_merge(
                        $all_term_ids,
                        [ $term->term_id ],
                        is_wp_error( $children ) ? [] : $children
                    );
                }
                if ( ! $all_term_ids ) return [];

                $tids = implode( ',', array_map( 'intval', array_unique( $all_term_ids ) ) );
                $i    = $join_index++;
                $tax_joins[] = "INNER JOIN {$wpdb->term_relationships} tr{$i}
                        ON  tr{$i}.object_id = p.ID
                    INNER JOIN {$wpdb->term_taxonomy} tt{$i}
                        ON  tt{$i}.term_taxonomy_id = tr{$i}.term_taxonomy_id
                        AND tt{$i}.taxonomy = 'product_cat'
                        AND tt{$i}.term_id IN ( $tids )";
            }
        }

        // ── Marca ──────────────────────────────────────────────────────────
        if ( ! empty( $p['marca'] ) ) {
            $slugs = array_filter( array_map( 'sanitize_title', explode( ',', $p['marca'] ) ) );
            if ( $slugs ) {
                $in  = "'" . implode( "','", array_map( 'esc_sql', $slugs ) ) . "'";
                $i   = $join_index++;
                $tax_joins[] = "INNER JOIN {$wpdb->term_relationships} tr{$i}
                        ON  tr{$i}.object_id = p.ID
                    INNER JOIN {$wpdb->term_taxonomy} tt{$i}
                        ON  tt{$i}.term_taxonomy_id = tr{$i}.term_taxonomy_id
                        AND tt{$i}.taxonomy = 'marca'
                    INNER JOIN {$wpdb->terms} t{$i}
                        ON  t{$i}.term_id = tt{$i}.term_id
                        AND t{$i}.slug IN ( $in )";
            }
        }

        // ── Atributos pa_* (uno por filtro → relación AND entre ellos) ─────
        foreach ( $p as $key => $value ) {
            if ( ! str_starts_with( $key, 'pa_' ) || empty( $value ) ) continue;

            $slugs = array_filter( array_map( 'sanitize_title', explode( ',', $value ) ) );
            if ( ! $slugs ) continue;

            $tax_safe = esc_sql( $key );
            $in       = "'" . implode( "','", array_map( 'esc_sql', $slugs ) ) . "'";
            $i        = $join_index++;
            $tax_joins[] = "INNER JOIN {$wpdb->term_relationships} tr{$i}
                    ON  tr{$i}.object_id = p.ID
                INNER JOIN {$wpdb->term_taxonomy} tt{$i}
                    ON  tt{$i}.term_taxonomy_id = tr{$i}.term_taxonomy_id
                    AND tt{$i}.taxonomy = '$tax_safe'
                INNER JOIN {$wpdb->terms} t{$i}
                    ON  t{$i}.term_id = tt{$i}.term_id
                    AND t{$i}.slug IN ( $in )";
        }

        // ── Orden ──────────────────────────────────────────────────────────
        $order = 'p.menu_order ASC, p.ID ASC';
        switch ( $p['orden'] ?? '' ) {
            case 'precio_asc':  $order = 'wml.min_price ASC';     break;
            case 'precio_desc': $order = 'wml.min_price DESC';    break;
            case 'nombre':      $order = 'p.post_title ASC';      break;
            case 'popularidad': $order = 'wml.rating_count DESC'; break;
        }

        $where_sql    = 'WHERE ' . implode( "\n  AND ", $wheres );
        $tax_join_sql = implode( "\n        ", $tax_joins );

        $ids = $wpdb->get_col( "
            SELECT DISTINCT p.ID
            FROM   {$wpdb->posts} p
            $join_wml
            $tax_join_sql
            $where_sql
            ORDER BY $order
        " );

        return array_map( 'intval', $ids ?: [] );
    }

    // ── GET /nextech/v1/filtros ───────────────────────────────────────────────

    public static function get_filtros( WP_REST_Request $request ): WP_REST_Response {
        $contexto  = $request->get_param( 'contexto' );
        $cache_key = 'nxf_filtros_' . ( $contexto ? md5( $contexto ) : 'global' ) . '_v1';

        $cached = get_transient( $cache_key );
        if ( $cached !== false ) return rest_ensure_response( $cached );

        $ctx_term = $contexto ? get_term_by( 'slug', $contexto, 'product_cat' ) : null;

        // ── IDs de productos en este contexto (reutilizado por marcas y atributos)
        $product_ids = $ctx_term ? self::get_product_ids_in_category( $ctx_term ) : [];

        // ── Categorías ───────────────────────────────────────────────────────
        // En categorías donde los atributos configurados ya cubren la navegación
        // (ej. Refrigeración usa Tipo=Aire/Líquida/Ventiladores en lugar de subcats),
        // suprimimos el árbol de subcategorías para evitar duplicación visual.
        $slugs_sin_subcats = [ 'refrigeracion', 'memoria-ram', 'almacenamiento', 'placa-madre-motherboard' ];
        $mostrar_cats      = ! $ctx_term || ! in_array( $ctx_term->slug, $slugs_sin_subcats, true );
        $cat_tree          = $mostrar_cats ? self::build_category_tree( $ctx_term ) : [];

        // ── Sin productos en stock → devolver flag para ocultar el sidebar ───
        // El JS ocultará el precio y los atributos, y llamará fetchProducts()
        // para reemplazar el loop nativo de WC con el mensaje "sin resultados".
        if ( $ctx_term && empty( $product_ids ) ) {
            $response = [
                'categorias'    => $cat_tree,   // conserva navegación de categorías
                'marcas'        => [],
                'atributos'     => [],
                'precios'       => [ 'min' => 0, 'max' => 0 ],
                'sin_productos' => true,
            ];
            set_transient( $cache_key, $response, 6 * HOUR_IN_SECONDS );
            return rest_ensure_response( $response );
        }

        // ── Marcas ───────────────────────────────────────────────────────────
        $marcas = $ctx_term
            ? self::get_terms_for_products( $product_ids, 'marca' )
            : array_map( fn( $m ) => [
                'id'     => $m->term_id,
                'nombre' => $m->name,
                'slug'   => $m->slug,
                'count'  => (int) $m->count,
            ], (array) get_terms( [ 'taxonomy' => 'marca', 'hide_empty' => true, 'orderby' => 'name' ] ) );

        // ── Atributos de producto ────────────────────────────────────────────
        // Config explícita por slug de categoría → evita contaminación cruzada.
        // Si el slug no está en attr_config(), auto-detecta (fallback).
        $atributos = $ctx_term
            ? self::get_atributos_en_categoria( $product_ids, $ctx_term->slug )
            : [];

        // ── Rango de precios ─────────────────────────────────────────────────
        $prices = self::get_price_range( $ctx_term );

        $response = [
            'categorias' => $cat_tree,
            'marcas'     => $marcas,
            'atributos'  => $atributos,
            'precios'    => [
                'min' => $prices ? (int) $prices->min_precio : 0,
                'max' => $prices ? (int) $prices->max_precio : 9999999,
            ],
        ];

        set_transient( $cache_key, $response, 6 * HOUR_IN_SECONDS );
        return rest_ensure_response( $response );
    }

    // ── Helpers privados ─────────────────────────────────────────────────────

    /**
     * Construye el árbol de categorías según el contexto:
     * - Con contexto + hijos   → muestra hijos (drill-down)
     * - Con contexto sin hijos → muestra hermanas (navegación lateral)
     * - Sin contexto           → árbol completo raíz + hijos directos
     */
    private static function build_category_tree( ?WP_Term $ctx_term ): array {
        if ( ! $ctx_term ) {
            $raices = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0 ] );
            if ( is_wp_error( $raices ) || empty( $raices ) ) return [];

            // ── Batch: una sola query para todos los hijos en lugar de 1 por raíz
            $root_ids = array_map( fn( $c ) => $c->term_id, $raices );
            $all_hijos = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent__in' => $root_ids ] );
            $hijos_por_padre = [];
            foreach ( (array) $all_hijos as $hijo ) {
                $hijos_por_padre[ $hijo->parent ][] = $hijo;
            }
            // ────────────────────────────────────────────────────────────────

            $cat_tree = [];
            foreach ( (array) $raices as $cat ) {
                $hijos      = $hijos_por_padre[ $cat->term_id ] ?? [];
                $cat_tree[] = [
                    'id'     => $cat->term_id,
                    'nombre' => $cat->name,
                    'slug'   => $cat->slug,
                    'count'  => (int) $cat->count,
                    'hijos'  => array_map( fn( $h ) => [
                        'id'     => $h->term_id,
                        'nombre' => $h->name,
                        'slug'   => $h->slug,
                        'count'  => (int) $h->count,
                    ], $hijos ),
                ];
            }
            return $cat_tree;
        }

        $subcats = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => $ctx_term->term_id ] );

        if ( ! empty( $subcats ) ) {
            // Tiene hijos → mostrar para drill-down
            return array_map( fn( $t ) => [
                'id'     => $t->term_id,
                'nombre' => $t->name,
                'slug'   => $t->slug,
                'count'  => (int) $t->count,
                'hijos'  => [],
            ], (array) $subcats );
        }

        // Categoría hoja → mostrar hermanas para navegación lateral
        $siblings = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => $ctx_term->parent,
            'exclude'    => [ $ctx_term->term_id ],
        ] );

        return array_map( fn( $t ) => [
            'id'     => $t->term_id,
            'nombre' => $t->name,
            'slug'   => $t->slug,
            'count'  => (int) $t->count,
            'hijos'  => [],
        ], (array) $siblings );
    }

    /**
     * IDs de productos publicados dentro de una categoría (incluye subcategorías).
     */
    private static function get_product_ids_in_category( WP_Term $ctx_term ): array {
        global $wpdb;

        $cat_ids   = self::get_all_child_ids( $ctx_term->term_id );
        $cat_ids[] = $ctx_term->term_id;
        $ids_sql   = implode( ',', array_map( 'intval', $cat_ids ) );
        $lookup    = $wpdb->prefix . 'wc_product_meta_lookup';

        // Filtramos por instock desde el origen para que todos los conteos
        // del sidebar (marcas, atributos, categorías) sean coherentes con
        // los resultados reales — el usuario ve exactamente lo que va a obtener.
        return $wpdb->get_col( "
            SELECT DISTINCT tr.object_id
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p           ON p.ID = tr.object_id
            INNER JOIN $lookup wml                ON wml.product_id = p.ID
            WHERE tt.taxonomy      = 'product_cat'
              AND tt.term_id       IN ( $ids_sql )
              AND p.post_status    = 'publish'
              AND p.post_password  = ''
              AND p.post_type      = 'product'
              AND wml.stock_status = 'instock'
              AND p.ID NOT IN (
                  SELECT tr_v.object_id
                  FROM {$wpdb->term_relationships} tr_v
                  INNER JOIN {$wpdb->term_taxonomy} tt_v ON tt_v.term_taxonomy_id = tr_v.term_taxonomy_id
                  INNER JOIN {$wpdb->terms} t_v          ON t_v.term_id = tt_v.term_id
                  WHERE tt_v.taxonomy = 'product_visibility'
                    AND t_v.slug      = 'exclude-from-catalog'
              )
        " );
    }

    /**
     * Términos de una taxonomía usados por un conjunto específico de productos.
     * Usa get_terms con object_ids → JOIN indexado en wp_term_relationships.
     */
    private static function get_terms_for_products( array $product_ids, string $taxonomy ): array {
        if ( empty( $product_ids ) || ! taxonomy_exists( $taxonomy ) ) return [];

        global $wpdb;

        // Cuenta cuántos productos de $product_ids usan cada término.
        // wp_term_taxonomy.count es global y no refleja el contexto de categoría,
        // por eso usamos COUNT(DISTINCT object_id) directo sobre las relaciones.
        $ids_sql  = implode( ',', array_map( 'intval', $product_ids ) );
        $tax_safe = esc_sql( $taxonomy );

        $rows = $wpdb->get_results( "
            SELECT   t.term_id,
                     t.name,
                     t.slug,
                     COUNT( DISTINCT tr.object_id ) AS cnt,
                     COALESCE( CAST( tm.meta_value AS UNSIGNED ), 9999 ) AS term_order
            FROM     {$wpdb->terms}              t
            JOIN     {$wpdb->term_taxonomy}      tt ON tt.term_id            = t.term_id
            JOIN     {$wpdb->term_relationships} tr ON tr.term_taxonomy_id   = tt.term_taxonomy_id
            LEFT JOIN {$wpdb->termmeta}          tm ON tm.term_id            = t.term_id
                                                   AND tm.meta_key           = 'order'
            WHERE    tt.taxonomy   = '$tax_safe'
              AND    tr.object_id IN ( $ids_sql )
            GROUP BY t.term_id
            ORDER BY term_order ASC, t.name ASC
        " );

        if ( empty( $rows ) ) return [];

        return array_map( fn( $r ) => [
            'id'     => (int) $r->term_id,
            'nombre' => $r->name,
            'slug'   => $r->slug,
            'count'  => (int) $r->cnt,
        ], $rows );
    }

    /**
     * Devuelve los atributos del sidebar para una categoría.
     *
     * — Si el slug está en attr_config() → configuración explícita
     *   (solo muestra lo definido, soporta grupos anidados).
     * — Si no → auto-detecta todos los pa_* del catálogo (fallback).
     *
     * Estructura de cada ítem devuelto:
     *   hijos = []  → atributo simple (acordeón con checkboxes)
     *   hijos ≠ []  → grupo (acordeón padre con sub-acordeones)
     */
    private static function get_atributos_en_categoria( array $product_ids, string $cat_slug = '' ): array {
        if ( empty( $product_ids ) ) return [];

        $config = self::attr_config();

        // Busca config exacta; si no existe, sube por los ancestros hasta encontrar una.
        // Esto permite que pc-gamer-rtx-4060 herede de pc-gamer sin entrada propia.
        // Admin UI config (wp_options) takes priority over the PHP hardcoded config.
        if ( $cat_slug ) {
            $node = get_term_by( 'slug', $cat_slug, 'product_cat' );
            while ( $node && ! is_wp_error( $node ) ) {
                // Check Admin UI options first (takes priority over PHP config)
                $saved = Nextech_Admin_Page::get_saved_config( $node->slug );
                if ( $saved !== null ) {
                    return self::build_atributos_from_config( $saved, $product_ids );
                }
                // Then check hardcoded PHP config
                if ( isset( $config[ $node->slug ] ) ) {
                    return self::build_atributos_from_config( $config[ $node->slug ], $product_ids );
                }
                if ( ! $node->parent ) break;
                $node = get_term( $node->parent, 'product_cat' );
            }
        }

        // Fallback: auto-detect (lista plana sin grupos)
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        if ( empty( $attribute_taxonomies ) ) return [];

        $atributos = [];
        foreach ( $attribute_taxonomies as $attr ) {
            $tax_slug = wc_attribute_taxonomy_name( $attr->attribute_name );
            $valores  = self::get_terms_for_products( $product_ids, $tax_slug );
            if ( empty( $valores ) ) continue;
            $atributos[] = [
                'nombre'  => $attr->attribute_label,
                'slug'    => $tax_slug,
                'valores' => $valores,
                'hijos'   => [],
            ];
        }
        return $atributos;
    }

    /**
     * Construye la lista de atributos a partir de la configuración explícita.
     * Soporta atributos simples y grupos con sub-acordeones.
     */
    private static function build_atributos_from_config( array $config, array $product_ids ): array {
        $result = [];

        foreach ( $config as $item ) {
            if ( ! empty( $item['grupo'] ) ) {
                // ── Grupo anidado ────────────────────────────────────────
                $hijos = [];
                foreach ( $item['hijos'] as $hijo ) {
                    $valores = self::get_terms_for_products( $product_ids, $hijo['slug'] );
                    if ( empty( $valores ) ) continue;
                    $hijos[] = [
                        'nombre'  => $hijo['nombre'],
                        'slug'    => $hijo['slug'],
                        'valores' => $valores,
                        'hijos'   => [],
                    ];
                }
                if ( empty( $hijos ) ) continue;
                $result[] = [
                    'nombre'  => $item['nombre'],
                    'slug'    => '_grupo_' . sanitize_title( $item['nombre'] ),
                    'valores' => [],
                    'hijos'   => $hijos,
                ];
            } else {
                // ── Atributo simple ──────────────────────────────────────
                $valores = self::get_terms_for_products( $product_ids, $item['slug'] );
                if ( empty( $valores ) ) continue;
                $result[] = [
                    'nombre'  => $item['nombre'],
                    'slug'    => $item['slug'],
                    'valores' => $valores,
                    'hijos'   => [],
                ];
            }
        }

        return $result;
    }

    /**
     * Configuración explícita: slug de categoría → atributos a mostrar.
     *
     * ⚠️  Verifica tus slugs en: WP Admin → Productos → Categorías → columna Slug.
     *     Si el slug real difiere, agrégalo aquí como nueva entrada.
     *
     * grupo: true → acordeón padre que agrupa varios sub-filtros
     * (sin grupo)  → atributo simple con sus checkboxes
     */
    /** Public alias used by Nextech_Admin_Page to read the PHP hardcoded config. */
    public static function get_attr_config_public(): array {
        return self::attr_config();
    }

    public static function attr_config(): array {
        // ── Bloques reutilizables ────────────────────────────────────────
        $gpu = [
            'nombre' => 'Tarjeta de Video', 'grupo' => true,
            'hijos'  => [
                [ 'nombre' => 'Marca',  'slug' => 'pa_marca-gpu' ],
                [ 'nombre' => 'Serie',  'slug' => 'pa_serie-gpu' ],
                [ 'nombre' => 'VRAM',   'slug' => 'pa_vram'      ],
            ],
        ];

        $ram = [
            'nombre' => 'Memoria RAM', 'grupo' => true,
            'hijos'  => [
                [ 'nombre' => 'Capacidad',  'slug' => 'pa_capacidad-ram'  ],
                [ 'nombre' => 'Generación', 'slug' => 'pa_generacion-ram' ],
            ],
        ];

        $psu = [
            'nombre' => 'Fuente de Poder', 'grupo' => true,
            'hijos'  => [
                [ 'nombre' => 'Potencia',      'slug' => 'pa_potencia'      ],
                [ 'nombre' => 'Certificación', 'slug' => 'pa_certificacion' ],
                [ 'nombre' => 'Modularidad',   'slug' => 'pa_modularidad'   ],
            ],
        ];

        $case = [
            'nombre' => 'Gabinete', 'grupo' => true,
            'hijos'  => [
                [ 'nombre' => 'Factor de Forma', 'slug' => 'pa_factor-forma'   ],
                [ 'nombre' => 'Panel',           'slug' => 'pa_panel'          ],
                [ 'nombre' => 'Color',           'slug' => 'pa_color-gabinete' ],
            ],
        ];

        $refrig = [
            'nombre' => 'Refrigeración', 'grupo' => true,
            'hijos'  => [
                [ 'nombre' => 'Tipo',   'slug' => 'pa_tipo-refrigeracion' ],
                [ 'nombre' => 'Tamaño', 'slug' => 'pa_tamano'             ],
            ],
        ];

        $procesador     = [ 'nombre' => 'Procesador',     'slug' => 'pa_procesador'     ];
        $chipset        = [ 'nombre' => 'Chipset',        'slug' => 'pa_chipset'        ];
        $almacenamiento = [ 'nombre' => 'Almacenamiento', 'slug' => 'pa_almacenamiento' ];
        $color_item     = [ 'nombre' => 'Color',          'slug' => 'pa_color-gabinete' ];

        // GPU simplificado para notebooks (sin VRAM — no aplica)
        $gpu_notebook = [
            'nombre' => 'Tarjeta de Video', 'grupo' => true,
            'hijos'  => [
                [ 'nombre' => 'Marca', 'slug' => 'pa_marca-gpu' ],
                [ 'nombre' => 'Serie', 'slug' => 'pa_serie-gpu' ],
            ],
        ];

        // Atributos específicos de monitores
        $monitor = [
            'nombre' => 'Pantalla', 'grupo' => true,
            'hijos'  => [
                [ 'nombre' => 'Tamaño',       'slug' => 'pa_tamano-monitor' ],
                [ 'nombre' => 'Resolución',   'slug' => 'pa_resolucion'     ],
                [ 'nombre' => 'Panel',        'slug' => 'pa_panel-monitor'  ],
                [ 'nombre' => 'Frecuencia',   'slug' => 'pa_hz'             ],
            ],
        ];

        // ── Mapa: slug de categoría → atributos a mostrar ────────────────
        // Las subcategorías heredan automáticamente del padre — no necesitan
        // entrada propia. Ejemplo: pc-gamer-rtx-4060 hereda de pc-gamer.
        return [

            /* ── PC Gamer (padre) ─────────────────────────────────────────
             *  Sus ~25 subcategorías (pc-amd, pc-gamer-rtx-4060, etc.)
             *  heredan este config sin entrada propia.
             * ─────────────────────────────────────────────────────────── */
            'pc-gamer' => [
                $procesador,
                $almacenamiento,
                $ram,
                $gpu,
            ],

            /* ── Componentes individuales ─────────────────────────────── */
            'procesadores'            => [ $procesador     ],
            'placa-madre-motherboard' => [ $chipset        ],
            'memoria-ram'             => [ $ram            ],
            'almacenamiento'          => [ $almacenamiento ],
            'refrigeracion'           => [ $refrig         ],
            'tarjetas-de-video'       => [ $gpu            ],
            'fuentes-de-poder'        => [ $psu            ],
            'gabinetes'               => [ $case           ],

            /* ── PC Armado (categorías del configurador) ──────────────── */
            'procesadores-pc-armado'    => [ $procesador     ],
            'placas-pc-armado'          => [ $chipset        ],
            'memoria-ram-pc-armado'     => [ $ram            ],
            'almacenamiento-pc-armado'  => [ $almacenamiento ],
            'fuente-de-poder-pc-armado' => [ $psu            ],
            'gabinetes-pc-armado'       => [ $case           ],

            /* ── Notebooks ────────────────────────────────────────────── */
            'notebook-gamer' => [ $procesador, $gpu_notebook, $ram, $almacenamiento ],

            /* ── Monitores ────────────────────────────────────────────── */
            'monitores'        => [ $monitor ],
            'monitores-galax'  => [ $monitor ],
            'hall-of-fame-hof' => [ $monitor ],
            'newgen'           => [ $monitor ],

            /* ── Periféricos ──────────────────────────────────────────── */
            'audifonos-y-perifericos' => [ $color_item ],
            'perifericos-galax'       => [ $color_item ],

            /* ── Sillas Gamer ─────────────────────────────────────────── */
            'sillas-gamer'       => [ $color_item ],
            'sillas-gamer-galax' => [ $color_item ],

            /* ── Ventiladores y Accesorios ───────────────────────────── */
            'ventiladores-y-accesorios' => [ $refrig ],  // hereda filtros de tipo/tamaño de refrigeración

            /* ── Accesorios ───────────────────────────────────────────── */
            'accesorios'           => [],
            'accesorios-pc-armado' => [],

            /* ── GALAX (marca contenedora) ────────────────────────────── *
             *  Sus subcategorías tienen config propia (heredan hacia arriba
             *  pero encuentran su entrada antes de llegar aquí).
             *  Vacío suprime el fallback si alguien navega a /galax directo.
             * ─────────────────────────────────────────────────────────── */
            'galax' => [],

        ];
    }

    private static function get_all_child_ids( int $term_id ): array {
        $children = get_term_children( $term_id, 'product_cat' );
        return is_wp_error( $children ) ? [] : $children;
    }

    private static function get_price_range( ?WP_Term $ctx_term ): ?object {
        global $wpdb;
        $lookup = $wpdb->prefix . 'wc_product_meta_lookup';

        if ( $ctx_term ) {
            $cat_ids   = self::get_all_child_ids( $ctx_term->term_id );
            $cat_ids[] = $ctx_term->term_id;
            $ids_sql   = implode( ',', array_map( 'intval', $cat_ids ) );

            return $wpdb->get_row( "
                SELECT MIN( wml.min_price ) AS min_precio,
                       MAX( wml.max_price ) AS max_precio
                FROM {$lookup} wml
                INNER JOIN {$wpdb->posts} p          ON p.ID = wml.product_id
                INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                INNER JOIN {$wpdb->term_taxonomy} tt  ON tt.term_taxonomy_id = tr.term_taxonomy_id
                WHERE p.post_status = 'publish'
                  AND p.post_type   = 'product'
                  AND wml.min_price > 0
                  AND tt.taxonomy   = 'product_cat'
                  AND tt.term_id    IN ( $ids_sql )
            " );
        }

        return $wpdb->get_row( "
            SELECT MIN( wml.min_price ) AS min_precio,
                   MAX( wml.max_price ) AS max_precio
            FROM {$lookup} wml
            INNER JOIN {$wpdb->posts} p ON p.ID = wml.product_id
            WHERE p.post_status = 'publish'
              AND p.post_type   = 'product'
              AND wml.min_price > 0
        " );
    }
}
