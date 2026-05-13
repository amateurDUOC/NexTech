<?php
/**
 * Cart Page — Nextech custom layout (two-column)
 *
 * Columna izquierda : tabla del carrito + botones de acción
 * Columna derecha   : resumen de totales + cupón (sticky)
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.9.0
 */

defined( 'ABSPATH' ) || exit;

$auto_refresh = get_theme_mod( 'cart_auto_refresh' );

do_action( 'woocommerce_before_cart' );
?>

<div class="nxt-cart-page">

<?php wc_print_notices(); ?>

<div class="nxt-cart-layout">

<!-- ══════════════════════════════════════════════════════════════════════════
     COLUMNA IZQUIERDA — Tabla del carrito + acciones
══════════════════════════════════════════════════════════════════════════════ -->
<div class="nxt-cart-left">

    <form class="woocommerce-cart-form<?php echo $auto_refresh ? ' cart-auto-refresh' : ''; ?>"
          action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">

        <?php do_action( 'woocommerce_before_cart_table' ); ?>

        <div class="cart-wrapper sm-touch-scroll">
            <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">
                <thead>
                    <tr>
                        <th class="product-remove"></th>
                        <th class="product-thumbnail"></th>
                        <th class="product-name"><?php esc_html_e( 'Producto', 'woocommerce' ); ?></th>
                        <th class="product-price"><?php esc_html_e( 'Precio', 'woocommerce' ); ?></th>
                        <th class="product-quantity"><?php esc_html_e( 'Cantidad', 'woocommerce' ); ?></th>
                        <th class="product-subtotal"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php do_action( 'woocommerce_before_cart_contents' ); ?>

                    <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                        $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                        $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
                        $product_name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );

                        if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) continue;
                        if ( ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) continue;

                        $product_permalink = apply_filters(
                            'woocommerce_cart_item_permalink',
                            $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '',
                            $cart_item,
                            $cart_item_key
                        );
                    ?>
                    <tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

                        <td class="product-remove">
                            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo apply_filters( 'woocommerce_cart_item_remove_link',
                                sprintf(
                                    '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                    esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                    esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ),
                                    esc_attr( $product_id ),
                                    esc_attr( $_product->get_sku() )
                                ),
                                $cart_item_key
                            ); ?>
                        </td>

                        <td class="product-thumbnail">
                            <?php
                            $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
                            if ( $product_permalink ) {
                                echo '<a href="' . esc_url( $product_permalink ) . '">' . $thumbnail . '</a>'; // phpcs:ignore
                            } else {
                                echo $thumbnail; // phpcs:ignore
                            }
                            ?>
                        </td>

                        <td class="product-name" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
                            <?php
                            if ( $product_permalink ) {
                                echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name',
                                    '<a href="' . esc_url( $product_permalink ) . '">' . $_product->get_name() . '</a>',
                                    $cart_item, $cart_item_key
                                ) );
                            } else {
                                echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name',
                                    $_product->get_name(), $cart_item, $cart_item_key
                                ) );
                            }

                            do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
                            echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore

                            if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
                                echo wp_kses_post( apply_filters(
                                    'woocommerce_cart_item_backorder_notification',
                                    '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>',
                                    $product_id
                                ) );
                            }
                            ?>
                        </td>

                        <td class="product-price" data-title="<?php esc_attr_e( 'Price', 'woocommerce' ); ?>">
                            <?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
                        </td>

                        <td class="product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'woocommerce' ); ?>">
                            <?php
                            if ( $_product->is_sold_individually() ) {
                                $min_quantity = 1;
                                $max_quantity = 1;
                            } else {
                                $min_quantity = 0;
                                $max_quantity = $_product->get_max_purchase_quantity();
                            }
                            $product_quantity = woocommerce_quantity_input(
                                [
                                    'input_name'   => "cart[{$cart_item_key}][qty]",
                                    'input_value'  => $cart_item['quantity'],
                                    'max_value'    => $max_quantity,
                                    'min_value'    => $min_quantity,
                                    'product_name' => $product_name,
                                ],
                                $_product,
                                false
                            );
                            echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // phpcs:ignore
                            ?>
                        </td>

                        <td class="product-subtotal" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>">
                            <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?>
                        </td>

                    </tr>
                    <?php endforeach; ?>

                    <?php do_action( 'woocommerce_cart_contents' ); ?>

                    <?php do_action( 'woocommerce_after_cart_contents' ); ?>
                </tbody>
            </table>
        </div>

        <?php do_action( 'woocommerce_after_cart_table' ); ?>

        <?php // Nonce + botón update fuera de la tabla para no dejar fila visible ?>
        <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
        <button type="submit" name="update_cart"
                value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"
                style="position:absolute;left:-9999px;visibility:hidden;pointer-events:none;"
                aria-hidden="true">
            <?php esc_html_e( 'Update cart', 'woocommerce' ); ?>
        </button>
    </form>

    <!-- Botones debajo de la tabla — en fila horizontal -->
    <div class="nxt-cart-actions">

        <!-- 1. Seguir comprando -->
        <a class="button wc-backward nxt-btn-continue"
           href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">
            ← <?php esc_html_e( 'Seguir comprando', 'woocommerce' ); ?>
        </a>

        <!-- 2. Exportar cotización -->
        <?php if ( function_exists( 'ncc_boton_cotizacion' ) ) ncc_boton_cotizacion(); ?>

    </div>

    <?php
    // Modal de cotización (overlay oculto, necesario para el JS del plugin).
    // Se llama directamente para evitar que do_action('woocommerce_after_cart')
    // dispare también el botón "Seguir comprando" de Flatsome (duplicado).
    if ( function_exists( 'ncc_modal_html' ) ) ncc_modal_html();
    ?>

</div><!-- .nxt-cart-left -->

<!-- ══════════════════════════════════════════════════════════════════════════
     COLUMNA DERECHA — Resumen de totales + cupón
══════════════════════════════════════════════════════════════════════════════ -->
<div class="nxt-cart-right">

    <?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
    <?php do_action( 'woocommerce_cart_collaterals' ); ?>

    <?php if ( wc_coupons_enabled() ) : ?>
    <div class="nxt-coupon-section">
        <!-- Botón disparador con ícono de etiqueta -->
        <button type="button" class="nxt-coupon-toggle" id="nxt-coupon-toggle" aria-expanded="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
            <?php esc_html_e( '¿Tienes un cupón?', 'woocommerce' ); ?>
            <span class="nxt-coupon-arrow">▾</span>
        </button>

        <!-- Formulario oculto por defecto -->
        <form class="nxt-coupon-form" id="nxt-coupon-form" method="post" hidden>
            <div class="coupon">
                <label for="coupon_code" class="screen-reader-text">
                    <?php esc_html_e( 'Coupon:', 'woocommerce' ); ?>
                </label>
                <input type="text" name="coupon_code" class="input-text" id="coupon_code"
                       value="" placeholder="<?php esc_attr_e( 'Ingresa tu código', 'woocommerce' ); ?>" />
                <button type="submit" class="button" name="apply_coupon"
                        value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>">
                    <?php esc_html_e( 'Aplicar', 'woocommerce' ); ?>
                </button>
                <?php do_action( 'woocommerce_cart_coupon' ); ?>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div><!-- .nxt-cart-right -->

</div><!-- .nxt-cart-layout -->

</div><!-- .nxt-cart-page -->
