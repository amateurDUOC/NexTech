<?php
/**
 * My Addresses
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/my-address.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$customer_id = get_current_user_id();

if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) {
    $get_addresses = apply_filters(
        'woocommerce_my_account_get_addresses',
        array(
            'billing'  => __( 'Direccion de envio', 'woocommerce' ),
            'shipping' => __( 'Shipping address', 'woocommerce' ),
        ),
        $customer_id
    );
} else {
    $get_addresses = apply_filters(
        'woocommerce_my_account_get_addresses',
        array(
            'billing' => __( 'Direccion de envío', 'woocommerce' ),
        ),
        $customer_id
    );
}
?>

<div class="woocommerce-address-grid">
    <?php foreach ( $get_addresses as $name => $address_title ) : ?>
        <?php
        $address = wc_get_account_formatted_address( $name );
        ?>

        <div class="woocommerce-address-card-wrapper">
            <div class="woocommerce-address-card">
                <div class="woocommerce-address-content-wrapper">
                    <header class="woocommerce-address-header">
                        <h3 class="woocommerce-address-title">
                            <i class="fas fa-map-marker-alt"></i> <!-- Address Icon -->
                            <?php echo esc_html( $address_title ); ?>
                        </h3>
                    </header>
                    <div class="woocommerce-address-content">
                        <p>
                            <?php
                            echo $address ? wp_kses_post( $address ) : esc_html_e( 'You have not set up this type of address yet.', 'woocommerce' );
                            ?>
                        </p>
                    </div>
                    <div class="woocommerce-address-action">
                        <a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', $name ) ); ?>" class="woocommerce-address-edit-button-text">
                            <i class="fas fa-edit"></i> Editar direccón
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.woocommerce-address-grid {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 20px;
    width: 100%;
}

.woocommerce-address-card-wrapper {
    display: flex;
    justify-content: center;
    width: 100%;
}

.woocommerce-address-card {
    display: flex;
    flex-direction: column;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    max-width: 400px;
}

.woocommerce-address-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
}

.woocommerce-address-content-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.woocommerce-address-header {
    margin-bottom: 10px;
    text-align: center;
}

.woocommerce-address-title {
    font-size: 18px;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 8px;
}

.woocommerce-address-content {
    font-size: 14px;
    color: #333;
    margin-bottom: 20px;
    text-align: center;
}

.woocommerce-address-action {
    text-align: center;
}

.woocommerce-address-edit-button-text {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 15px;
    background-color: #007cba;
    border-radius: 5px;
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    transition: background-color 0.3s ease;
}

.woocommerce-address-edit-button-text:hover {
    background-color: #005a87;
}

.woocommerce-address-edit-button-text i {
    margin-right: 5px;
}
</style>

<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
