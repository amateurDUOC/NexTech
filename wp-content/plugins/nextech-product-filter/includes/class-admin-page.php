<?php
defined( 'ABSPATH' ) || exit;

class Nextech_Admin_Page {

    const OPTION_KEY = 'nxf_attr_config';

    public static function init(): void {
        add_action( 'admin_menu',            [ self::class, 'register_menu'    ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets'   ] );
        add_action( 'wp_ajax_nxf_load_category',   [ self::class, 'ajax_load'   ] );
        add_action( 'wp_ajax_nxf_save_category',   [ self::class, 'ajax_save'   ] );
        add_action( 'wp_ajax_nxf_delete_category', [ self::class, 'ajax_delete' ] );
    }

    public static function register_menu(): void {
        add_menu_page(
            'Nextech Filter',
            'Nextech Filter',
            'manage_woocommerce',
            'nextech-filter',
            [ self::class, 'render_page' ],
            'dashicons-filter',
            58
        );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( $hook !== 'toplevel_page_nextech-filter' ) return;

        wp_enqueue_style(
            'nxf-admin',
            NEXTECH_FILTER_URL . 'assets/css/nxf-admin.css',
            [],
            NEXTECH_FILTER_VERSION
        );
        wp_enqueue_script(
            'nxf-admin',
            NEXTECH_FILTER_URL . 'assets/js/nxf-admin.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            NEXTECH_FILTER_VERSION,
            true
        );
        wp_localize_script( 'nxf-admin', 'NxfAdmin', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'nxf_admin' ),
            'attributes' => self::get_wc_attributes(),
        ] );
    }

    /** Returns all WC product attributes registered. */
    private static function get_wc_attributes(): array {
        $result = [];
        foreach ( wc_get_attribute_taxonomies() as $attr ) {
            $result[] = [
                'nombre' => $attr->attribute_label,
                'slug'   => wc_attribute_taxonomy_name( $attr->attribute_name ),
            ];
        }
        return $result;
    }

