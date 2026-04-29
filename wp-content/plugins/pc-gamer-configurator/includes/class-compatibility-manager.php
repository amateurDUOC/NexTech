<?php
/**
 * CompatibilityManager - Gestor de compatibilidad de componentes PC
 *
 * @since 0.7.0
 * @package PCGamerConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

class CompatibilityManager {

    /**
     * Mapeo de relaciones de compatibilidad.
     * Cada entrada: "categoría dependiente" => qué categoría la restringe y cómo.
     * La dirección es: la categoría de la KEY depende de la categoría en 'dependencies'.
     *
     * Ejemplo: Placas depende de Procesadores (el socket de la placa debe coincidir con el CPU).
     */
    private $dependency_map = [
        'Placas PC Armado' => [
            'dependencies' => ['Procesadores PC Armado'],
            'check_via'    => 'socket',
        ],
        'Memoria RAM PC Armado' => [
            'dependencies' => ['Procesadores PC Armado'],
            'check_via'    => 'ram_type',
        ],
        'Gabinetes PC Armado' => [
            'dependencies' => ['Placas PC Armado'],
            'check_via'    => 'form_factor',
        ],
        'refrigeracion' => [
            'dependencies' => ['Procesadores PC Armado'],
            'check_via'    => 'socket',
        ],
        'Almacenamiento PC Armado' => [
            'dependencies' => [],
            'check_via'    => null,
        ],
        'Fuente de Poder PC Armado' => [
            'dependencies' => [],
            'check_via'    => null,
        ],
    ];

    public function __construct() {
        add_action('wp_ajax_pcgamer_get_compatible_products', [$this, 'ajax_get_compatible_products']);
        add_action('wp_ajax_pcgamer_validate_build',          [$this, 'ajax_validate_build']);
    }

    /**
     * Retorna IDs de productos de $target_category compatibles con $selected_component_id.
     * Si el componente seleccionado no tiene el meta de compatibilidad configurado,
     * devuelve TODOS los productos (sin filtrar) para no ocultar tarjetas por falta de datos.
     */
    public function get_compatible_products($target_category, $selected_component_id, $compatibility_type = null) {
        $selected_product = wc_get_product($selected_component_id);
        if (!$selected_product) {
            return [];
        }

        $target_products = $this->get_products_in_category($target_category);
        if (empty($target_products)) {
            return [];
        }

        // Sin tipo de compatibilidad o sin meta en el producto → no hay restricción, mostrar todo
        if (!$compatibility_type) {
            return $target_products;
        }

        $compatibility_value = $this->get_compatibility_attribute($selected_product, $compatibility_type);
        if (!$compatibility_value) {
            // Producto sin metadato configurado: devolver todos para no ocultar tarjetas
            return $target_products;
        }

        $compatible = [];
        foreach ($target_products as $product_id) {
            $target_product = wc_get_product($product_id);
            if (!$target_product) {
                continue;
            }
            $target_value = $this->get_compatibility_attribute($target_product, $compatibility_type);
            // Si el producto destino tampoco tiene meta, se considera compatible (sin datos = sin restricción)
            if (!$target_value || $this->is_compatible($compatibility_value, $target_value, $compatibility_type)) {
                $compatible[] = $product_id;
            }
        }

        return $compatible;
    }

    /**
     * Devuelve el valor de un atributo de compatibilidad de un producto.
     * Para form_factor: primero intenta _pcgamer_form_factors (gabinete/array),
     * luego _pcgamer_form_factor (placa/string).
     */
    private function get_compatibility_attribute($product, $attribute_type) {
        if (!$product || !$attribute_type) {
            return null;
        }

        $meta_map = [
            'socket'      => '_pcgamer_socket',
            'ram_type'    => '_pcgamer_ram_type',
            'form_factor' => '_pcgamer_form_factor',
            'wattage'     => '_pcgamer_wattage',
        ];

        if (!isset($meta_map[$attribute_type])) {
            return null;
        }

        if ($attribute_type === 'form_factor') {
            // _pcgamer_form_factors (plural) es el campo del gabinete (array de tamaños soportados)
            $value = get_post_meta($product->get_id(), '_pcgamer_form_factors', true);
            if (!empty($value)) {
                return $value;
            }
        }

        $value = get_post_meta($product->get_id(), $meta_map[$attribute_type], true);
        return !empty($value) ? $value : null;
    }

    /**
     * Compara dos valores de compatibilidad.
     * - socket / ram_type: igualdad exacta (case-insensitive), soporta multi-valor separado por comas
     * - form_factor: el form factor de la placa debe estar en la lista del gabinete
     */
    private function is_compatible($value1, $value2, $attribute_type) {
        $v1 = strtolower(trim($value1));

        if ($attribute_type === 'form_factor') {
            // value2 puede ser array (guardado por metabox) o string separado por comas (legado)
            if (is_array($value2)) {
                $list = array_map(function($v) { return strtolower(trim($v)); }, $value2);
            } else {
                $list = array_map('trim', explode(',', strtolower($value2)));
            }
            return in_array($v1, $list, true);
        }

        // Para socket y otros: soportar valores múltiples separados por coma en cualquiera de los dos lados.
        // Ejemplo: cooler tiene "AM4,AM5,LGA1200,LGA1700" y CPU tiene "AM4" → compatible.
        $v1_list = array_map('trim', explode(',', $v1));
        $v2_list = array_map('trim', explode(',', strtolower($value2)));

        // Hay compatibilidad si al menos uno de los valores de v1 aparece en v2
        foreach ($v1_list as $v) {
            if (in_array($v, $v2_list, true)) return true;
        }
        return false;
    }

    /**
     * Obtiene los IDs de productos asociados a una categoría.
     * Primero busca en la opción de sincronización del admin, luego por taxonomía, luego por keywords.
     */
    private function get_products_in_category($category) {
        $synced_products = get_option('pcgamer_synced_products_per_category', []);

        if (!empty($synced_products[$category])) {
            return $synced_products[$category];
        }

        // Buscar por término de categoría (nombre o slug)
        $term = get_term_by('name', $category, 'product_cat');
        if (!$term || is_wp_error($term)) {
            $term = get_term_by('slug', sanitize_title($category), 'product_cat');
        }

        if ($term && !is_wp_error($term)) {
            $products = get_posts([
                'tax_query' => [[
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ]],
                'post_type'      => 'product',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ]);
            if (!empty($products)) {
                return $products;
            }
        }

        // Fallback: búsqueda por keywords en el título
        $category_variations = [
            'Placas PC Armado'          => ['Placa', 'Madre', 'placa'],
            'Procesadores PC Armado'    => ['Procesador', 'CPU', 'procesador'],
            'Memoria RAM PC Armado'     => ['Memoria', 'RAM', 'memoria'],
            'Almacenamiento PC Armado'  => ['Almacenamiento', 'SSD', 'HDD'],
            'Fuente de Poder PC Armado' => ['PSU', 'Fuente', 'Poder'],
            'Gabinetes PC Armado'       => ['Gabinete', 'Cabinet', 'Case'],
            'refrigeracion'             => ['Cooler', 'Refrigeraci', 'refrigeracion'],
        ];

        if (isset($category_variations[$category])) {
            global $wpdb;
            $keywords       = $category_variations[$category];
            $where_clauses  = [];
            foreach ($keywords as $keyword) {
                $where_clauses[] = "($wpdb->posts.post_title LIKE '%" . $wpdb->esc_like($keyword) . "%')";
            }

            if (!empty($where_clauses)) {
                $combined = implode(' OR ', $where_clauses);
                // Usar una referencia nombrada para poder remover solo este filtro
                $filter_fn = function($where) use ($combined) {
                    return $where . ' AND (' . $combined . ')';
                };
                add_filter('posts_where', $filter_fn, 10, 1);

                $products = get_posts([
                    'post_type'      => 'product',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'post_status'    => 'publish',
                ]);

                remove_filter('posts_where', $filter_fn, 10);

                if (!empty($products)) {
                    return $products;
                }
            }
        }

        return [];
    }

    /**
     * Valida la compatibilidad de un build completo.
     *
     * @param array $selected_items ['Categoría' => product_id, ...]
     * @return array { valid: bool, errors: string[], warnings: string[] }
     */
    public function validate_build($selected_items) {
        $result = [
            'valid'    => true,
            'errors'   => [],
            'warnings' => [],
        ];

        if (empty($selected_items)) {
            return $result;
        }

        // ── CPU + Placa Madre (socket) ──────────────────────────────────────────
        if (isset($selected_items['Procesadores PC Armado'], $selected_items['Placas PC Armado'])) {
            $cpu_socket = get_post_meta($selected_items['Procesadores PC Armado'], '_pcgamer_socket', true);
            $mb_socket  = get_post_meta($selected_items['Placas PC Armado'],       '_pcgamer_socket', true);

            if ($cpu_socket && $mb_socket && strtolower($cpu_socket) !== strtolower($mb_socket)) {
                $cpu = wc_get_product($selected_items['Procesadores PC Armado']);
                $result['valid']    = false;
                $result['errors'][] = sprintf(
                    'Socket incompatible: CPU %s requiere socket %s, pero la placa madre es %s',
                    $cpu ? $cpu->get_name() : '',
                    strtoupper($cpu_socket),
                    strtoupper($mb_socket)
                );
            }
        }

        // ── CPU + RAM (tipo de memoria) ─────────────────────────────────────────
        if (isset($selected_items['Procesadores PC Armado'], $selected_items['Memoria RAM PC Armado'])) {
            $cpu_ram = get_post_meta($selected_items['Procesadores PC Armado'], '_pcgamer_ram_type', true);
            $ram     = get_post_meta($selected_items['Memoria RAM PC Armado'],  '_pcgamer_ram_type', true);

            if ($cpu_ram && $ram && strtolower($cpu_ram) !== strtolower($ram)) {
                $result['valid']    = false;
                $result['errors'][] = sprintf(
                    'Tipo de RAM incompatible: el procesador soporta %s pero la memoria seleccionada es %s',
                    strtoupper($cpu_ram),
                    strtoupper($ram)
                );
            }
        }

        // ── Placa Madre + Gabinete (form factor) ───────────────────────────────
        if (isset($selected_items['Placas PC Armado'], $selected_items['Gabinetes PC Armado'])) {
            $mb_ff        = get_post_meta($selected_items['Placas PC Armado'],    '_pcgamer_form_factor',  true);
            $case_ffs_raw = get_post_meta($selected_items['Gabinetes PC Armado'], '_pcgamer_form_factors', true);

            if ($mb_ff && $case_ffs_raw) {
                $case_ffs = is_array($case_ffs_raw) ? $case_ffs_raw : [$case_ffs_raw];
                $found = false;
                foreach ($case_ffs as $ff) {
                    if (strtolower(trim($ff)) === strtolower(trim($mb_ff))) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $result['valid']    = false;
                    $result['errors'][] = sprintf(
                        'Form factor incompatible: la placa madre es %s pero el gabinete solo soporta %s',
                        $mb_ff,
                        implode(', ', $case_ffs)
                    );
                }
            }
        }

        // ── CPU + Refrigeración (socket) ────────────────────────────────────────
        if (isset($selected_items['Procesadores PC Armado'], $selected_items['refrigeracion'])) {
            $cpu_socket    = get_post_meta($selected_items['Procesadores PC Armado'], '_pcgamer_socket', true);
            $cooler_socket = get_post_meta($selected_items['refrigeracion'],          '_pcgamer_socket', true);

            if ($cpu_socket && $cooler_socket) {
                // El cooler puede soportar múltiples sockets separados por coma
                $cooler_sockets = array_map('trim', explode(',', strtolower($cooler_socket)));
                if (!in_array(strtolower($cpu_socket), $cooler_sockets, true)) {
                    $result['valid']    = false;
                    $result['errors'][] = sprintf(
                        'Refrigeración incompatible: el procesador usa socket %s pero el cooler soporta %s',
                        strtoupper($cpu_socket),
                        strtoupper($cooler_socket)
                    );
                }
            }
        }

        // ── Refrigeración + Gabinete (altura del cooler vs clearance del gabinete) ──
        if (isset($selected_items['refrigeracion'], $selected_items['Gabinetes PC Armado'])) {
            $cooler_height  = intval(get_post_meta($selected_items['refrigeracion'],       '_pcgamer_cooler_height',    true));
            $case_clearance = intval(get_post_meta($selected_items['Gabinetes PC Armado'], '_pcgamer_cooler_clearance', true));

            if ($cooler_height > 0 && $case_clearance > 0 && $cooler_height > $case_clearance) {
                $result['valid']    = false;
                $result['errors'][] = sprintf(
                    'Cooler demasiado alto: el refrigerador mide %d mm pero el gabinete soporta máximo %d mm',
                    $cooler_height,
                    $case_clearance
                );
            }
        }

        // ── Refrigeración AIO + Gabinete (tamaño de radiador vs soporte del gabinete) ──
        if (isset($selected_items['refrigeracion'], $selected_items['Gabinetes PC Armado'])) {
            $rad_size    = get_post_meta($selected_items['refrigeracion'],       '_pcgamer_radiator_size',    true);
            $rad_support = get_post_meta($selected_items['Gabinetes PC Armado'], '_pcgamer_radiator_support', true);

            if ($rad_size) {
                // El cooler es AIO; verificar soporte del gabinete
                $rad_support = is_array($rad_support) ? $rad_support : [];
                if (!empty($rad_support) && !in_array($rad_size, $rad_support, true)) {
                    $result['valid']    = false;
                    $result['errors'][] = sprintf(
                        'Radiador AIO incompatible: el refrigerador tiene radiador de %d mm pero el gabinete solo soporta %s mm',
                        intval($rad_size),
                        implode(', ', $rad_support)
                    );
                } elseif (empty($rad_support)) {
                    $result['warnings'][] = sprintf(
                        'Verifica compatibilidad: el refrigerador es AIO de %d mm — asegúrate de que el gabinete tenga soporte para ese radiador',
                        intval($rad_size)
                    );
                }
            }
        }

        return $result;
    }

    // ── ENDPOINTS AJAX ─────────────────────────────────────────────────────────

    public function ajax_get_compatible_products() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'pcgamer_compatibility')) {
            wp_send_json_error(['message' => 'Verificación de seguridad fallida']);
        }

        $component_id       = isset($_POST['component_id'])       ? intval($_POST['component_id'])                      : 0;
        $target_category    = isset($_POST['target_category'])    ? sanitize_text_field($_POST['target_category'])      : '';
        $compatibility_type = isset($_POST['compatibility_type']) ? sanitize_text_field($_POST['compatibility_type'])   : null;

        if (!$component_id || !$target_category) {
            wp_send_json_error(['message' => 'Parámetros inválidos']);
        }

        $compatible_ids = $this->get_compatible_products($target_category, $component_id, $compatibility_type);

        $product_details = [];
        foreach ($compatible_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product_details[] = [
                    'id'    => $product_id,
                    'name'  => $product->get_name(),
                    'price' => $product->get_price(),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
                ];
            }
        }

        wp_send_json_success([
            'compatible_products' => $compatible_ids,
            'product_details'     => $product_details,
        ]);
    }

    public function ajax_validate_build() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'pcgamer_compatibility')) {
            wp_send_json_error(['message' => 'Verificación de seguridad fallida']);
        }

        // El JS envía los items como JSON para preservar las claves de categoría
        $selected_items = [];
        if (!empty($_POST['selected_items'])) {
            $decoded = json_decode(stripslashes(sanitize_text_field($_POST['selected_items'])), true);
            if (is_array($decoded)) {
                foreach ($decoded as $category => $product_id) {
                    $selected_items[sanitize_text_field($category)] = intval($product_id);
                }
            }
        }

        if (empty($selected_items)) {
            wp_send_json_success(['valid' => true, 'errors' => [], 'warnings' => []]);
        }

        wp_send_json_success($this->validate_build($selected_items));
    }

    public function get_product_specs($product_id) {
        $specs     = [];
        $meta_keys = [
            'socket'           => '_pcgamer_socket',
            'ram_type'         => '_pcgamer_ram_type',
            'form_factor'      => '_pcgamer_form_factor',
            'wattage'          => '_pcgamer_wattage',
            'cooler_height'    => '_pcgamer_cooler_height',
            'cooler_clearance' => '_pcgamer_cooler_clearance',
            'radiator_size'    => '_pcgamer_radiator_size',
            'radiator_support' => '_pcgamer_radiator_support',
        ];
        foreach ($meta_keys as $key => $meta_key) {
            $value = get_post_meta($product_id, $meta_key, true);
            if (!empty($value)) {
                $specs[$key] = $value;
            }
        }
        return $specs;
    }
}
