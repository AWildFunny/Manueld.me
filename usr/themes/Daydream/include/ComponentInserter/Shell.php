<?php
/**
 * Daydream 内置「组件插入」面板壳
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Daydream_ComponentInserter
{
    /** 站点主题色（与前台 Daydream 主色一致） */
    const PRIMARY = '#4E7289';

    public static function adminOption()
    {
        echo '<section class="typecho-post-option component-inserter-admin-option">'
            . '<label class="typecho-label">组件插入</label>'
            . '<p><button type="button" class="btn btn-xs" id="ci-open-inserter">打开组件面板</button></p>'
            . '</section>';
    }

    public static function adminBottom()
    {
        if (!class_exists('ComponentInserter_Registry')) {
            require_once dirname(__FILE__) . '/Registry.php';
        }

        ComponentInserter_Registry::reset();
        try {
            Typecho_Plugin::factory('ComponentInserter')->collect();
        } catch (Exception $e) {
        }

        $items = ComponentInserter_Registry::all();
        $themeUrl = rtrim(Helper::options()->themeUrl, '/');
        $assetBase = $themeUrl . '/assets/admin/component-inserter';
        $css = htmlspecialchars($assetBase . '/admin-shell.css?ver=1.2.1', ENT_QUOTES, 'UTF-8');
        $js = htmlspecialchars($assetBase . '/admin-shell.js?ver=1.2.0', ENT_QUOTES, 'UTF-8');

        $componentsBoot = array();
        $extraCss = array();
        $extraJs = array();
        $listHtml = '';
        $panelsHtml = '';

        foreach ($items as $item) {
            $id = htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
            $listHtml .= '<button type="button" class="ci-comp-item" data-ci-comp="' . $id . '">' . $label . '</button>';
            $panelsHtml .= '<div id="ci-panel-' . $id . '" class="ci-panel" data-ci-panel="' . $id . '" hidden>'
                . (isset($item['panelHtml']) ? $item['panelHtml'] : '')
                . '</div>';
            $componentsBoot[$item['id']] = isset($item['boot']) && is_array($item['boot']) ? $item['boot'] : array();
            if (!empty($item['css']) && is_array($item['css'])) {
                foreach ($item['css'] as $href) {
                    $extraCss[] = $href;
                }
            }
            if (!empty($item['js']) && is_array($item['js'])) {
                foreach ($item['js'] as $src) {
                    $extraJs[] = $src;
                }
            }
        }

        $defaultId = !empty($items[0]['id']) ? $items[0]['id'] : '';
        $boot = array(
            'primary' => self::PRIMARY,
            'defaultComponent' => $defaultId,
            'components' => $componentsBoot,
        );

        foreach (array_unique($extraCss) as $href) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
        }
        echo '<link rel="stylesheet" href="' . $css . '">';
        echo '<script>window.CI_BOOT=' . json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';

        $emptyHint = empty($items)
            ? '<p class="description" style="padding:12px 16px">暂无已注册组件。请启用 CustomMusicPlayer、AuthorNotice 或 ContributionGraph 等插件。</p>'
            : '';

        $defaultEsc = htmlspecialchars($defaultId, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<div id="ci-inserter-modal" class="ci-modal" hidden>
  <div class="ci-modal-backdrop" data-ci-close></div>
  <div class="ci-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ci-modal-title">
    <div class="ci-modal-header">
      <h3 id="ci-modal-title">组件插入</h3>
      <button type="button" class="ci-modal-close" data-ci-close aria-label="关闭">&times;</button>
    </div>
    <div class="ci-modal-body">
      <aside class="ci-modal-aside">
        <div class="ci-comp-picker">
          <div class="ci-comp-picker-label">选择组件</div>
          <div class="ci-comp-list" role="tablist" aria-label="选择组件">
            {$listHtml}
          </div>
          <input type="hidden" id="ci-component" value="{$defaultEsc}">
          {$emptyHint}
        </div>
        <div class="ci-modal-preview-wrap">
          <div class="ci-preview-label">预览</div>
          <div id="ci-preview" class="ci-preview-stage">
            <p class="ci-preview-empty">编辑右侧参数后，此处显示插入效果。</p>
          </div>
        </div>
      </aside>
      <div class="ci-modal-main">
        {$panelsHtml}
      </div>
    </div>
    <div class="ci-modal-footer">
      <button type="button" class="btn" data-ci-close>取消</button>
      <button type="button" class="btn" id="ci-refresh-preview">刷新预览</button>
      <button type="button" class="btn primary" id="ci-insert-btn">插入短代码</button>
    </div>
  </div>
</div>
HTML;

        echo '<script src="' . $js . '"></script>';
        foreach (array_unique($extraJs) as $src) {
            echo '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
        }
    }

    /**
     * 是否由主题组件插入壳接管后台入口
     */
    public static function isActive()
    {
        return true;
    }
}
