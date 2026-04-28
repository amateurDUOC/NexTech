<?php
// Agrega este código en dashboard-links.php
?>

<div style="text-align: center; margin-top: 20px;">
    <a href="https://rstech.cl" style="text-decoration: none;">
        <button id="animatedButton" style="padding: 10px 20px; background-color: #0073aa; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
            Volver al Inicio
        </button>
    </a>
</div>

<script>
// JavaScript para agregar la animación
document.addEventListener('DOMContentLoaded', function() {
    const button = document.getElementById('animatedButton');
    
    button.addEventListener('mouseover', function() {
        button.style.transform = 'scale(1.1)'; // Aumenta el tamaño al pasar el mouse
        button.style.transition = 'transform 0.2s ease'; // Animación suave
    });
    
    button.addEventListener('mouseout', function() {
        button.style.transform = 'scale(1)'; // Vuelve al tamaño original al salir
    });
});
</script>
