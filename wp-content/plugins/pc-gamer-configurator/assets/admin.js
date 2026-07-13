/* ── PC Gamer Configurator — Admin Panel JS ──────────────────────────────── */

jQuery(document).ready(function ($) {

    // ── Buscar/filtrar productos por tabla ────────────────────────────────
    $('.pcgamer-sync-search').on('input', function () {
        var search  = $(this).val().toLowerCase();
        var tableId = $(this).data('table');
        $('#' + tableId + ' tbody tr').each(function () {
            var rowText = '';
            $(this).find('td').each(function (idx) {
                if (idx === 1 || idx === 2) {
                    rowText += $(this).text().toLowerCase() + ' ';
                }
            });
            $(this).toggle(rowText.indexOf(search) !== -1);
        });
    });

    // ── Seleccionar / Deseleccionar todos ─────────────────────────────────
    $('.pcgamer-select-all-btn').on('click', function () {
        var tableId = $(this).data('table');
        $('#' + tableId + ' tbody tr').each(function () {
            $(this).find('input[type="checkbox"]').prop('checked', true).trigger('change');
        });
    });

    $('.pcgamer-deselect-all-btn').on('click', function () {
        var tableId = $(this).data('table');
        $('#' + tableId + ' tbody tr').each(function () {
            $(this).find('input[type="checkbox"]').prop('checked', false).trigger('change');
        });
    });

    // ── Ordenar por precio ────────────────────────────────────────────────
    $('.pcgamer-sort-price').on('change', function () {
        var tableId = $(this).data('table');
        var order   = $(this).val();
        var $rows   = $('#' + tableId + ' tbody tr').detach();
        $rows.sort(function (a, b) {
            var priceA = parseFloat($(a).data('price')) || 0;
            var priceB = parseFloat($(b).data('price')) || 0;
            return order === 'asc' ? priceA - priceB : priceB - priceA;
        });
        $('#' + tableId + ' tbody').append($rows);
    });

    // ── Sincronizar y guardar por categoría ───────────────────────────────
    $('.pcgamer-category-sync-btn').on('click', function (e) {
        e.preventDefault();
        var btn             = $(this);
        var category        = btn.data('category');
        var nonce           = btn.data('nonce');
        var spinner         = btn.siblings('.spinner');
        var resultContainer = btn.siblings('.pcgamer-category-sync-result');

        spinner.css('visibility', 'visible');
        resultContainer.html('Sincronizando...');

        var tableId      = 'pcgamer-sync-table-' + category.replace(/[^a-zA-Z0-9_-]/g, '-').toLowerCase();
        var checked      = [];
        var customPrices = {};

        $('#' + tableId + ' tbody tr').each(function () {
            var $row   = $(this);
            var $cb    = $row.find('input[type="checkbox"]');
            var $price = $row.find('input[type="number"]');
            if ($cb.prop('checked')) {
                checked.push($cb.val());
            }
            if ($price.length && $price.val() !== '') {
                customPrices[$cb.val()] = $price.val();
            }
        });

        $.ajax({
            url:    ajaxurl,
            method: 'POST',
            data: {
                action:        'pcgamer_category_sync_and_save',
                category:      category,
                nonce:         nonce,
                products:      checked,
                custom_prices: customPrices,
            },
            success: function (response) {
                spinner.css('visibility', 'hidden');
                if (response.success) {
                    resultContainer.html('<span style="color:green;">' + response.data.message + '</span>');
                } else {
                    resultContainer.html('<span style="color:red;">Error: ' + response.data.message + '</span>');
                }
            },
            error: function () {
                spinner.css('visibility', 'hidden');
                resultContainer.html('<span style="color:red;">Error al comunicarse con el servidor</span>');
            },
        });
    });

    // ── Toggle ON/OFF por categoría ───────────────────────────────────────
    $('.pcgamer-category-toggle').on('change', function () {
        var $toggle  = $(this);
        var category = $toggle.data('category');
        var enabled  = $toggle.is(':checked') ? 1 : 0;
        var label    = $toggle.closest('.pcgamer-toggle-switch').find('.toggle-label');

        label.text(enabled ? 'ON' : 'OFF');

        var row = $toggle.closest('tr');
        row.find('input[type="checkbox"], input[type="number"], button, select, input.pcgamer-sync-search')
           .not('.pcgamer-category-toggle')
           .prop('disabled', !enabled);

        $.post(ajaxurl, {
            action:        'pcgamer_toggle_category',
            category:      category,
            value:         enabled,
            pcgamer_nonce: $('input[name="pcgamer_nonce"]').val(),
        });
    });
});
