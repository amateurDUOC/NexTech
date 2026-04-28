<?php
namespace PCGamer;

class PriceTotal {
    public function __construct() {
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_script' ]);
        add_action('woocommerce_before_add_to_cart_button', [ $this, 'render_total_container' ], 25);
    }

    public function enqueue_script() {
        if (!is_product()) return;

        global $post;
        $enabled = get_post_meta($post->ID, '_pcgamer_enabled', true);
        if ($enabled !== 'yes') return;

        $plugin_url = plugin_dir_url(dirname(__FILE__)); // dirname(__FILE__) = /includes/
        
        wp_enqueue_script(
            'pcgamer-price-total-js',
            $plugin_url . 'assets/pricetotal.js',
            ['jquery'],
            '1.0',
            true
        );
        
        // Add the summary table script
        wp_enqueue_script(
            'pcgamer-summary-table-js',
            $plugin_url . 'assets/summary-table.js',
            ['jquery', 'pcgamer-price-total-js'],
            '1.0',
            true
        );
    }

    public function render_total_container() {
        global $product;

        if (! $product instanceof \WC_Product || $product->get_type() !== 'simple') return;

        $enabled = get_post_meta($product->get_id(), '_pcgamer_enabled', true);
        if ($enabled !== 'yes') return;

        echo '<div id="pcgamer-total-container" style="margin: 30px auto 15px; font-weight: 600; font-size: 26px; text-align: center; color: #333;">';
        echo '💰 Total: <span id="pcgamer-total-price" data-base-price="' . esc_attr($product->get_price()) . '" style="color: #4a90e2; font-weight: 700;">';
        echo wc_price($product->get_price());
        echo '</span>';
        echo '</div>';
        
        // Add the enhanced summary table container
        echo '<div id="pcgamer-summary-table-container" class="pcgamer-summary-section">';
        echo '<h3>📋 Resumen de Componentes </h3>';

        echo '<div class="pcgamer-summary-table-wrapper">';
        echo '<table id="pcgamer-summary-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Categoría</th>';
        echo '<th>Producto Seleccionado</th>';
        echo '<th>Precio</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        // Base product row
        echo '<tr class="base-product-row">';
        echo '<td>🖥️ PC Base</td>';
        echo '<td>' . esc_html($product->get_name()) . '</td>';
        echo '<td>' . wc_price($product->get_price()) . '</td>';
        echo '</tr>';
        // Dynamic rows will be added by JavaScript
        echo '</tbody>';
        echo '<tfoot>';
        echo '<tr>';
        echo '<th colspan="2">PRECIO TOTAL</th>';
        echo '<th id="pcgamer-summary-total">' . wc_price($product->get_price()) . '</th>';
        echo '</tr>';
        echo '</tfoot>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
    }
}
