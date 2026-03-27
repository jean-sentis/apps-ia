/**
 * Script admin LMD Apps IA
 */
(function($) {
    'use strict';

    (function initActivityTracking() {
        var pageType = null, estimationId = null, startTime = Date.now(), maxSec = 60;
        var $detail = $('.lmd-estimation-detail[id^="ed-wrap-"]');
        if ($detail.length) {
            var m = $detail.attr('id').match(/ed-wrap-(\d+)/);
            if (m) { pageType = 'detail'; estimationId = m[1]; }
        } else if ($('#lmd-estimations-list-wrap').length) {
            pageType = 'grid';
        }
        if (!pageType) return;
        function sendLog() {
            var elapsed = Math.floor((Date.now() - startTime) / 1000);
            var duration = Math.min(elapsed, maxSec);
            if (duration < 3) return;
            var opts = typeof lmdAdmin !== 'undefined' ? lmdAdmin : {};
            var fd = new FormData();
            fd.append('action', 'lmd_log_activity');
            fd.append('nonce', opts.nonce || '');
            fd.append('page_type', pageType);
            fd.append('duration', duration);
            if (estimationId) fd.append('estimation_id', estimationId);
            if (navigator.sendBeacon && opts.ajaxurl) {
                navigator.sendBeacon(opts.ajaxurl, fd);
            } else if (opts.ajaxurl) {
                $.post(opts.ajaxurl, { action: 'lmd_log_activity', nonce: opts.nonce, page_type: pageType, duration: duration, estimation_id: estimationId || '' });
            }
        }
        $(window).on('beforeunload', sendLog);
        document.addEventListener('visibilitychange', function() { if (document.visibilityState === 'hidden') sendLog(); });
    })();

    $(document).ready(function() {
        // Vignettes des fichiers (nouvelle demande)
        var $adminPhotos = $('#photos');
        var $adminVignettes = $('#lmd-admin-photos-vignettes');
        if ($adminPhotos.length && $adminVignettes.length) {
            $adminPhotos.on('change', function() {
                $adminVignettes.empty();
                var files = this.files;
                for (var i = 0; i < files.length; i++) {
                    var f = files[i];
                    if (!f.type.match(/^image\//)) continue;
                    var reader = new FileReader();
                    reader.onload = (function() {
                        return function(e) {
                            var $img = $('<img>').attr('src', e.target.result).css({ width: '64px', height: '64px', objectFit: 'contain', borderRadius: '6px', border: '1px solid #e5e7eb' });
                            var $wrap = $('<div>').append($img);
                            $adminVignettes.append($wrap);
                        };
                    })();
                    reader.readAsDataURL(f);
                }
            });
        }

        $('#lmd-analysis-pricing-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this), $btn = $form.find('button[type="submit"]');
            var opts = window.lmdAdmin || {};
            var data = $form.serialize() + '&action=lmd_save_analysis_pricing&nonce=' + encodeURIComponent(opts.nonce || '');
            $btn.prop('disabled', true);
            $.post(opts.ajaxurl || (typeof ajaxurl !== 'undefined' ? ajaxurl : ''), data)
                .done(function(r) {
                    if (r.success) alert(r.data && r.data.message ? r.data.message : 'Enregistré.');
                    else alert(r.data && r.data.message ? r.data.message : 'Erreur');
                })
                .fail(function() { alert('Erreur réseau'); })
                .always(function() { $btn.prop('disabled', false); });
        });
    });
})(jQuery);
