<?php
/**
 * Plugin Name:  Nextech Cart Cotización
 * Description:  Genera cotizaciones PDF desde el carrito sin APIs externas. Numeración automática, diseño profesional con marca RS Tech.
 * Version:      1.1.0
 * Author:       Nextech / RStech
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'NCC_VERSION', '1.2.0' );
define( 'NCC_DIR',     plugin_dir_path( __FILE__ ) );
define( 'NCC_URL',     plugin_dir_url( __FILE__ ) );
define( 'NCC_OPTION',  'ncc_settings' );

// ── Helpers ───────────────────────────────────────────────────────────────────
function ncc_get( string $key, $default = '' ) {
    $opts = get_option( NCC_OPTION, [] );
    return $opts[ $key ] ?? $default;
}

/**
 * Devuelve el logo como data-URI base64.
 * Prioridad: (1) logo personalizado del tema → (2) logo hardcodeado del plugin → (3) cadena vacía.
 * Un data-URI es autónomo: funciona en cualquier ventana/iframe aislado sin petición HTTP.
 */
function ncc_logo_url(): string {
    // 1. Logo del tema (Apariencia → Personalizar → Logo)
    $logo_id = (int) get_theme_mod( 'custom_logo' );
    if ( $logo_id ) {
        $url = wp_get_attachment_url( $logo_id );
        if ( $url ) return $url;
    }

    // 2. Fallback: logo del plugin
    return NCC_URL . 'assets/logo_rst.png';
}

function ncc_logo_data_uri(): string {
    // Siempre usar el logo del plugin (logo_rst.png en assets)
    $path = NCC_DIR . 'assets/logo_rst.png';
    if ( file_exists( $path ) ) {
        $data = base64_encode( file_get_contents( $path ) );
        return 'data:image/png;base64,' . $data;
    }

    // Fallback: logo del tema
    $logo_id = (int) get_theme_mod( 'custom_logo' );
    if ( $logo_id ) {
        $theme_path = get_attached_file( $logo_id );
        if ( $theme_path && file_exists( $theme_path ) ) {
            return ncc_file_to_data_uri( $theme_path );
        }
    }

    return '';
}

function ncc_file_to_data_uri( string $path ): string {
    $mime = mime_content_type( $path ) ?: 'image/png';
    $data = base64_encode( (string) file_get_contents( $path ) );
    return "data:{$mime};base64,{$data}";
}

function ncc_siguiente_numero(): int {
    $n = (int) get_option( 'ncc_ultimo_numero', 2000 );
    $n++;
    update_option( 'ncc_ultimo_numero', $n );
    return $n;
}

// ── Admin: página de ajustes ──────────────────────────────────────────────────
add_action( 'admin_menu', 'ncc_admin_menu' );
function ncc_admin_menu(): void {
    add_submenu_page(
        'woocommerce',
        'Cotización PDF',
        'Cotización PDF',
        'manage_woocommerce',
        'ncc-settings',
        'ncc_settings_page'
    );
}

add_action( 'admin_init', 'ncc_register_settings' );
function ncc_register_settings(): void {
    register_setting( 'ncc_settings_group', NCC_OPTION, [
        'sanitize_callback' => 'ncc_sanitize_settings',
    ] );
    register_setting( 'ncc_settings_group', 'ncc_ultimo_numero', [
        'sanitize_callback' => 'absint',
    ] );
}

function ncc_sanitize_settings( $input ): array {
    return [
        'empresa_nombre'  => sanitize_text_field( $input['empresa_nombre']  ?? 'RS TECH LIMITADA' ),
        'empresa_rut'     => sanitize_text_field( $input['empresa_rut']     ?? '77.288.722-1' ),
        'empresa_giro'    => sanitize_text_field( $input['empresa_giro']    ?? '' ),
        'empresa_dir'     => sanitize_text_field( $input['empresa_dir']     ?? '' ),
        'empresa_comuna'  => sanitize_text_field( $input['empresa_comuna']  ?? '' ),
        'empresa_ciudad'  => sanitize_text_field( $input['empresa_ciudad']  ?? 'Santiago' ),
        'empresa_region'  => sanitize_text_field( $input['empresa_region']  ?? '' ),
        'empresa_tel'     => sanitize_text_field( $input['empresa_tel']     ?? '' ),
        'empresa_email'   => sanitize_email(      $input['empresa_email']   ?? '' ),
        'empresa_web'     => esc_url_raw(          $input['empresa_web']    ?? '' ),
        'validez_dias'    => absint(               $input['validez_dias']   ?? 7  ),
        'terminos'        => sanitize_textarea_field( $input['terminos']    ?? '' ),
    ];
}

