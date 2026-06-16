<?php
/**
 * Plugin Name: PC Gamer Configurator
 * Description: Permite agregar productos extra en carruseles antes de la descripción del producto principal (PC Gamer), y que se agreguen como productos simples al carrito.
 * Version: 0.6.7
 * Author: ManuelReyes RST
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Include all required files
require_once plugin_dir_path(__FILE__) . 'includes/class-cartgroup.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-onepercategory.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-pricetotal.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-customprice.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-pricerestore.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-compatibility-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-compatibility-metabox.php';

class PCGamerConfigurator {

    /** @var CompatibilityManager */
    private $compatibility_manager;

    /** @var CompatibilityMetabox */
    private $compatibility_metabox;

    // Orden correcto: CPU → Placa → RAM → Almacenamiento → Fuente → Gabinete → Refrigeración → extras
    private $plugin_categories = [
        'Procesadores PC Armado'    => 'Selección de procesador',
        'Placas PC Armado'          => 'Selección de placa madre',
        'Memoria RAM PC Armado'     => 'Selección de memoria RAM',
        'Almacenamiento PC Armado'  => 'Selección de almacenamiento',
        'Fuente de Poder PC Armado' => 'Selección de fuente de poder',
        'Gabinetes PC Armado'       => 'Selección de gabinete',
        'refrigeracion'             => 'Selección de refrigeración de procesador',
        'Accesorios PC Armado'      => 'Agregar Accesorios',
        'Monitores'                 => 'Agregar monitor',
    ];

    public function __construct() {
        // Instanciar módulos de compatibilidad
        $this->compatibility_manager = new CompatibilityManager();
        $this->compatibility_metabox = new CompatibilityMetabox();

        add_action('admin_menu', [ $this, 'admin_menu' ]);
        add_action('add_meta_boxes', [ $this, 'register_metabox' ]);
        add_action('save_post', [ $this, 'save_product_upgrades' ]);
        add_action('woocommerce_before_add_to_cart_button', [ $this, 'render_upgrades_frontend' ], 5);
        add_filter('woocommerce_add_cart_item_data', [ $this, 'capture_selected_extras' ], 10, 2);
        add_action('woocommerce_add_to_cart', [ $this, 'add_extras_once_main_added' ], 20, 6);
        add_filter('woocommerce_product_supports', [ $this, 'force_disable_ajax' ], 10, 3);
        add_action('wp_enqueue_scripts',    [ $this, 'enqueue_styles' ]);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ]);

        // AJAX: precios
        add_action('wp_ajax_pcgamer_sync_category_prices',    [ $this, 'ajax_sync_category_prices' ]);
        add_action('wp_ajax_pcgamer_category_sync_and_save',  [ $this, 'ajax_category_sync_and_save' ]);
        // AJAX: compatibilidad masiva
        add_action('wp_ajax_pcgamer_save_compatibility_bulk', [ $this, 'ajax_save_compatibility_bulk' ]);
        // Eliminar pestañas "Descripción" e "Información Adicional" en productos del configurador
        add_filter('woocommerce_product_tabs', [ $this, 'remove_additional_info_tab' ], 98);
        // Vaciar descripción (ya se muestra la info en el bloque dedicado)
        add_filter('the_content', [ $this, 'remove_description_table' ], 20);
        // Mostrar información del producto (Windows, garantía, etc.) junto al SKU/Categorías
        add_action('woocommerce_product_meta_end', [ $this, 'render_product_info_section' ]);
        // Quitar líneas redundantes de la descripción corta en productos del configurador
        add_filter('woocommerce_short_description', [ $this, 'clean_short_description' ], 20);

        // Sincronización automática de precios: cuando cambia el precio de un componente en WC,
        // se propaga automáticamente a todos los PC base que lo usen.
        add_action('woocommerce_product_object_updated_props', [ $this, 'auto_sync_price_on_update' ], 10, 2);

        // Mantener la tabla nextech_component_specs actualizada al guardar compatibilidad
        add_action('woocommerce_update_product', [ $this, 'sync_component_specs_table' ], 20 );
    }

    public function enqueue_styles() {
        // Solo cargar en páginas de producto individual
        if ( ! is_product() ) return;

        $plugin_url = plugin_dir_url(__FILE__);
        wp_enqueue_style('pcgamer-configurator-styles', $plugin_url . 'assets/style.css', [], '1.3.4');
        wp_enqueue_script('pcgamer-checkbox-control',        $plugin_url . 'assets/checkbox-control.js',        [], '1.4',   true);
        wp_enqueue_script('pcgamer-carousel-dropdown',       $plugin_url . 'assets/carousel-dropdown.js',       [], '1.2',   true);
        wp_enqueue_script('pcgamer-mobile-carousel',         $plugin_url . 'assets/mobile-carousel.js',         [], '1.0.1', true);
        wp_enqueue_script('pcgamer-mobile-enhancements',     $plugin_url . 'assets/mobile-enhancements.js',     [], '1.1.1', true);
        wp_enqueue_script('pcgamer-compatibility-filters',   $plugin_url . 'assets/compatibility-filters.js',   [], '0.9.0', true);

        wp_localize_script('pcgamer-compatibility-filters', 'pcgamerAjax', [
            'nonce'   => wp_create_nonce('pcgamer_compatibility'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);

    }

    public function enqueue_admin_assets( $hook ) {
        $plugin_url = plugin_dir_url( __FILE__ );

        // Metabox script — cargar en editor de productos
        if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            $screen = get_current_screen();
            if ( $screen && $screen->post_type === 'product' ) {
                wp_enqueue_script( 'pcgamer-admin-metabox-js', $plugin_url . 'assets/admin-metabox.js', [], '1.0.0', true );
            }
        }

        if ( $hook !== 'toplevel_page_pcgamer-config' ) return;

        wp_enqueue_style( 'pcgamer-admin-styles', $plugin_url . 'assets/admin.css', [], '1.0.0' );
        wp_enqueue_script( 'pcgamer-admin-js', $plugin_url . 'assets/admin.js', [ 'jquery' ], '1.0.0', true );

        $compat_nonce = wp_create_nonce( 'pcgamer_compatibility_bulk' );
        wp_enqueue_script( 'pcgamer-admin-compat-js', $plugin_url . 'assets/admin-compat.js', [ 'jquery' ], '1.0.0', true );
        wp_localize_script( 'pcgamer-admin-compat-js', 'pcgamerAdminCompat', [
            'nonce' => $compat_nonce,
        ] );
    }

    public function admin_menu() {
        add_menu_page('PC Gamer Configurator', 'PC Gamer Config', 'manage_options', 'pcgamer-config', [ $this, 'admin_page' ]);
    }

    public function admin_page() {
        $synced_products_per_category = get_option('pcgamer_synced_products_per_category', []);
        $sync_custom_prices = get_option('pcgamer_sync_custom_prices', []);
        $last_synced = get_option('pcgamer_categories_last_synced', []);
        $pcgamer_category_enabled = get_option('pcgamer_category_enabled', []);

        // Handle clear custom prices action
        if (isset($_POST['pcgamer_clear_custom_prices']) && check_admin_referer('pcgamer_config_settings', 'pcgamer_nonce')) {
            update_option('pcgamer_sync_custom_prices', []);
            $sync_custom_prices = [];
            echo '<div class="notice notice-success is-dismissible"><p>Todos los precios personalizados han sido limpiados.</p></div>';
        }

        // Handle ON/OFF toggles for categories (AJAX)
        if (isset($_POST['pcgamer_toggle_category']) && isset($_POST['category']) && check_admin_referer('pcgamer_config_settings', 'pcgamer_nonce')) {
            $cat = sanitize_text_field($_POST['category']);
            $val = ($_POST['value'] === '1') ? 1 : 0;
            $pcgamer_category_enabled[$cat] = $val;
            update_option('pcgamer_category_enabled', $pcgamer_category_enabled);
            wp_send_json_success(['enabled' => $val]);
            exit;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'precios';
        ?>
        <div class="pcgamer-admin-container">

            <!-- Navegación de pestañas -->
            <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=pcgamer-config&tab=precios')); ?>"
                   class="nav-tab <?php echo $active_tab === 'precios' ? 'nav-tab-active' : ''; ?>">
                    💰 Precios por Categoría
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=pcgamer-config&tab=compatibilidad')); ?>"
                   class="nav-tab <?php echo $active_tab === 'compatibilidad' ? 'nav-tab-active' : ''; ?>">
                    🔧 Compatibilidad de Componentes
                </a>
            </nav>

            <?php if ($active_tab === 'compatibilidad'): ?>
                <?php $this->render_compatibility_tab(); ?>
            <?php else: ?>

            <form method="post" action="" style="margin-bottom:20px;">
                <?php wp_nonce_field('pcgamer_config_settings', 'pcgamer_nonce'); ?>
                <button type="submit" name="pcgamer_clear_custom_prices" class="button" style="background:#e53935;color:#fff;border:none;padding:8px 18px;font-weight:600;">
                    Limpiar precios personalizados
                </button>
            </form>
            <div class="pcgamer-admin-card">
                <h1 style="margin-top:0;">PC Gamer Configurator Settings</h1>
                <h2>Control de Precios Por Categoría</h2>
                <p>Active/desactive cada categoría con el interruptor ON/OFF. Solo las categorías activadas pueden sincronizar y guardar precios desde el panel.</p>
                <form id="pcgamer-category-sync-form" method="post" action="">
                    <?php wp_nonce_field('pcgamer_config_settings', 'pcgamer_nonce'); ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <?php foreach ($this->plugin_categories as $slug => $label): 
                                $enabled = isset($pcgamer_category_enabled[$slug]) ? (bool)$pcgamer_category_enabled[$slug] : true;
                                ?>
                                <tr>
                                    <th scope="row" style="vertical-align:top;">
                                        <label><?php echo esc_html($label); ?></label>
                                        <div class="pcgamer-toggle-switch" style="margin-top:8px;">
                                            <label class="switch">
                                                <input type="checkbox" class="pcgamer-category-toggle" data-category="<?php echo esc_attr($slug); ?>" <?php echo $enabled ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <span class="toggle-label" style="margin-left:8px;font-weight:600;"><?php echo $enabled ? 'ON' : 'OFF'; ?></span>
                                        </div>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <label><strong>Productos a sincronizar en esta categoría:</strong></label>
                                            <?php
                                            $args = [
                                                'post_type' => 'product',
                                                'posts_per_page' => -1,
                                                'post_status' => 'publish',
                                                'tax_query' => [[ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slug ]]
                                            ];
                                            $products = get_posts($args);
                                            $selected_products = isset($synced_products_per_category[$slug]) ? $synced_products_per_category[$slug] : [];
                                            $disabled_attr = $enabled ? '' : 'disabled';
                                            ?>
                                            <div style="margin-bottom:8px;display:flex;gap:10px;align-items:center;">
                                                <button type="button" class="button pcgamer-select-all-btn" data-table="pcgamer-sync-table-<?php echo esc_attr(sanitize_title($slug)); ?>" <?php echo $disabled_attr; ?>>Seleccionar todos</button>
                                                <button type="button" class="button pcgamer-deselect-all-btn" data-table="pcgamer-sync-table-<?php echo esc_attr(sanitize_title($slug)); ?>" <?php echo $disabled_attr; ?>>Deseleccionar todos</button>
                                                <label style="margin-left:15px;font-weight:normal;">
                                                    Ordenar por precio:
                                                    <select class="pcgamer-sort-price" data-table="pcgamer-sync-table-<?php echo esc_attr(sanitize_title($slug)); ?>" <?php echo $disabled_attr; ?>>
                                                        <option value="asc">Menor a mayor</option>
                                                        <option value="desc">Mayor a menor</option>
                                                    </select>
                                                </label>
                                            </div>
                                            <input type="text" class="pcgamer-sync-search" placeholder="Buscar producto..." data-table="pcgamer-sync-table-<?php echo esc_attr(sanitize_title($slug)); ?>" style="margin:8px 0 12px 0;max-width:350px;width:100%;" <?php echo $disabled_attr; ?>>
                                            <div style="max-height:220px;overflow:auto;border:1px solid #e0e0e0;border-radius:4px;">
                                            <table class="wp-list-table widefat striped pcgamer-sync-table" id="pcgamer-sync-table-<?php echo esc_attr(sanitize_title($slug)); ?>" style="margin:0;">
                                                <thead>
                                                    <tr>
                                                        <th style="width:40px;text-align:center;">Sync</th>
                                                        <th>Producto</th>
                                                        <th style="width:100px;">Precio Woo</th>
                                                        <th style="width:120px;">Precio personalizado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($products as $p): 
                                                        $wc_product = wc_get_product($p->ID);
                                                        if (!$wc_product) continue;
                                                        $checked = in_array($p->ID, $selected_products) ? 'checked' : '';
                                                        $woo_price = $wc_product->get_sale_price();
                                                        if (!$woo_price || $woo_price === '') $woo_price = $wc_product->get_regular_price();
                                                        $custom_price = (array_key_exists($p->ID, $sync_custom_prices)) ? $sync_custom_prices[$p->ID] : '';
                                                        ?>
                                                        <tr data-price="<?php echo esc_attr($woo_price); ?>">
                                                            <td style="text-align:center;">
                                                                <input type="checkbox" name="pcgamer_synced_products_<?php echo esc_attr(sanitize_title($slug)); ?>[]" value="<?php echo esc_attr($p->ID); ?>" <?php echo $checked; ?> <?php echo $disabled_attr; ?>>
                                                            </td>
                                                            <td>
                                                                <?php echo esc_html($p->post_title); ?> <span style="color:#888;font-size:11px;">(ID: <?php echo $p->ID; ?>)</span>
                                                            </td>
                                                            <td>
                                                                <?php echo wc_price($woo_price); ?>
                                                            </td>
                                                            <td>
                                                                <input type="number" step="0.01" min="0" name="pcgamer_sync_custom_prices[<?php echo esc_attr($p->ID); ?>]" value="<?php echo ($custom_price !== '' ? esc_attr($custom_price) : ''); ?>" style="width:100px;" placeholder="Opcional" <?php echo $disabled_attr; ?>>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            </div>
                                            <p class="description">Busque y seleccione los productos a sincronizar. Puede definir un precio personalizado para cada producto (solo afecta compras por el configurador).</p>
                                            <button type="button"
                                                class="button button-primary pcgamer-category-sync-btn"
                                                data-category="<?php echo esc_attr($slug); ?>"
                                                data-label="<?php echo esc_attr($label); ?>"
                                                data-nonce="<?php echo wp_create_nonce('pcgamer_sync_save_' . sanitize_title($slug)); ?>"
                                                style="margin-top:10px;"
                                                <?php echo $disabled_attr; ?>>
                                                Sincronizar y Guardar
                                            </button>
                                            <span class="spinner" style="float:none;vertical-align:middle;"></span>
                                            <span class="pcgamer-category-sync-result"></span>
                                            <?php if (isset($last_synced[$slug])): ?>
                                                <p class="description" style="margin-top:6px;">
                                                    Última sincronización: <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_synced[$slug])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </fieldset>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>
            <?php endif; // fin else (tab precios) ?>

        </div><!-- cierre .pcgamer-admin-container -->
        <?php
    }

    // ── TAB COMPATIBILIDAD ────────────────────────────────────────────────────

    private function render_compatibility_tab() {
        $nonce = wp_create_nonce('pcgamer_compatibility_bulk');

        $categories_config = [
            'Procesadores PC Armado'    => ['label' => 'Procesadores',    'fields' => ['socket', 'ram_type']],
            'Placas PC Armado'          => ['label' => 'Placas Madre',     'fields' => ['socket', 'form_factor']],
            'Memoria RAM PC Armado'     => ['label' => 'Memoria RAM',      'fields' => ['ram_type']],
            'Fuente de Poder PC Armado' => ['label' => 'Fuentes de Poder', 'fields' => ['wattage']],
            'Gabinetes PC Armado'       => ['label' => 'Gabinetes',        'fields' => ['form_factors', 'cooler_clearance', 'radiator_support']],
            'refrigeracion'             => ['label' => 'Refrigeración',    'fields' => ['socket', 'cooler_height', 'radiator_size']],
        ];

        $field_labels = [
            'socket'           => 'Socket',
            'ram_type'         => 'Tipo RAM',
            'form_factor'      => 'Form Factor',
            'form_factors'     => 'Form Factors soportados',
            'wattage'          => 'Wattaje (W)',
            'cooler_height'    => 'Altura Cooler (mm)',
            'cooler_clearance' => 'Espacio para Cooler (mm)',
            'radiator_size'    => 'Tamaño Radiador (mm)',
            'radiator_support' => 'Radiadores soportados',
        ];

        $ram_options  = ['DDR4', 'DDR5', 'DDR4,DDR5'];
        $ff_options   = ['ATX', 'Micro-ATX', 'Mini-ITX'];
        $rad_options  = ['120', '240', '360'];
        ?>
        <div class="pcgamer-admin-card">
            <h1 style="margin-top:0;">🔧 Compatibilidad de Componentes</h1>
            <p style="color:#555;max-width:700px;">
                Llena las especificaciones técnicas de todos tus componentes desde aquí.
                Solo se muestran los campos relevantes para cada tipo de categoría.
                Al hacer click en <strong>Guardar todo</strong> se actualiza cada producto de una sola vez.
            </p>

            <div id="pcgamer-compat-notice" style="display:none;padding:10px 16px;border-radius:4px;margin-bottom:16px;font-weight:600;"></div>

            <?php foreach ($categories_config as $slug => $config):
                $fields = $config['fields'];

                $synced = get_option('pcgamer_synced_products_per_category', []);
                if (!empty($synced[$slug])) {
                    $product_ids = $synced[$slug];
                    $products    = array_filter(array_map('wc_get_product', $product_ids));
                } else {
                    $term = get_term_by('name', $slug, 'product_cat');
                    if (!$term) $term = get_term_by('slug', sanitize_title($slug), 'product_cat');
                    $products = [];
                    if ($term && !is_wp_error($term)) {
                        $posts = get_posts([
                            'post_type'      => 'product',
                            'posts_per_page' => -1,
                            'post_status'    => 'publish',
                            'tax_query'      => [[
                                'taxonomy' => 'product_cat',
                                'field'    => 'term_id',
                                'terms'    => $term->term_id,
                            ]],
                        ]);
                        foreach ($posts as $p) {
                            $wcp = wc_get_product($p->ID);
                            if ($wcp) $products[$p->ID] = $wcp;
                        }
                    }
                }

                if (empty($products)) continue;
                ?>
                <div style="margin-bottom:36px;">
                    <h2 style="border-bottom:2px solid #1a73e8;padding-bottom:6px;color:#1a73e8;">
                        <?php echo esc_html($config['label']); ?>
                        <span style="font-size:13px;font-weight:400;color:#888;margin-left:8px;">(<?php echo count($products); ?> productos)</span>
                    </h2>

                    <div style="overflow-x:auto;">
                    <table class="wp-list-table widefat striped" style="min-width:600px;">
                        <thead>
                            <tr>
                                <th style="min-width:220px;">Producto</th>
                                <?php foreach ($fields as $f): ?>
                                    <th style="min-width:<?php echo $f === 'form_factors' || $f === 'radiator_support' ? '200px' : '140px'; ?>;">
                                        <?php echo esc_html($field_labels[$f]); ?>
                                    </th>
                                <?php endforeach; ?>
                                <th style="width:80px;text-align:center;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product):
                                $pid          = $product->get_id();
                                $cur_socket   = get_post_meta($pid, '_pcgamer_socket',           true);
                                $cur_ram      = get_post_meta($pid, '_pcgamer_ram_type',         true);
                                $cur_ff       = get_post_meta($pid, '_pcgamer_form_factor',      true);
                                $cur_ffs      = get_post_meta($pid, '_pcgamer_form_factors',     true) ?: [];
                                $cur_wattage  = get_post_meta($pid, '_pcgamer_wattage',          true);
                                $cur_ch       = get_post_meta($pid, '_pcgamer_cooler_height',    true);
                                $cur_cc       = get_post_meta($pid, '_pcgamer_cooler_clearance', true);
                                $cur_rad_size = get_post_meta($pid, '_pcgamer_radiator_size',    true);
                                $cur_rad_sup  = get_post_meta($pid, '_pcgamer_radiator_support', true) ?: [];

                                $has_data = false;
                                foreach ($fields as $f) {
                                    if ($f === 'socket')               $val = $cur_socket;
                                    elseif ($f === 'ram_type')         $val = $cur_ram;
                                    elseif ($f === 'form_factor')      $val = $cur_ff;
                                    elseif ($f === 'form_factors')     $val = !empty($cur_ffs);
                                    elseif ($f === 'wattage')          $val = $cur_wattage;
                                    elseif ($f === 'cooler_height')    $val = $cur_ch;
                                    elseif ($f === 'cooler_clearance') $val = $cur_cc;
                                    elseif ($f === 'radiator_size')    $val = ($cur_rad_size !== '');
                                    elseif ($f === 'radiator_support') $val = !empty($cur_rad_sup);
                                    else                               $val = '';
                                    if ($val) { $has_data = true; break; }
                                }
                                ?>
                                <tr data-product-id="<?php echo $pid; ?>">
                                    <td>
                                        <strong><?php echo esc_html($product->get_name()); ?></strong>
                                        <br><span style="color:#999;font-size:11px;">ID: <?php echo $pid; ?></span>
                                    </td>

                                    <?php foreach ($fields as $f): ?>
                                    <td>
                                        <?php if ($f === 'socket'): ?>
                                            <input type="text" class="pcgamer-compat-field" data-field="socket"
                                                value="<?php echo esc_attr($cur_socket); ?>" placeholder="AM4, LGA1700…"
                                                style="width:100%;max-width:130px;padding:5px 7px;" />

                                        <?php elseif ($f === 'ram_type'): ?>
                                            <select class="pcgamer-compat-field" data-field="ram_type" style="width:100%;max-width:150px;padding:5px 7px;">
                                                <option value="">— Sin especificar —</option>
                                                <?php
                                                $ram_labels = ['DDR4' => 'DDR4', 'DDR5' => 'DDR5', 'DDR4,DDR5' => 'DDR4/DDR5 (dual)'];
                                                foreach ($ram_labels as $ro => $rl): ?>
                                                    <option value="<?php echo esc_attr($ro); ?>" <?php selected($cur_ram, $ro); ?>><?php echo esc_html($rl); ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                        <?php elseif ($f === 'form_factor'): ?>
                                            <select class="pcgamer-compat-field" data-field="form_factor" style="width:100%;max-width:130px;padding:5px 7px;">
                                                <option value="">— Sin especificar —</option>
                                                <?php foreach ($ff_options as $ffo): ?>
                                                    <option value="<?php echo $ffo; ?>" <?php selected($cur_ff, $ffo); ?>><?php echo $ffo; ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                        <?php elseif ($f === 'form_factors'): ?>
                                            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                                <?php foreach ($ff_options as $ffo): ?>
                                                    <label style="white-space:nowrap;font-weight:normal;">
                                                        <input type="checkbox" class="pcgamer-compat-ff" value="<?php echo esc_attr($ffo); ?>"
                                                            <?php checked(in_array($ffo, (array)$cur_ffs)); ?> />
                                                        <?php echo esc_html($ffo); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>

                                        <?php elseif ($f === 'wattage'): ?>
                                            <input type="number" class="pcgamer-compat-field" data-field="wattage"
                                                value="<?php echo esc_attr($cur_wattage); ?>" placeholder="650"
                                                min="0" step="50" style="width:90px;padding:5px 7px;" />

                                        <?php elseif ($f === 'cooler_height'): ?>
                                            <input type="number" class="pcgamer-compat-field" data-field="cooler_height"
                                                value="<?php echo esc_attr($cur_ch); ?>" placeholder="165"
                                                min="0" style="width:90px;padding:5px 7px;" />

                                        <?php elseif ($f === 'cooler_clearance'): ?>
                                            <input type="number" class="pcgamer-compat-field" data-field="cooler_clearance"
                                                value="<?php echo esc_attr($cur_cc); ?>" placeholder="170"
                                                min="0" style="width:90px;padding:5px 7px;" />

                                        <?php elseif ($f === 'radiator_size'): ?>
                                            <select class="pcgamer-compat-field" data-field="radiator_size" style="width:100%;max-width:130px;padding:5px 7px;">
                                                <option value="" <?php selected($cur_rad_size, ''); ?>>— Aire / Sin radiador —</option>
                                                <?php foreach ($rad_options as $ro): ?>
                                                    <option value="<?php echo $ro; ?>" <?php selected($cur_rad_size, $ro); ?>><?php echo $ro; ?> mm</option>
                                                <?php endforeach; ?>
                                            </select>

                                        <?php elseif ($f === 'radiator_support'): ?>
                                            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                                <?php foreach ($rad_options as $ro): ?>
                                                    <label style="white-space:nowrap;font-weight:normal;">
                                                        <input type="checkbox" class="pcgamer-compat-rad" value="<?php echo esc_attr($ro); ?>"
                                                            <?php checked(in_array($ro, (array)$cur_rad_sup)); ?> />
                                                        <?php echo esc_html($ro); ?>mm
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php endforeach; ?>

                                    <td style="text-align:center;">
                                        <span class="pcgamer-row-status" title="<?php echo $has_data ? 'Datos cargados' : 'Sin datos'; ?>">
                                            <?php echo $has_data ? '✅' : '⬜'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="position:sticky;bottom:0;background:#fff;border-top:2px solid #1a73e8;padding:14px 0;margin-top:10px;z-index:10;">
                <button id="pcgamer-save-compat-btn" class="button button-primary" style="font-size:15px;padding:8px 28px;font-weight:700;">
                    💾 Guardar todo
                </button>
                <span id="pcgamer-save-spinner" class="spinner" style="float:none;vertical-align:middle;margin-left:8px;"></span>
                <span id="pcgamer-save-result" style="margin-left:10px;font-weight:600;"></span>
            </div>
        </div>

        <?php
    }

    // ── AJAX: Guardar compatibilidad masiva ───────────────────────────────────

    public function ajax_save_compatibility_bulk() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pcgamer_compatibility_bulk')) {
            wp_send_json_error(['message' => 'Verificación de seguridad fallida']);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos']);
        }

        $items = json_decode(stripslashes($_POST['items'] ?? ''), true);
        if (!is_array($items)) {
            wp_send_json_error(['message' => 'Datos inválidos']);
        }

        $allowed_ff  = ['ATX', 'Micro-ATX', 'Mini-ITX'];
        $allowed_ram = ['DDR4', 'DDR5', 'DDR4,DDR5'];
        $saved       = 0;

        foreach ($items as $item) {
            $pid = intval($item['id'] ?? 0);
            if (!$pid || get_post_type($pid) !== 'product') continue;

            if (isset($item['socket'])) {
                $val = sanitize_text_field($item['socket']);
                $val ? update_post_meta($pid, '_pcgamer_socket', $val) : delete_post_meta($pid, '_pcgamer_socket');
            }
            if (isset($item['ram_type'])) {
                $val = sanitize_text_field($item['ram_type']);
                ($val && in_array($val, $allowed_ram))
                    ? update_post_meta($pid, '_pcgamer_ram_type', $val)
                    : delete_post_meta($pid, '_pcgamer_ram_type');
            }
            if (isset($item['form_factor'])) {
                $val = sanitize_text_field($item['form_factor']);
                ($val && in_array($val, $allowed_ff))
                    ? update_post_meta($pid, '_pcgamer_form_factor', $val)
                    : delete_post_meta($pid, '_pcgamer_form_factor');
            }
            if (isset($item['form_factors'])) {
                $vals = array_values(array_filter(
                    array_map('sanitize_text_field', (array) $item['form_factors']),
                    fn($v) => in_array($v, $allowed_ff)
                ));
                $vals ? update_post_meta($pid, '_pcgamer_form_factors', $vals)
                      : delete_post_meta($pid, '_pcgamer_form_factors');
            }
            if (isset($item['wattage'])) {
                $val = intval($item['wattage']);
                $val > 0 ? update_post_meta($pid, '_pcgamer_wattage', $val)
                         : delete_post_meta($pid, '_pcgamer_wattage');
            }
            if (isset($item['cooler_height'])) {
                $val = intval($item['cooler_height']);
                $val > 0 ? update_post_meta($pid, '_pcgamer_cooler_height', $val)
                         : delete_post_meta($pid, '_pcgamer_cooler_height');
            }
            if (isset($item['cooler_clearance'])) {
                $val = intval($item['cooler_clearance']);
                $val > 0 ? update_post_meta($pid, '_pcgamer_cooler_clearance', $val)
                         : delete_post_meta($pid, '_pcgamer_cooler_clearance');
            }
            if (array_key_exists('radiator_size', $item)) {
                $val = sanitize_text_field($item['radiator_size']);
                ($val !== '' && in_array($val, ['120', '240', '360'], true))
                    ? update_post_meta($pid, '_pcgamer_radiator_size', $val)
                    : delete_post_meta($pid, '_pcgamer_radiator_size');
            }
            if (isset($item['radiator_support'])) {
                $vals = array_values(array_filter(
                    array_map('sanitize_text_field', (array) $item['radiator_support']),
                    fn($v) => in_array($v, ['120', '240', '360'], true)
                ));
                $vals ? update_post_meta($pid, '_pcgamer_radiator_support', $vals)
                      : delete_post_meta($pid, '_pcgamer_radiator_support');
            }

            $saved++;
        }

        wp_send_json_success(['message' => "Se actualizaron {$saved} productos correctamente."]);
    }

    // ── Eliminar pestañas "Descripción" e "Información Adicional" ────────────

    public function remove_additional_info_tab($tabs) {
        global $product;
        if ($product && get_post_meta($product->get_id(), '_pcgamer_enabled', true) === 'yes') {
            unset($tabs['description']);
            unset($tabs['additional_information']);
        }
        return $tabs;
    }

    // ── Eliminar descripción completa en productos del configurador ───────────

    public function remove_description_table($content) {
        if (!is_singular('product')) return $content;
        global $post;
        if (!$post || get_post_meta($post->ID, '_pcgamer_enabled', true) !== 'yes') return $content;
        // Vaciar la descripción completamente: la información se muestra en el configurador
        return '';
    }

    // ── Limpiar descripción corta: quitar líneas que se muestran en el recuadro azul ──

    public function clean_short_description($excerpt) {
        global $post;
        if (!$post || get_post_meta($post->ID, '_pcgamer_enabled', true) !== 'yes') return $excerpt;

        // Eliminar párrafos que ahora aparecen en el recuadro de información del producto,
        // evitando duplicados. El flag /is permite tags anidados y saltos de línea.
        $patterns = [
            // "Incluye windows instalado y 1 año de garantía"
            '/<p[^>]*>.*?(?:windows instalado|garantia|garant&iacute;a|garant[ií]a).*?<\/p>/is',
            // "Producto a pedido, tiempo de armado y pruebas..."
            '/<(?:p|h[1-6])[^>]*>.*?producto a pedido.*?<\/(?:p|h[1-6])>/is',
            // "Necesitas agregar / cambiar un componente, háblanos Aquí"
            '/<p[^>]*>.*?(?:necesitas agregar|h[aá]blanos).*?<\/p>/is',
        ];

        foreach ($patterns as $pattern) {
            $excerpt = preg_replace($pattern, '', $excerpt);
        }

        return $excerpt;
    }

    /**
     * AJAX handler for syncing category prices with WooCommerce
     */
    public function ajax_sync_category_prices() {
        // Verify nonce
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        
        if (!wp_verify_nonce($nonce, 'pcgamer_sync_prices_' . sanitize_title($category))) {
            wp_send_json_error(['message' => 'Verificación de seguridad fallida']);
            return;
        }
        
        // Check if category exists
        if (!isset($this->plugin_categories[$category])) {
            wp_send_json_error(['message' => 'Categoría no válida']);
            return;
        }
        
        // Get products in this category
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'tax_query' => [[ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $category ]]
        ];
        $products = get_posts($args);
        if (empty($products)) {
            wp_send_json_error(['message' => 'No se encontraron productos en esta categoría']);
            return;
        }

        // Get selected products for sync in this category
        $synced_products_per_category = get_option('pcgamer_synced_products_per_category', []);
        $selected_product_ids = isset($synced_products_per_category[$category]) ? $synced_products_per_category[$category] : [];
        $sync_custom_prices = get_option('pcgamer_sync_custom_prices', []);

        if (empty($selected_product_ids)) {
            wp_send_json_error(['message' => 'No hay productos seleccionados para sincronizar en esta categoría']);
            return;
        }

        $pc_gamer_products = $this->get_pc_gamer_products_using_category($category, $selected_product_ids);

        if (empty($pc_gamer_products)) {
            // List selected products for admin feedback
            $product_titles = [];
            foreach ($selected_product_ids as $pid) {
                $p = get_post($pid);
                if ($p) $product_titles[] = esc_html($p->post_title) . " (ID: $pid)";
            }
            $msg = 'No se encontraron productos PC Gamer configurados con esta categoría usando los productos seleccionados.<br>';
            if (!empty($product_titles)) {
                $msg .= 'Productos seleccionados:<br><ul>';
                foreach ($product_titles as $title) {
                    $msg .= "<li>$title</li>";
                }
                $msg .= '</ul>';
                $msg .= 'Asegúrese de asignar estos productos como upgrades en algún PC Gamer antes de sincronizar.';
            }
            wp_send_json_error(['message' => $msg]);
            return;
        }
        
        // Process each PC Gamer product and update custom prices
        $updated_count = 0;
        foreach ($pc_gamer_products as $pc_product) {
            $upgrades = get_post_meta($pc_product->ID, '_pcgamer_upgrades', true);
            $custom_prices = get_post_meta($pc_product->ID, '_pcgamer_custom_prices', true) ?: [];
            $updated = false;
            if (isset($upgrades[$category]) && is_array($upgrades[$category])) {
                foreach ($upgrades[$category] as $product_id) {
                    if (!empty($selected_product_ids) && !in_array($product_id, $selected_product_ids)) continue;
                    // Use custom sync price if set (including 0), else WooCommerce price
                    if (array_key_exists($product_id, $sync_custom_prices)) {
                        $price = $sync_custom_prices[$product_id];
                    } else {
                        $wc_product = wc_get_product($product_id);
                        if (!$wc_product) continue;
                        $price = $wc_product->get_sale_price();
                        if (!$price || $price === '') {
                            $price = $wc_product->get_regular_price();
                        }
                    }
                    $custom_prices[$product_id] = (float) $price;
                    $updated = true;
                }
                if ($updated) {
                    update_post_meta($pc_product->ID, '_pcgamer_custom_prices', $custom_prices);
                    $updated_count++;
                }
            }
        }
        
        // Update last synced timestamp
        $last_synced = get_option('pcgamer_categories_last_synced', []);
        $last_synced[$category] = time();
        update_option('pcgamer_categories_last_synced', $last_synced);
        
        wp_send_json_success([
            'message' => sprintf('Precios sincronizados: %d productos actualizados', $updated_count),
            'timestamp' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_synced[$category])
        ]);
    }
    
    /**
     * --- FIX: AJAX handler for per-category sync & save ---
     */
    public function ajax_category_sync_and_save() {
        // Always return JSON and exit
        if ( ! defined( 'DOING_AJAX' ) ) define( 'DOING_AJAX', true );
        header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );

        // Validate
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'pcgamer_sync_save_' . sanitize_title($category))) {
            wp_send_json_error(['message' => 'Verificación de seguridad fallida']);
        }
        if (!isset($this->plugin_categories[$category])) {
            wp_send_json_error(['message' => 'Categoría no válida']);
        }
        $products = isset($_POST['products']) && is_array($_POST['products']) ? array_map('intval', $_POST['products']) : [];
        $custom_prices = isset($_POST['custom_prices']) && is_array($_POST['custom_prices']) ? $_POST['custom_prices'] : [];

        // Save selected products for this category
        $synced_products_per_category = get_option('pcgamer_synced_products_per_category', []);
        $synced_products_per_category[$category] = $products;
        update_option('pcgamer_synced_products_per_category', $synced_products_per_category);

        // Save custom prices for these products
        $sync_custom_prices = get_option('pcgamer_sync_custom_prices', []);
        // Remove all previous custom prices for this category's products
        foreach ($sync_custom_prices as $pid => $val) {
            if (in_array($pid, $products)) {
                unset($sync_custom_prices[$pid]);
            }
        }
        // Set new custom prices for checked products
        foreach ($products as $pid) {
            if (isset($custom_prices[$pid]) && $custom_prices[$pid] !== '') {
                $sync_custom_prices[$pid] = floatval($custom_prices[$pid]);
            }
        }
        update_option('pcgamer_sync_custom_prices', $sync_custom_prices);

        // Update custom prices for all PC Gamer products using these upgrades in this category
        $pc_gamer_products = $this->get_pc_gamer_products_using_category($category, $products);
        $updated_count = 0;
        foreach ($pc_gamer_products as $pc_product) {
            $upgrades = get_post_meta($pc_product->ID, '_pcgamer_upgrades', true);
            $custom_prices_meta = get_post_meta($pc_product->ID, '_pcgamer_custom_prices', true) ?: [];
            $updated = false;
            if (isset($upgrades[$category]) && is_array($upgrades[$category])) {
                foreach ($upgrades[$category] as $product_id) {
                    if (!in_array($product_id, $products)) continue;
                    if (isset($custom_prices[$product_id]) && $custom_prices[$product_id] !== '') {
                        $custom_prices_meta[$product_id] = floatval($custom_prices[$product_id]);
                    } else {
                        // Use WooCommerce price if no custom price
                        $wc_product = wc_get_product($product_id);
                        if ($wc_product) {
                            $price = $wc_product->get_sale_price();
                            if ($price === '' || $price === false) {
                                $price = $wc_product->get_regular_price();
                            }
                            $custom_prices_meta[$product_id] = (float) $price;
                        }
                    }
                    $updated = true;
                }
                if ($updated) {
                    update_post_meta($pc_product->ID, '_pcgamer_custom_prices', $custom_prices_meta);
                    $updated_count++;
                }
            }
        }

        // Update last synced timestamp
        $last_synced = get_option('pcgamer_categories_last_synced', []);
        $last_synced[$category] = time();
        update_option('pcgamer_categories_last_synced', $last_synced);

        // Always end with wp_die() to prevent further output
        wp_send_json_success([
            'message' => sprintf('Sincronización y guardado exitoso. %d productos PC Gamer actualizados.', $updated_count),
            'timestamp' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_synced[$category])
        ]);
        wp_die();
    }

    /**
     * Get PC Gamer products that use products from a specific category, filtered by selected products
     */
    private function get_pc_gamer_products_using_category($category, $selected_product_ids = []) {
        $pc_gamer_products = [];
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_pcgamer_enabled',
                    'value' => 'yes',
                ]
            ]
        ];
        $products = get_posts($args);
        foreach ($products as $product) {
            $upgrades = get_post_meta($product->ID, '_pcgamer_upgrades', true);
            if (is_array($upgrades) && isset($upgrades[$category]) && !empty($upgrades[$category])) {
                // Only include if at least one selected product is present
                if (!empty($selected_product_ids) && array_intersect($upgrades[$category], $selected_product_ids)) {
                    $pc_gamer_products[] = $product;
                }
            }
        }
        return $pc_gamer_products;
    }
    
    /**
     * Update custom prices for all products in a category, only for selected products
     */
    private function update_custom_prices_from_woocommerce($pc_gamer_products, $category, $selected_product_ids = []) {
        $updated_count = 0;
        
        foreach ($pc_gamer_products as $pc_product) {
            $upgrades = get_post_meta($pc_product->ID, '_pcgamer_upgrades', true);
            $custom_prices = get_post_meta($pc_product->ID, '_pcgamer_custom_prices', true) ?: [];
            $updated = false;
            
            if (isset($upgrades[$category]) && is_array($upgrades[$category])) {
                foreach ($upgrades[$category] as $product_id) {
                    if (!empty($selected_product_ids) && !in_array($product_id, $selected_product_ids)) continue;
                    $wc_product = wc_get_product($product_id);
                    if (!$wc_product) continue;
                    
                    // Get WooCommerce sale price or regular price
                    $price = $wc_product->get_sale_price();
                    if (!$price || empty($price)) {
                        $price = $wc_product->get_regular_price();
                    }
                    
                    // Update custom price to match WooCommerce price
                    $custom_prices[$product_id] = (float) $price;
                    $updated = true;
                }
                
                if ($updated) {
                    update_post_meta($pc_product->ID, '_pcgamer_custom_prices', $custom_prices);
                    $updated_count++;
                }
            }
        }
        
        return $updated_count;
    }

    public function register_metabox() {
        add_meta_box('pcgamer-upgrades', 'Configuración de Upgrades (Plugin)', [ $this, 'metabox_content' ], 'product', 'normal');
    }

    public function metabox_content($post) {
        $enabled = get_post_meta($post->ID, '_pcgamer_enabled', true);
        $saved_upgrades = get_post_meta($post->ID, '_pcgamer_upgrades', true);
        $custom_prices = get_post_meta($post->ID, '_pcgamer_custom_prices', true) ?: [];
        $synced_categories = get_option('pcgamer_synced_categories', []);

        echo '<p><label><input type="checkbox" name="pcgamer_enabled" value="yes" ' . checked($enabled, 'yes', false) . '> Activar configurador para este producto</label></p>';
        echo '<div id="pcgamer-upgrades-settings" style="' . ($enabled === 'yes' ? '' : 'display: none;') . '">';
        echo '<p>Selecciona los productos para cada categoría y define precios opcionales:</p>';

        foreach ($this->plugin_categories as $slug => $label) {
            $is_synced = isset($synced_categories[$slug]) && $synced_categories[$slug];
            $sync_notice = $is_synced ? ' <span style="color:#d63638;font-weight:normal;">(Precios sincronizados con WooCommerce)</span>' : '';
            
            echo '<label><strong>' . esc_html($label) . $sync_notice . '</strong></label><br />';
            $args = [
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'orderby' => 'meta_value_num',
                'meta_key' => '_price',
                'order' => 'ASC',
                'tax_query' => [[ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slug ]]
            ];
            $posts = get_posts($args);

            echo '<table style="width:100%; max-width:800px;">';
            foreach ($posts as $p) {
                $wc_product = wc_get_product($p->ID);
                if (!$wc_product || $wc_product->get_type() !== 'simple') continue;

                $is_selected = isset($saved_upgrades[$slug]) && in_array($p->ID, $saved_upgrades[$slug]) ? 'checked' : '';
                $real_price = $wc_product->get_price();
                
                // Get sale price or regular price for synced categories
                $woo_price = $wc_product->get_sale_price();
                $woo_price = empty($woo_price) ? $wc_product->get_regular_price() : $woo_price;
                
                // Use either custom price or the synced price
                $custom_price = isset($custom_prices[$p->ID]) ? $custom_prices[$p->ID] : $real_price;
                if ($is_synced && !empty($woo_price)) {
                    $custom_price = $woo_price;
                }

                echo '<tr>';
                echo '<td style="width:60%">';
                echo '<label><input type="checkbox" name="pcgamer_upgrades[' . esc_attr($slug) . '][]" value="' . esc_attr($p->ID) . '" ' . $is_selected . '> ' . esc_html($p->post_title) . ' (' . wc_price($real_price) . ')</label>';
                echo '</td>';
                echo '<td style="width:40%"><input type="number" step="0.01" name="pcgamer_custom_prices[' . esc_attr($p->ID) . ']" placeholder="' . esc_attr($real_price) . '" value="' . esc_attr($custom_price) . '" style="width:100%;"' . ($is_synced ? ' readonly="readonly" disabled="disabled"' : '') . '></td>';
                echo '</tr>';
            }
            echo '</table><br />';
        }

        echo '</div>';
    }

    public function save_product_upgrades($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        update_post_meta($post_id, '_pcgamer_enabled', isset($_POST['pcgamer_enabled']) ? 'yes' : 'no');

        if (isset($_POST['pcgamer_upgrades'])) {
            update_post_meta($post_id, '_pcgamer_upgrades', $_POST['pcgamer_upgrades']);
        } else {
            delete_post_meta($post_id, '_pcgamer_upgrades');
        }

        if (isset($_POST['pcgamer_custom_prices'])) {
            // Get synced categories
            $synced_categories = get_option('pcgamer_synced_categories', []);
            $prices = array_map('floatval', $_POST['pcgamer_custom_prices']);
            
            // Preserve WooCommerce prices for synced categories
            if (!empty($synced_categories)) {
                $existing_prices = get_post_meta($post_id, '_pcgamer_custom_prices', true) ?: [];
                $upgrades = isset($_POST['pcgamer_upgrades']) ? $_POST['pcgamer_upgrades'] : [];
                
                foreach ($synced_categories as $cat_slug => $enabled) {
                    if (!$enabled || !isset($upgrades[$cat_slug])) continue;
                    
                    // For each product in this synced category
                    foreach ($upgrades[$cat_slug] as $product_id) {
                        $wc_product = wc_get_product($product_id);
                        if (!$wc_product) continue;
                        
                        // Use WooCommerce price instead of submitted price
                        $woo_price = $wc_product->get_sale_price();
                        $woo_price = empty($woo_price) ? $wc_product->get_regular_price() : $woo_price;
                        
                        if (!empty($woo_price)) {
                            $prices[$product_id] = (float) $woo_price;
                        }
                    }
                }
            }
            
            update_post_meta($post_id, '_pcgamer_custom_prices', $prices);
        }
    }

    public function render_upgrades_frontend() {
        global $product;
        if (!$product instanceof WC_Product || $product->get_type() !== 'simple') return;

        $enabled = get_post_meta($product->get_id(), '_pcgamer_enabled', true);
        if ($enabled !== 'yes') return;

        $upgrades = get_post_meta($product->get_id(), '_pcgamer_upgrades', true);
        if (!$upgrades || !is_array($upgrades)) return;
        
        $custom_prices = get_post_meta($product->get_id(), '_pcgamer_custom_prices', true) ?: [];

        echo '<div class="pcgamer-upgrades-wrapper" data-compatibility-nonce="' . esc_attr(wp_create_nonce('pcgamer_compatibility')) . '">';
        echo '<div class="pcgamer-title-row">';
        echo '<h2>🛠️ Personaliza tu PC Gamer</h2>';
        echo '<button type="button" id="pcgamer-reset-all" title="Borrar todas las selecciones y empezar de nuevo">↺ Borrar selección</button>';
        echo '</div>';

        $required_steps = [
            'Procesadores PC Armado',
            'Placas PC Armado',
            'Memoria RAM PC Armado',
            'Almacenamiento PC Armado',
            'Fuente de Poder PC Armado',
            'Gabinetes PC Armado',
            'refrigeracion',
        ];
        $step_counter = 0;

        // ── Batch-load: recolectar todos los IDs de upgrades y cargarlos de
        //    una sola vez con una única query, evitando el patrón N+1.
        $all_upgrade_ids = [];
        foreach ( $this->plugin_categories as $slug => $label ) {
            if ( ! empty( $upgrades[ $slug ] ) ) {
                foreach ( $upgrades[ $slug ] as $uid ) {
                    $all_upgrade_ids[] = (int) $uid;
                }
            }
        }
        $all_upgrade_ids = array_unique( array_filter( $all_upgrade_ids ) );

        $upgrade_products_map = [];
        if ( ! empty( $all_upgrade_ids ) ) {
            $loaded = wc_get_products( [
                'include' => $all_upgrade_ids,
                'limit'   => -1,
                'status'  => 'publish',
                'return'  => 'objects',
            ] );
            foreach ( $loaded as $lp ) {
                $upgrade_products_map[ $lp->get_id() ] = $lp;
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        foreach ($this->plugin_categories as $slug => $label) {
            if (empty($upgrades[$slug])) continue;

            $sorted_products = [];
            foreach ($upgrades[$slug] as $upgrade_id) {
                $upgrade_product = $upgrade_products_map[ (int) $upgrade_id ] ?? null;
                if (!$upgrade_product) continue;

                // Only skip products that are explicitly marked as out of stock
                if (!$upgrade_product->is_in_stock()) {
                    continue;
                }

                $regular_price = $upgrade_product->get_price();
                $special_price = isset($custom_prices[$upgrade_id]) ? $custom_prices[$upgrade_id] : $regular_price;

                $sorted_products[$upgrade_id] = [
                    'product'        => $upgrade_product,
                    'price'          => $special_price,
                    'original_price' => $regular_price,
                ];
            }

            // Skip rendering this category if no products are in stock
            if (empty($sorted_products)) continue;

            uasort($sorted_products, function($a, $b) {
                return $a['price'] <=> $b['price'];
            });

            $is_required = in_array($slug, $required_steps);
            if ($is_required) $step_counter++;
            $step_attr   = $is_required ? ' data-step="' . $step_counter . '"' : '';
            $locked_attr = ' data-locked="false"'; // Todos los pasos abiertos (sin candados)

            echo '<div class="pcgamer-category-dropdown"' . $step_attr . $locked_attr . '>';
            echo '<div class="pcgamer-dropdown-header">';
            if ($is_required) {
                echo '<span class="pcgamer-step-number" style="display:inline-block;width:22px;height:22px;line-height:22px;text-align:center;background:#1a73e8;color:#fff;border-radius:50%;font-size:12px;font-weight:700;margin-right:8px;">' . $step_counter . '</span>';
            }
            echo '<h3 style="display:inline;">' . esc_html($label) . '</h3>';
            echo '<span class="pcgamer-dropdown-icon">▼</span>';
            echo '</div>';
            
            echo '<div class="pcgamer-dropdown-content">';
            
            echo '<div class="pcgamer-carousel-container">';
            echo '<button type="button" class="pcgamer-carousel-nav prev" aria-label="Anterior">❮</button>';
            
            echo '<div class="pcgamer-carousel-wrapper" data-category="' . esc_attr($slug) . '" data-category-label="' . esc_attr($label) . '">';
            echo '<div class="pcgamer-carousel">';
        
            $first_item = true;
            foreach ($sorted_products as $upgrade_id => $product_data) {
                $upgrade_product = $product_data['product'];
                $price_to_use = $product_data['price'];
                $regular_price = $product_data['original_price'];
                
                $price_html = wc_price($price_to_use);
        
                echo '<div class="upgrade-item">';
                echo $upgrade_product->get_image();
                echo '<p><strong>' . esc_html($upgrade_product->get_name()) . '</strong></p>';
                echo '<p class="upgrade-price">' . $price_html . '</p>';
                
                $checked = $first_item && !in_array($slug, ['Accesorios PC Armado', 'Monitores']) ? 'checked' : '';
                echo '<label>';
                echo '<input type="checkbox" name="pcgamer_extra[]" value="' . esc_attr($upgrade_id) . '" data-price="' . esc_attr($price_to_use) . '" data-product-name="' . esc_attr($upgrade_product->get_name()) . '" ' . $checked . ' />'; 
                echo '<span class="select-button">Seleccionar</span>';
                echo '</label>';
                
                echo '<input type="hidden" name="pcgamer_special_prices[' . esc_attr($upgrade_id) . ']" value="' . esc_attr($price_to_use) . '">';
                
                echo '</div>';
                $first_item = false;
            }
        
            echo '</div>';
            echo '</div>';
            
            echo '<button type="button" class="pcgamer-carousel-nav next" aria-label="Siguiente">❯</button>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
        }

        // Mensaje de validación al final, debajo de todos los pasos incluido "Agregar monitor"
        echo '<div class="pcgamer-validation-messages" style="margin-top:16px;"></div>';

        echo '</div>';
    }

    // ── Información del producto (SKU / Categorías) ───────────────────────────

    public function render_product_info_section() {
        global $product;
        if (!$product || get_post_meta($product->get_id(), '_pcgamer_enabled', true) !== 'yes') return;

        // Intentar extraer el link de "háblanos Aquí" del short description original (sin filtrar).
        // Se prueban varios patrones en orden de especificidad.
        $raw_excerpt = $product->get_short_description();
        $contact_url = '';
        $url_patterns = [
            // Link cuyo texto es "Aquí" o "Aqui"
            '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>\s*[Aa]qu[ií]\s*<\/a>/i',
            // Cualquier link dentro de un párrafo que contenga "háblanos" o "hablanos"
            '/h[aá]blanos[^<]*<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i',
            // Primer link de cualquier tipo en el excerpt (último recurso)
            '/<a[^>]+href=["\']([^"\'#][^"\']+)["\'][^>]*>/i',
        ];
        foreach ($url_patterns as $pat) {
            if (preg_match($pat, $raw_excerpt, $m)) {
                $contact_url = $m[1];
                break;
            }
        }

        echo '<div class="pcgamer-product-info">';

        // ── Especificaciones incluidas ──────────────────────────────────────
        echo '<ul class="pcgamer-info-list">';
        echo '<li><span class="pcgamer-info-icon">✅</span> Incluye <strong>Windows 11</strong> instalado y activado</li>';
        echo '<li><span class="pcgamer-info-icon">🛡️</span> <strong>1 año de garantía</strong> en todos los componentes</li>';
        echo '<li><span class="pcgamer-info-icon">❌</span> No incluye WiFi ni Bluetooth integrado</li>';
        echo '<li><span class="pcgamer-info-icon">ℹ️</span> Los componentes pueden variar según disponibilidad de stock</li>';
        echo '</ul>';

        // ── Detalles de entrega y contacto ─ siempre se muestran ────────────
        echo '<div class="pcgamer-info-footer">';
        echo '<p class="pcgamer-info-delivery">📦 <strong>Producto a pedido</strong> — tiempo de armado y pruebas de 2 a 5 días hábiles</p>';
        if ($contact_url) {
            echo '<p class="pcgamer-info-contact">🔧 Necesitas agregar / cambiar un componente, <a href="' . esc_url($contact_url) . '">háblanos aquí</a></p>';
        } else {
            echo '<p class="pcgamer-info-contact">🔧 Necesitas agregar / cambiar un componente, <a href="' . esc_url(home_url('/contacto')) . '">háblanos aquí</a></p>';
        }
        echo '</div>';

        echo '</div>';
    }

    public function capture_selected_extras($cart_item_data, $product_id) {
        if (!empty($_POST['pcgamer_extra']) && is_array($_POST['pcgamer_extra'])) {
            $cart_item_data['pcgamer_selected_extras'] = array_map('intval', $_POST['pcgamer_extra']);
            
            if (!empty($_POST['pcgamer_special_prices']) && is_array($_POST['pcgamer_special_prices'])) {
                $cart_item_data['pcgamer_special_prices'] = array_map('floatval', $_POST['pcgamer_special_prices']);
            }
        }
        return $cart_item_data;
    }

    public function add_extras_once_main_added($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if (!isset($cart_item_data['pcgamer_selected_extras'])) return;
        if (isset($cart_item_data['is_extra']) && $cart_item_data['is_extra'] === true) return;

        $custom_prices = get_post_meta($product_id, '_pcgamer_custom_prices', true) ?: [];
        $special_prices = isset($cart_item_data['pcgamer_special_prices']) ? $cart_item_data['pcgamer_special_prices'] : [];
        $synced_categories = get_option('pcgamer_synced_categories', []);

        // Prevent duplicate extras for the same parent cart item key
        $cart = WC()->cart;
        $existing_extras = [];
        foreach ($cart->get_cart() as $key => $item) {
            if (isset($item['parent_cart_item_key']) && $item['parent_cart_item_key'] === $cart_item_key && isset($item['is_extra']) && $item['is_extra'] === true) {
                $existing_extras[$item['product_id']] = $key;
            }
        }

        $items_to_add = [];
        foreach ($cart_item_data['pcgamer_selected_extras'] as $extra_id) {
            if (get_post_status($extra_id) === 'publish') {
                // If already present as a child, update its quantity instead of adding again
                if (isset($existing_extras[$extra_id])) {
                    $cart->set_quantity($existing_extras[$extra_id], $quantity, false);
                    continue;
                }

                $extra_product = wc_get_product($extra_id);
                if (!$extra_product) continue;

                $original_price = $extra_product->get_price();

                $custom_data = [
                    'is_extra' => true,
                    'parent_cart_item_key' => $cart_item_key,
                    'original_price' => $original_price,
                    'pcgamer_config_id' => uniqid('pcg_')
                ];

                // Check if this product is in a synced category
                $is_synced = false;
                $categories = get_the_terms($extra_id, 'product_cat');
                if ($categories && !is_wp_error($categories)) {
                    foreach ($categories as $category) {
                        if (isset($synced_categories[$category->slug]) && $synced_categories[$category->slug]) {
                            $is_synced = true;
                            $woo_price = $extra_product->get_sale_price();
                            if (!$woo_price || empty($woo_price)) {
                                $woo_price = $extra_product->get_regular_price();
                            }
                            if (!empty($woo_price)) {
                                $custom_data['pcgamer_custom_price'] = (float) $woo_price;
                            }
                            break;
                        }
                    }
                }
                if (!$is_synced) {
                    if (isset($special_prices[$extra_id])) {
                        $custom_data['pcgamer_custom_price'] = floatval($special_prices[$extra_id]);
                    } elseif (isset($custom_prices[$extra_id])) {
                        $custom_data['pcgamer_custom_price'] = floatval($custom_prices[$extra_id]);
                    }
                }

                $items_to_add[] = [
                    'product_id' => $extra_id,
                    'custom_data' => $custom_data
                ];
            }
        }

        // Add all items at once, with correct quantity
        foreach ($items_to_add as $item) {
            WC()->cart->add_to_cart($item['product_id'], $quantity, 0, [], $item['custom_data']);
        }

        // Force cart total recalculation after all items are added
        WC()->cart->calculate_totals();
    }

    public function force_disable_ajax($supports, $feature, $product) {
        if ($feature === 'ajax_add_to_cart' && $this->has_upgrades($product)) return false;
        return $supports;
    }

    private function has_upgrades($product) {
        $upgrades = get_post_meta($product->get_id(), '_pcgamer_upgrades', true);
        return is_array($upgrades) && count($upgrades) > 0;
    }

    /**
     * Cuando WooCommerce guarda un precio nuevo en un componente, lo propaga
     * automáticamente a todos los PC base que lo incluyan como upgrade.
     */
    public function auto_sync_price_on_update( $product, $updated_props ) {
        $price_props = [ 'price', 'sale_price', 'regular_price' ];
        if ( empty( array_intersect( $price_props, $updated_props ) ) ) {
            return;
        }

        $product_id = $product->get_id();
        $new_price  = $product->get_sale_price();
        if ( $new_price === '' || $new_price === false ) {
            $new_price = $product->get_regular_price();
        }
        if ( $new_price === '' ) {
            return;
        }

        $pc_bases = get_posts( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [ [ 'key' => '_pcgamer_enabled', 'value' => 'yes' ] ],
            'fields'         => 'ids',
        ] );

        $synced = 0;
        foreach ( $pc_bases as $pc_id ) {
            $upgrades = get_post_meta( $pc_id, '_pcgamer_upgrades', true );
            if ( ! is_array( $upgrades ) ) continue;

            $found = false;
            foreach ( $upgrades as $ids ) {
                if ( in_array( $product_id, (array) $ids, true ) ) { $found = true; break; }
            }
            if ( ! $found ) continue;

            $custom_prices = get_post_meta( $pc_id, '_pcgamer_custom_prices', true ) ?: [];
            $custom_prices[ $product_id ] = (float) $new_price;
            update_post_meta( $pc_id, '_pcgamer_custom_prices', $custom_prices );
            $synced++;
        }

        // Actualizar también pcgamer_sync_custom_prices global
        $global_prices = get_option( 'pcgamer_sync_custom_prices', [] );
        $global_prices[ $product_id ] = (float) $new_price;
        update_option( 'pcgamer_sync_custom_prices', $global_prices );
    }

    /**
     * Mantiene wp_nextech_component_specs sincronizada cuando se guarda un producto.
     */
    public function sync_component_specs_table( $product_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'nextech_component_specs';

        // Verificar que la tabla existe antes de escribir
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            return;
        }

        $fields = [
            '_pcgamer_socket'            => 'socket',
            '_pcgamer_ram_type'          => 'ram_type',
            '_pcgamer_form_factor'       => 'form_factor',
            '_pcgamer_wattage'           => 'wattage',
            '_pcgamer_cooler_height'     => 'cooler_height',
            '_pcgamer_cooler_clearance'  => 'cooler_clearance',
            '_pcgamer_radiator_size'     => 'radiator_size',
        ];

        $has_any = false;
        $data    = [ 'product_id' => $product_id, 'category_slug' => 'componente' ];
        foreach ( $fields as $meta_key => $col ) {
            $val = get_post_meta( $product_id, $meta_key, true );
            if ( $val !== '' && $val !== false ) {
                $data[ $col ] = $val;
                $has_any = true;
            }
        }

        if ( ! $has_any ) return;

        $wpdb->replace( $table, $data );
    }
}

// Initialize all components
new PCGamerConfigurator();
new \PCGamer\CartGroup();
new \PCGamer\OnePerCategory();
new \PCGamer\PriceTotal();
new \PCGamer\CustomPrice();
new \PCGamer\PriceRestore();