    /** Returns saved config for a slug, or null if not saved. */
    public static function get_saved_config( string $slug ): ?array {
        $all = get_option( self::OPTION_KEY, [] );
        return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public static function render_page(): void {
        $saved_config = get_option( self::OPTION_KEY, [] );

        // All WC categories
        $categories = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );
        if ( is_wp_error( $categories ) ) $categories = [];

        ?>
        <div class="wrap nxf-admin-wrap">

            <div class="nxf-admin-header">
                <h1><span class="dashicons dashicons-filter"></span> Nextech Product Filter <span class="nxf-version">v<?= esc_html( NEXTECH_FILTER_VERSION ) ?></span></h1>
            </div>

            <nav class="nav-tab-wrapper nxf-tabs">
                <a href="#" class="nav-tab nav-tab-active" data-tab="filtros">&#128193; Filtros por Categor&iacute;a</a>
                <a href="#" class="nav-tab" data-tab="herramientas">&#128295; Herramientas</a>
            </nav>

            <!-- ── TAB: Filtros ──────────────────────────────────────────────── -->
            <div id="nxf-tab-filtros" class="nxf-tab-content">
                <div class="nxf-layout">

                    <!-- Left: Category list -->
                    <div class="nxf-cat-sidebar">
                        <div class="nxf-search-wrap">
                            <input type="text" id="nxf-cat-search" placeholder="&#128269; Buscar categor&iacute;a..." class="widefat">
                        </div>
                        <ul id="nxf-cat-list">
                            <?php foreach ( (array) $categories as $cat ):
                                $configured = isset( $saved_config[ $cat->slug ] );
                            ?>
                            <li class="nxf-cat-item <?= $configured ? 'is-configured' : '' ?>"
                                data-slug="<?= esc_attr( $cat->slug ) ?>"
                                data-name="<?= esc_attr( $cat->name ) ?>">
                                <span class="nxf-cat-name"><?= esc_html( $cat->name ) ?></span>
                                <?php if ( $configured ): ?>
                                    <span class="nxf-configured-badge" title="Configurado en admin">&#10003;</span>
                                <?php endif ?>
                            </li>
                            <?php endforeach ?>
                        </ul>
                    </div>

                    <!-- Right: Config panel -->
                    <div class="nxf-config-panel">

                        <div id="nxf-empty-state" class="nxf-empty-state">
                            <div class="nxf-empty-icon">&#128193;</div>
                            <h3>Selecciona una categor&iacute;a</h3>
                            <p>Elige una categor&iacute;a de la lista para configurar qu&eacute; filtros se muestran en el sidebar.</p>
                        </div>

                        <div id="nxf-panel" style="display:none">

                            <div class="nxf-panel-header">
                                <div>
                                    <h2 id="nxf-panel-title"></h2>
                                    <code id="nxf-panel-slug" class="nxf-slug-badge"></code>
                                    <span id="nxf-source-badge" class="nxf-source-badge"></span>
                                </div>
                                <div class="nxf-panel-actions">
                                    <button id="nxf-btn-save" class="button button-primary">&#128190; Guardar</button>
                                    <button id="nxf-btn-delete" class="button nxf-btn-delete">&#128465; Eliminar config</button>
                                    <span id="nxf-save-msg" class="nxf-save-msg"></span>
                                </div>
                            </div>

                            <div class="nxf-panel-body">

                                <!-- Current attrs list -->
                                <div class="nxf-section">
                                    <h3>Atributos del sidebar <span class="nxf-hint">(arrastra para reordenar)</span></h3>
                                    <ul id="nxf-attrs-list" class="nxf-attrs-list">
                                        <li class="nxf-no-attrs" id="nxf-no-attrs">
                                            Sin filtros configurados &mdash; agrega uno abajo.
                                        </li>
                                    </ul>
                                </div>

                                <hr>

                                <!-- Add simple attribute -->
                                <div class="nxf-section">
                                    <h3>&#10133; Agregar atributo simple</h3>
                                    <div class="nxf-add-row">
                                        <select id="nxf-new-slug" class="nxf-select">
                                            <option value="">&mdash; Seleccionar atributo de WooCommerce &mdash;</option>
                                        </select>
                                        <input type="text" id="nxf-new-nombre" class="nxf-input" placeholder="Nombre visible (ej: Procesador)">
                                        <button id="nxf-btn-add-simple" class="button button-secondary">Agregar</button>
                                    </div>
                                </div>

                                <hr>

                                <!-- Add group -->
                                <div class="nxf-section">
                                    <h3>&#10133; Agregar grupo de atributos</h3>
                                    <p class="description">Un grupo agrupa varios atributos en un acorde&oacute;n padre. Ej: &quot;Tarjeta de Video&quot; con sub-filtros Marca + Serie + VRAM.</p>
                                    <div class="nxf-group-builder">
                                        <div class="nxf-group-header-row">
                                            <input type="text" id="nxf-group-nombre" class="nxf-input" placeholder="Nombre del grupo (ej: Tarjeta de Video)">
                                        </div>
                                        <div id="nxf-group-children">
                                            <!-- Dynamic children rows -->
                                        </div>
                                        <div class="nxf-group-footer">
                                            <button id="nxf-btn-add-child" class="button" type="button">+ Sub-atributo</button>
                                            <button id="nxf-btn-add-group" class="button button-secondary" type="button">Agregar grupo</button>
                                        </div>
                                    </div>
                                </div>

                            </div><!-- .nxf-panel-body -->
                        </div><!-- #nxf-panel -->
                    </div><!-- .nxf-config-panel -->
                </div><!-- .nxf-layout -->
            </div><!-- #nxf-tab-filtros -->

            <!-- ── TAB: Herramientas ─────────────────────────────────────────── -->
            <div id="nxf-tab-herramientas" class="nxf-tab-content" style="display:none">
                <div class="nxf-tools-grid">

                    <div class="postbox">
                        <h2 class="hndle"><span>&#128465; Cach&eacute; del filtro</span></h2>
                        <div class="inside">
                            <p>Limpia todos los resultados guardados. H&aacute;zlo despu&eacute;s de actualizar productos, categor&iacute;as o atributos.</p>
                            <a href="<?= esc_url( add_query_arg( 'nxf_clear_cache', '1' ) ) ?>" class="button button-primary">Limpiar cach&eacute;</a>
                        </div>
                    </div>

                    <div class="postbox">
                        <h2 class="hndle"><span>&#9889; &Iacute;ndices de base de datos</span></h2>
                        <div class="inside">
                            <p>Crea &iacute;ndices en <code>wp_postmeta</code> y <code>wc_product_meta_lookup</code> para mejorar la velocidad del filtro.</p>
                            <a href="<?= esc_url( add_query_arg( 'nxf_apply_indexes', '1' ) ) ?>" class="button">Aplicar &iacute;ndices</a>
                            <?php if ( get_option( 'nxf_indexes_applied' ) ): ?>
                                <span class="nxf-ok-badge">&#9989; Ya aplicados</span>
                            <?php endif ?>
                        </div>
                    </div>

                    <div class="postbox" style="grid-column: 1 / -1">
                        <h2 class="hndle"><span>&#128203; Resumen de configuraci&oacute;n</span></h2>
                        <div class="inside">
                            <?php
                            $php_config  = Nextech_Rest_Endpoint::get_attr_config_public();
                            $all_slugs   = array_unique( array_merge( array_keys( $php_config ), array_keys( $saved_config ) ) );
                            sort( $all_slugs );
                            ?>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Categor&iacute;a (slug)</th>
                                        <th>Fuente</th>
                                        <th>Atributos configurados</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $all_slugs as $slug ):
                                        $from_admin = isset( $saved_config[ $slug ] );
                                        $config     = $from_admin ? $saved_config[ $slug ] : ( $php_config[ $slug ] ?? [] );
                                        $term       = get_term_by( 'slug', $slug, 'product_cat' );
                                        $names      = [];
                                        foreach ( $config as $item ) {
                                            $names[] = $from_admin
                                                ? ( $item['nombre'] ?? $item['slug'] ?? '?' )
                                                : ( isset( $item['nombre'] ) ? $item['nombre'] : ( $item['slug'] ?? '?' ) );
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <?= $term ? esc_html( $term->name ) : '&mdash;' ?>
                                            <br><code><?= esc_html( $slug ) ?></code>
                                        </td>
                                        <td>
                                            <?php if ( $from_admin ): ?>
                                                <span class="nxf-badge-admin">Admin UI</span>
                                            <?php else: ?>
                                                <span class="nxf-badge-php">PHP</span>
                                            <?php endif ?>
                                        </td>
                                        <td><?= $names ? esc_html( implode( ', ', $names ) ) : '<em>Sin filtros</em>' ?></td>
                                    </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div><!-- #nxf-tab-herramientas -->

        </div><!-- .nxf-admin-wrap -->
        <?php
    }

