<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cotización Nº <?= esc_html( $numero ) ?> — <?= esc_html( $e['nombre'] ) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 12px;
    color: #1a1a1a;
    background: #fff;
    padding: 30px;
    max-width: 820px;
    margin: 0 auto;
  }

  /* ── Header ────────────────────────────────────────────────────────────── */
  .header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 28px;
    gap: 20px;
  }

  .header-left {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    flex: 1;
  }

  .header-logo img {
    width: 90px;
    height: 90px;
    object-fit: contain;
    display: block;
  }

  .header-empresa {
    flex: 1;
  }

  .header-empresa h1 {
    font-size: 22px;
    font-weight: 800;
    color: #1565c0;
    margin-bottom: 2px;
  }

  .header-empresa .giro {
    font-size: 11px;
    font-style: italic;
    color: #444;
    margin-bottom: 8px;
  }

  .header-empresa p {
    font-size: 11px;
    color: #444;
    line-height: 1.7;
  }

  /* ── Caja cotización ────────────────────────────────────────────────────── */
  .header-doc {
    border: 2px solid #1565c0;
    border-radius: 4px;
    overflow: hidden;
    text-align: center;
    min-width: 180px;
  }

  .header-doc-title {
    padding: 8px 20px;
    font-size: 16px;
    font-weight: 800;
    color: #1565c0;
  }

  .header-doc-numero {
    padding: 4px 20px 8px;
    font-size: 16px;
    font-weight: 800;
    color: #1565c0;
  }

  .header-doc-badge {
    background: #b71c1c;
    color: #fff;
    padding: 5px 16px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
  }

  .header-doc-fecha {
    padding: 6px 16px;
    font-size: 11px;
    color: #444;
  }

  /* ── Sección títulos ────────────────────────────────────────────────────── */
  .seccion-titulo {
    font-size: 13px;
    font-weight: 700;
    color: #1565c0;
    border-bottom: 2px solid #1565c0;
    padding-bottom: 4px;
    margin-bottom: 12px;
    margin-top: 20px;
  }

  /* ── Receptor ───────────────────────────────────────────────────────────── */
  .receptor-tabla {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 4px;
    font-size: 11.5px;
  }

  .receptor-tabla td {
    padding: 7px 10px;
    border-bottom: 1px solid #e8e8e8;
  }

  .receptor-tabla td.label {
    font-weight: 700;
    color: #1a1a1a;
    width: 90px;
  }

  .receptor-tabla td.value {
    color: #333;
    border-right: 1px solid #e8e8e8;
    min-width: 180px;
  }

  .receptor-tabla td.value:last-child {
    border-right: none;
  }

  /* ── Tabla de productos ─────────────────────────────────────────────────── */
  .detalle-tabla {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    font-size: 12px;
  }

  .detalle-tabla thead tr {
    background: #1565c0;
    color: #fff;
  }

  .detalle-tabla th {
    padding: 10px 14px;
    text-align: left;
    font-weight: 700;
    font-size: 11px;
    letter-spacing: 0.3px;
  }

  .detalle-tabla th.right,
  .detalle-tabla td.right { text-align: right; }

  .detalle-tabla tbody tr td {
    padding: 10px 14px;
    border-bottom: 1px solid #eee;
    color: #222;
  }

  .detalle-tabla tbody tr:nth-child(even) td {
    background: #f7f9ff;
  }

  /* ── Footer ─────────────────────────────────────────────────────────────── */
  .footer-grid {
    display: flex;
    gap: 16px;
    margin-top: 8px;
    align-items: flex-start;
  }

  .footer-terminos {
    flex: 1;
    padding: 14px;
  }

  .footer-terminos h4 {
    font-size: 11px;
    font-weight: 800;
    color: #1565c0;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .footer-terminos ul {
    list-style: disc;
    padding-left: 16px;
  }

  .footer-terminos ul li {
    font-size: 10.5px;
    color: #444;
    margin-bottom: 5px;
    line-height: 1.5;
  }

  .footer-resumen {
    min-width: 220px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
  }

  .resumen-tabla {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }

  .resumen-tabla td {
    padding: 10px 16px;
    border-bottom: 1px solid #eee;
    color: #333;
  }

  .resumen-tabla td:last-child { text-align: right; }

  .resumen-tabla tr.total td {
    font-weight: 800;
    font-size: 13px;
    color: #1a1a1a;
    background: #f0f0f0;
    border-bottom: none;
  }

  /* ── Pie de página ──────────────────────────────────────────────────────── */
  .pie-pagina {
    text-align: right;
    font-size: 10px;
    color: #999;
    margin-top: 24px;
  }

  /* ── Print ──────────────────────────────────────────────────────────────── */
  @media print {
    body { padding: 15px; }
    @page { margin: 10mm; size: A4; }
  }
