<?php
/**
 * Displayed when no products are found matching the current query.
 * Child-theme override — RST Nextech visual design.
 *
 * @package Wordpressstester (Flatsome child)
 */

defined( 'ABSPATH' ) || exit;

$shop_url = get_permalink( wc_get_page_id( 'shop' ) );
?>

<div class="nxt-no-products">
	<div class="nxt-no-products__inner">

		<div class="nxt-no-products__icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" fill="none">
				<circle cx="40" cy="40" r="38" stroke="#1e73be" stroke-width="2" stroke-dasharray="6 4"/>
				<path d="M24 30h32l-4 22H28L24 30Z" stroke="#1e73be" stroke-width="2" stroke-linejoin="round" fill="rgba(30,115,190,0.08)"/>
				<path d="M20 26h40" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round"/>
				<path d="M35 26l1.5-4h7L45 26" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				<line x1="33" y1="36" x2="35" y2="46" stroke="#c62828" stroke-width="2" stroke-linecap="round"/>
				<line x1="40" y1="36" x2="40" y2="46" stroke="#c62828" stroke-width="2" stroke-linecap="round"/>
				<line x1="47" y1="36" x2="45" y2="46" stroke="#c62828" stroke-width="2" stroke-linecap="round"/>
				<circle cx="57" cy="23" r="8" fill="#c62828"/>
				<path d="M54 23h6M57 20v6" stroke="#fff" stroke-width="2" stroke-linecap="round" transform="rotate(45 57 23)"/>
			</svg>
		</div>

		<h2 class="nxt-no-products__title">
			<?php esc_html_e( 'Productos temporalmente agotados', 'woocommerce' ); ?>
		</h2>

		<p class="nxt-no-products__desc">
			<?php esc_html_e( 'Estamos trabajando para reponer este stock lo antes posible. Mientras tanto, podés explorar otras categorías o contactarnos si necesitás un producto específico.', 'woocommerce' ); ?>
		</p>

		<div class="nxt-no-products__actions">
			<a href="<?php echo esc_url( $shop_url ); ?>" class="nxt-no-products__btn nxt-no-products__btn--primary">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
				<?php esc_html_e( 'Explora otros productos', 'woocommerce' ); ?>
			</a>
			<?php
			$contact_page = get_page_by_path( 'contacto' );
			if ( $contact_page ) : ?>
			<a href="<?php echo esc_url( get_permalink( $contact_page->ID ) ); ?>" class="nxt-no-products__btn nxt-no-products__btn--secondary">
				<?php esc_html_e( 'Contactarnos', 'woocommerce' ); ?>
			</a>
			<?php endif; ?>
		</div>

	</div>
</div>
