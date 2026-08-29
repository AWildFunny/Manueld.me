(function ($) {
    'use strict';

    var items = [];
    var selected = 0;
    var drag = null;

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
        return /\.(jpe?g|png|gif|webp|bmp|svg)(\?|#|$)/i.test(s) || /image\//i.test(s);
    }

    function collectImages() {
        var images = [];
        $('#file-list li').each(function () {
            var $li = $(this);
            var $insert = $li.find('a.insert').first();
            if (!$insert.length) return;
            var url = $insert.attr('href') || $insert.data('url') || '';
            var name = $.trim($insert.text()) || $li.text();
            if (!url || url.indexOf('javascript:') === 0) {
                var $url = $li.find('input[name="attachment[]"], .url, code').first();
                if ($url.length) url = $url.val() || $url.text() || '';
            }
            if ((!url || url.indexOf('javascript:') === 0) && $insert.attr('onclick')) {
                var m = $insert.attr('onclick').match(/['"](https?:\/\/[^'"]+|\/[^'"]+)['"]/);
                if (m) url = m[1];
            }
            if (!url || url.indexOf('javascript:') === 0) return;
            if (isImageUrl(url, name)) images.push({ url: url, name: name });
        });
        return images;
    }

    function fillSelects() {
        var html = '<option value="">— 从已上传附件选择图片 —</option>';
        collectImages().forEach(function (item) {
            html += '<option value="' + escapeHtml(item.url) + '">' + escapeHtml(item.name) + '</option>';
        });
        var $src = $('#as-src-pick');
        var $board = $('#as-board-pick');
        if ($src.length) {
            var cur = $src.val();
            $src.html(html);
            if (cur) $src.val(cur);
        }
        if ($board.length) {
            $board.html(html.replace('选择图片', '加入画布'));
        }
    }

    function cat() {
        return $('#as-cat').val() || 'single';
    }

    function isBoardMode() {
        return cat() !== 'single';
    }

    function syncUi() {
        var board = isBoardMode();
        $('#as-single-wrap').prop('hidden', board);
        $('#as-board-wrap').prop('hidden', !board);
        $('#as-custom-wrap').prop('hidden', !board && $('#as-layout').val() !== 'custom' ? true : ($('#as-layout').val() !== 'custom'));
        if (!board) {
            $('#as-custom-wrap').prop('hidden', ($('#as-layout').val() || '') !== 'custom');
        }
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
        urls = urls.filter(Boolean);
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
        var r = ($('#as-ratio').val() || '3:2').replace(':', ' / ');
        return r;
    }

    function boardEditorHtml() {
        return '<div class="as-board-ui">'
            + '<p class="as-board-hint">拖拽排版 · 右下角缩放 · 保持原比例</p>'
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
            $board.html('<div class="as-board-empty">从右侧加入 2 张及以上图片</div>');
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
        if (!url || !/^(https?:\/\/|\/)/i.test(url)) {
            return;
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
        var catNow = cat();
        if (catNow !== 'canvas') {
            applyPreset($('#as-preset').val() || 'duo-split', items.map(function (it) { return it.src; }));
        }
        paintBoard();
    }

    function singlePreviewHtml() {
        var src = $.trim($('#as-src').val());
        var alt = $.trim($('#as-alt').val()) || '章节标题';
        var layout = $('#as-layout').val() || 'auto';
        var media = src
            ? '<div class="as-preview-media"><img src="' + escapeHtml(src) + '" alt=""></div>'
            : '<div class="as-preview-media"><div class="as-preview-ph">选择图片后在此预览</div></div>';
        return '<div class="as-preview-chapter"><h4>' + escapeHtml(alt) + '</h4>' + media
            + '<p class="as-preview-body">单图按原比例显示，不再裁切拉伸。版式：' + escapeHtml(layout) + '</p></div>';
    }

    function buildShortcode() {
        if (!isBoardMode()) {
            var src = $.trim($('#as-src').val());
            if (!src) {
                window.alert('请填写或选择图片 URL');
                return '';
            }
            if (!/^(https?:\/\/|\/)/i.test(src)) {
                window.alert('图片 URL 需以 http(s):// 或 / 开头');
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
            return '[album-shot ' + parts.join(' ') + ']';
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
        return '[album-board ratio="' + escapeAttr(ratio) + '"]' + inner + '[/album-board]';
    }

    function pctFromEvent(e, $board, isW) {
        var rect = $board[0].getBoundingClientRect();
        var x = ((e.clientX - rect.left) / rect.width) * 100;
        var y = ((e.clientY - rect.top) / rect.height) * 100;
        return { x: x, y: y, w: rect.width, h: rect.height };
    }

    window.CI_HANDLERS = window.CI_HANDLERS || {};
    window.CI_HANDLERS['album-shot'] = {
        onShow: function () {
            fillSelects();
            syncUi();
        },
        preview: function () {
            if (isBoardMode()) {
                if (!$('#as-board').length) {
                    window.setTimeout(paintBoard, 0);
                    return boardEditorHtml();
                }
                paintBoard();
                return false;
            }
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
        $(document).on('change', '#as-cat', function () {
            syncUi();
            if (isBoardMode() && items.length && cat() !== 'canvas') {
                applyPreset($('#as-preset').val(), items.map(function (it) { return it.src; }));
            }
            if (window.CI_refreshPreview) window.CI_refreshPreview();
            window.setTimeout(paintBoard, 0);
        });
        $(document).on('change', '#as-layout, #as-pos, #as-titlepos, #as-wrap, #as-src, #as-alt', function () {
            syncUi();
            if (window.CI_refreshPreview) window.CI_refreshPreview();
        });
        $(document).on('change', '#as-preset', function () {
            applyPreset($(this).val(), items.map(function (it) { return it.src; }));
            paintBoard();
        });
        $(document).on('change', '#as-ratio', function () {
            paintBoard();
            $('#as-board').css('--board-ratio', ratioCss());
        });
        $(document).on('change', '#as-src-pick', function () {
            var v = $(this).val();
            if (v) $('#as-src').val(v);
            if (window.CI_refreshPreview) window.CI_refreshPreview();
        });
        $(document).on('change', '#as-board-pick', function () {
            addItem($(this).val());
            $(this).val('');
            if (!$('#as-board').length && window.CI_refreshPreview) window.CI_refreshPreview();
            window.setTimeout(paintBoard, 0);
        });
        $(document).on('keydown', '#as-board-url', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addItem($.trim($(this).val()));
                $(this).val('');
                if (!$('#as-board').length && window.CI_refreshPreview) window.CI_refreshPreview();
                window.setTimeout(paintBoard, 0);
            }
        });
        $(document).on('click', '#as-board-clear', function () {
            items = [];
            paintBoard();
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
            var $el = $('.as-board-item[data-i="' + drag.i + '"]');
            $el.css({ left: it.x + '%', top: it.y + '%', width: it.w + '%' });
        });
        $(document).on('mouseup', function () {
            drag = null;
        });
    });
})(window.jQuery);
