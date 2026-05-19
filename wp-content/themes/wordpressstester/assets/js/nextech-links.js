/* ── Nextech — Eliminar target="_blank" de enlaces internos ─────────────── */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('a[target="_blank"]:not(.external)').forEach(function (link) {
        link.removeAttribute('target');
    });
});
