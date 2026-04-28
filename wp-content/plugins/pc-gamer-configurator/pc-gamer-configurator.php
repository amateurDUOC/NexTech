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

class PCGamerConfigurator {

    private $plugin_categories = [
        'Gabinetes PC Armado' => 'Selección de gabinete',
        'refrigeracion' => 'Selección de refrigeración de procesador',
        'Memoria RAM PC Armado' => 'Selección de memoria RAM',
        'Almacenamiento PC Armado' => 'Selección de disco duro',
        'Fuente de Poder PC Armado' => 'Selección de fuente de poder',
        'Placas PC Armado' => 'Selección de placa madre',
        'Procesadores PC Armado' => 'Selección de procesador',
        'Accesorios PC Armado' => 'Agregar Accesorios',
        'Monitores' => 'Agregar monitor'
    ];

    public function __construct() {
        add_action('admin_menu', [ $this, 'admin_menu' ]);
        add_action('add_meta_boxes', [ $this, 'register_metabox' ]);
        add_action('save_post', [ $this, 'save_product_upgrades' ]);
        add_action('woocommerce_before_add_to_cart_button', [ $this, 'render_upgrades_frontend' ], 5);
        add_filter('woocommerce_add_cart_item_data', [ $this, 'capture_selected_extras' ], 10, 2);
        add_action('woocommerce_add_to_cart', [ $this, 'add_extras_once_main_added' ], 20, 6);
        add_filter('woocommerce_product_supports', [ $this, 'force_disable_ajax' ], 10, 3);
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_styles' ]);
        
        // Add AJAX handler for syncing prices
        add_action('wp_ajax_pcgamer_sync_category_prices', [ $this, 'ajax_sync_category_prices' ]);
        add_action('wp_ajax_pcgamer_category_sync_and_save', [ $this, 'ajax_category_sync_and_save' ]);
    }

