(function ($) {
    'use strict';

    var boot = window.AC_BOOT || {};
    var siteUrl = boot.siteUrl || '/';

    function escapeAttr(value) {
        return String(value || '')
            .replace(/\\/g, '\\\\')
            .replace(/"/g, '\\"');
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function insertIntoEditor(text) {
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
    }

    /* ---------- Music (ported) ---------- */

    function extractPlatformId(input, source) {
        var raw = String(input || '').trim();
        if (!raw) return '';
        if (/^\d+$/.test(raw)) return raw;
        var m;
        if (source === 'netease') {
            m = raw.match(/[?&#]id=(\d+)/i) || raw.match(/song\/(\d+)/i) || raw.match(/(\d{5,})/);
        } else if (source === 'tencent') {
            m = raw.match(/[?&#]songmid=([a-zA-Z0-9]+)/i)
                || raw.match(/[?&#]id=(\d+)/i)
                || raw.match(/songDetail\/(\d+)/i)
                || raw.match(/(\d{5,})/);
        } else {
            m = raw.match(/(\d{5,})/);
        }
        return m ? m[1] : '';
    }

    function buildMetingUrl(server, type, id) {
        var tpl = boot.metingApi || 'https://meting.mikus.ink/api?server=:server&type=:type&id=:id';
        return tpl
            .replace(':server', encodeURIComponent(server))
            .replace(':type', encodeURIComponent(type))
            .replace(':id', encodeURIComponent(id));
    }

    function buildMusicShortcode(data) {
        var parts = ['[music'];
        if (data.from && data.from !== 'custom') {
            parts.push('from="' + escapeAttr(data.from) + '"');
            parts.push('id="' + escapeAttr(data.id) + '"');
            if (data.title) parts.push('title="' + escapeAttr(data.title) + '"');
            if (data.artist) parts.push('artist="' + escapeAttr(data.artist) + '"');
            if (data.cover) parts.push('cover="' + escapeAttr(data.cover) + '"');
        } else {
            parts.push('title="' + escapeAttr(data.title) + '"');
            if (data.artist) parts.push('artist="' + escapeAttr(data.artist) + '"');
            parts.push('src="' + escapeAttr(data.src) + '"');
            if (data.cover) parts.push('cover="' + escapeAttr(data.cover) + '"');
        }
        parts.push('mode="' + escapeAttr(data.mode || 'click') + '"');
        if (data.notice) parts.push('notice="1"');
        return parts.join(' ') + ']';
    }

    function isAudioUrl(url, name) {
        var s = ((url || '') + ' ' + (name || '')).toLowerCase();
        return /\.(mp3|m4a|ogg|wav|flac)(\?|#|$)/i.test(s) || /audio\//i.test(s);
    }

    function isImageUrl(url, name) {
        var s = ((url || '') + ' ' + (name || '')).toLowerCase();
        return /\.(jpe?g|png|gif|webp|bmp|svg)(\?|#|$)/i.test(s) || /image\//i.test(s);
    }

    function collectAttachments() {
        var audio = [];
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
            var item = { url: url, name: name };
            if (isAudioUrl(url, name)) audio.push(item);
            else if (isImageUrl(url, name)) images.push(item);
        });
        return { audio: audio, images: images };
    }

    function fillAttachmentSelects() {
        var data = collectAttachments();
        var $src = $('#cmp-src-pick').empty().append('<option value="">— 从已上传附件选择音频 —</option>');
        var $cover = $('#cmp-cover-pick').empty().append('<option value="">— 从已上传附件选择封面 —</option>');
        data.audio.forEach(function (item) {
            $src.append($('<option></option>').val(item.url).text(item.name));
        });
        data.images.forEach(function (item) {
            $cover.append($('<option></option>').val(item.url).text(item.name));
        });
    }

    function syncMusicSourcePanels() {
        var source = $('#cmp-source').val() || 'custom';
        var isCustom = source === 'custom';
        $('#cmp-panel-custom').prop('hidden', !isCustom);
        $('#cmp-panel-platform').prop('hidden', isCustom);
        $('.cmp-req-title').toggle(isCustom);
        if (!isCustom) $('#cmp-resolve-status').text('');
    }

    function resolvePlatformTrack() {
        var source = $('#cmp-source').val();
        var id = extractPlatformId($('#cmp-platform-id').val(), source);
        var $status = $('#cmp-resolve-status');
        if (!id) {
            $status.text('请填写有效的歌曲 ID 或链接');
            return $.Deferred().reject().promise();
        }
        $status.text('解析中…');
        return $.ajax({
            url: buildMetingUrl(source, 'song', id),
            dataType: 'json',
            timeout: 12000
        }).then(function (data) {
            var item = Array.isArray(data) ? data[0] : data;
            if (!item) throw new Error('empty');
            var title = item.title || item.name || '';
            var artist = item.author || item.artist || '';
            if (Array.isArray(artist)) artist = artist.join(' / ');
            var cover = item.pic || item.cover || '';
            var src = item.url || '';
            if (title) $('#cmp-title').val(title);
            if (artist) $('#cmp-artist').val(artist);
            if (cover) $('#cmp-cover').val(cover);
            if (src) $('#cmp-src').val(src);
            $('#cmp-platform-id').val(id);
            $status.text('已解析：' + (title || id));
            return { from: source, id: id, title: title, artist: artist, cover: cover, src: src };
        }, function () {
            $status.text('解析失败，请检查 ID 或插件 Meting API');
        });
    }

    function musicPreviewHtml() {
        var title = $.trim($('#cmp-title').val()) || '歌曲名';
        var artist = $.trim($('#cmp-artist').val()) || '艺术家';
        var mode = $('#cmp-mode').val() || 'click';
        var source = $('#cmp-source').val() || 'custom';
        var notice = $('#cmp-notice').is(':checked');
        return (
            '<div class="ac-music-preview">' +
            '<div class="ac-music-preview-disc" aria-hidden="true"></div>' +
            '<div class="ac-music-preview-meta">' +
            '<strong>' + escapeHtml(title) + '</strong>' +
            '<span>' + escapeHtml(artist) + '</span>' +
            '<span class="ac-music-preview-tags">' +
            escapeHtml(source === 'custom' ? '自定义音频' : (source === 'netease' ? '网易云' : 'QQ 音乐')) +
            ' · ' + escapeHtml(mode === 'scroll' ? '滚入播放' : '点击播放') +
            (notice ? ' · 进页提示' : '') +
            '</span></div></div>'
        );
    }

    /* ---------- Notice ---------- */

    var PRESET_STORAGE_KEY = 'ac-notice-user-presets-v1';

    var BUILTIN_PRESETS = {
        custom: {
            label: '自定义（空白）',
            title: '',
            color: boot.defaultColor || '#1095c1',
            text: boot.defaultTextColor || '#ffffff',
            radius: '12',
            shadow: true,
            body: ''
        },
        welcome: {
            label: '作者欢迎',
            title: '',
            color: '#1095c1',
            text: '#ffffff',
            radius: '12',
            shadow: true,
            body: [
                '你好 👋 我是 Manueld。本站是我的个人博客，于 2024.8.22 起向私域测试性开放。',
                '',
                '本站仍处于并将长期处于「装修」状态，许多文章完成度尚不高。欢迎你来随意走走，留下你的足迹 👣',
                '',
                '寻找笔记等资料的学弟学妹，请跳转→ [分类：笔记](' + siteUrl + 'category/notes/)；如果你想了解更多有关我和本站的内容，请访问→ [关于](' + siteUrl + 'about.html)'
            ].join('\n')
        },
        cosmos: {
            label: '宇宙安全声明',
            title: '宇宙安全声明',
            color: '#8b0000',
            text: '#ffffff',
            radius: '12',
            shadow: true,
            body: [
                '我完全拥护社会主义，拥护中国共产党的领导，拥护社会主义核心价值观；反对任何分裂国家的行为；遵守《网络信息内容生态治理规定》等法规，共同营造清朗网络空间。我反对法西斯主义、殖民主义、帝国主义、修正主义、霸权主义，反对邪教与毒品，拥护中华人民共和国宪法。文中含我的主观见解，请自行甄别；若感不适请立即关闭本页。评论区请文明讨论，严禁违法、色情、暴力及极端言论。本声明随页发出，视为已阅知。'
            ].join('\n')
        },
        fiction: {
            label: '内容性质声明',
            title: '内容性质声明',
            color: '#37474f',
            text: '#ffffff',
            radius: '12',
            shadow: true,
            body: [
                '本页内容如无特别说明，均属虚构或学习笔记整理，仅供娱乐 / 交流参考，与现实事件、现实意识形态无直接对应关系。',
                '',
                '文中展示物品均经合法渠道取得；字体优先使用开源或已获授权资源，避免侵权。若出现地图等信息，均以符合国家有关规定的公开资料为准。',
                '',
                '作者非任何团体「水军」，亦不代表除本人以外的任何组织或个人立场。'
            ].join('\n')
        },
        audience: {
            label: '受众与互动提示',
            title: '阅读提示',
            color: '#455a64',
            text: '#ffffff',
            radius: '12',
            shadow: true,
            body: [
                '建议具备独立思考能力、年龄约 12 岁及以上的读者阅读；若不认同文中观点或感到不适，请立即关闭页面。',
                '',
                '欢迎在评论区礼貌讨论；严禁发布违法违规、色情低俗、暴力血腥或极端言论。作者有权视情况删除不当留言。'
            ].join('\n')
        },
        persona: {
            label: '作者趣味声明',
            title: '作者声明',
            color: '#5d4037',
            text: '#ffffff',
            radius: '12',
            shadow: true,
            body: [
                '作者为地球人，热爱地球文化，无反人类倾向；承诺不向三体人、外星文明或其他维度发送地球坐标。',
                '',
                '作者家庭和睦，智力与精神状态正常；表达能力与表情管理一般，请勿过度解读语气或神态。个人观点仅代表作者本人，不代表父母、亲友或所在地区。',
                '',
                '支持男女平等；爱护小动物，无意践踏人类尊严或动物权益。'
            ].join('\n')
        },
        revision: {
            label: '内容修订提示',
            title: '内容提示',
            color: '#546e7a',
            text: '#ffffff',
            radius: '12',
            shadow: true,
            body: [
                '本文仍在修订中，部分段落可能不完整或将调整。',
                '',
                '引用前请核对发布时间与后续更新；如发现事实性错误，欢迎通过评论或站内渠道告知。'
            ].join('\n')
        }
    };

    function loadUserPresets() {
        try {
            var raw = window.localStorage.getItem(PRESET_STORAGE_KEY);
            if (!raw) return {};
            var data = JSON.parse(raw);
            return data && typeof data === 'object' ? data : {};
        } catch (e) {
            return {};
        }
    }

    function saveUserPresets(map) {
        try {
            window.localStorage.setItem(PRESET_STORAGE_KEY, JSON.stringify(map || {}));
        } catch (e) {
            window.alert('无法保存预设（浏览器可能禁用了本地存储）');
        }
    }

    function isUserPresetKey(key) {
        return String(key || '').indexOf('user_') === 0;
    }

    function getPreset(key) {
        if (BUILTIN_PRESETS[key]) {
            return BUILTIN_PRESETS[key];
        }
        var user = loadUserPresets();
        return user[key] || null;
    }

    function rebuildPresetSelect(selected) {
        var $sel = $('#ac-notice-preset').empty();
        Object.keys(BUILTIN_PRESETS).forEach(function (key) {
            $sel.append($('<option></option>').val(key).text(BUILTIN_PRESETS[key].label || key));
        });
        var user = loadUserPresets();
        var userKeys = Object.keys(user);
        if (userKeys.length) {
            var $group = $('<optgroup></optgroup>').attr('label', '我的预设');
            userKeys.sort(function (a, b) {
                return String(user[a].label || a).localeCompare(String(user[b].label || b), 'zh');
            }).forEach(function (key) {
                $group.append($('<option></option>').val(key).text(user[key].label || key));
            });
            $sel.append($group);
        }
        if (selected && $sel.find('option[value="' + selected + '"]').length) {
            $sel.val(selected);
        } else {
            $sel.val('custom');
        }
        syncPresetDeleteBtn();
    }

    function syncPresetDeleteBtn() {
        var key = $('#ac-notice-preset').val();
        $('#ac-preset-delete').prop('hidden', !isUserPresetKey(key));
    }

    function collectNoticeForm() {
        return {
            title: $.trim($('#ac-notice-title').val()),
            color: $.trim($('#ac-notice-color-text').val()) || '#1095c1',
            text: $.trim($('#ac-notice-text-text').val()) || '#ffffff',
            radius: String(parseInt($('#ac-notice-radius').val(), 10) || 12),
            shadow: $('#ac-notice-shadow').is(':checked'),
            body: String($('#ac-notice-body').val() || '').replace(/\r\n|\r/g, '\n').trim()
        };
    }

    function applyNoticePreset(key) {
        var p = getPreset(key) || BUILTIN_PRESETS.custom;
        var color = p.color || '#1095c1';
        var text = p.text || '#ffffff';
        $('#ac-notice-title').val(p.title || '');
        $('#ac-notice-color').val(/^#([0-9a-f]{6})$/i.test(color) ? color : '#1095c1');
        $('#ac-notice-color-text').val(color);
        $('#ac-notice-text').val(/^#([0-9a-f]{6})$/i.test(text) ? text : '#ffffff');
        $('#ac-notice-text-text').val(text);
        $('#ac-notice-radius').val(p.radius || '12');
        $('#ac-notice-shadow').prop('checked', p.shadow !== false);
        $('#ac-notice-body').val(p.body || '');
        syncPresetDeleteBtn();
        refreshPreview();
    }

    function saveCurrentAsPreset() {
        var form = collectNoticeForm();
        if (!form.title && !form.body) {
            window.alert('请先填写标题或内容，再保存预设');
            return;
        }
        var defaultName = form.title || '我的预设';
        var name = window.prompt('预设名称', defaultName);
        if (name === null) return;
        name = $.trim(name);
        if (!name) {
            window.alert('预设名称不能为空');
            return;
        }
        var map = loadUserPresets();
        var key = 'user_' + Date.now().toString(36);
        map[key] = {
            label: name,
            title: form.title,
            color: form.color,
            text: form.text,
            radius: form.radius,
            shadow: form.shadow,
            body: form.body
        };
        saveUserPresets(map);
        rebuildPresetSelect(key);
        window.alert('已保存预设「' + name + '」');
    }

    function deleteCurrentUserPreset() {
        var key = $('#ac-notice-preset').val();
        if (!isUserPresetKey(key)) return;
        var p = getPreset(key);
        var label = p && p.label ? p.label : key;
        if (!window.confirm('删除自定义预设「' + label + '」？')) return;
        var map = loadUserPresets();
        delete map[key];
        saveUserPresets(map);
        rebuildPresetSelect('custom');
        applyNoticePreset('custom');
    }

    function syncColorPair(pickerId, textId) {
        var $picker = $(pickerId);
        var $text = $(textId);
        $picker.on('input change', function () {
            $text.val($picker.val());
            refreshPreview();
        });
        $text.on('input change', function () {
            var v = $.trim($text.val());
            if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v)) {
                $picker.val(v.length === 4
                    ? '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3]
                    : v);
            }
            refreshPreview();
        });
    }

    function formatNoticeBodyHtml(raw) {
        raw = String(raw || '').replace(/\r\n|\r/g, '\n').trim();
        if (!raw) return '';
        if (/<(p|div|ul|ol)\b/i.test(raw)) {
            return raw.replace(/<p>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/gi, '');
        }
        return raw.split(/\n\s*\n/).map(function (part) {
            part = $.trim(part);
            if (!part) return '';
            var escaped = escapeHtml(part).replace(
                /\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]*|#[^)\s]*)\)/g,
                '<a href="$2">$1</a>'
            );
            return '<p>' + escaped.replace(/\n/g, '<br>') + '</p>';
        }).join('');
    }

    function noticePreviewHtml() {
        var title = $.trim($('#ac-notice-title').val());
        var color = $.trim($('#ac-notice-color-text').val()) || '#1095c1';
        var text = $.trim($('#ac-notice-text-text').val()) || '#ffffff';
        var radius = String(parseInt($('#ac-notice-radius').val(), 10) || 12);
        var shadow = $('#ac-notice-shadow').is(':checked');
        var body = formatNoticeBodyHtml($('#ac-notice-body').val());
        if (!title && !body) {
            return '<p class="ac-preview-empty">填写标题或内容后显示预览。</p>';
        }
        var style = '--ac-bg:' + color + ';--ac-fg:' + text + ';--ac-radius:' + radius + 'px;';
        var cls = 'ac-notice' + (shadow ? '' : ' ac-notice--flat');
        var html = '<aside class="' + cls + '" style="' + escapeHtml(style) + '">';
        if (title) html += '<div class="ac-notice-title">' + escapeHtml(title) + '</div>';
        if (body) html += '<div class="ac-notice-body">' + body + '</div>';
        html += '</aside>';
        return html;
    }

    function buildNoticeShortcode() {
        var form = collectNoticeForm();
        if (!form.title && !form.body) {
            window.alert('请填写标题或内容');
            return '';
        }
        var open = '[notice color="' + escapeAttr(form.color) + '" text="' + escapeAttr(form.text) + '"'
            + ' radius="' + escapeAttr(form.radius) + '"'
            + ' shadow="' + (form.shadow ? '1' : '0') + '"';
        if (form.title) open += ' title="' + escapeAttr(form.title) + '"';
        open += ']';
        return open + '\n' + form.body + '\n[/notice]';
    }

    /* ---------- Shell ---------- */

    function currentComponent() {
        return $('#ac-component').val() || 'notice';
    }

    function setComponent(type) {
        if (type === 'music' && !boot.musicActive) {
            type = 'notice';
        }
        $('#ac-component').val(type);
        $('.ac-comp-chip').removeClass('is-active');
        $('.ac-comp-chip[data-ac-comp="' + type + '"]').addClass('is-active');
        syncComponentPanels();
    }

    function syncComponentPanels() {
        var type = currentComponent();
        $('#ac-panel-music').prop('hidden', type !== 'music');
        $('#ac-panel-notice').prop('hidden', type !== 'notice');
        if (type === 'music') {
            fillAttachmentSelects();
            syncMusicSourcePanels();
        }
        refreshPreview();
    }

    function refreshPreview() {
        var type = currentComponent();
        var html;
        if (type === 'music') {
            if (!boot.musicActive) {
                html = '<p class="ac-preview-empty">未启用 CustomMusicPlayer，无法预览音乐组件。</p>';
            } else {
                html = musicPreviewHtml();
            }
        } else {
            html = noticePreviewHtml();
        }
        $('#ac-preview').html(html);
    }

    function openModal() {
        rebuildPresetSelect($('#ac-notice-preset').val() || 'custom');
        if (boot.musicActive) {
            $('#cmp-notice').prop('checked', true);
            fillAttachmentSelects();
            syncMusicSourcePanels();
            setComponent('music');
        } else {
            setComponent('notice');
            if (!$.trim($('#ac-notice-body').val())) {
                $('#ac-notice-preset').val('welcome');
                applyNoticePreset('welcome');
            }
        }
        $('#ac-inserter-modal').prop('hidden', false);
    }

    function closeModal() {
        $('#ac-inserter-modal').prop('hidden', true);
    }

    function insertCurrent() {
        var type = currentComponent();
        if (type === 'music') {
            if (!boot.musicActive) {
                window.alert('请先启用 CustomMusicPlayer 插件');
                return;
            }
            var source = $('#cmp-source').val() || 'custom';
            var mode = $('#cmp-mode').val() || 'click';
            var notice = $('#cmp-notice').is(':checked');
            if (source === 'custom') {
                var title = $.trim($('#cmp-title').val());
                var src = $.trim($('#cmp-src').val());
                if (!title) { window.alert('请填写歌曲名'); return; }
                if (!src) { window.alert('请填写音频 URL，或从附件选择'); return; }
                if (insertIntoEditor(buildMusicShortcode({
                    from: 'custom', title: title,
                    artist: $.trim($('#cmp-artist').val()),
                    src: src, cover: $.trim($('#cmp-cover').val()),
                    mode: mode, notice: notice
                }))) closeModal();
                return;
            }
            var id = extractPlatformId($('#cmp-platform-id').val(), source);
            if (!id) {
                window.alert('请填写网易云/QQ 音乐歌曲 ID 或分享链接');
                return;
            }
            var doInsert = function (meta) {
                if (insertIntoEditor(buildMusicShortcode({
                    from: source, id: id,
                    title: meta && meta.title ? meta.title : $.trim($('#cmp-title').val()),
                    artist: meta && meta.artist ? meta.artist : $.trim($('#cmp-artist').val()),
                    cover: meta && meta.cover ? meta.cover : $.trim($('#cmp-cover').val()),
                    mode: mode, notice: notice
                }))) closeModal();
            };
            if ($.trim($('#cmp-title').val()) && $('#cmp-platform-id').val() === id) {
                doInsert({
                    title: $.trim($('#cmp-title').val()),
                    artist: $.trim($('#cmp-artist').val()),
                    cover: $.trim($('#cmp-cover').val())
                });
                return;
            }
            resolvePlatformTrack().then(function (meta) { doInsert(meta || {}); });
            return;
        }

        var code = buildNoticeShortcode();
        if (code && insertIntoEditor(code)) closeModal();
    }

    $(function () {
        if (!$('#ac-music-preview-style').length) {
            $('head').append(
                '<style id="ac-music-preview-style">' +
                '.ac-music-preview{display:flex;gap:12px;align-items:center;}' +
                '.ac-music-preview-disc{width:56px;height:56px;border-radius:50%;' +
                'background:radial-gradient(circle at 50% 50%,#333 18%,#111 19%,#222 42%,#000 43%,#444 70%,#111 71%);flex-shrink:0;}' +
                '.ac-music-preview-meta{display:flex;flex-direction:column;gap:4px;font-size:13px;}' +
                '.ac-music-preview-tags{color:#888;font-size:12px;}' +
                '</style>'
            );
        }

        if (boot.defaultColor) {
            BUILTIN_PRESETS.custom.color = boot.defaultColor;
            $('#ac-notice-color').val(boot.defaultColor);
            $('#ac-notice-color-text').val(boot.defaultColor);
        }
        if (boot.defaultTextColor) {
            BUILTIN_PRESETS.custom.text = boot.defaultTextColor;
            $('#ac-notice-text').val(boot.defaultTextColor);
            $('#ac-notice-text-text').val(boot.defaultTextColor);
        }

        rebuildPresetSelect('custom');
        syncColorPair('#ac-notice-color', '#ac-notice-color-text');
        syncColorPair('#ac-notice-text', '#ac-notice-text-text');
        setComponent(boot.musicActive ? 'music' : 'notice');

        $(document).on('click', '#ac-open-inserter', function (e) {
            e.preventDefault();
            openModal();
        });
        $(document).on('click', '[data-ac-close]', function (e) {
            e.preventDefault();
            closeModal();
        });
        $(document).on('click', '.ac-comp-chip', function (e) {
            e.preventDefault();
            setComponent($(this).data('ac-comp'));
        });
        $(document).on('change', '#cmp-source', function () {
            syncMusicSourcePanels();
            refreshPreview();
        });
        $(document).on('change', '#cmp-src-pick', function () {
            var v = $(this).val();
            if (v) $('#cmp-src').val(v);
            refreshPreview();
        });
        $(document).on('change', '#cmp-cover-pick', function () {
            var v = $(this).val();
            if (v) $('#cmp-cover').val(v);
        });
        $(document).on('click', '#cmp-resolve-btn', function (e) {
            e.preventDefault();
            resolvePlatformTrack().then(refreshPreview);
        });
        $(document).on('change', '#ac-notice-preset', function () {
            applyNoticePreset($(this).val());
        });
        $(document).on('click', '#ac-preset-save', function (e) {
            e.preventDefault();
            saveCurrentAsPreset();
        });
        $(document).on('click', '#ac-preset-delete', function (e) {
            e.preventDefault();
            deleteCurrentUserPreset();
        });
        $(document).on(
            'input change',
            '#cmp-title,#cmp-artist,#cmp-mode,#cmp-notice,#ac-notice-title,#ac-notice-body,#ac-notice-radius,#ac-notice-shadow',
            refreshPreview
        );
        $(document).on('click', '#ac-refresh-preview', function (e) {
            e.preventDefault();
            refreshPreview();
        });
        $(document).on('click', '#ac-insert-btn', function (e) {
            e.preventDefault();
            insertCurrent();
        });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && !$('#ac-inserter-modal').prop('hidden')) {
                closeModal();
            }
        });

        var fileList = document.getElementById('file-list');
        if (fileList && window.MutationObserver) {
            new MutationObserver(function () {
                if (!$('#ac-inserter-modal').prop('hidden') && currentComponent() === 'music') {
                    fillAttachmentSelects();
                }
            }).observe(fileList, { childList: true, subtree: true });
        }
    });
})(window.jQuery);