</style>
</head>
<body>

  <!-- Header -->
  <div class="header">
    <div class="header-left">
      <?php if ( $logo_url ) : ?>
      <div class="header-logo">
        <img src="<?= esc_attr( $logo_url ) ?>" alt="<?= esc_attr( $e['nombre'] ) ?>">
      </div>
      <?php endif; ?>
      <div class="header-empresa">
        <h1><?= esc_html( $e['nombre'] ) ?></h1>
        <?php if ( $e['giro'] ) : ?>
          <p class="giro"><?= esc_html( $e['giro'] ) ?></p>
        <?php endif; ?>
        <p>
          RUT: <?= esc_html( $e['rut'] ) ?><br>
          <?= esc_html( $e['dir'] ) ?><?= $e['comuna'] ? ', ' . esc_html( $e['comuna'] ) . ', ' . esc_html( $e['ciudad'] ) : '' ?><br>
          <?php if ( $e['tel'] || $e['email'] ) : ?>
            Contacto: <?= esc_html( $e['tel'] ) ?><?= ( $e['tel'] && $e['email'] ) ? ' | ' : '' ?><?= esc_html( $e['email'] ) ?><br>
          <?php endif; ?>
          <?php if ( $e['web'] ) : ?>
            Web: <?= esc_html( preg_replace( '#^https?://#', '', rtrim( $e['web'], '/' ) ) ) ?>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <div class="header-doc">
      <div class="header-doc-title">COTIZACIÓN</div>
      <div class="header-doc-numero">N° <?= esc_html( $numero ) ?></div>
      <div class="header-doc-badge">DOCUMENTO NO TRIBUTARIO</div>
      <div class="header-doc-fecha">Emisión: <?= date_i18n( 'j \d\e F Y' ) ?></div>
    </div>
  </div>

  <!-- Receptor -->
  <div class="seccion-titulo">DATOS DEL RECEPTOR</div>
  <table class="receptor-tabla">
    <tbody>
      <tr>
        <td class="label">Señor(es):</td>
        <td class="value"><?= esc_html( $nombre ) ?></td>
        <td class="label">RUT:</td>
        <td class="value"><?= esc_html( $rut ) ?></td>
      </tr>
      <tr>
        <td class="label">Giro:</td>
        <td class="value"><?= esc_html( $giro ) ?></td>
        <td class="label">Dirección:</td>
        <td class="value"><?= esc_html( $direccion ) ?></td>
      </tr>
      <tr>
        <td class="label">Comuna:</td>
        <td class="value"><?= esc_html( $comuna ) ?></td>
        <td class="label">Ciudad:</td>
        <td class="value"><?= esc_html( $ciudad ) ?></td>
      </tr>
    </tbody>
  </table>

  <!-- Detalle productos -->
  <div class="seccion-titulo">DETALLE DE PRODUCTOS</div>
  <table class="detalle-tabla">
    <thead>
      <tr>
        <th>DESCRIPCIÓN</th>
        <th class="right" style="width:60px">CANT.</th>
        <th class="right" style="width:110px">P. UNIT.</th>
        <th class="right" style="width:110px">TOTAL</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ( $items as $item ) : ?>
      <tr>
        <td><?= esc_html( $item['nombre'] ) ?></td>
        <td class="right"><?= esc_html( $item['cantidad'] ) ?></td>
        <td class="right">$<?= number_format( $item['precio'], 0, ',', '.' ) ?></td>
        <td class="right">$<?= number_format( $item['total'],  0, ',', '.' ) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Footer -->
  <div class="footer-grid">
    <div class="footer-terminos">
      <h4>Términos y condiciones</h4>
      <ul>
        <?php foreach ( explode( "\n", $terminos ) as $linea ) : ?>
          <?php if ( trim( $linea ) ) : ?>
            <li><?= esc_html( trim( $linea ) ) ?></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="footer-resumen">
      <table class="resumen-tabla">
        <tbody>
          <tr>
            <td>Monto Neto</td>
            <td>$<?= number_format( $neto, 0, ',', '.' ) ?></td>
          </tr>
          <tr>
            <td>IVA (19%)</td>
            <td>$<?= number_format( $iva, 0, ',', '.' ) ?></td>
          </tr>
          <tr class="total">
            <td>TOTAL A PAGAR</td>
            <td>$<?= number_format( $subtotal, 0, ',', '.' ) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pie -->
  <div class="pie-pagina">Página 1</div>

  <script>
    window.addEventListener('load', function() {
      setTimeout(function() { window.print(); }, 400);
    });
  </script>

</body>
</html>
