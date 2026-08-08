<?php
/**
 * 文章组件：统一后台「组件插入」，含作者申明等可扩展区块
 *
 * @package ArticleComponents
 * @author Manueld
 * @version 1.0.5
 * @dependence 9.9.2-*
 * @link https://github.com/AWildFunny/Manueld.me
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class ArticleComponents_Plugin implements Typecho_Plugin_Interface
{
    /** @var bool */
    private static $needsAssets = false;

    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('ArticleComponents_Plugin', 'parse');
        Typecho_Plugin::factory('Widget_Archive')->header = array('ArticleComponents_Plugin', 'header');

        Typecho_Plugin::factory('admin/write-post.php')->option = array('ArticleComponents_Plugin', 'adminOption');
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array('ArticleComponents_Plugin', 'adminBottom');
        Typecho_Plugin::factory('admin/write-page.php')->option = array('ArticleComponents_Plugin', 'adminOption');
        Typecho_Plugin::factory('admin/write-page.php')->bottom = array('ArticleComponents_Plugin', 'adminBottom');

        return _t('已启用组件插入。请到写文章页右侧使用「组件插入」；作者申明短代码为 [notice]…[/notice]。');
    }

    public static function deactivate() {}

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $defaultColor = new Typecho_Widget_Helper_Form_Element_Text(
            'defaultColor',
            null,
            '#1095c1',
            '作者申明默认背景色',
            '未指定 color 时使用。建议与主题主色接近。'
        );
        $form->addInput($defaultColor);

        $defaultText = new Typecho_Widget_Helper_Form_Element_Text(
            'defaultTextColor',
            null,
            '#ffffff',
            '作者申明默认文字色',
            '未指定 text 时使用。'
        );
        $form->addInput($defaultText);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * @return bool
     */
    public static function isMusicPluginActive()
    {
        return Typecho_Plugin::exists('CustomMusicPlayer');
    }

    /**
     * @param string $content
     * @param Widget_Abstract_Contents $widget
     * @param string $lastResult
     * @return string
     */
    public static function parse($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;

        if (!($widget instanceof Widget_Archive) || !$widget->is('single')) {
            return $content;
        }

        if (stripos($content, '[notice') === false) {
            return $content;
        }

        self::$needsAssets = true;

        // 避免 Markdown 把短代码包进 <p>
        $content = preg_replace('/<p>\s*(\[notice\b[^\]]*\])/i', '$1', $content);
        $content = preg_replace('/(\[\/notice\])\s*<\/p>/i', '$1', $content);

        $content = preg_replace_callback(
            '/\[notice\b([^\]]*)\](.*?)\[\/notice\]/is',
            array('ArticleComponents_Plugin', 'renderNotice'),
            $content
        );

        return $content;
    }

    /**
     * @param array<int, string> $matches
     * @return string
     */
    public static function renderNotice($matches)
    {
        $attrs = self::parseAttributes(isset($matches[1]) ? $matches[1] : '');
        $bodyRaw = isset($matches[2]) ? trim($matches[2]) : '';

        $title = isset($attrs['title']) ? trim($attrs['title']) : '';
        $color = isset($attrs['color']) ? trim($attrs['color']) : '';
        $text = isset($attrs['text']) ? trim($attrs['text']) : '';
        if ($text === '' && isset($attrs['textcolor'])) {
            $text = trim($attrs['textcolor']);
        }

        $shadow = self::attrBool(isset($attrs['shadow']) ? $attrs['shadow'] : '1', true);
        $radius = isset($attrs['radius']) ? trim($attrs['radius']) : '12';
        if (!preg_match('/^\d{1,2}$/', $radius)) {
            $radius = '12';
        }

        $defaults = self::getColorDefaults();
        if ($color === '' || !self::isCssColor($color)) {
            $color = $defaults['color'];
        }
        if ($text === '' || !self::isCssColor($text)) {
            $text = $defaults['text'];
        }

        $bodyHtml = self::formatNoticeBody($bodyRaw);
        if ($bodyHtml === '' && $title === '') {
            return '';
        }

        $style = '--ac-bg:' . $color . ';--ac-fg:' . $text . ';--ac-radius:' . $radius . 'px;';
        $classes = 'ac-notice';
        if (!$shadow) {
            $classes .= ' ac-notice--flat';
        }

        $html = '<aside class="' . $classes . '" style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '" role="note">';
        if ($title !== '') {
            $html .= '<div class="ac-notice-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($bodyHtml !== '') {
            $html .= '<div class="ac-notice-body">' . $bodyHtml . '</div>';
        }
        $html .= '</aside>';

        return $html;
    }

    /**
     * @return array{color:string,text:string}
     */
    private static function getColorDefaults()
    {
        $color = '#1095c1';
        $text = '#ffffff';
        try {
            $plugin = Helper::options()->plugin('ArticleComponents');
            if (!empty($plugin->defaultColor) && self::isCssColor($plugin->defaultColor)) {
                $color = trim($plugin->defaultColor);
            }
            if (!empty($plugin->defaultTextColor) && self::isCssColor($plugin->defaultTextColor)) {
                $text = trim($plugin->defaultTextColor);
            }
        } catch (Exception $e) {
        }
        return array('color' => $color, 'text' => $text);
    }

    /**
     * @param string $value
     * @param bool $default
     * @return bool
     */
    private static function attrBool($value, $default)
    {
        $v = strtolower(trim((string) $value));
        if ($v === '') {
            return $default;
        }
        if (in_array($v, array('0', 'false', 'no', 'off'), true)) {
            return false;
        }
        if (in_array($v, array('1', 'true', 'yes', 'on'), true)) {
            return true;
        }
        return $default;
    }

    /**
     * @param string $color
     * @return bool
     */
    private static function isCssColor($color)
    {
        $color = trim($color);
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color)) {
            return true;
        }
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $color)) {
            return true;
        }
        return false;
    }

    /**
     * @param string $raw
     * @return string
     */
    private static function formatNoticeBody($raw)
    {
        $raw = trim(str_replace(array("\r\n", "\r"), "\n", $raw));
        if ($raw === '') {
            return '';
        }

        // Markdown 常把短代码正文拆成「裸文本 + <p>」，或在开头插入 <br>/空段落
        if (preg_match('/<(p|div|ul|ol|br)\b/i', $raw)) {
            return self::normalizeNoticeHtml(self::sanitizeNoticeHtml($raw));
        }

        $parts = preg_split('/\n\s*\n/', $raw);
        $html = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $escaped = htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
            $escaped = preg_replace(
                '/\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]*|#[^)\s]*)\)/',
                '<a href="$2">$1</a>',
                $escaped
            );
            $html .= '<p>' . nl2br($escaped, false) . '</p>';
        }

        return self::normalizeNoticeHtml($html);
    }

    /**
     * @param string $html
     * @return string
     */
    private static function stripEmptyNoticeBlocks($html)
    {
        $html = preg_replace('/<p\b[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/i', '', $html);
        return trim($html);
    }

    /**
     * 清理 Markdown 造成的前导 <br>/空段落，并把开头裸文本包进 <p>
     *
     * @param string $html
     * @return string
     */
    private static function normalizeNoticeHtml($html)
    {
        $html = trim($html);
        $html = preg_replace('/^(?:<br\s*\/?>|\s|&nbsp;)+/i', '', $html);
        $html = preg_replace('/(?:<br\s*\/?>|\s|&nbsp;)+$/i', '', $html);
        $html = self::stripEmptyNoticeBlocks($html);

        if ($html === '') {
            return '';
        }

        if (!preg_match('/^<(p|ul|ol|div)\b/i', $html)) {
            if (preg_match('/^(.*?)(?=<(?:p|ul|ol|div)\b)/is', $html, $m)) {
                $lead = trim(preg_replace('/^(?:<br\s*\/?>|\s|&nbsp;)+|(?:<br\s*\/?>|\s|&nbsp;)+$/i', '', $m[1]));
                $rest = substr($html, strlen($m[1]));
                $html = ($lead !== '' ? '<p>' . $lead . '</p>' : '') . $rest;
            } else {
                $html = '<p>' . preg_replace('/^(?:<br\s*\/?>|\s|&nbsp;)+|(?:<br\s*\/?>|\s|&nbsp;)+$/i', '', $html) . '</p>';
            }
        }

        return self::stripEmptyNoticeBlocks($html);
    }

    /**
     * @param string $html
     * @return string
     */
    private static function sanitizeNoticeHtml($html)
    {
        $allowed = '<p><br><a><strong><b><em><i><ul><ol><li><span>';
        $html = strip_tags($html, $allowed);

        // 仅保留 http(s) 与站内相对链接
        $html = preg_replace_callback(
            '/<a\s+([^>]*)>/i',
            function ($m) {
                $attrs = $m[1];
                $href = '';
                if (preg_match('/href\s*=\s*"([^"]*)"/i', $attrs, $hm) || preg_match("/href\s*=\s*'([^']*)'/i", $attrs, $hm)) {
                    $href = trim($hm[1]);
                }
                if ($href === '' || (!preg_match('#^https?://#i', $href) && strpos($href, '/') !== 0 && strpos($href, '#') !== 0)) {
                    return '<a>';
                }
                return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
            },
            $html
        );

        return $html;
    }

    /**
     * @param string $attrString
     * @return array<string, string>
     */
    private static function parseAttributes($attrString)
    {
        $attrs = array();
        if (preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $attrString, $m, PREG_SET_ORDER)) {
            foreach ($m as $item) {
                $attrs[strtolower($item[1])] = $item[2];
            }
        }
        if (preg_match_all("/(\w+)\s*=\s*'([^']*)'/", $attrString, $m, PREG_SET_ORDER)) {
            foreach ($m as $item) {
                $key = strtolower($item[1]);
                if (!isset($attrs[$key])) {
                    $attrs[$key] = $item[2];
                }
            }
        }
        return $attrs;
    }

    private static function shouldLoadAssets()
    {
        if (self::$needsAssets) {
            return true;
        }

        try {
            $widget = Typecho_Widget::widget('Widget_Archive');
            if ($widget->is('single') && isset($widget->text)) {
                $raw = (string) $widget->text;
                if ($raw !== '' && stripos($raw, '[notice') !== false) {
                    self::$needsAssets = true;
                    return true;
                }
            }
        } catch (Exception $e) {
        }

        return false;
    }

    public static function header()
    {
        if (!self::shouldLoadAssets()) {
            return;
        }

        $base = Helper::options()->pluginUrl . '/ArticleComponents/assets/notice.css';
        echo '<link rel="stylesheet" href="' . htmlspecialchars($base . '?ver=1.0.5', ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function adminOption()
    {
        echo '<section class="typecho-post-option article-components-admin-option">'
            . '<label class="typecho-label">组件插入</label>'
            . '<p><button type="button" class="btn btn-xs" id="ac-open-inserter">打开组件面板</button></p>'
            . '</section>';
    }

    public static function adminBottom()
    {
        $pluginUrl = Helper::options()->pluginUrl . '/ArticleComponents/assets';
        $css = htmlspecialchars($pluginUrl . '/admin-components.css?ver=1.0.5', ENT_QUOTES, 'UTF-8');
        $js = htmlspecialchars($pluginUrl . '/admin-components.js?ver=1.0.5', ENT_QUOTES, 'UTF-8');
        $noticeCss = htmlspecialchars($pluginUrl . '/notice.css?ver=1.0.5', ENT_QUOTES, 'UTF-8');

        $defaults = self::getColorDefaults();
        $siteUrl = Helper::options()->siteUrl;
        $musicActive = self::isMusicPluginActive();

        $apiTpl = 'https://meting.mikus.ink/api?server=:server&type=:type&id=:id';
        if ($musicActive) {
            try {
                $plugin = Helper::options()->plugin('CustomMusicPlayer');
                if (!empty($plugin->metingApi)) {
                    $apiTpl = trim($plugin->metingApi);
                }
            } catch (Exception $e) {
            }
        }

        $boot = array(
            'musicActive' => $musicActive,
            'metingApi' => $apiTpl,
            'defaultColor' => $defaults['color'],
            'defaultTextColor' => $defaults['text'],
            'siteUrl' => rtrim($siteUrl, '/') . '/',
        );

        echo '<link rel="stylesheet" href="' . $noticeCss . '">';
        echo '<link rel="stylesheet" href="' . $css . '">';
        echo '<script>window.AC_BOOT=' . json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';

        $musicPanelHidden = $musicActive ? '' : ' hidden';
        $musicChip = $musicActive
            ? '<button type="button" class="ac-comp-chip" data-ac-comp="music">音乐播放器</button>'
            : '';
        $defaultComp = $musicActive ? 'music' : 'notice';

        echo <<<HTML
<div id="ac-inserter-modal" class="ac-modal" hidden>
  <div class="ac-modal-backdrop" data-ac-close></div>
  <div class="ac-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ac-modal-title">
    <div class="ac-modal-header">
      <h3 id="ac-modal-title">组件插入</h3>
      <button type="button" class="ac-modal-close" data-ac-close aria-label="关闭">&times;</button>
    </div>
    <div class="ac-modal-body">
      <aside class="ac-modal-aside">
        <div class="ac-comp-picker">
          <div class="ac-comp-picker-label">选择组件</div>
          <div class="ac-comp-switch" role="tablist" aria-label="选择组件">
            {$musicChip}
            <button type="button" class="ac-comp-chip" data-ac-comp="notice">作者申明</button>
          </div>
          <input type="hidden" id="ac-component" value="{$defaultComp}">
        </div>
        <div class="ac-modal-preview-wrap">
          <div class="ac-preview-label">预览</div>
          <div id="ac-preview" class="ac-preview-stage">
            <p class="ac-preview-empty">编辑右侧参数后，此处显示插入效果。</p>
          </div>
        </div>
      </aside>

      <div class="ac-modal-main">
        <div id="ac-panel-music" class="ac-panel"{$musicPanelHidden}>
          <p>
            <label for="cmp-source">插入方式</label>
            <select id="cmp-source" class="w-100">
              <option value="custom">自定义（URL / 附件）</option>
              <option value="netease">网易云音乐（歌曲 ID）</option>
              <option value="tencent">QQ 音乐（歌曲 ID）</option>
            </select>
          </p>
          <div id="cmp-panel-platform" class="cmp-panel" hidden>
            <p>
              <label for="cmp-platform-id">歌曲 ID 或分享链接 <span class="ac-req">*</span></label>
              <input type="text" id="cmp-platform-id" class="text w-100 mono" placeholder="如 185809 或分享链接">
            </p>
            <p>
              <button type="button" class="btn btn-xs" id="cmp-resolve-btn">解析曲目信息</button>
              <span id="cmp-resolve-status" class="description" style="margin-left:8px"></span>
            </p>
          </div>
          <p>
            <label for="cmp-title">歌曲名 <span class="ac-req cmp-req-title">*</span></label>
            <input type="text" id="cmp-title" class="text w-100" placeholder="歌曲名">
          </p>
          <p>
            <label for="cmp-artist">艺术家</label>
            <input type="text" id="cmp-artist" class="text w-100" placeholder="可选">
          </p>
          <div id="cmp-panel-custom" class="cmp-panel">
            <p>
              <label for="cmp-src">音频 URL（MP3） <span class="ac-req">*</span></label>
              <input type="text" id="cmp-src" class="text w-100 mono" placeholder="https://... 或从附件选择">
              <select id="cmp-src-pick" class="w-100"><option value="">— 从已上传附件选择音频 —</option></select>
            </p>
            <p>
              <label for="cmp-cover">封面 URL</label>
              <input type="text" id="cmp-cover" class="text w-100 mono" placeholder="可选">
              <select id="cmp-cover-pick" class="w-100"><option value="">— 从已上传附件选择封面 —</option></select>
            </p>
          </div>
          <p>
            <label for="cmp-mode">播放模式</label>
            <select id="cmp-mode" class="w-100">
              <option value="click">点击播放</option>
              <option value="scroll">滚入视口自动播放</option>
            </select>
          </p>
          <p class="ac-check">
            <label><input type="checkbox" id="cmp-notice" value="1" checked> 进页显示「含背景音频」提示窗</label>
          </p>
        </div>

        <div id="ac-panel-notice" class="ac-panel" hidden>
          <p>
            <label for="ac-notice-preset">预设</label>
            <select id="ac-notice-preset" class="w-100"></select>
          </p>
          <p class="ac-preset-actions">
            <button type="button" class="btn btn-xs" id="ac-preset-save">保存为预设</button>
            <button type="button" class="btn btn-xs" id="ac-preset-delete" hidden>删除该预设</button>
          </p>
          <p>
            <label for="ac-notice-title">标题（可选）</label>
            <input type="text" id="ac-notice-title" class="text w-100" placeholder="留空则不显示标题行">
          </p>
          <p class="ac-inline-fields">
            <span>
              <label for="ac-notice-color">背景色</label>
              <input type="color" id="ac-notice-color" value="#1095c1">
              <input type="text" id="ac-notice-color-text" class="text mono ac-color-text" value="#1095c1">
            </span>
            <span>
              <label for="ac-notice-text">文字色</label>
              <input type="color" id="ac-notice-text" value="#ffffff">
              <input type="text" id="ac-notice-text-text" class="text mono ac-color-text" value="#ffffff">
            </span>
          </p>
          <p class="ac-inline-fields">
            <span>
              <label for="ac-notice-radius">圆角（px）</label>
              <input type="number" id="ac-notice-radius" class="text" min="0" max="32" value="12">
            </span>
            <span class="ac-check ac-check-inline">
              <label><input type="checkbox" id="ac-notice-shadow" checked> 悬浮阴影</label>
            </span>
          </p>
          <p>
            <label for="ac-notice-body">内容</label>
            <textarea id="ac-notice-body" class="w-100 mono" rows="12" placeholder="支持空行分段；链接可用 [文字](/path) 或 HTML &lt;a&gt;"></textarea>
          </p>
        </div>
      </div>
    </div>
    <div class="ac-modal-footer">
      <button type="button" class="btn" data-ac-close>取消</button>
      <button type="button" class="btn" id="ac-refresh-preview">刷新预览</button>
      <button type="button" class="btn primary" id="ac-insert-btn">插入短代码</button>
    </div>
  </div>
</div>
HTML;
        echo '<script src="' . $js . '"></script>';
    }
}