    public function enqueue_styles() {
        $plugin_url = plugin_dir_url(__FILE__);
        wp_enqueue_style('pcgamer-configurator-styles', $plugin_url . 'assets/style.css', [], '1.3.1');
        wp_enqueue_script('pcgamer-checkbox-control', $plugin_url . 'assets/checkbox-control.js', [], '1.2', true);
        wp_enqueue_script('pcgamer-carousel-dropdown', $plugin_url . 'assets/carousel-dropdown.js', [], '1.2', true);
        wp_enqueue_script('pcgamer-mobile-carousel', $plugin_url . 'assets/mobile-carousel.js', [], '1.0.1', true);
        wp_enqueue_script('pcgamer-mobile-enhancements', $plugin_url . 'assets/mobile-enhancements.js', ['jquery'], '1.1.1', true);
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

        ?>
        <div class="pcgamer-admin-container">
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
            <style>
                .pcgamer-admin-container {
                    max-width: 1100px;
                    margin: 40px auto 40px auto;
                    padding: 0 16px;
                    box-sizing: border-box;
                }
                .pcgamer-admin-card {
                    background: #fff;
                    border-radius: 10px;
                    box-shadow: 0 4px 24px rgba(0,0,0,0.07), 0 1.5px 4px rgba(0,0,0,0.03);
                    padding: 36px 32px 32px 32px;
                    margin: 0 auto;
                    width: 100%;
                    box-sizing: border-box;
                    overflow-x: auto;
                }
                .pcgamer-admin-card h1 {
                    font-size: 2rem;
                    margin-bottom: 18px;
                    color: #222;
                    font-weight: 700;
                }
                .pcgamer-admin-card h2 {
                    font-size: 1.25rem;
                    margin-top: 0;
                    margin-bottom: 10px;
                    color: #4a90e2;
                    font-weight: 600;
                }
                .pcgamer-admin-card table.form-table {
                    background: #f9f9f9;
                    border-radius: 8px;
                    box-shadow: 0 1px 4px rgba(0,0,0,0.03);
                    padding: 0;
                    width: 100%;
                    margin-bottom: 0;
                }
                .pcgamer-admin-card table.form-table th,
                .pcgamer-admin-card table.form-table td {
                    padding: 18px 12px 18px 0;
                    vertical-align: top;
                }
                .pcgamer-admin-card table.form-table th {
                    width: 220px;
                    font-weight: 600;
                    color: #333;
                }
                .pcgamer-admin-card table.form-table td {
                    background: #fff;
                    border-radius: 6px;
                }
                .pcgamer-admin-card fieldset {
                    border: none;
                    padding: 0;
                    margin: 0;
                }
                .pcgamer-admin-card .wp-list-table {
                    background: #fff;
                    border-radius: 6px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
                    margin-bottom: 0;
                }
                .pcgamer-admin-card .wp-list-table th,
                .pcgamer-admin-card .wp-list-table td {
                    padding: 8px 10px;
                    font-size: 15px;
                }
                .pcgamer-admin-card .wp-list-table th {
                    background: #f4f7fa;
                    color: #444;
                    font-weight: 600;
                }
                .pcgamer-admin-card .wp-list-table tr:nth-child(even) td {
                    background: #f9f9f9;
                }
                .pcgamer-admin-card .wp-list-table tr td input[type="checkbox"] {
                    transform: scale(1.2);
                }
                .pcgamer-admin-card .pcgamer-sync-search {
                    margin-bottom: 8px;
                }
                .pcgamer-admin-card .button {
                    margin-bottom: 0;
                }
                .pcgamer-admin-card .pcgamer-select-all-btn,
                .pcgamer-admin-card .pcgamer-deselect-all-btn {
                    margin-right: 5px;
                }
                .pcgamer-admin-card .pcgamer-sort-price {
                    margin-left: 5px;
                }
                .pcgamer-admin-card .spinner {
                    vertical-align: middle;
                }
                .switch {
                    position: relative;
                    display: inline-block;
                    width: 46px;
                    height: 24px;
                }
                .switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                .slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background-color: #ccc;
                    transition: .4s;
                    border-radius: 24px;
                }
                .slider:before {
                    position: absolute;
                    content: "";
                    height: 18px;
                    width: 18px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }
                input:checked + .slider {
                    background-color: #4a90e2;
                }
                input:checked + .slider:before {
                    transform: translateX(22px);
                }
                .toggle-label {
                    font-size: 14px;
                    color: #333;
                }
                .switch input:disabled + .slider {
                    background-color: #e0e0e0;
                }
                @media (max-width: 900px) {
                    .pcgamer-admin-card {
                        padding: 18px 6px 18px 6px;
                    }
                    .pcgamer-admin-card table.form-table th {
                        width: 120px;
                        font-size: 14px;
                    }
                }
                @media (max-width: 600px) {
                    .pcgamer-admin-container {
                        padding: 0 2px;
                    }
                    .pcgamer-admin-card {
                        padding: 8px 2px 8px 2px;
                    }
                    .pcgamer-admin-card table.form-table th,
                    .pcgamer-admin-card table.form-table td {
                        padding: 8px 2px 8px 0;
                        font-size: 13px;
                    }
                }
            </style>
            <script>
                jQuery(document).ready(function($) {
                    // Search/filter for each table
                    $('.pcgamer-sync-search').on('input', function() {
                        var search = $(this).val().toLowerCase();
                        var tableId = $(this).data('table');
                        $('#' + tableId + ' tbody tr').each(function() {
                            // Only search in visible text columns (Producto and Precio Woo)
                            var rowText = '';
                            $(this).find('td').each(function(idx) {
                                // Only include columns 1 and 2 (Producto, Precio Woo)
                                if (idx === 1 || idx === 2) {
                                    rowText += $(this).text().toLowerCase() + ' ';
                                }
                            });
                            $(this).toggle(rowText.indexOf(search) !== -1);
                        });
                    });
                    // Select/Deselect all functionality
                    $('.pcgamer-select-all-btn').on('click', function() {
                        var tableId = $(this).data('table');
                        $('#' + tableId + ' tbody tr').each(function() {
                            var $cb = $(this).find('input[type="checkbox"]');
                            $cb.prop('checked', true).trigger('change');
                        });
                    });
                    $('.pcgamer-deselect-all-btn').on('click', function() {
                        var tableId = $(this).data('table');
                        $('#' + tableId + ' tbody tr').each(function() {
                            var $cb = $(this).find('input[type="checkbox"]');
                            $cb.prop('checked', false).trigger('change');
                        });
                    });
                    // Sort by price functionality
                    $('.pcgamer-sort-price').on('change', function() {
                        var tableId = $(this).data('table');
                        var order = $(this).val();
                        var $rows = $('#' + tableId + ' tbody tr').detach();
                        $rows.sort(function(a, b) {
                            var priceA = parseFloat($(a).data('price')) || 0;
                            var priceB = parseFloat($(b).data('price')) || 0;
                            return order === 'asc' ? priceA - priceB : priceB - priceA;
                        });
                        $('#' + tableId + ' tbody').append($rows);
                    });
                    // Per-category sync & save
                    $('.pcgamer-category-sync-btn').on('click', function(e) {
                        e.preventDefault();
                        var btn = $(this);
                        var category = btn.data('category');
                        var nonce = btn.data('nonce');
                        var label = btn.data('label');
                        var spinner = btn.siblings('.spinner');
                        var resultContainer = btn.siblings('.pcgamer-category-sync-result');
                        spinner.css('visibility', 'visible');
                        resultContainer.html('Sincronizando...');

                        // Gather checked products and custom prices for this category
                        var tableId = 'pcgamer-sync-table-' + category.replace(/[^a-zA-Z0-9_-]/g, '-').toLowerCase();
                        var checked = [];
                        var customPrices = {};
                        $('#' + tableId + ' tbody tr').each(function() {
                            var $row = $(this);
                            var $cb = $row.find('input[type="checkbox"]');
                            var $price = $row.find('input[type="number"]');
                            if ($cb.prop('checked')) {
                                checked.push($cb.val());
                            }
                            if ($price.length) {
                                var val = $price.val();
                                if (val !== '') {
                                    customPrices[$cb.val()] = val;
                                }
                            }
                        });

                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'pcgamer_category_sync_and_save',
                                category: category,
                                nonce: nonce,
                                products: checked,
                                custom_prices: customPrices
                            },
                            success: function(response) {
                                spinner.css('visibility', 'hidden');
                                if (response.success) {
                                    resultContainer.html('<span style="color:green;">' + response.data.message + '</span>');
                                } else {
                                    resultContainer.html('<span style="color:red;">Error: ' + response.data.message + '</span>');
                                }
                            },
                            error: function() {
                                spinner.css('visibility', 'hidden');
                                resultContainer.html('<span style="color:red;">Error al comunicarse con el servidor</span>');
                            }
                        });
                    });

                    // Toggle ON/OFF for each category
                    $('.pcgamer-category-toggle').on('change', function() {
                        var $toggle = $(this);
                        var category = $toggle.data('category');
                        var enabled = $toggle.is(':checked') ? 1 : 0;
                        var label = $toggle.closest('.pcgamer-toggle-switch').find('.toggle-label');
                        label.text(enabled ? 'ON' : 'OFF');

                        // Disable/enable all controls in this category
                        var row = $toggle.closest('tr');
                        row.find('input[type="checkbox"], input[type="number"], button, select, input.pcgamer-sync-search').not('.pcgamer-category-toggle').prop('disabled', !enabled);

                        // AJAX save toggle state
                        $.post(ajaxurl, {
                            action: 'pcgamer_toggle_category',
                            category: category,
                            value: enabled,
                            pcgamer_nonce: $('input[name="pcgamer_nonce"]').val()
                        });
                    });
                });
            </script>
        </div>
        <?php
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
        echo <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cb = document.querySelector("input[name='pcgamer_enabled']");
    const settings = document.getElementById("pcgamer-upgrades-settings");
    if (cb) {
        cb.addEventListener("change", function () {
            settings.style.display = this.checked ? "" : "none";
        });
    }
});
</script>
HTML;
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

        echo '<div class="pcgamer-upgrades-wrapper">';
        echo '<h2 style="margin-bottom:20px; text-align: center;">🛠️ Personaliza tu PC Gamer</h2>';

        foreach ($this->plugin_categories as $slug => $label) {
            if (empty($upgrades[$slug])) continue;
            
            $sorted_products = [];
            foreach ($upgrades[$slug] as $upgrade_id) {
                $upgrade_product = wc_get_product($upgrade_id);
                if (!$upgrade_product) continue;
                
                // Only skip products that are explicitly marked as out of stock
                if (!$upgrade_product->is_in_stock()) {
                    continue;
                }
                
                $regular_price = $upgrade_product->get_price();
                $special_price = isset($custom_prices[$upgrade_id]) ? $custom_prices[$upgrade_id] : $regular_price;
                
                $sorted_products[$upgrade_id] = [
                    'product' => $upgrade_product,
                    'price' => $special_price,
                    'original_price' => $regular_price
                ];
            }
            
            // Skip rendering this category if no products are in stock
            if (empty($sorted_products)) continue;
            
            uasort($sorted_products, function($a, $b) {
                return $a['price'] <=> $b['price'];
            });
            
            echo '<div class="pcgamer-category-dropdown">';
            echo '<div class="pcgamer-dropdown-header">';
            echo '<h3>' . esc_html($label) . '</h3>';
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
}

// Initialize all components
new PCGamerConfigurator();
new \PCGamer\CartGroup();
new \PCGamer\OnePerCategory();
new \PCGamer\PriceTotal();
new \PCGamer\CustomPrice();
new \PCGamer\PriceRestore();
