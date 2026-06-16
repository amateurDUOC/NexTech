<?php
/**
 * Empty cart page — Nextech override
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="nxt-empty-cart">

    <div class="nxt-empty-cart__icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 01-8 0"/>
        </svg>
    </div>

    <h2 class="nxt-empty-cart__title"><?php esc_html_e( 'Tu carrito está vacío', 'woocommerce' ); ?></h2>
    <p class="nxt-empty-cart__subtitle">Agrega productos para comenzar tu compra</p>

    <?php do_action( 'woocommerce_cart_is_empty' ); ?>

    <div class="nxt-empty-cart__actions">
        <?php if ( wc_get_page_id( 'shop' ) > 0 ) : ?>
            <a class="nxt-empty-cart__btn-shop" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">
                <?php echo esc_html( apply_filters( 'woocommerce_return_to_shop_text', __( 'Ir a la tienda', 'woocommerce' ) ) ); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="nxt-empty-cart__categories">
        <p class="nxt-empty-cart__categories-title">Explorar categorías</p>
        <div class="nxt-empty-cart__categories-grid">
            <?php
            $terms = get_terms( [
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'number'     => 4,
                'exclude'    => get_option( 'default_product_cat', 0 ),
                'orderby'    => 'count',
                'order'      => 'DESC',
            ] );
            foreach ( $terms as $term ) :
                $thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
                $image        = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : wc_placeholder_img_src();
            ?>
                <a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="nxt-empty-cat-card">
                    <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $term->name ); ?>">
                    <span><?php echo esc_html( $term->name ); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

</div>
