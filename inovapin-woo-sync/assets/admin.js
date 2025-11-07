(function ($, window) {
    'use strict';

    const settings = window.InovapinWooSync || {};
    let reportChart = null;

    function toast(message, type = 'success') {
        const notice = $('<div class="inovapin-toast inovapin-toast-' + type + '">' + message + '</div>');
        $('body').append(notice);
        setTimeout(function () {
            notice.addClass('visible');
        }, 10);
        setTimeout(function () {
            notice.removeClass('visible').fadeOut(300, function () {
                notice.remove();
            });
        }, 4000);
    }

    function updateStatusCard(status, message) {
        const statusEl = $('.inovapin-status');
        if (!statusEl.length) {
            return;
        }
        statusEl.removeClass('status-ok status-error status-idle');
        statusEl.addClass('status-' + status);
        statusEl.text(message);
    }

    function fetchStats(range) {
        $.ajax({
            url: settings.ajaxUrl,
            method: 'GET',
            data: {
                action: 'inovapin_get_stats',
                nonce: settings.nonce,
                range: range,
            },
        })
            .done(function (response) {
                if (!response.success) {
                    toast(response.data && response.data.message ? response.data.message : 'İstatistik alınamadı', 'error');
                    return;
                }
                renderStats(response.data.stats || []);
            })
            .fail(function () {
                toast('İstatistik alınamadı', 'error');
            });
    }

    function renderStats(stats) {
        const tbody = $('.inovapin-report-body');
        tbody.empty();

        stats.forEach(function (item) {
            const row = $('<tr />');
            const label = item.stat_period || item.stat_date;
            row.append('<td>' + $('<span />').text(label).html() + '</td>');
            row.append('<td>' + parseInt(item.created_products || 0, 10) + '</td>');
            row.append('<td>' + parseInt(item.updated_products || 0, 10) + '</td>');
            row.append('<td>' + parseInt(item.error_count || 0, 10) + '</td>');
            row.append('<td>' + parseInt(item.duration || 0, 10) + '</td>');
            tbody.append(row);
        });

        if (!reportChart) {
            const canvas = document.getElementById('inovapin-report-chart');
            reportChart = window.InovapinCharts.createChart(canvas);
        }
        window.InovapinCharts.updateChart(reportChart, stats);
    }

    function triggerHealthCheck() {
        if (!settings.restUrl) {
            return;
        }
        updateStatusCard('idle', 'Kontrol ediliyor...');
        $.ajax({
            url: settings.restUrl + '/health',
            method: 'GET',
        })
            .done(function () {
                updateStatusCard('ok', settings.testLabels.success);
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : settings.testLabels.error;
                updateStatusCard('error', message);
            });
    }

    function bindEvents() {
        $('.inovapin-get-token').on('click', function (event) {
            event.preventDefault();
            const email = $('#woocommerce_inovapin-woo-sync_email').val();
            const password = $('#woocommerce_inovapin-woo-sync_password').val();

            if (!email || !password) {
                toast('Email ve parola zorunlu.', 'error');
                return;
            }

            $.post(settings.ajaxUrl, {
                action: 'inovapin_get_token',
                nonce: settings.nonce,
                email: email,
                password: password,
            })
                .done(function (response) {
                    if (!response.success) {
                        toast(response.data && response.data.message ? response.data.message : 'Token alınamadı', 'error');
                        return;
                    }
                    $('#woocommerce_inovapin-woo-sync_api_token').val(response.data.token);
                    if (response.data.apiKey) {
                        $('#woocommerce_inovapin-woo-sync_api_key').val(response.data.apiKey);
                    }
                    toast('Token başarıyla alındı.', 'success');
                    triggerHealthCheck();
                })
                .fail(function () {
                    toast('Token alınamadı', 'error');
                });
        });

        $('.inovapin-test-connection').on('click', function (event) {
            event.preventDefault();
            updateStatusCard('idle', 'Kontrol ediliyor...');
            $.post(settings.ajaxUrl, {
                action: 'inovapin_test_connection',
                nonce: settings.nonce,
            })
                .done(function (response) {
                    if (!response.success) {
                        updateStatusCard('error', response.data && response.data.message ? response.data.message : settings.testLabels.error);
                        toast(settings.testLabels.error, 'error');
                        return;
                    }
                    updateStatusCard('ok', settings.testLabels.success);
                    toast(settings.testLabels.success, 'success');
                })
                .fail(function () {
                    updateStatusCard('error', settings.testLabels.error);
                    toast(settings.testLabels.error, 'error');
                });
        });

        $('.inovapin-start-sync').on('click', function (event) {
            event.preventDefault();
            const button = $(this);
            button.prop('disabled', true);
            const progress = $('<div class="inovapin-progress"><span class="bar"></span></div>');
            button.after(progress);
            progress.find('.bar').css('width', '15%');

            $.post(settings.ajaxUrl, {
                action: 'inovapin_manual_sync',
                nonce: settings.nonce,
                categories: true,
                products: true,
            })
                .done(function (response) {
                    if (!response.success) {
                        toast(response.data && response.data.message ? response.data.message : settings.manualSync.error, 'error');
                        progress.find('.bar').css('width', '100%').addClass('error');
                        return;
                    }
                    toast(settings.manualSync.success, 'success');
                    progress.find('.bar').css('width', '100%');
                    fetchStats($('.inovapin-toggle button.active').data('range') || 'daily');
                })
                .fail(function () {
                    toast(settings.manualSync.error, 'error');
                    progress.find('.bar').css('width', '100%').addClass('error');
                })
                .always(function () {
                    button.prop('disabled', false);
                    setTimeout(function () {
                        progress.fadeOut(300, function () { progress.remove(); });
                    }, 1200);
                });
        });

        $('.inovapin-toggle button').on('click', function (event) {
            event.preventDefault();
            const btn = $(this);
            $('.inovapin-toggle button').removeClass('active');
            btn.addClass('active');
            fetchStats(btn.data('range'));
        });
    }

    $(function () {
        bindEvents();
        fetchStats('daily');
        triggerHealthCheck();
    });
})(jQuery, window);
