/* ── PC Gamer Configurator — Product Metabox JS ─────────────────────────── */

document.addEventListener('DOMContentLoaded', function () {
    var cb       = document.querySelector("input[name='pcgamer_enabled']");
    var settings = document.getElementById('pcgamer-upgrades-settings');
    if (cb && settings) {
        cb.addEventListener('change', function () {
            settings.style.display = this.checked ? '' : 'none';
        });
    }
});