function ncc_settings_page(): void {
    $logo_id = get_option( 'ncc_logo_id', 0 );
    $logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
    ?>
    <div class="wrap">
        <h1>📄 Nextech — Cotización PDF</h1>
        <p>Configuración del documento. No requiere API externa.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'ncc_settings_group' ); ?>

            <h2>Datos de la empresa emisora</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="ncc_nombre">Razón Social</label></th>
                    <td><input type="text" id="ncc_nombre" name="<?= NCC_OPTION ?>[empresa_nombre]"
                               value="<?= esc_attr( ncc_get('empresa_nombre','RS TECH LIMITADA') ) ?>" class="regular-text"/></td>
                </tr>
                <tr>
                    <th><label for="ncc_rut">RUT</label></th>
                    <td><input type="text" id="ncc_rut" name="<?= NCC_OPTION ?>[empresa_rut]"
                               value="<?= esc_attr( ncc_get('empresa_rut','77.288.722-1') ) ?>" class="regular-text"/></td>
                </tr>
                <tr>
                    <th><label for="ncc_giro">Giro</label></th>
                    <td><input type="text" id="ncc_giro" name="<?= NCC_OPTION ?>[empresa_giro]"
                               value="<?= esc_attr( ncc_get('empresa_giro') ) ?>" class="large-text"/></td>
                </tr>
                <tr>
                    <th><label for="ncc_dir">Dirección</label></th>
                    <td><input type="text" id="ncc_dir" name="<?= NCC_OPTION ?>[empresa_dir]"
                               value="<?= esc_attr( ncc_get('empresa_dir') ) ?>" class="regular-text"/></td>
                </tr>
                <tr>
                    <th><label>Comuna / Ciudad / Región</label></th>
                    <td>
                        <input type="text" name="<?= NCC_OPTION ?>[empresa_comuna]"
                               placeholder="Comuna" value="<?= esc_attr( ncc_get('empresa_comuna') ) ?>" class="regular-text" style="width:30%"/>
                        <input type="text" name="<?= NCC_OPTION ?>[empresa_ciudad]"
                               placeholder="Ciudad" value="<?= esc_attr( ncc_get('empresa_ciudad','Santiago') ) ?>" class="regular-text" style="width:28%"/>
                        <input type="text" name="<?= NCC_OPTION ?>[empresa_region]"
                               placeholder="Región" value="<?= esc_attr( ncc_get('empresa_region') ) ?>" class="regular-text" style="width:30%"/>
                    </td>
                </tr>
                <tr>
                    <th><label>Teléfono / Email / Web</label></th>
                    <td>
                        <input type="text" name="<?= NCC_OPTION ?>[empresa_tel]"
                               placeholder="+56 9 xxxx xxxx" value="<?= esc_attr( ncc_get('empresa_tel') ) ?>" style="width:30%"/>
                        <input type="email" name="<?= NCC_OPTION ?>[empresa_email]"
                               placeholder="contacto@rstech.cl" value="<?= esc_attr( ncc_get('empresa_email') ) ?>" style="width:28%"/>
                        <input type="url" name="<?= NCC_OPTION ?>[empresa_web]"
                               placeholder="https://rstech.cl" value="<?= esc_attr( ncc_get('empresa_web') ) ?>" style="width:30%"/>
                    </td>
                </tr>
                <tr>
                    <th><label for="ncc_validez">Validez (días hábiles)</label></th>
                    <td>
                        <input type="number" id="ncc_validez" min="1" max="30"
                               name="<?= NCC_OPTION ?>[validez_dias]"
                               value="<?= esc_attr( ncc_get('validez_dias', 7) ) ?>" class="small-text"/>
                    </td>
                </tr>
                <tr>
                    <th><label for="ncc_terminos">Términos y condiciones</label></th>
                    <td>
                        <textarea id="ncc_terminos" rows="4" class="large-text"
                                  name="<?= NCC_OPTION ?>[terminos]"><?= esc_textarea( ncc_get('terminos',
                            "Cotización válida por 7 días hábiles o hasta agotar stock.\nPagos vía transferencia o efectivo; tarjetas de crédito tienen recargo: 2% (Webpay) y 3% (Mercado Pago)."
                        ) ) ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label>Número de cotización siguiente</label></th>
                    <td>
                        <input type="number" min="1" name="ncc_ultimo_numero"
                               value="<?= esc_attr( (int) get_option('ncc_ultimo_numero', 2000) ) ?>"
                               class="small-text"/>
                        <p class="description">El próximo PDF usará este número + 1.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Guardar ajustes' ); ?>
        </form>
    </div>
    <?php
}