    // ── AJAX handlers ────────────────────────────────────────────────────────

    public static function ajax_load(): void {
        check_ajax_referer( 'nxf_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( -1 );

        $slug = sanitize_text_field( $_POST['slug'] ?? '' );
        if ( ! $slug ) wp_send_json_error( 'Slug requerido' );

        $saved = get_option( self::OPTION_KEY, [] );

        if ( isset( $saved[ $slug ] ) ) {
            wp_send_json_success( [ 'config' => $saved[ $slug ], 'source' => 'admin' ] );
        }

        // Fallback: load from PHP hardcoded config
        $php = Nextech_Rest_Endpoint::get_attr_config_public();
        if ( isset( $php[ $slug ] ) ) {
            wp_send_json_success( [ 'config' => $php[ $slug ], 'source' => 'php' ] );
        }

        wp_send_json_success( [ 'config' => [], 'source' => 'none' ] );
    }

    public static function ajax_save(): void {
        check_ajax_referer( 'nxf_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( -1 );

        $slug   = sanitize_text_field( $_POST['slug'] ?? '' );
        $config = json_decode( stripslashes( $_POST['config'] ?? '[]' ), true );

        if ( ! $slug || ! is_array( $config ) ) wp_send_json_error( 'Datos inv&aacute;lidos' );

        $all           = get_option( self::OPTION_KEY, [] );
        $all[ $slug ]  = $config;
        update_option( self::OPTION_KEY, $all );

        // Invalidate filter cache for this category
        delete_transient( 'nxf_filtros_' . md5( $slug ) . '_v1' );

        wp_send_json_success();
    }

    public static function ajax_delete(): void {
        check_ajax_referer( 'nxf_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( -1 );

        $slug = sanitize_text_field( $_POST['slug'] ?? '' );
        if ( ! $slug ) wp_send_json_error();

        $all = get_option( self::OPTION_KEY, [] );
        unset( $all[ $slug ] );
        update_option( self::OPTION_KEY, $all );
        delete_transient( 'nxf_filtros_' . md5( $slug ) . '_v1' );

        wp_send_json_success();
    }
}
