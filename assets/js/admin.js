/**
 * Antigravity Connect - Lógica JavaScript para el panel de administración
 */
(function ($) {
    'use strict';

    $(function () {
        var $alertBox = $('#agy-alert-box');
        var $exchangeBtn = $('#agy-btn-exchange');
        var $codeInput = $('#agy-auth-code-input');
        var $spinner = $('#agy-exchange-spinner');
        var $pingBtn = $('#agy-btn-ping');
        var $pingResult = $('#agy-ping-result');
        var $disconnectBtn = $('#agy-btn-disconnect');
        var $copyUrlBtn = $('#agy-btn-copy-url');

        /**
         * Muestra una alerta temporal o persistente en la cabecera del panel.
         */
        function showAlert(type, message) {
            $alertBox
                .removeClass('agy-alert-success agy-alert-error')
                .addClass('agy-alert-' + type)
                .html('<span class="dashicons dashicons-' + (type === 'success' ? 'yes' : 'warning') + '"></span> ' + $('<div>').text(message).html())
                .fadeIn(200);
        }

        /**
         * 1. Copiar URL de autorización al portapapeles
         */
        $copyUrlBtn.on('click', function (e) {
            e.preventDefault();
            var url = $(this).data('url');
            if (!url) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    showAlert('success', agyVars.i18n.copySuccess);
                });
            } else {
                var $temp = $('<input>').val(url).appendTo('body').select();
                document.execCommand('copy');
                $temp.remove();
                showAlert('success', agyVars.i18n.copySuccess);
            }
        });

        /**
         * 2. Intercambio de AuthCode por Tokens
         */
        $exchangeBtn.on('click', function (e) {
            e.preventDefault();
            var rawVal = $.trim($codeInput.val());

            if (!rawVal) {
                showAlert('error', agyVars.i18n.emptyCode);
                $codeInput.focus();
                return;
            }

            var code = rawVal;
            // Si el usuario pegó la URL de redirección completa
            if (rawVal.indexOf('code=') !== -1) {
                var match = rawVal.match(/[?&]code=([^&]+)/);
                if (match && match[1]) {
                    code = decodeURIComponent(match[1]);
                }
            }

            $exchangeBtn.prop('disabled', true);
            $spinner.addClass('is-active');
            showAlert('success', agyVars.i18n.connecting);

            $.ajax({
                url: agyVars.restUrl + 'exchange-code',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agyVars.restNonce);
                },
                data: {
                    code: code
                },
                success: function (res) {
                    $spinner.removeClass('is-active');
                    showAlert('success', agyVars.i18n.connectedSuccess);
                    setTimeout(function () {
                        window.location.reload();
                    }, 1200);
                },
                error: function (xhr) {
                    $spinner.removeClass('is-active');
                    $exchangeBtn.prop('disabled', false);
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) 
                        ? xhr.responseJSON.message 
                        : 'Error desconocido durante la autorización.';
                    showAlert('error', msg);
                }
            });
        });

        /**
         * 3. Probar conexión en vivo (Ping)
         */
        $pingBtn.on('click', function (e) {
            e.preventDefault();
            $pingBtn.prop('disabled', true);
            $pingResult.show().html('<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> ' + agyVars.i18n.testing);

            $.ajax({
                url: agyVars.restUrl + 'ping',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agyVars.restNonce);
                },
                success: function (res) {
                    $pingBtn.prop('disabled', false);
                    var html = '<span class="dashicons dashicons-yes-alt" style="color:#137333;"></span> ' +
                        $('<div>').text(res.message).html();
                    $pingResult.html(html);
                },
                error: function (xhr) {
                    $pingBtn.prop('disabled', false);
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Fallo en la prueba.';
                    var html = '<span class="dashicons dashicons-dismiss" style="color:#c5221f;"></span> ' +
                        $('<div>').text(msg).html();
                    $pingResult.html(html);
                }
            });
        });

        /**
         * 4. Desconectar y revocar credenciales
         */
        $disconnectBtn.on('click', function (e) {
            e.preventDefault();
            if (!confirm(agyVars.i18n.disconnectConfirm)) {
                return;
            }

            $disconnectBtn.prop('disabled', true);
            showAlert('success', agyVars.i18n.disconnecting);

            $.ajax({
                url: agyVars.restUrl + 'disconnect',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', agyVars.restNonce);
                },
                success: function () {
                    showAlert('success', agyVars.i18n.disconnectedSuccess);
                    setTimeout(function () {
                        window.location.reload();
                    }, 1000);
                },
                error: function () {
                    $disconnectBtn.prop('disabled', false);
                    showAlert('error', 'No fue posible completar la desconexión.');
                }
            });
        });
    });
})(jQuery);