// ── Frontend: assets + botón + modal ─────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'ncc_enqueue' );
function ncc_enqueue(): void {
    if ( ! is_cart() ) return;

    wp_enqueue_style(  'ncc-style',  NCC_URL . 'assets/cotizacion.css', [], NCC_VERSION );
    wp_enqueue_script( 'ncc-script', NCC_URL . 'assets/cotizacion.js',  [], NCC_VERSION, true );

    wp_localize_script( 'ncc-script', 'NCC', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'ncc_generar_pdf' ),
        'i18n'    => [
            'sin_nombre' => 'El nombre del cliente es obligatorio.',
            'error'      => 'Error al generar el PDF. Intenta nuevamente.',
        ],
    ] );
}

// ── Botón en acciones del carrito ─────────────────────────────────────────────
add_action( 'woocommerce_cart_actions', 'ncc_boton_cotizacion', 20 );
function ncc_boton_cotizacion(): void {
    if ( WC()->cart->is_empty() ) return;
    echo '<button type="button" id="ncc-btn-cotizacion" class="button ncc-btn-export">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Exportar Cotización (PDF)
          </button>';
}

// ── Modal HTML ────────────────────────────────────────────────────────────────
add_action( 'woocommerce_after_cart', 'ncc_modal_html' );
function ncc_modal_html(): void { ?>
    <div id="ncc-overlay" class="ncc-overlay" hidden>
      <div class="ncc-modal" role="dialog" aria-modal="true" aria-labelledby="ncc-modal-title">

        <div class="ncc-modal-header">
          <h2 id="ncc-modal-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
            Exportar Cotización PDF
          </h2>
          <button type="button" id="ncc-close" class="ncc-close" aria-label="Cerrar">&times;</button>
        </div>

        <div class="ncc-modal-body">
          <p class="ncc-subtitle">Datos del receptor. Solo el nombre es obligatorio.</p>

          <form id="ncc-form" novalidate>
            <div class="ncc-field ncc-field--required">
              <label for="ncc-nombre">Nombre del Cliente</label>
              <input type="text" id="ncc-nombre" name="nombre" placeholder="Ej: Juan Pérez" autocomplete="name"/>
              <span class="ncc-field-error" hidden>Campo obligatorio.</span>
            </div>

            <div class="ncc-row">
              <div class="ncc-field">
                <label for="ncc-rut">RUT <span class="ncc-optional">(opcional)</span></label>
                <input type="text" id="ncc-rut" name="rut" placeholder="Ej: 12.345.678-9" maxlength="12"/>
              </div>
              <div class="ncc-field">
                <label for="ncc-giro">Giro <span class="ncc-optional">(opcional)</span></label>
                <input type="text" id="ncc-giro" name="giro" placeholder="Ej: Venta de computadores"/>
              </div>
            </div>

            <div class="ncc-field">
              <label for="ncc-direccion">Dirección <span class="ncc-optional">(opcional)</span></label>
              <input type="text" id="ncc-direccion" name="direccion" placeholder="Ej: Los Leones 382"/>
            </div>

            <div class="ncc-row">
              <div class="ncc-field">
                <label for="ncc-comuna">Comuna <span class="ncc-optional">(opcional)</span></label>
                <input type="text" id="ncc-comuna" name="comuna" placeholder="Ej: Ñuñoa"/>
              </div>
              <div class="ncc-field">
                <label for="ncc-ciudad">Ciudad <span class="ncc-optional">(opcional)</span></label>
                <input type="text" id="ncc-ciudad" name="ciudad" placeholder="Santiago" value="Santiago"/>
              </div>
            </div>

            <div id="ncc-error-global" class="ncc-alert ncc-alert--error" hidden></div>

            <div class="ncc-modal-footer">
              <button type="button" id="ncc-cancel" class="ncc-btn-cancel">Cancelar</button>
              <button type="submit" id="ncc-submit" class="ncc-btn-submit">
                <span class="ncc-submit-text">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                       fill="none" stroke="currentColor" stroke-width="2.5"
                       stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                  </svg>
                  Generar PDF
                </span>
                <span class="ncc-submit-loading" hidden>
                  <span class="ncc-spinner"></span> Generando…
                </span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
<?php }

