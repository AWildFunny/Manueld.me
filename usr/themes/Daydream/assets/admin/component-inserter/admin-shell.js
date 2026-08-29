(function ($) {
    'use strict';

    var boot = window.CI_BOOT || {};
    window.CI_HANDLERS = window.CI_HANDLERS || {};

    function currentComponent() {
        return $('#ci-component').val() || '';
    }

    function setComponent(id) {
        if (!id) return;
        $('#ci-component').val(id);
        $('.ci-comp-item').removeClass('is-active');
        $('.ci-comp-item[data-ci-comp="' + id + '"]').addClass('is-active');
        $('.ci-panel').prop('hidden', true);
        $('#ci-panel-' + id).prop('hidden', false);
        $('#ci-inserter-modal').toggleClass('is-wide', id === 'album-shot');
        var handler = window.CI_HANDLERS[id];
        if (handler && typeof handler.onShow === 'function') {
            handler.onShow();
        }
        refreshPreview();
    }

    function refreshPreview() {
        var id = currentComponent();
        var handler = window.CI_HANDLERS[id];
        var html;
        if (handler && typeof handler.preview === 'function') {
            html = handler.preview();
            if (html === false || html === null) {
                return;
            }
        } else {
            html = '<p class="ci-preview-empty">请选择组件并编辑参数。</p>';
        }
        $('#ci-preview').html(html);
    }

    function insertCurrent() {
        var id = currentComponent();
        var handler = window.CI_HANDLERS[id];
        if (!handler || typeof handler.insert !== 'function') {
            window.alert('当前组件无法插入');
            return;
        }
        handler.insert(function () {
            closeModal();
        });
    }

    function openModal() {
        var id = $('#ci-component').val() || boot.defaultComponent || '';
        if (!id && $('.ci-comp-item').length) {
            id = $('.ci-comp-item').first().data('ci-comp');
        }
        if (id) {
            setComponent(id);
        }
        $('#ci-inserter-modal').prop('hidden', false);
    }

    function closeModal() {
        $('#ci-inserter-modal').prop('hidden', true);
        $('#ci-inserter-modal').removeClass('is-wide');
    }

    window.CI_insertIntoEditor = function (text) {
        var textarea = document.getElementById('text');
        if (!textarea) {
            window.alert('未找到正文编辑框');
            return false;
        }
        textarea.focus();
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var before = value.substring(0, start);
        var after = value.substring(end);
        var needsNlBefore = before.length && !/\n$/.test(before);
        var needsNlAfter = after.length && !/^\n/.test(after);
        var chunk = (needsNlBefore ? '\n' : '') + text + (needsNlAfter ? '\n' : '');
        textarea.value = before + chunk + after;
        var caret = (before + chunk).length;
        textarea.selectionStart = textarea.selectionEnd = caret;
        if (typeof $ !== 'undefined') {
            $(textarea).trigger('input').trigger('change');
        }
        return true;
    };

    window.CI_escapeAttr = function (value) {
        return String(value || '')
            .replace(/\\/g, '\\\\')
            .replace(/"/g, '\\"');
    };

    window.CI_escapeHtml = function (value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    };

    window.CI_refreshPreview = refreshPreview;

    $(function () {
        var primary = (boot.primary || '#4E7289');
        document.documentElement.style.setProperty('--ci-primary', primary);

        $(document).on('click', '#ci-open-inserter', function (e) {
            e.preventDefault();
            openModal();
        });
        $(document).on('click', '[data-ci-close]', function (e) {
            e.preventDefault();
            closeModal();
        });
        $(document).on('click', '.ci-comp-item', function (e) {
            e.preventDefault();
            setComponent($(this).data('ci-comp'));
        });
        $(document).on('click', '#ci-refresh-preview', function (e) {
            e.preventDefault();
            refreshPreview();
        });
        $(document).on('click', '#ci-insert-btn', function (e) {
            e.preventDefault();
            insertCurrent();
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && !$('#ci-inserter-modal').prop('hidden')) {
                closeModal();
            }
        });
    });
})(window.jQuery);
