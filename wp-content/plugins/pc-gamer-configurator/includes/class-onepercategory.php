<?php
namespace PCGamer;

class OnePerCategory {
    public function __construct() {
        add_action('woocommerce_add_to_cart_validation', [ $this, 'validate_one_per_category' ], 10, 3);
    }

    public function validate_one_per_category($passed, $product_id, $quantity) {
        $enabled = get_post_meta($product_id, '_pcgamer_enabled', true);
        if ($enabled !== 'yes') return $passed;

        $selected = isset($_POST['pcgamer_extra']) && is_array($_POST['pcgamer_extra']) ? array_map('intval', $_POST['pcgamer_extra']) : [];

        $upgrades = get_post_meta($product_id, '_pcgamer_upgrades', true);
        if (!is_array($upgrades)) return $passed;

        $errores = [];

        foreach ($upgrades as $category => $product_ids) {
            if (empty($product_ids)) continue;

            $matches = array_intersect($selected, $product_ids);

            if (in_array($category, ['Accesorios PC Armado', 'Monitores'])) continue;


            if (count($matches) !== 1) {
                $errores[] = $this->get_category_label($category);
            }
        }

        if (!empty($errores)) {
            $mensaje = 'Debes seleccionar exactamente <strong>1 opción</strong> en las siguientes categorías:<br><ul>';
            foreach ($errores as $cat_label) {
                $mensaje .= '<li><strong>' . esc_html($cat_label) . '</strong></li>';
            }
            $mensaje .= '</ul>';
            wc_add_notice($mensaje, 'error');
            return false;
        }

        return $passed;
    }

    private function get_category_label($slug) {
        $labels = [
            'Gabinetes PC Armado' => 'Elección de gabinete',
            'refrigeracion' => 'Elección de refrigeración de procesador',
            'Memoria RAM PC Armado' => 'Elección de memoria RAM (upgrade)',
            'Almacenamiento PC Armado' => 'Elección de disco duro (upgrade)',
            'Fuente de Poder PC Armado' => 'Fuente de poder',
            'Placas PC Armado' => 'Elección placa',
            'Procesadores PC Armado' => 'Upgrade de procesador',
            'Accesorios PC Armado' => 'Accesorios',
            'Monitores' => 'Agregar monitor'
        ];
        return $labels[$slug] ?? ucfirst($slug);
    }
}