// ── AJAX: generar HTML de cotización y devolver para impresión ────────────────
add_action( 'wp_ajax_ncc_generar_pdf',        'ncc_ajax_generar_pdf' );
add_action( 'wp_ajax_nopriv_ncc_generar_pdf', 'ncc_ajax_generar_pdf' );

function ncc_ajax_generar_pdf(): void {
    check_ajax_referer( 'ncc_generar_pdf', 'nonce' );

    $nombre    = sanitize_text_field( $_POST['nombre']    ?? '' );
    $rut       = sanitize_text_field( $_POST['rut']       ?? '' );
    $giro      = sanitize_text_field( $_POST['giro']      ?? '' );
    $direccion = sanitize_text_field( $_POST['direccion'] ?? '' );
    $comuna    = sanitize_text_field( $_POST['comuna']    ?? '' );
    $ciudad    = sanitize_text_field( $_POST['ciudad']    ?? 'Santiago' );

    if ( empty( $nombre ) ) {
        wp_send_json_error( [ 'message' => 'El nombre del cliente es obligatorio.' ], 422 );
    }

    $numero = ncc_siguiente_numero();

    // ── Items del carrito ─────────────────────────────────────────────────────
    $items       = [];
    $subtotal    = 0.0;
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $product = $cart_item['data'];
        $precio  = isset( $cart_item['pcgamer_custom_price'] )
            ? (float) $cart_item['pcgamer_custom_price']
            : (float) $product->get_price();
        $qty     = (int) $cart_item['quantity'];
        $total   = $precio * $qty;
        $subtotal += $total;
        $items[] = [
            'nombre'   => $product->get_name(),
            'unidad'   => 'ud',
            'cantidad' => $qty,
            'precio'   => $precio,
            'total'    => $total,
        ];
    }

    $neto  = round( $subtotal / 1.19 );
    $iva   = $subtotal - $neto;

    // ── Datos empresa ─────────────────────────────────────────────────────────
    $e = [
        'nombre'  => ncc_get( 'empresa_nombre', 'RS TECH LIMITADA' ),
        'rut'     => ncc_get( 'empresa_rut',    '77.288.722-1' ),
        'giro'    => ncc_get( 'empresa_giro',   'VENTA DE ARTÍCULOS DE COMPUTACIÓN, TELEFONÍA CELULAR Y TECNOLOGÍA.' ),
        'dir'     => ncc_get( 'empresa_dir',    'Eliodoro Flores 2475' ),
        'comuna'  => ncc_get( 'empresa_comuna', 'Ñuñoa' ),
        'ciudad'  => ncc_get( 'empresa_ciudad', 'Santiago' ),
        'region'  => ncc_get( 'empresa_region', 'Región Metropolitana de Santiago' ),
        'tel'     => ncc_get( 'empresa_tel',    '+56 9 3416 5163' ),
        'email'   => ncc_get( 'empresa_email',  'contacto@rstech.cl' ),
        'web'     => ncc_get( 'empresa_web',    'https://rstech.cl' ),
    ];
    $validez  = (int) ncc_get( 'validez_dias', 7 );
    $terminos = ncc_get( 'terminos',
        "Cotización válida por $validez días hábiles o hasta agotar stock.\nPagos vía transferencia o efectivo; tarjetas de crédito tienen recargo: 2% (Webpay) y 3% (Mercado Pago)."
    );
    $fecha = date_i18n( 'Y-m-d' );

    // ── Logo como base64 data-URI ─────────────────────────────────────────────
    $logo_url = ncc_logo_data_uri();

    // ── Generar HTML de la cotización ─────────────────────────────────────────
    ob_start();
    include NCC_DIR . 'templates/cotizacion.php';
    $html = ob_get_clean();

    wp_send_json_success( [ 'html' => $html, 'numero' => $numero ] );
}
