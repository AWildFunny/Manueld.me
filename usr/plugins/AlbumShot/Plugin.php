<?php
/**
 * 图文融合：音乐相册章头视觉短代码，并注册到主题「组件插入」
 *
 * @package AlbumShot
 * @author Manueld
 * @version 1.3.1
 * @dependence 9.9.2-*
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AlbumShot_Plugin implements Typecho_Plugin_Interface
{
    /** @var bool */
    private static $needsAssets = false;

    public static function activate()
    {
        // 必须在 Markdown 转换前保护短代码：相邻 [a][b] 会被当成引用链接吃掉
        Typecho_Plugin::factory('Widget_Abstract_Contents')->markdown = array('AlbumShot_Plugin', 'markdown');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('AlbumShot_Plugin', 'parse');
        Typecho_Plugin::factory('Widget_Archive')->header = array('AlbumShot_Plugin', 'header');
        Typecho_Plugin::factory('ComponentInserter')->collect = array('AlbumShot_Plugin', 'registerComponent');

        return _t('图文融合已启用。写文章侧栏「组件插入」可从附件库拖图到预览区。若从旧版升级，请禁用后再启用一次。');
    }

    public static function deactivate() {}

    public static function config(Typecho_Widget_Helper_Form $form) {}

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    private static function loadCiRegistry()
    {
        if (class_exists('ComponentInserter_Registry')) {
            return true;
        }
        try {
            $options = Helper::options();
            $reg = $options->themeFile($options->theme, 'include/ComponentInserter/Registry.php');
            if (is_file($reg)) {
                require_once $reg;
            }
        } catch (Exception $e) {
        }
        return class_exists('ComponentInserter_Registry');
    }

    /**
     * 收集可插入的图片附件（优先本文，再补近期其它）
     * @return array
     */
    private static function listImageAttachments()
    {
        $images = array();
        $seen = array();
        $cid = 0;
        if (isset($_REQUEST['cid'])) {
            $cid = intval($_REQUEST['cid']);
        }

        try {
            $db = Typecho_Db::get();
            $options = Helper::options();
            $uploadBase = defined('__TYPECHO_UPLOAD_URL__')
                ? rtrim(__TYPECHO_UPLOAD_URL__, '/') . '/'
                : rtrim($options->siteUrl, '/') . '/';

            $pushRow = function ($row) use (&$images, &$seen, $uploadBase) {
                if (empty($row['text'])) {
                    return;
                }
                $data = @unserialize($row['text']);
                if (!is_array($data) || empty($data['path'])) {
                    return;
                }
                $ext = strtolower(isset($data['type']) ? $data['type'] : pathinfo($data['path'], PATHINFO_EXTENSION));
                if (!in_array($ext, array('jpg', 'jpeg', 'gif', 'png', 'tiff', 'bmp', 'webp', 'avif'), true)) {
                    return;
                }
                $path = str_replace('\\', '/', $data['path']);
                // 与核心 Upload::attachmentHandle 默认逻辑一致
                if (class_exists('Typecho_Common')) {
                    $url = Typecho_Common::url(
                        $path,
                        defined('__TYPECHO_UPLOAD_URL__') ? __TYPECHO_UPLOAD_URL__ : Helper::options()->siteUrl
                    );
                } else {
                    $url = rtrim($uploadBase, '/') . '/' . ltrim($path, '/');
                }
                if (isset($seen[$url])) {
                    return;
                }
                $seen[$url] = true;
                $images[] = array(
                    'url' => $url,
                    'name' => isset($row['title']) && $row['title'] !== '' ? $row['title'] : basename($path),
                    'cid' => isset($row['cid']) ? intval($row['cid']) : 0,
                );
            };

            if ($cid > 0) {
                $mine = $db->fetchAll($db->select('cid', 'title', 'text')
                    ->from('table.contents')
                    ->where('type = ?', 'attachment')
                    ->where('parent = ?', $cid)
                    ->order('created', Typecho_Db::SORT_DESC)
                    ->limit(80));
                foreach ($mine as $row) {
                    $pushRow($row);
                }
            }

            $others = $db->fetchAll($db->select('cid', 'title', 'text')
                ->from('table.contents')
                ->where('type = ?', 'attachment')
                ->order('created', Typecho_Db::SORT_DESC)
                ->limit(100));
            foreach ($others as $row) {
                if (count($images) >= 120) {
                    break;
                }
                $pushRow($row);
            }
        } catch (Exception $e) {
        }

        return $images;
    }

    public static function registerComponent()
    {
        if (!self::loadCiRegistry()) {
            return;
        }

        $pluginUrl = Helper::options()->pluginUrl . '/AlbumShot/assets';
        $panelHtml = <<<'HTML'
<div class="as-workbench">
  <div class="as-toolbar">
    <p class="as-toolbar-row">
      <span>
        <label for="as-cat">分类</label>
        <select id="as-cat" class="w-100">
          <option value="single">单图 · 与标题排版</option>
          <option value="duo">双图</option>
          <option value="multi">多图</option>
          <option value="canvas">自由画布</option>
        </select>
      </span>
      <span id="as-layout-field">
        <label for="as-layout">版式</label>
        <select id="as-layout" class="w-100">
          <option value="auto">智能默认</option>
          <option value="banner">横幅</option>
          <option value="overlay">叠字封面</option>
          <option value="split-left">左图右文</option>
          <option value="split-right">左文右图</option>
          <option value="float">文绕图</option>
          <option value="custom">自定义</option>
        </select>
      </span>
      <span id="as-preset-field" hidden>
        <label for="as-preset">构图</label>
        <select id="as-preset" class="w-100">
          <option value="duo-split">左右对开</option>
          <option value="duo-main-side">主图 + 侧图</option>
          <option value="duo-overlap">轻微叠压</option>
          <option value="tri-stack">一大两小</option>
          <option value="tri-row">三联横排</option>
          <option value="quad">四宫错落</option>
          <option value="canvas">自由坐标</option>
        </select>
      </span>
      <span id="as-ratio-field" hidden>
        <label for="as-ratio">画幅</label>
        <select id="as-ratio" class="w-100">
          <option value="3:2">3:2</option>
          <option value="16:9">16:9</option>
          <option value="4:3">4:3</option>
          <option value="1:1">1:1</option>
        </select>
      </span>
    </p>
    <div id="as-custom-wrap" hidden>
      <p class="ci-inline-fields">
        <span>
          <label for="as-pos">图位置</label>
          <select id="as-pos" class="w-100">
            <option value="top">上</option>
            <option value="left">左</option>
            <option value="right">右</option>
            <option value="bg">作底</option>
          </select>
        </span>
        <span>
          <label for="as-titlepos">标题位置</label>
          <select id="as-titlepos" class="w-100">
            <option value="above">图上</option>
            <option value="on">叠在图上</option>
            <option value="beside">图旁</option>
            <option value="below">图下</option>
          </select>
        </span>
      </p>
      <p class="ci-check">
        <label><input type="checkbox" id="as-wrap"> 正文环绕图片</label>
      </p>
    </div>
    <p id="as-alt-wrap">
      <label for="as-alt">说明 / alt</label>
      <input type="text" id="as-alt" class="text w-100" placeholder="可选">
    </p>
    <p class="as-toolbar-actions">
      <button type="button" class="btn btn-xs" id="as-lib-refresh">刷新附件库</button>
      <button type="button" class="btn btn-xs" id="as-board-clear" hidden>清空画布</button>
      <input type="hidden" id="as-src" value="">
    </p>
    <p class="description as-drop-hint">从下方图库<strong>拖到左侧预览</strong>，或点击选用。单图替换主图；多图/画布则加入画布。</p>
  </div>
  <div class="as-lib">
    <div class="as-lib-head">
      <span>附件图片库</span>
      <span id="as-lib-count" class="as-lib-count">0</span>
    </div>
    <div id="as-lib-grid" class="as-lib-grid"></div>
    <p id="as-lib-empty" class="as-lib-empty" hidden>暂无图片附件。请先在文章右侧「附件」上传图片，再点「刷新附件库」。</p>
  </div>
</div>
HTML;

        ComponentInserter_Registry::register(array(
            'id' => 'album-shot',
            'label' => '图文融合',
            'order' => 15,
            'panelHtml' => $panelHtml,
            'boot' => array(
                'images' => self::listImageAttachments(),
            ),
            'css' => array($pluginUrl . '/admin-panel.css?ver=1.3.1'),
            'js' => array($pluginUrl . '/admin-panel.js?ver=1.3.1'),
        ));
    }

    /**
     * Markdown 转换前先把短代码变成 HTML，避免 [board][img] 被当成引用链接。
     */
    public static function markdown($text, $lastResult = null)
    {
        $text = ($lastResult === null || $lastResult === '') ? $text : $lastResult;
        $text = self::parse($text, null, null);
        if (class_exists('\\Utils\\Markdown')) {
            return \Utils\Markdown::convert($text);
        }
        if (class_exists('Markdown')) {
            return Markdown::convert($text);
        }
        return $text;
    }

    public static function parse($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;
        if (stripos($content, '[album-shot') === false && stripos($content, '[album-board') === false) {
            return $content;
        }

        self::$needsAssets = true;
        $content = preg_replace('/<p>\s*(\[album-shot\b[^\]]*\])\s*<\/p>/i', '$1', $content);
        $content = preg_replace('/<div[^>]*>\s*(\[album-shot\b[^\]]*\])\s*<\/div>/i', '$1', $content);
        $content = preg_replace('/<p>\s*(\[album-board\b[\s\S]*?\[\/album-board\])\s*<\/p>/i', '$1', $content);
        $content = preg_replace('/<div[^>]*>\s*(\[album-board\b[\s\S]*?\[\/album-board\])\s*<\/div>/i', '$1', $content);

        $content = preg_replace_callback(
            '/\[album-board\b([^\]]*)\](.*?)\[\/album-board\]/is',
            array('AlbumShot_Plugin', 'renderBoard'),
            $content
        );

        $pattern = '/\[album-shot((?:\s+[a-zA-Z_][\w-]*\s*=\s*(?:"[^"]*"|\'[^\']*\'|&quot;.*?&quot;|[^\s\]]+))*)\s*\]/i';
        $content = preg_replace_callback($pattern, array('AlbumShot_Plugin', 'renderShot'), $content);
        return $content;
    }

    public static function renderBoard($matches)
    {
        $attrs = self::parseAttributes(isset($matches[1]) ? $matches[1] : '');
        $inner = isset($matches[2]) ? $matches[2] : '';
        $inner = html_entity_decode($inner, ENT_QUOTES, 'UTF-8');
        $ratio = isset($attrs['ratio']) ? trim($attrs['ratio']) : '3:2';
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*:\s*(\d+(?:\.\d+)?)$/', $ratio, $rm)) {
            $ratio = '3:2';
            $rw = 3;
            $rh = 2;
        } else {
            $rw = $rm[1];
            $rh = $rm[2];
        }

        $items = array();
        if (preg_match_all('/\[img\b([^\]]*)\]/i', $inner, $im, PREG_SET_ORDER)) {
            foreach ($im as $row) {
                $ia = self::parseAttributes($row[1]);
                $src = isset($ia['src']) ? trim($ia['src']) : '';
                if (!self::isSafeUrl($src)) {
                    continue;
                }
                $items[] = array(
                    'src' => $src,
                    'alt' => isset($ia['alt']) ? trim($ia['alt']) : '',
                    'x' => self::num($ia, 'x', 4),
                    'y' => self::num($ia, 'y', 4),
                    'w' => self::num($ia, 'w', 44),
                );
            }
        }
        if (empty($items)) {
            return '';
        }

        $ratioCss = htmlspecialchars($rw . ' / ' . $rh, ENT_QUOTES, 'UTF-8');
        $html = '<div class="album-board" data-ratio="' . htmlspecialchars($ratio, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="album-board-stage" style="--board-ratio:' . $ratioCss . '">';
        foreach ($items as $item) {
            $srcEsc = htmlspecialchars($item['src'], ENT_QUOTES, 'UTF-8');
            $altEsc = htmlspecialchars($item['alt'], ENT_QUOTES, 'UTF-8');
            $style = 'left:' . $item['x'] . '%;top:' . $item['y'] . '%;width:' . $item['w'] . '%;';
            $html .= '<figure class="album-board-item" style="' . $style . '">';
            $html .= '<a data-fancybox="gallery" href="' . $srcEsc . '" data-caption="' . $altEsc . '">';
            $html .= '<img src="' . $srcEsc . '" alt="' . $altEsc . '">';
            $html .= '</a></figure>';
        }
        $html .= '</div></div>';
        return $html;
    }

    private static function num($attrs, $key, $default)
    {
        if (!isset($attrs[$key]) || $attrs[$key] === '') {
            return $default;
        }
        $v = floatval($attrs[$key]);
        if ($v < 0) {
            $v = 0;
        }
        if ($v > 100) {
            $v = 100;
        }
        return round($v, 2);
    }

    public static function renderShot($matches)
    {
        $attrs = self::parseAttributes(isset($matches[1]) ? $matches[1] : '');
        $src = isset($attrs['src']) ? trim($attrs['src']) : '';
        if (!self::isSafeUrl($src)) {
            return '';
        }

        $layout = isset($attrs['layout']) ? strtolower(trim($attrs['layout'])) : 'auto';
        $allowedLayout = array('auto', 'banner', 'overlay', 'split-left', 'split-right', 'float', 'custom');
        if (!in_array($layout, $allowedLayout, true)) {
            $layout = 'auto';
        }

        $pos = isset($attrs['pos']) ? strtolower(trim($attrs['pos'])) : '';
        $titlepos = isset($attrs['titlepos']) ? strtolower(trim($attrs['titlepos'])) : '';
        $wrap = isset($attrs['wrap']) ? strtolower(trim($attrs['wrap'])) : '';
        $alt = isset($attrs['alt']) ? trim($attrs['alt']) : '';
        $caption = isset($attrs['caption']) ? trim($attrs['caption']) : $alt;

        $srcEsc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $altEsc = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
        $captionEsc = htmlspecialchars($caption, ENT_QUOTES, 'UTF-8');
        $layoutEsc = htmlspecialchars($layout, ENT_QUOTES, 'UTF-8');

        $data = ' data-layout="' . $layoutEsc . '"';
        if ($pos !== '' && in_array($pos, array('top', 'left', 'right', 'bg'), true)) {
            $data .= ' data-pos="' . htmlspecialchars($pos, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($titlepos !== '' && in_array($titlepos, array('above', 'on', 'beside', 'below'), true)) {
            $data .= ' data-titlepos="' . htmlspecialchars($titlepos, ENT_QUOTES, 'UTF-8') . '"';
        }
        if (in_array($wrap, array('1', 'true', 'yes', 'on'), true)) {
            $data .= ' data-wrap="1"';
        }

        $html = '<figure class="album-shot"' . $data . '>';
        $html .= '<a data-fancybox="gallery" href="' . $srcEsc . '" data-caption="' . $captionEsc . '">';
        $html .= '<img src="' . $srcEsc . '" alt="' . $altEsc . '">';
        $html .= '</a>';
        if ($caption !== '' && $caption !== $alt) {
            $html .= '<figcaption>' . $captionEsc . '</figcaption>';
        }
        $html .= '</figure>';
        return $html;
    }

    private static function parseAttributes($raw)
    {
        $attrs = array();
        if ($raw === '' || $raw === null) {
            return $attrs;
        }
        $raw = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
        if (preg_match_all('/([a-zA-Z_][\w-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/', $raw, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $key = strtolower($match[1]);
                $val = '';
                if (isset($match[2]) && $match[2] !== '') {
                    $val = $match[2];
                } elseif (isset($match[3]) && $match[3] !== '') {
                    $val = $match[3];
                } elseif (isset($match[4])) {
                    $val = $match[4];
                }
                $attrs[$key] = $val;
            }
        }
        return $attrs;
    }

    private static function isSafeUrl($url)
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $url)) {
            return true;
        }
        if (strpos($url, '/') === 0) {
            return true;
        }
        return false;
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
                if ($raw !== '' && (stripos($raw, '[album-shot') !== false || stripos($raw, '[album-board') !== false)) {
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
        $css = Helper::options()->pluginUrl . '/AlbumShot/assets/album-shot.css?ver=1.3.1';
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
}
