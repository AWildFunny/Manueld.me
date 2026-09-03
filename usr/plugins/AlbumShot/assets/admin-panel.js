(function ($) {
    'use strict';

    var bootRoot = window.CI_BOOT || {};
    var boot = (bootRoot.components && bootRoot.components['album-shot']) || {};
    var library = [];
    var items = [];
    var selected = 0;
    var drag = null;
    var libDragUrl = '';

    function escapeHtml(v) {
        return window.CI_escapeHtml ? window.CI_escapeHtml(v) : String(v || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeAttr(v) {
        return window.CI_escapeAttr ? window.CI_escapeAttr(v) : String(v || '')
            .replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function isImageUrl(url, name) {
        var s = ((url || '') + ' ' + (name || '')).toLowerCase();
        return /\.(jpe?g|png|gif|webp|bmp|svg|avif|tiff?)(\?|#|$)/i.test(s) || /image\//i.test(s);
    }

    function isUsableUrl(url) {
        return !!(url && /^(https?:\/\/|\/)/i.test(url) && url.indexOf('###') !== 0);
    }

    /** 从写文章页 #file-list 刮取；真实地址在 li[data-url]，href 常为 ### */
    function scrapeDomImages() {
        var images = [];
        $('#file-list li').each(function () {
            var $li = $(this);
            var url = $li.attr('data-url') || $li.data('url') || '';
            var isImage = $li.attr('data-image');
            if (isImage === '0' || isImage === 0) {
                return;
            }
            var $insert = $li.find('a.insert').first();
            var name = $.trim($insert.text()) || $.trim($li.text()) || 'image';
            if (!isUsableUrl(url)) {
                var href = $insert.attr('href') || '';
                if (isUsableUrl(href)) {
                    url = href;
                }
            }
            if (!isUsableUrl(url) && $insert.attr('onclick')) {
                var m = String($insert.attr('onclick')).match(/['"](https?:\/\/[^'"]+|\/[^'"]+)['"]/);
                if (m) url = m[1];
            }
            if (!isUsableUrl(url)) {
                return;
            }
            if (isImage === '1' || isImage === 1 || isImageUrl(url, name)) {
                images.push({ url: url, name: name });
            }
        });
        return images;
    }

    function mergeLibrary(extra) {
        var map = {};
        var out = [];
        function push(item) {
            if (!item || !isUsableUrl(item.url)) {
                return;
            }
            if (map[item.url]) {
                return;
            }
            map[item.url] = true;
            out.push({
                url: item.url,
                name: item.name || item.url.split('/').pop() || 'image'
            });
        }
        (Array.isArray(boot.images) ? boot.images : []).forEach(push);
        (extra || []).forEach(push);
        scrapeDomImages().forEach(push);
        library = out;
        return library;
    }

    function currentSrc() {
        return $.trim($('#as-src').val());
    }

    function setSrc(url) {
        $('#as-src').val(url || '');
        renderLibrary();
        if (window.CI_refreshPreview) {
            window.CI_refreshPreview();
        }
    }

    function renderLibrary() {
        var $grid = $('#as-lib-grid');
        var $empty = $('#as-lib-empty');
        var $count = $('#as-lib-count');
        if (!$grid.length) {
            return;
        }
        $count.text(String(library.length));
        if (!library.length) {
            $grid.empty();
            $empty.prop('hidden', false);
            return;
        }
        $empty.prop('hidden', true);
        var src = currentSrc();
        var boardUrls = {};
        items.forEach(function (it) {
            if (it.src) boardUrls[it.src] = true;
        });
        var html = '';
        library.forEach(function (item, idx) {
            var on = src === item.url || !!boardUrls[item.url];
            html += '<button type="button" class="as-lib-card' + (on ? ' is-on' : '') + '" draggable="true"'
                + ' data-url="' + escapeHtml(item.url) + '"'
                + ' data-idx="' + idx + '"'
                + ' title="' + escapeHtml(item.name) + '">'
                + '<span class="as-lib-thumb"><img src="' + escapeHtml(item.url) + '" alt="" loading="lazy"></span>'
                + '<span class="as-lib-name">' + escapeHtml(item.name) + '</span>'
                + '</button>';
        });
        $grid.html(html);
    }

    function refreshLibrary() {
        mergeLibrary();
        renderLibrary();
    }

    function cat() {
        return $('#as-cat').val() || 'single';
    }

    function isBoardMode() {
        return cat() !== 'single';
    }

    function syncUi() {
        var board = isBoardMode();
        $('#as-layout-field').prop('hidden', board);
        $('#as-alt-wrap').prop('hidden', board);
        $('#as-preset-field').prop('hidden', !board);
        $('#as-ratio-field').prop('hidden', !board);
        $('#as-board-clear').prop('hidden', !board);
        $('#as-custom-wrap').prop('hidden', board || ($('#as-layout').val() || '') !== 'custom');

        var presets = {
            duo: ['duo-split', 'duo-main-side', 'duo-overlap'],
            multi: ['tri-stack', 'tri-row', 'quad'],
            canvas: ['canvas']
        };
        var $preset = $('#as-preset');
        if (board && $preset.length) {
            var allow = presets[cat()] || presets.canvas;
            $preset.find('option').each(function () {
                var v = this.value;
                this.hidden = allow.indexOf(v) === -1 && v !== 'canvas';
            });
            if (allow.indexOf($preset.val()) === -1) {
                $preset.val(allow[0]);
            }
        }
    }

    function clamp(n, a, b) {
        return Math.max(a, Math.min(b, n));
    }

    function applyPreset(kind, urls) {
        urls = (urls || []).filter(Boolean);
        if (!urls.length && items.length) {
            urls = items.map(function (it) { return it.src; });
        }
        if (kind === 'canvas') {
            return;
        }
        var next = [];
        function take(i) { return urls[i] || urls[urls.length - 1] || ''; }
        if (kind === 'duo-split') {
            next = [
                { src: take(0), x: 2, y: 6, w: 47, alt: '' },
                { src: take(1), x: 51, y: 6, w: 47, alt: '' }
            ];
        } else if (kind === 'duo-main-side') {
            next = [
                { src: take(0), x: 2, y: 5, w: 60, alt: '' },
                { src: take(1), x: 64, y: 22, w: 34, alt: '' }
            ];
        } else if (kind === 'duo-overlap') {
            next = [
                { src: take(0), x: 5, y: 8, w: 58, alt: '' },
                { src: take(1), x: 40, y: 24, w: 52, alt: '' }
            ];
        } else if (kind === 'tri-stack') {
            next = [
                { src: take(0), x: 2, y: 4, w: 58, alt: '' },
                { src: take(1), x: 62, y: 4, w: 36, alt: '' },
                { src: take(2), x: 62, y: 50, w: 36, alt: '' }
            ];
        } else if (kind === 'tri-row') {
            next = [0, 1, 2].map(function (i) {
                return { src: take(i), x: 2 + i * 32.5, y: 10, w: 31, alt: '' };
            });
        } else if (kind === 'quad') {
            next = [
                { src: take(0), x: 2, y: 4, w: 47, alt: '' },
                { src: take(1), x: 51, y: 4, w: 47, alt: '' },
                { src: take(2), x: 2, y: 50, w: 47, alt: '' },
                { src: take(3), x: 51, y: 50, w: 47, alt: '' }
            ];
        }
        if (next.length) {
            items = next.filter(function (it) { return !!it.src; });
            selected = 0;
        }
    }

    function ratioCss() {
        return ($('#as-ratio').val() || '3:2').replace(':', ' / ');
    }

    function boardEditorHtml() {
        return '<div class="as-board-ui as-drop-stage" data-as-drop="1">'
            + '<p class="as-board-hint">从右侧拖图到此 · 画布内可拖动排版 · 右下角缩放</p>'
            + '<div id="as-board" class="as-board" style="--board-ratio:' + ratioCss() + '"></div>'
            + '</div>';
    }

    function paintBoard() {
        var $board = $('#as-board');
        if (!$board.length) {
            return;
        }
        $board.css('--board-ratio', ratioCss());
        if (!items.length) {
            $board.html('<div class="as-board-empty">将右侧图片拖到此处，或点击选用</div>');
            return;
        }
        var html = '';
        items.forEach(function (it, i) {
            html += '<figure class="as-board-item' + (i === selected ? ' is-on' : '') + '" data-i="' + i + '" style="left:' + it.x + '%;top:' + it.y + '%;width:' + it.w + '%">'
                + '<img src="' + escapeHtml(it.src) + '" alt="">'
                + '<i class="as-board-handle" data-resize="' + i + '"></i>'
                + '</figure>';
        });
        $board.html(html);
    }

    function addItem(url) {
        if (!isUsableUrl(url)) {
            return false;
        }
        var n = items.length;
        items.push({
            src: url,
            x: 6 + (n % 3) * 8,
            y: 8 + (n % 4) * 10,
            w: n ? 36 : 48,
            alt: ''
        });
        selected = items.length - 1;
        if (cat() !== 'canvas') {
            applyPreset($('#as-preset').val() || 'duo-split', items.map(function (it) { return it.src; }));
        }
        paintBoard();
        renderLibrary();
        return true;
    }

    function applyImage(url) {
        if (!isUsableUrl(url)) {
            return;
        }
        if (isBoardMode()) {
            addItem(url);
            if (!$('#as-board').length && window.CI_refreshPreview) {
                window.CI_refreshPreview();
            }
            window.setTimeout(paintBoard, 0);
        } else {
            setSrc(url);
        }
    }

    function previewLayoutClass() {
        var layout = $('#as-layout').val() || 'auto';
        var pos = $('#as-pos').val() || 'top';
        var titlepos = $('#as-titlepos').val() || 'above';
        var wrap = $('#as-wrap').prop('checked');
        var cls = ['as-preview-chapter'];
        if (layout === 'overlay' || (layout === 'custom' && titlepos === 'on')) {
            cls.push('is-overlay');
        } else if (layout === 'split-left' || (layout === 'custom' && titlepos === 'beside' && pos === 'left')) {
            cls.push('is-split', 'is-split-left');
        } else if (layout === 'split-right' || (layout === 'custom' && titlepos === 'beside' && pos === 'right')) {
            cls.push('is-split', 'is-split-right');
        } else if (layout === 'float' || (layout === 'custom' && wrap)) {
            cls.push('is-float');
            if (layout === 'float' && pos === 'right') cls.push('is-right');
            if (layout === 'custom' && pos === 'right') cls.push('is-right');
        } else if (layout === 'custom' && titlepos === 'below') {
            cls.push('is-below');
        } else if (layout === 'banner' || layout === 'auto') {
            cls.push('is-banner');
        }
        return cls.join(' ');
    }

    function singlePreviewHtml() {
        var src = currentSrc();
        var alt = $.trim($('#as-alt').val()) || '章节标题';
        var layout = $('#as-layout').val() || 'auto';
        var media = src
            ? '<div class="as-preview-media"><img src="' + escapeHtml(src) + '" alt=""></div>'
            : '<div class="as-preview-media"><div class="as-preview-ph">将右侧图片拖到此处，或点击选用</div></div>';
        var title = '<h4>' + escapeHtml(alt) + '</h4>';
        var body = '<p class="as-preview-body">单图按原比例显示。当前版式：' + escapeHtml(layout) + '</p>';
        var cls = previewLayoutClass();
        var inner;
        if (cls.indexOf('is-overlay') !== -1 || cls.indexOf('is-below') !== -1) {
            inner = media + title + body;
        } else {
            inner = title + media + body;
        }
        return '<div class="' + cls + ' as-drop-stage" data-as-drop="1">' + inner + '</div>';
    }

    function wrapForMarkdown(code) {
        return '<div>\n' + code + '\n</div>';
    }

    function buildShortcode() {
        if (!isBoardMode()) {
            var src = currentSrc();
            if (!src) {
                window.alert('请从右侧附件库选择或拖入一张图片');
                return '';
            }
            if (!isUsableUrl(src)) {
                window.alert('图片 URL 无效，请重新从附件库选用');
                return '';
            }
            var layout = $('#as-layout').val() || 'auto';
            var parts = ['layout="' + escapeAttr(layout) + '"', 'src="' + escapeAttr(src) + '"'];
            var alt = $.trim($('#as-alt').val());
            if (alt) parts.push('alt="' + escapeAttr(alt) + '"');
            if (layout === 'custom') {
                parts.push('pos="' + escapeAttr($('#as-pos').val() || 'top') + '"');
                parts.push('titlepos="' + escapeAttr($('#as-titlepos').val() || 'above') + '"');
                if ($('#as-wrap').prop('checked')) parts.push('wrap="1"');
            }
            return wrapForMarkdown('[album-shot ' + parts.join(' ') + ']');
        }
        if (items.length < 1) {
            window.alert('请至少加入一张图片');
            return '';
        }
        var ratio = $('#as-ratio').val() || '3:2';
        var inner = items.map(function (it) {
            return '[img src="' + escapeAttr(it.src) + '" x="' + it.x + '" y="' + it.y + '" w="' + it.w + '"'
                + (it.alt ? ' alt="' + escapeAttr(it.alt) + '"' : '') + ']';
        }).join('');
        return wrapForMarkdown('[album-board ratio="' + escapeAttr(ratio) + '"]' + inner + '[/album-board]');
    }

    function pctFromEvent(e, $board) {
        var rect = $board[0].getBoundingClientRect();
        return {
            x: ((e.clientX - rect.left) / rect.width) * 100,
            y: ((e.clientY - rect.top) / rect.height) * 100
        };
    }

    function setDropHighlight(on) {
        $('#ci-preview, .ci-preview-stage, .as-drop-stage, #as-board')
            .toggleClass('as-drop-over', !!on);
    }

    function bindDropZone() {
        var $stage = $('#ci-preview').closest('.ci-preview-stage');
        if (!$stage.length) {
            $stage = $('#ci-preview');
        }
        $stage.attr('data-as-drop', '1');
    }

    window.CI_HANDLERS = window.CI_HANDLERS || {};
    window.CI_HANDLERS['album-shot'] = {
        onShow: function () {
            refreshLibrary();
            syncUi();
            bindDropZone();
        },
        preview: function () {
            if (isBoardMode()) {
                if (!$('#as-board').length) {
                    window.setTimeout(function () {
                        paintBoard();
                        bindDropZone();
                    }, 0);
                    return boardEditorHtml();
                }
                paintBoard();
                return false;
            }
            window.setTimeout(bindDropZone, 0);
            return singlePreviewHtml();
        },
        insert: function (done) {
            var code = buildShortcode();
            if (code && window.CI_insertIntoEditor(code)) {
                done();
            }
        }
    };

    $(function () {
        mergeLibrary();

        $(document).on('change', '#as-cat', function () {
            syncUi();
            if (isBoardMode() && items.length && cat() !== 'canvas') {
                applyPreset($('#as-preset').val(), items.map(function (it) { return it.src; }));
            }
            if (window.CI_refreshPreview) window.CI_refreshPreview();
            window.setTimeout(function () {
                paintBoard();
                renderLibrary();
            }, 0);
        });

        $(document).on('change', '#as-layout, #as-pos, #as-titlepos, #as-wrap, #as-alt', function () {
            syncUi();
            if (window.CI_refreshPreview) window.CI_refreshPreview();
        });

        $(document).on('change', '#as-preset', function () {
            applyPreset($(this).val(), items.map(function (it) { return it.src; }));
            paintBoard();
            renderLibrary();
            if (window.CI_refreshPreview) window.CI_refreshPreview();
        });

        $(document).on('change', '#as-ratio', function () {
            paintBoard();
            $('#as-board').css('--board-ratio', ratioCss());
            if (window.CI_refreshPreview) window.CI_refreshPreview();
        });

        $(document).on('click', '#as-lib-refresh', function (e) {
            e.preventDefault();
            refreshLibrary();
        });

        $(document).on('click', '#as-board-clear', function () {
            items = [];
            paintBoard();
            renderLibrary();
        });

        $(document).on('click', '.as-lib-card', function (e) {
            e.preventDefault();
            applyImage($(this).attr('data-url') || '');
        });

        $(document).on('dragstart', '.as-lib-card', function (e) {
            libDragUrl = $(this).attr('data-url') || '';
            $(this).addClass('is-dragging');
            try {
                e.originalEvent.dataTransfer.setData('text/plain', libDragUrl);
                e.originalEvent.dataTransfer.effectAllowed = 'copy';
            } catch (err) {}
        });

        $(document).on('dragend', '.as-lib-card', function () {
            $(this).removeClass('is-dragging');
            libDragUrl = '';
            setDropHighlight(false);
        });

        $(document).on('dragover', '#ci-preview, .ci-preview-stage, .as-drop-stage, #as-board', function (e) {
            if (!$('#ci-panel-album-shot').length || $('#ci-panel-album-shot').prop('hidden')) {
                return;
            }
            e.preventDefault();
            try {
                e.originalEvent.dataTransfer.dropEffect = 'copy';
            } catch (err) {}
            setDropHighlight(true);
        });

        $(document).on('dragleave', '#ci-preview, .ci-preview-stage, .as-drop-stage, #as-board', function (e) {
            var related = e.relatedTarget;
            if (related && this.contains && this.contains(related)) {
                return;
            }
            setDropHighlight(false);
        });

        $(document).on('drop', '#ci-preview, .ci-preview-stage, .as-drop-stage, #as-board', function (e) {
            if (!$('#ci-panel-album-shot').length || $('#ci-panel-album-shot').prop('hidden')) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            setDropHighlight(false);
            var url = libDragUrl;
            try {
                url = url || e.originalEvent.dataTransfer.getData('text/plain') || '';
            } catch (err) {}
            applyImage($.trim(url));
        });

        $(document).on('mousedown', '.as-board-item', function (e) {
            if ($(e.target).hasClass('as-board-handle')) return;
            var i = parseInt($(this).data('i'), 10);
            selected = i;
            var $board = $('#as-board');
            var start = pctFromEvent(e, $board);
            drag = {
                mode: 'move',
                i: i,
                ox: start.x - items[i].x,
                oy: start.y - items[i].y
            };
            paintBoard();
            e.preventDefault();
        });

        $(document).on('mousedown', '.as-board-handle', function (e) {
            var i = parseInt($(this).data('resize'), 10);
            selected = i;
            drag = { mode: 'resize', i: i, startW: items[i].w, startX: e.clientX };
            e.preventDefault();
            e.stopPropagation();
        });

        $(document).on('mousemove', function (e) {
            if (!drag || !items[drag.i]) return;
            var it = items[drag.i];
            if (drag.mode === 'move') {
                var p = pctFromEvent(e, $('#as-board'));
                it.x = clamp(Math.round(p.x - drag.ox), 0, 88);
                it.y = clamp(Math.round(p.y - drag.oy), 0, 88);
            } else {
                var dw = (e.clientX - drag.startX) / Math.max($('#as-board').width(), 1) * 100;
                it.w = clamp(Math.round(drag.startW + dw), 12, 96);
            }
            $('.as-board-item[data-i="' + drag.i + '"]').css({
                left: it.x + '%',
                top: it.y + '%',
                width: it.w + '%'
            });
        });

        $(document).on('mouseup', function () {
            drag = null;
        });

        // 上传完成后自动刷新图库
        if (window.MutationObserver && document.body) {
            var libObserver = new MutationObserver(function () {
                window.clearTimeout(window.__asLibTimer);
                window.__asLibTimer = window.setTimeout(refreshLibrary, 400);
            });
            var watchList = function () {
                var el = document.getElementById('file-list');
                if (el) {
                    libObserver.observe(el, { childList: true, subtree: true });
                }
            };
            watchList();
            var panel = document.getElementById('upload-panel');
            if (panel) {
                libObserver.observe(panel, { childList: true, subtree: false });
                window.setTimeout(watchList, 0);
            }
        }
    });
})(window.jQuery);
