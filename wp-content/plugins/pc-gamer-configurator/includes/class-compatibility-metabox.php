<?php
/**
 * Compatibility Metabox - Metadatos de compatibilidad en productos WooCommerce
 *
 * Campos guardados:
 * - _pcgamer_socket            → CPU, Placa Madre, Cooler
 * - _pcgamer_ram_type          → CPU, Memoria RAM
 * - _pcgamer_form_factor       → Placa Madre (singular)
 * - _pcgamer_form_factors      → Gabinete (array de tamaños soportados)
 * - _pcgamer_wattage           → Fuente de Poder
 * - _pcgamer_cooler_height     → Cooler (altura física del cooler en mm)
 * - _pcgamer_cooler_clearance  → Gabinete (espacio máximo para cooler en mm)
 * - _pcgamer_radiator_size     → Cooler AIO (tamaño del radiador: 120/240/360, vacío = aire)
 * - _pcgamer_radiator_support  → Gabinete (array de tamaños de radiador soportados)
 *
 * @since 0.7.0
 * @package PCGamerConfigurator
 */

if (!defined('ABSPATH')) {
    exit;
}

class CompatibilityMetabox {

    public function __construct() {
        add_action('add_meta_boxes',    [$this, 'register_metabox']);
        add_action('save_post_product', [$this, 'save_metabox_data']);
    }

    public function register_metabox() {
        add_meta_box(
            'pcgamer_compatibility_specs',
            '🔧 Especificaciones de Compatibilidad (v0.7.0)',
            [$this, 'render_metabox'],
            'product',
            'normal',
            'default'
        );
    }

