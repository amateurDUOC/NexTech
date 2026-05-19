<?php
/* ── Nextech — Mi Cuenta: panel principal ───────────────────────────────────
   Cargado por custom_account_interface() en inc/account.php.
   ─────────────────────────────────────────────────────────────────────────── */
?>
<div class="my-account-container">
    <div class="account-header">
        <p>Accede a tu información y pedidos desde aquí.</p>
    </div>
    <div class="account-content">
        <div class="account-section">
            <h3>Información de tu cuenta</h3>
            <p>Gestiona tus datos personales y contraseñas.</p>
            <p><a class="button" href="<?php echo esc_url( BASE_URL ); ?>/mi-cuenta/edit-account">Editar mi información</a></p>
        </div>
        <div class="account-section">
            <h3>Historial de Pedidos</h3>
            <p>Consulta el historial de tus pedidos</p>
            <p><a class="button" href="<?php echo esc_url( BASE_URL ); ?>/mi-cuenta/orders">Ver mis pedidos</a></p>
        </div>
        <div class="account-section">
            <h3>Direcciones Guardadas</h3>
            <p>Actualiza tu dirección de envío o facturación.</p>
            <p><a class="button" href="<?php echo esc_url( BASE_URL ); ?>/mi-cuenta/edit-address">Gestionar direcciones</a></p>
        </div>
        <div class="account-section">
            <h3>¿Cerrar Sesión?</h3>
            <p>Haz clic aquí si deseas cerrar tu sesión.</p>
            <p><a class="button logout-button" href="<?php echo esc_url( BASE_URL ); ?>/mi-cuenta/customer-logout">Cerrar Sesión</a></p>
        </div>
    </div>
</div>
