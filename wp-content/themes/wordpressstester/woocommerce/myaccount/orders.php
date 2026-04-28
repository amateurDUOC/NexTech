<?php
/**
 * Orders
 *
 * Shows orders on the account page with improved pagination.
 *
 * @package WooCommerce\Templates
 * @version 9.5.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders ); ?>

<?php if ( $has_orders ) : ?>

    <div class="orders-panel">
        <h2><?php echo esc_html__( 'Historial de tus órdenes', 'woocommerce' ); ?></h2>
        <table class="woocommerce-orders-table shop_table shop_table_responsive my_account_orders">
            <thead>
                <tr>
                    <?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) : ?>
                        <th class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr( $column_id ); ?>">
                            <span><?php echo esc_html( $column_name ); ?></span>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $customer_orders->orders as $customer_order ) :
                    $order = wc_get_order( $customer_order );
                    $item_count = $order->get_item_count() - $order->get_item_count_refunded();
                ?>
                    <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?>">
                        <?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) : ?>
                            <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
                                <?php if ( has_action( 'woocommerce_my_account_my_orders_column_' . $column_id ) ) :
                                    do_action( 'woocommerce_my_account_my_orders_column_' . $column_id, $order );
                                elseif ( 'order-number' === $column_id ) : ?>
                                    <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
                                        <?php echo esc_html( '#' . $order->get_order_number() ); ?>
                                    </a>
                                <?php elseif ( 'order-date' === $column_id ) : ?>
                                    <time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>">
                                        <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
                                    </time>
                                <?php elseif ( 'order-status' === $column_id ) : ?>
                                    <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
                                <?php elseif ( 'order-total' === $column_id ) : ?>
                                    <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
                                <?php elseif ( 'order-actions' === $column_id ) :
                                    $actions = wc_get_account_orders_actions( $order );
                                    if ( ! empty( $actions ) ) :
                                        foreach ( $actions as $action ) :
                                            echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button button ' . sanitize_html_class( $action['name'] ) . '">' . esc_html( $action['name'] ) . '</a>';
                                        endforeach;
                                    endif;
                                endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Paginación -->
        <?php if ( 1 < $customer_orders->max_num_pages ) : ?>
            <div class="pagination-container">
                <span class="page-info"><?php echo sprintf( esc_html__( 'Page %1$d of %2$d', 'woocommerce' ), $current_page, $customer_orders->max_num_pages ); ?></span>
                <ul class="pagination">
                    <!-- Botón Anterior -->
                    <?php if ( $current_page > 1 ) : ?>
                        <li><a href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ); ?>" class="prev-page">&laquo;</a></li>
                    <?php else : ?>
                        <li class="disabled"><span class="prev-page disabled">&laquo;</span></li>
                    <?php endif; ?>

                    <!-- Páginas Numeradas -->
                    <?php
                    $range = 2;
                    for ( $i = 1; $i <= $customer_orders->max_num_pages; $i++ ) :
                        if ( $i === 1 || $i === $customer_orders->max_num_pages || ( $i >= $current_page - $range && $i <= $current_page + $range ) ) :
                            if ( $i === $current_page ) :
                                echo '<li class="active"><span>' . $i . '</span></li>';
                            else :
                                echo '<li><a href="' . esc_url( wc_get_endpoint_url( 'orders', $i ) ) . '">' . $i . '</a></li>';
                            endif;
                        elseif ( $i === $current_page - $range - 1 || $i === $current_page + $range + 1 ) :
                            echo '<li class="dots"><span>...</span></li>';
                        endif;
                    endfor;
                    ?>

                    <!-- Botón Siguiente -->
                    <?php if ( $current_page < $customer_orders->max_num_pages ) : ?>
                        <li><a href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ); ?>" class="next-page">&raquo;</a></li>
                    <?php else : ?>
                        <li class="disabled"><span class="next-page disabled">&raquo;</span></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>


<?php endif;

do_action( 'woocommerce_after_account_orders', $has_orders );