    public function render_metabox($post) {
        wp_nonce_field('pcgamer_compatibility_nonce', 'pcgamer_compatibility_nonce');

        $socket            = get_post_meta($post->ID, '_pcgamer_socket',            true);
        $ram_type          = get_post_meta($post->ID, '_pcgamer_ram_type',          true);
        $form_factor       = get_post_meta($post->ID, '_pcgamer_form_factor',       true);
        $form_factors      = get_post_meta($post->ID, '_pcgamer_form_factors',      true);
        $wattage           = get_post_meta($post->ID, '_pcgamer_wattage',           true);
        $cooler_height     = get_post_meta($post->ID, '_pcgamer_cooler_height',     true);
        $cooler_clearance  = get_post_meta($post->ID, '_pcgamer_cooler_clearance',  true);
        $radiator_size     = get_post_meta($post->ID, '_pcgamer_radiator_size',     true);
        $radiator_support  = get_post_meta($post->ID, '_pcgamer_radiator_support',  true) ?: [];
        ?>
        <div style="padding: 16px; background: #f9f9f9; border-radius: 4px;">
            <p style="color: #666; margin-top: 0;">
                📋 Especifica los detalles técnicos de este componente para que el sistema de compatibilidad
                pueda filtrar productos en el configurador.
            </p>

            <table class="form-table" style="background: white;">
                <tbody>

                    <!-- Socket (CPU, Placa Madre, Cooler) -->
                    <tr valign="top">
                        <th scope="row" style="padding: 16px 0;">
                            <label for="pcgamer_socket">
                                <strong>🔌 Socket</strong><br>
                                <small style="color:#999;">CPU · Placa Madre · Cooler</small>
                            </label>
                        </th>
                        <td style="padding: 16px 0;">
                            <input
                                type="text"
                                id="pcgamer_socket"
                                name="pcgamer_socket"
                                value="<?php echo esc_attr($socket); ?>"
                                placeholder="Ej: LGA1700, AM5, LGA1151"
                                style="width:100%;max-width:300px;padding:8px;border:1px solid #ddd;border-radius:4px;"
                            />
                            <p style="color:#999;margin-top:8px;">
                                Ejemplos: <code>LGA1700</code>, <code>AM5</code>, <code>LGA1151</code>
                            </p>
                        </td>
                    </tr>

                    <!-- Tipo de RAM (CPU, RAM) -->
                    <tr valign="top">
                        <th scope="row" style="padding: 16px 0;">
                            <label for="pcgamer_ram_type">
                                <strong>🎛️ Tipo de RAM</strong><br>
                                <small style="color:#999;">CPU · Memoria RAM</small>
                            </label>
                        </th>
                        <td style="padding: 16px 0;">
                            <select
                                id="pcgamer_ram_type"
                                name="pcgamer_ram_type"
                                style="width:100%;max-width:300px;padding:8px;border:1px solid #ddd;border-radius:4px;"
                            >
                                <option value="">-- Sin especificar --</option>
                                <option value="DDR4" <?php selected($ram_type, 'DDR4'); ?>>DDR4</option>
                                <option value="DDR5" <?php selected($ram_type, 'DDR5'); ?>>DDR5</option>
                            </select>
                        </td>
                    </tr>

                    <!-- Form Factor singular (Placa Madre) -->
                    <tr valign="top">
                        <th scope="row" style="padding: 16px 0;">
                            <label for="pcgamer_form_factor">
                                <strong>📐 Form Factor</strong><br>
                                <small style="color:#999;">Placa Madre</small>
                            </label>
                        </th>
                        <td style="padding: 16px 0;">
                            <select
                                id="pcgamer_form_factor"
                                name="pcgamer_form_factor"
                                style="width:100%;max-width:300px;padding:8px;border:1px solid #ddd;border-radius:4px;"
                            >
                                <option value="">-- Sin especificar --</option>
                                <option value="ATX"      <?php selected($form_factor, 'ATX'); ?>>ATX (305×244 mm)</option>
                                <option value="Micro-ATX" <?php selected($form_factor, 'Micro-ATX'); ?>>Micro-ATX (244×244 mm)</option>
                                <option value="Mini-ITX" <?php selected($form_factor, 'Mini-ITX'); ?>>Mini-ITX (170×170 mm)</option>
                            </select>
                            <p style="color:#999;margin-top:8px;">Tamaño físico de la placa madre</p>
                        </td>
                    </tr>

                    <!-- Form Factors soportados (Gabinete) -->
                    <tr valign="top">
                        <th scope="row" style="padding: 16px 0;">
                            <label>
                                <strong>📦 Form Factors que Soporta</strong><br>
                                <small style="color:#999;">Gabinete</small>
                            </label>
                        </th>
                        <td style="padding: 16px 0;">
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <?php foreach (['ATX' => 'ATX (305×244 mm)', 'Micro-ATX' => 'Micro-ATX (244×244 mm)', 'Mini-ITX' => 'Mini-ITX (170×170 mm)'] as $val => $lbl): ?>
                                <label style="margin:0;">
                                    <input
                                        type="checkbox"
                                        name="pcgamer_form_factors[]"
                                        value="<?php echo esc_attr($val); ?>"
                                        <?php checked(is_array($form_factors) && in_array($val, $form_factors)); ?>
                                    />
                                    <?php echo esc_html($lbl); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <p style="color:#999;margin-top:8px;">Marca los tamaños de placas madre que caben en este gabinete</p>
                        </td>
                    </tr>

                    <!-- Wattaje (Fuente de Poder) -->
                    <tr valign="top">
                        <th scope="row" style="padding: 16px 0;">
                            <label for="pcgamer_wattage">
                                <strong>⚡ Wattaje</strong><br>
                                <small style="color:#999;">Fuente de Poder</small>
                            </label>
                        </th>
                        <td style="padding: 16px 0;">
                            <input
                                type="number"
                                id="pcgamer_wattage"
                                name="pcgamer_wattage"
                                value="<?php echo esc_attr($wattage); ?>"
                                placeholder="Ej: 650"
                                style="width:100%;max-width:300px;padding:8px;border:1px solid #ddd;border-radius:4px;"
                                min="0" step="50"
                            />
                            <p style="color:#999;margin-top:8px;">Wattaje de la fuente (ej: 650 para 650W)</p>
                        </td>
                    </tr>

                    <!-- Altura del Cooler (Cooler CPU) -->
                    <tr valign="top">
                        <th scope="row" style="padding: 16px 0;">
                            <label for="pcgamer_cooler_height">
                                <strong>📏 Altura del Cooler</strong><br>
                                <small style="color:#999;">Cooler de CPU</small>
                            </label>
                        </th>
                        <td style="padding: 16px 0;">
                            <input
                                type="number"
                                id="pcgamer_cooler_height"
                                name="pcgamer_cooler_height"
                                value="<?php echo esc_attr($cooler_height); ?>"
                                placeholder="Ej: 165"
                                style="width:100%;max-width:300px;padding:8px;border:1px solid #ddd;border-radius:4px;"
                                min="0"
                            />
                            <p style="color:#999;margin-top:8px;">Altura física del cooler en mm (ej: 165 mm). Se compara con el espacio disponible en el gabinete.</p>
                        </td>
                    </tr>

                    <!-- Clearance máximo para cooler (Gabinete) -->
                    <tr valign="top">
                        <th scope="row" style="padding: 16px 0;">
                            <label for="pcgamer_cooler_clearance">
                                <strong>🏠 Espacio máximo para Cooler</strong><br>
                                <small style="color:#999;">Gabinete</small>
                            </label>
                        </th>
                        <td style="padding: 16px 0;">
                            <input
                                type="number"
                                id="pcgamer_cooler_clearance"
                                name="pcgamer_cooler_clearance"
                                value="<?php echo esc_attr($cooler_clearance); ?>"
                                placeholder="Ej: 170"
                                style="width:100%;max-width:300px;padding:8px;border:1px solid #ddd;border-radius:4px;"
                                min="0"
                            />
                            <p style="color:#999;margin-top:8px;">Altura máxima en mm que el gabinete permite para un cooler de CPU (ej: 170 mm).</p>
                        </td>
                    </tr>

                    <!-- Tamaño del radiador AIO (Cooler) -->
                    <tr valign="top">
                        <th scope="row" style="padding: 16px 0;">
                            <label for="pcgamer_radiator_size">
                                <strong>💧 Tamaño de Radiador AIO</strong><br>
                                <small style="color:#999;">Cooler de CPU</small>
                            </label>
                        </th>
                        <td style="padding: 16px 0;">
                            <select
                                id="pcgamer_radiator_size"
                                name="pcgamer_radiator_size"
                                style="width:100%;max-width:300px;padding:8px;border:1px solid #ddd;border-radius:4px;"
                            >
                                <option value="" <?php selected($radiator_size, ''); ?>>— Refrigeración por aire (sin radiador) —</option>
                                <option value="120" <?php selected($radiator_size, '120'); ?>>120 mm</option>
                                <option value="240" <?php selected($radiator_size, '240'); ?>>240 mm</option>
                                <option value="360" <?php selected($radiator_size, '360'); ?>>360 mm</option>
                            </select>
                            <p style="color:#999;margin-top:8px;">Solo para refrigeración líquida (AIO). Dejar vacío si es cooler de aire.</p>
                        </td>
                    </tr>

                    <!-- Radiadores soportados (Gabinete) -->
                    <tr valign="top">
                        <th scope="row" style="padding: 16px 0;">
                            <label>
                                <strong>💧 Radiadores AIO Soportados</strong><br>
                                <small style="color:#999;">Gabinete</small>
                            </label>
                        </th>
                        <td style="padding: 16px 0;">
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <?php foreach (['120' => '120 mm', '240' => '240 mm', '360' => '360 mm'] as $val => $lbl): ?>
                                <label style="margin:0;">
                                    <input
                                        type="checkbox"
                                        name="pcgamer_radiator_support[]"
                                        value="<?php echo esc_attr($val); ?>"
                                        <?php checked(is_array($radiator_support) && in_array($val, $radiator_support)); ?>
                                    />
                                    <?php echo esc_html($lbl); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <p style="color:#999;margin-top:8px;">Marca los tamaños de radiador AIO que el gabinete puede montar</p>
                        </td>
                    </tr>

                </tbody>
            </table>

            <div style="margin-top:20px;padding:16px;background:#e8f4f8;border-radius:4px;border-left:4px solid #4a90e2;">
                <h4 style="margin-top:0;color:#4a90e2;">ℹ️ Guía rápida</h4>
                <ul style="margin:8px 0;padding-left:20px;">
                    <li><strong>Socket:</strong> Intel 13ª/14ª gen → LGA1700 | AMD Ryzen 7000 → AM5</li>
                    <li><strong>RAM:</strong> Intel 13ª gen soporta DDR4/DDR5 | AMD Ryzen 7000 requiere DDR5</li>
                    <li><strong>Form Factor:</strong> ATX es el mayor, Mini-ITX el menor</li>
                    <li><strong>Gabinete:</strong> marca todos los form factors que acepta (pueden ser varios)</li>
                    <li><strong>Cooler — Altura:</strong> mide el cooler físico (ej: 165 mm)</li>
                    <li><strong>Gabinete — Espacio para cooler:</strong> clearance del gabinete (ej: 170 mm)</li>
                    <li><strong>AIO — Tamaño radiador:</strong> ej. TH360 ARGB Sync → 360 mm</li>
                    <li><strong>Gabinete — Radiadores:</strong> marca todos los tamaños que puede montar</li>
                </ul>
            </div>
        </div>
        <?php
    }

