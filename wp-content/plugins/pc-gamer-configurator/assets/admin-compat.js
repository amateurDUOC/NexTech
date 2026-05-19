/* ── PC Gamer Configurator — Admin Compatibility Tab JS ─────────────────── */
/* Requires: pcgamerAdminCompat.nonce (localized via wp_localize_script)      */

jQuery(document).ready(function ($) {

    $('#pcgamer-save-compat-btn').on('click', function () {
        var btn     = $(this);
        var spinner = $('#pcgamer-save-spinner');
        var result  = $('#pcgamer-save-result');
        var notice  = $('#pcgamer-compat-notice');

        btn.prop('disabled', true);
        spinner.css('visibility', 'visible');
        result.text('Guardando...');
        notice.hide();

        var items = [];
        $('tr[data-product-id]').each(function () {
            var row  = $(this);
            var pid  = row.data('product-id');
            var data = { id: pid };

            row.find('.pcgamer-compat-field').each(function () {
                data[$(this).data('field')] = $(this).val();
            });

            var ffs = [];
            row.find('.pcgamer-compat-ff:checked').each(function () { ffs.push($(this).val()); });
            if (row.find('.pcgamer-compat-ff').length) data['form_factors'] = ffs;

            var rads = [];
            row.find('.pcgamer-compat-rad:checked').each(function () { rads.push($(this).val()); });
            if (row.find('.pcgamer-compat-rad').length) data['radiator_support'] = rads;

            items.push(data);
        });

        $.ajax({
            url:    ajaxurl,
            method: 'POST',
            data: {
                action: 'pcgamer_save_compatibility_bulk',
                nonce:  pcgamerAdminCompat.nonce,
                items:  JSON.stringify(items),
            },
            success: function (response) {
                spinner.css('visibility', 'hidden');
                btn.prop('disabled', false);
                if (response.success) {
                    result.css('color', 'green').text('✓ ' + response.data.message);
                    notice.css({ background: '#d4edda', color: '#155724', display: 'block' })
                          .text('✓ ' + response.data.message);
                    $('tr[data-product-id] .pcgamer-row-status')
                        .text('✅').attr('title', 'Datos cargados');
                } else {
                    result.css('color', 'red').text('Error: ' + response.data.message);
                    notice.css({ background: '#f8d7da', color: '#721c24', display: 'block' })
                          .text('Error: ' + response.data.message);
                }
                $('html,body').animate({ scrollTop: 0 }, 400);
            },
            error: function () {
                spinner.css('visibility', 'hidden');
                btn.prop('disabled', false);
                result.css('color', 'red').text('Error de conexión');
            },
        });
    });
});
