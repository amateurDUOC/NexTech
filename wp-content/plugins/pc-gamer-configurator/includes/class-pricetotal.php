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

        echo '<div id="pcgamer-total-container">';
        echo '💰 Total: <span id="pcgamer-total-price" data-base-price="' . esc_attr($product->get_price()) . '">';
        echo wc_price($product->get_price());
        echo '</span>';
        echo '</div>';

        echo '<div id="pcgamer-summary-table-container" class="pcgamer-summary-dropdown active">';
        echo '<div class="pcgamer-summary-header" id="pcgamer-summary-toggle" role="button" aria-expanded="true">';
        echo '<span>📋 Resumen de Componentes</span>';
        echo '<span class="pcgamer-dropdown-icon">▼</span>';
        echo '</div>';
        echo '<div class="pcgamer-summary-content">';
        echo '<table id="pcgamer-summary-table">';
        echo '<thead><tr>';
        echo '<th>Categoría</th>';
        echo '<th>Producto Seleccionado</th>';
        echo '<th>Precio</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        echo '<tr class="base-product-row">';
        echo '<td>🖥️ PC Base</td>';
        echo '<td>' . esc_html($product->get_name()) . '</td>';
        echo '<td>' . wc_price($product->get_price()) . '</td>';
        echo '</tr>';
        echo '</tbody>';
        echo '<tfoot><tr>';
        echo '<th colspan="2">PRECIO TOTAL</th>';
        echo '<th id="pcgamer-summary-total">' . wc_price($product->get_price()) . '</th>';
        echo '</tr></tfoot>';
        echo '</table>';
        echo '</div>';
        echo '</div>';
    }
}
