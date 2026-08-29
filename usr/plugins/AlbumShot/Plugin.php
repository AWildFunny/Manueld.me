<?php
/**
 * 图文融合：音乐相册章头视觉短代码，并注册到主题「组件插入」
 *
 * @package AlbumShot
 * @author Manueld
 * @version 1.1.0
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
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('AlbumShot_Plugin', 'parse');
        Typecho_Plugin::factory('Widget_Archive')->header = array('AlbumShot_Plugin', 'header');
        Typecho_Plugin::factory('ComponentInserter')->collect = array('AlbumShot_Plugin', 'registerComponent');

        return _t('图文融合已启用。在音乐相册章节的 ## 下方插入 [album-shot ...]；写文章侧栏「组件插入」可预览。');
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

    public static function registerComponent()
    {
        if (!self::loadCiRegistry()) {
            return;
        }

        $pluginUrl = Helper::options()->pluginUrl . '/AlbumShot/assets';
        $panelHtml = <<<'HTML'
<p>
  <label>分类</label>
  <select id="as-cat" class="w-100">
    <option value="single">单图 · 与标题排版</option>
    <option value="duo">双图</option>
    <option value="multi">多图</option>
    <option value="canvas">自由画布（可拖拽）</option>
  </select>
</p>
<div id="as-single-wrap">
  <p>
    <label for="as-layout">版式</label>
    <select id="as-layout" class="w-100">
      <option value="auto">智能默认（横图横幅 / 竖图左图右文）</option>
      <option value="banner">横幅</option>
      <option value="overlay">叠字封面</option>
      <option value="split-left">左图右文</option>
      <option value="split-right">左文右图</option>
      <option value="float">文绕图</option>
      <option value="custom">自定义组合</option>
    </select>
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
          <option value="above">图上（标题在图上方）</option>
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
  <p>
    <label for="as-src">图片 URL <span class="ci-req">*</span></label>
    <input type="text" id="as-src" class="text w-100 mono" placeholder="https://... 或从附件选择">
    <select id="as-src-pick" class="w-100"><option value="">— 从已上传附件选择图片 —</option></select>
  </p>
  <p>
    <label for="as-alt">说明 / alt</label>
    <input type="text" id="as-alt" class="text w-100" placeholder="可选">
  </p>
</div>
<div id="as-board-wrap" hidden>
  <p>
    <label for="as-preset">构图</label>
    <select id="as-preset" class="w-100">
      <option value="duo-split">左右对开</option>
      <option value="duo-main-side">主图 + 侧图</option>
      <option value="duo-overlap">轻微叠压</option>
      <option value="tri-stack">一大两小</option>
      <option value="tri-row">三联横排</option>
      <option value="quad">四宫错落</option>
      <option value="canvas">自由（保持当前坐标）</option>
    </select>
  </p>
  <p class="ci-inline-fields">
    <span>
      <label for="as-ratio">画幅</label>
      <select id="as-ratio" class="w-100">
        <option value="3:2">3:2 页</option>
        <option value="16:9">16:9 宽</option>
        <option value="4:3">4:3</option>
        <option value="1:1">1:1</option>
      </select>
    </span>
  </p>
  <p>
    <label>添加图片</label>
    <select id="as-board-pick" class="w-100"><option value="">— 从附件加入画布 —</option></select>
    <input type="text" id="as-board-url" class="text w-100 mono" placeholder="或粘贴图片 URL 后回车">
  </p>
  <p class="description">左侧画布可拖拽图片；右下角圆点缩放。位置按百分比保存，窄屏整页等比缩放，图片保持原比例、不拉伸。</p>
  <p><button type="button" class="btn btn-xs" id="as-board-clear">清空画布</button></p>
</div>
<p class="description">插入到该章 <code>## 标题</code> 下一行。</p>
HTML;

        ComponentInserter_Registry::register(array(
            'id' => 'album-shot',
            'label' => '图文融合',
            'order' => 15,
            'panelHtml' => $panelHtml,
            'boot' => array(),
            'css' => array($pluginUrl . '/admin-panel.css?ver=1.1.0'),
            'js' => array($pluginUrl . '/admin-panel.js?ver=1.1.0'),
        ));
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
        $href = Helper::options()->pluginUrl . '/AlbumShot/assets/album-shot.css?ver=1.1.0';
        echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
    }
}