    public function save_metabox_data($post_id) {
        if (!isset($_POST['pcgamer_compatibility_nonce']) ||
            !wp_verify_nonce($_POST['pcgamer_compatibility_nonce'], 'pcgamer_compatibility_nonce')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Socket
        if (isset($_POST['pcgamer_socket'])) {
            $val = sanitize_text_field($_POST['pcgamer_socket']);
            $val ? update_post_meta($post_id, '_pcgamer_socket', $val) : delete_post_meta($post_id, '_pcgamer_socket');
        }

        // Tipo de RAM
        if (isset($_POST['pcgamer_ram_type'])) {
            $val     = sanitize_text_field($_POST['pcgamer_ram_type']);
            $allowed = ['DDR4', 'DDR5'];
            if ($val && in_array($val, $allowed)) {
                update_post_meta($post_id, '_pcgamer_ram_type', $val);
            } else {
                delete_post_meta($post_id, '_pcgamer_ram_type');
            }
        }

        // Form Factor singular (Placa Madre)
        if (isset($_POST['pcgamer_form_factor'])) {
            $val     = sanitize_text_field($_POST['pcgamer_form_factor']);
            $allowed = ['ATX', 'Micro-ATX', 'Mini-ITX'];
            if ($val && in_array($val, $allowed)) {
                update_post_meta($post_id, '_pcgamer_form_factor', $val);
            } else {
                delete_post_meta($post_id, '_pcgamer_form_factor');
            }
        }

        // Form Factors (Gabinete)
        if (isset($_POST['pcgamer_form_factors'])) {
            $allowed = ['ATX', 'Micro-ATX', 'Mini-ITX'];
            $vals    = array_values(array_filter(
                array_map('sanitize_text_field', (array) $_POST['pcgamer_form_factors']),
                fn($v) => in_array($v, $allowed)
            ));
            $vals ? update_post_meta($post_id, '_pcgamer_form_factors', $vals) : delete_post_meta($post_id, '_pcgamer_form_factors');
        } else {
            delete_post_meta($post_id, '_pcgamer_form_factors');
        }

        // Wattaje
        if (isset($_POST['pcgamer_wattage'])) {
            $val = intval($_POST['pcgamer_wattage']);
            $val > 0 ? update_post_meta($post_id, '_pcgamer_wattage', $val) : delete_post_meta($post_id, '_pcgamer_wattage');
        }

        // Altura del Cooler (campo del cooler)
        if (isset($_POST['pcgamer_cooler_height'])) {
            $val = intval($_POST['pcgamer_cooler_height']);
            $val > 0 ? update_post_meta($post_id, '_pcgamer_cooler_height', $val) : delete_post_meta($post_id, '_pcgamer_cooler_height');
        }

        // Clearance del Gabinete (espacio máximo para cooler)
        if (isset($_POST['pcgamer_cooler_clearance'])) {
            $val = intval($_POST['pcgamer_cooler_clearance']);
            $val > 0 ? update_post_meta($post_id, '_pcgamer_cooler_clearance', $val) : delete_post_meta($post_id, '_pcgamer_cooler_clearance');
        }

        // Tamaño del radiador AIO (cooler)
        if (array_key_exists('pcgamer_radiator_size', $_POST)) {
            $val = sanitize_text_field($_POST['pcgamer_radiator_size']);
            ($val !== '' && in_array($val, ['120', '240', '360'], true))
                ? update_post_meta($post_id, '_pcgamer_radiator_size', $val)
                : delete_post_meta($post_id, '_pcgamer_radiator_size');
        }

        // Radiadores soportados (gabinete)
        if (isset($_POST['pcgamer_radiator_support'])) {
            $vals = array_values(array_filter(
                array_map('sanitize_text_field', (array) $_POST['pcgamer_radiator_support']),
                fn($v) => in_array($v, ['120', '240', '360'], true)
            ));
            $vals ? update_post_meta($post_id, '_pcgamer_radiator_support', $vals)
                  : delete_post_meta($post_id, '_pcgamer_radiator_support');
        } else {
            delete_post_meta($post_id, '_pcgamer_radiator_support');
        }
    }
}
