<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

define('__TYPECHO_GRAVATAR_PREFIX__', 'https://gravatar.loli.net/avatar/');

/**
 * Daydream 内置「组件插入」壳：写文章/页面侧栏默认开启，各业务插件通过
 * Typecho_Plugin::factory('ComponentInserter')->collect 注册。
 */
require_once __DIR__ . '/include/ComponentInserter/Registry.php';
require_once __DIR__ . '/include/ComponentInserter/Shell.php';
Typecho_Plugin::factory('admin/write-post.php')->option = array('Daydream_ComponentInserter', 'adminOption');
Typecho_Plugin::factory('admin/write-post.php')->bottom = array('Daydream_ComponentInserter', 'adminBottom');
Typecho_Plugin::factory('admin/write-page.php')->option = array('Daydream_ComponentInserter', 'adminOption');
Typecho_Plugin::factory('admin/write-page.php')->bottom = array('Daydream_ComponentInserter', 'adminBottom');

function themeConfig($form) {
    echo '<h2>Sky 主题设置</h2>';

    $logoUrl = new Typecho_Widget_Helper_Form_Element_Text('logoUrl', NULL, Helper::options()->themeUrl.'/assets/avatar.png', '站点 LOGO 地址', '在这里填入一个图片 URL 地址, 该图片会作为网站的 LOGO 显示在网站头部。');
    $form->addInput($logoUrl);

    $realHomepage = new Typecho_Widget_Helper_Form_Element_Text('realHomepage', NULL, NULL, '全站首页', '填入的链接会在导航栏首位显示为「首页」，适合将博客作为自己网站的一个子站点的情况。留空则不显示。');
    $form->addInput($realHomepage);

    $icpInfo = new Typecho_Widget_Helper_Form_Element_Text('icpInfo', NULL, NULL, 'ICP 备案号', '显示在底部，留空则不显示。');
    $form->addInput($icpInfo->addRule('xssCheck', '请不要使用特殊字符'));

    $nisInfo = new Typecho_Widget_Helper_Form_Element_Text('nisInfo', NULL, NULL, '网安备案号', '显示在底部（带国徽），留空则不显示。');
    $form->addInput($nisInfo->addRule('xssCheck', '请不要使用特殊字符'));

    $notification = new Typecho_Widget_Helper_Form_Element_Text('notification', NULL, NULL, '网站公告', '显示在首页，留空则不显示。');
    $form->addInput($notification);

    $oldPosts = new Typecho_Widget_Helper_Form_Element_Text('oldPosts', NULL, '365', '文章有效期', '单位：天。在此天数之前发布的文章将会显示「这是一篇旧文」的提示。留空则不显示。');
    $form->addInput($oldPosts);

    $commentsNotice = new Typecho_Widget_Helper_Form_Element_Text('commentsNotice', NULL, NULL, '评论区公告', '显示在评论区，留空则不显示。');
    $form->addInput($commentsNotice);

    $headerCode = new Typecho_Widget_Helper_Form_Element_Textarea('headerCode', NULL, NULL, '头部代码', '在头部添加的 HTML 代码，可以插入 JavsScript。');
    $form->addInput($headerCode);

    $footerCode = new Typecho_Widget_Helper_Form_Element_Textarea('footerCode', NULL, NULL, '页脚代码', '在页脚添加的 HTML 代码，可以插入 JavsScript。');
    $form->addInput($footerCode);

    $cunstomCSS = new Typecho_Widget_Helper_Form_Element_Textarea('cunstomCSS', NULL, NULL, '自定义 CSS', '加入自定义的 CSS 代码。');
    $form->addInput($cunstomCSS);
}

/**
 * 在后台footer中添加分类隐藏功能的JavaScript
 * 注意：主题的functions.php可能不会在后台页面加载，所以需要在admin/category.php中直接引用脚本
 */

function themeFields($layout) {
    $headPic = new Typecho_Widget_Helper_Form_Element_Text('headPic', NULL, NULL, '文章头图地址', '仅对文章有效。在这里填入一个图片 URL 地址, 就可以让文章加上头图。留空则不显示头图。');
    $layout->addItem($headPic);

    $pubPlace = new Typecho_Widget_Helper_Form_Element_Text('pubPlace', NULL, NULL, '文章发布地点', '仅对文章有效。在这里输入一个地点的名字，文章头部会显示。留空则不显示发布地点。');
    $layout->addItem($pubPlace);

    $pageIcon = new Typecho_Widget_Helper_Form_Element_Text('pageIcon', NULL, NULL, '页面图标', '仅对非隐藏的页面有效。在这里为页面填入一个草莓图标库的代码，在菜单栏链接前会显示图标。草莓图标库是 2.0.0 Free 版本，参见<a href="https://chuangzaoshi.com/icon/" target="_blank">草莓图标库</a>。留空则不显示图标。');
    $layout->addItem($pageIcon);

    $linkTo = new Typecho_Widget_Helper_Form_Element_Text('linkTo', NULL, NULL, '重定向至', '在这里输入一个 URL，打开该页面或文章时会自动重定向到这个 URL，可以用于定制菜单栏。留空则不重定向。');
    $layout->addItem($linkTo);

    $articleTemplate = new Typecho_Widget_Helper_Form_Element_Select(
        'articleTemplate',
        array(
            'default' => '默认文章',
            'music-album' => '音乐相册'
        ),
        'default',
        '文章模板',
        '选择「音乐相册」时，文章将以章节形式展示：每个 ## 标题为一章，并显示 sticky 章节目录。'
    );
    $layout->addItem($articleTemplate);
}

function exContent($content, $skipToc = false){

    // 文章内短代码
    $pattern = '/\[(info)\](.*?)\[\s*\/\1\s*\]/';
    $replacement = '
    <div class="alert" role="alert">$2</div>';
    $content = preg_replace($pattern, $replacement, $content);

    // 折叠内容功能 [fold title="标题"]内容[/fold]
    // 支持换行格式，使用 s 修饰符使 . 匹配换行符
    $fold_pattern = '/\[fold\s+title=["\']([^"\']+)["\']\](.*?)\[\/fold\]/is';
    $content = preg_replace_callback($fold_pattern, function($matches) {
        $title = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $fold_content = $matches[2];
        // 生成唯一 ID 用于 JavaScript 控制
        $fold_id = 'fold-' . uniqid();
        return '<div class="fold-container" data-fold-id="' . $fold_id . '">
            <div class="fold-header">
                <span class="fold-icon">></span>
                <span class="fold-title">' . $title . '</span>
            </div>
            <div class="fold-content">' . $fold_content . '</div>
        </div>';
    }, $content);

    // 文章 TOC 功能（音乐相册模板由 exContentAlbum 单独处理章节导航）
    if (!$skipToc && preg_match_all('/<h(\d)>(.*)<\/h\d>/isU', $content, $outarr)){
        $toc_out = "";
        $minlevel = 6;
        for ($key=0; $key<count($outarr[2]); $key++) $minlevel = min($minlevel, $outarr[1][$key]);

        $curlevel = $minlevel-1;
        for ($key=0; $key<count($outarr[2]); $key++) {
            $ta = $content;
            $tb = strpos($ta, $outarr[0][$key]);
            $level = $outarr[1][$key];
            // $content = substr($ta, 0, $tb). "<h{$level} id=\"toc_title{$key}\">{$outarr[2][$key]}</h{$level}>". substr($ta, strlen($outarr[0][$key])+$tb);
            $content = substr($ta, 0, $tb). "<a id=\"toc_title{$key}\" style=\"position:relative; top:-50px\"></a>". substr($ta, $tb);
            // 用伪锚点实现链接偏移。Safari 居然不支持！！
            if ($level > $curlevel) $toc_out.=str_repeat("<ol>\n", $level-$curlevel);
            elseif ($level < $curlevel) $toc_out.=str_repeat("</ol>\n", $curlevel-$level);
            $curlevel = $level;
            $toc_out .= "<li><a href=\"#toc_title{$key}\">{$outarr[2][$key]}</a></li>\n";
        }
        
        $content = "<div id=\"tableOfContents\">{$toc_out}</div>". $content;
    }

    // Fancybox 图片灯箱
    $content = preg_replace("/<img src=\"([^\"]*)\" alt=\"([^\"]*)\" title=\"([^\"]*)\">/i", "<a data-fancybox=\"gallery\" href=\"\\1\" data-caption=\"\\3\"><img src=\"\\1\" alt=\"\\2\" title=\"\\3\"></a>", $content);

    return $content;
}

/**
 * 将 album-shot 预设/自定义旋钮规范为 pos / titlepos / wrap
 *
 * @param string $layout
 * @param array $attrs
 * @return array{0:string,1:string,2:string,3:bool} [layout, pos, titlepos, wrap]
 */
function musicAlbumNormalizeShot($layout, $attrs) {
    $layout = strtolower(trim((string) $layout));
    $pos = isset($attrs['pos']) ? strtolower(trim((string) $attrs['pos'])) : '';
    $titlepos = isset($attrs['titlepos']) ? strtolower(trim((string) $attrs['titlepos'])) : '';
    if ($titlepos === '' && isset($attrs['heading'])) {
        $titlepos = strtolower(trim((string) $attrs['heading']));
    }
    $wrapRaw = isset($attrs['wrap']) ? strtolower(trim((string) $attrs['wrap'])) : '';
    $wrap = in_array($wrapRaw, array('1', 'true', 'yes', 'on'), true);

    if ($layout === '' || $layout === 'auto') {
        return array('auto', 'top', 'above', false);
    }
    if ($layout === 'banner') {
        return array('banner', 'top', 'above', false);
    }
    if ($layout === 'overlay') {
        return array('overlay', 'bg', 'on', false);
    }
    if ($layout === 'split-left' || $layout === 'splitleft') {
        return array('split-left', 'left', 'beside', false);
    }
    if ($layout === 'split-right' || $layout === 'splitright') {
        return array('split-right', 'right', 'beside', false);
    }
    if ($layout === 'float') {
        $pos = ($pos === 'right') ? 'right' : 'left';
        return array('float', $pos, 'above', true);
    }

    if (!in_array($pos, array('top', 'left', 'right', 'bg'), true)) {
        $pos = 'top';
    }
    if (!in_array($titlepos, array('above', 'on', 'beside', 'below'), true)) {
        $titlepos = 'above';
    }
    return array('custom', $pos, $titlepos, $wrap);
}

/**
 * 从 figure 标签读取 data-* 属性
 *
 * @param string $figureHtml
 * @return array<string,string>
 */
function musicAlbumReadShotAttrs($figureHtml) {
    $attrs = array();
    if (preg_match_all('/\bdata-([a-z]+)\s*=\s*"([^"]*)"/i', $figureHtml, $m, PREG_SET_ORDER)) {
        foreach ($m as $item) {
            $attrs[strtolower($item[1])] = html_entity_decode($item[2], ENT_QUOTES, 'UTF-8');
        }
    }
    return $attrs;
}

/**
 * 将章节内 album-shot 或首张图片提升为章头视觉，并包一层正文
 *
 * @param string $chapterHtml
 * @return array{0:string,1:bool,2:string,3:string,4:string,5:bool} html, hasMedia, layout, pos, titlepos, wrap
 */
function musicAlbumEnhanceChapterMedia($chapterHtml) {
    if (!preg_match('/^<h2\b[^>]*>.*?<\/h2>/is', $chapterHtml, $h2Match)) {
        return array($chapterHtml, false, '', 'top', 'above', false);
    }

    $h2 = $h2Match[0];
    $rest = substr($chapterHtml, strlen($h2));
    $figure = '';
    $layout = 'auto';
    $attrs = array();

        if (preg_match('/(?:<p>\s*)?(<div\b[^>]*\balbum-board\b[\s\S]*?<\/div>\s*<\/div>)(?:\s*<\/p>)?/is', $rest, $boardMatch, PREG_OFFSET_CAPTURE)) {
            $figure = $boardMatch[1][0];
            $full = $boardMatch[0][0];
            $offset = $boardMatch[0][1];
            $rest = substr($rest, 0, $offset) . substr($rest, $offset + strlen($full));
            $assembled = $h2 . "\n" . $figure . '<div class="music-album-chapter-body">' . $rest . '</div>';
            return array($assembled, true, 'board', 'top', 'above', false);
        }

        if (preg_match('/(?:<p>\s*)?(<figure\b[^>]*\balbum-shot\b[^>]*>.*?<\/figure>)(?:\s*<\/p>)?/is', $rest, $shotMatch, PREG_OFFSET_CAPTURE)) {
        $figure = $shotMatch[1][0];
        $full = $shotMatch[0][0];
        $offset = $shotMatch[0][1];
        $rest = substr($rest, 0, $offset) . substr($rest, $offset + strlen($full));
        $attrs = musicAlbumReadShotAttrs($figure);
        $layout = isset($attrs['layout']) ? $attrs['layout'] : 'auto';
        if (stripos($figure, 'music-album-chapter-media') === false) {
            if (preg_match('/<figure[^>]*\bclass="/i', $figure)) {
                $figure = preg_replace('/(<figure[^>]*\bclass=")([^"]*)(")/i', '$1$2 music-album-chapter-media$3', $figure, 1);
            } else {
                $figure = preg_replace('/<figure\b/i', '<figure class="music-album-chapter-media"', $figure, 1);
            }
        }
    } elseif (preg_match('/(?:<p>\s*)?(<a[^>]*\bdata-fancybox\b[^>]*>\s*<img\b[^>]*>\s*<\/a>)(?:\s*<\/p>)?/is', $rest, $imgMatch, PREG_OFFSET_CAPTURE)) {
        $imgHtml = $imgMatch[1][0];
        $full = $imgMatch[0][0];
        $offset = $imgMatch[0][1];
        $rest = substr($rest, 0, $offset) . substr($rest, $offset + strlen($full));
        $figure = '<figure class="album-shot music-album-chapter-media" data-layout="auto">' . $imgHtml . '</figure>';
        $layout = 'auto';
    } else {
        $chapterHtml = $h2 . '<div class="music-album-chapter-body">' . $rest . '</div>';
        return array($chapterHtml, false, '', 'top', 'above', false);
    }

    list($layout, $pos, $titlepos, $wrap) = musicAlbumNormalizeShot($layout, $attrs);

    if ($wrap) {
        $assembled = $h2 . '<div class="music-album-chapter-body">' . $figure . $rest . '</div>';
    } else {
        $assembled = $h2 . "\n" . $figure . '<div class="music-album-chapter-body">' . $rest . '</div>';
    }

    return array($assembled, true, $layout, $pos, $titlepos, $wrap);
}

/**
 * 音乐相册文章内容：按 h2 分章 + sticky 章节目录结构
 */
function exContentAlbum($content) {
    $content = exContent($content, true);

    if (!preg_match('/<h2[\s>]/i', $content)) {
        return '<div class="music-album-body">' . $content . '</div>';
    }

    $parts = preg_split('/(?=<h2[\s>])/i', $content, -1, PREG_SPLIT_NO_EMPTY);
    $navItems = '';
    $sections = '';
    $index = 0;

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        if (preg_match('/^<h2(\s[^>]*)?>(.*?)<\/h2>/is', $part, $match)) {
            $title = strip_tags($match[2]);
            $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $chapterId = 'music-album-ch-' . $index;
            $num = $index + 1;

            $part = preg_replace(
                '/^<h2(\s[^>]*)?>/i',
                '<h2 id="' . $chapterId . '-title"$1>',
                $part,
                1
            );

            list($part, $hasMedia, $shotLayout, $shotPos, $shotTitlepos, $shotWrap) = musicAlbumEnhanceChapterMedia($part);
            $chapterClass = 'music-album-chapter';
            $extraAttrs = '';
            if ($hasMedia) {
                $chapterClass .= ' has-media';
                if ($shotLayout === 'board') {
                    $chapterClass .= ' has-board';
                } else {
                    $chapterClass .= ' shot-pos-' . htmlspecialchars($shotPos, ENT_QUOTES, 'UTF-8');
                    $chapterClass .= ' shot-title-' . htmlspecialchars($shotTitlepos, ENT_QUOTES, 'UTF-8');
                    if ($shotWrap) {
                        $chapterClass .= ' shot-wrap';
                    }
                    if ($shotLayout === 'auto') {
                        $chapterClass .= ' shot-auto';
                    }
                }
                $extraAttrs .= ' data-layout="' . htmlspecialchars($shotLayout, ENT_QUOTES, 'UTF-8') . '"';
            }

            $navItems .= '<li><a class="music-album-nav-link" href="#' . $chapterId . '" data-chapter="' . $index . '">'
                . '<span class="music-album-nav-index">' . $num . '</span>'
                . '<span class="music-album-nav-title">' . $titleEsc . '</span>'
                . '</a></li>';
            $sections .= '<section class="' . $chapterClass . '" id="' . $chapterId . '" data-chapter="' . $index . '"'
                . $extraAttrs . ' aria-labelledby="' . $chapterId . '-title">' . $part . '</section>';
            $index++;
        } else {
            $sections .= '<div class="music-album-intro">' . $part . '</div>';
        }
    }

    if ($index === 0) {
        return '<div class="music-album-body">' . $content . '</div>';
    }

    $nav = '<nav class="music-album-nav' . ($index > 10 ? ' is-dense' : '') . '" aria-label="章节导航" data-total="' . $index . '">';
    $nav .= '<p class="music-album-nav-now" data-ma-now hidden></p>';
    $nav .= '<button type="button" class="music-album-nav-toggle" aria-expanded="false" aria-controls="music-album-nav-panel" title="章节">';
    $nav .= '<span class="music-album-nav-toggle-text">章节</span>';
    $nav .= '</button>';
    $nav .= '<div class="music-album-nav-panel" id="music-album-nav-panel">';
    $nav .= '<div class="music-album-nav-head">';
    $nav .= '<span class="music-album-nav-progress-text" data-ma-progress>1 / ' . $index . '</span>';
    $nav .= '</div>';
    $nav .= '<ol class="music-album-nav-list">' . $navItems . '</ol>';
    $nav .= '</div></nav>';

    return '<div class="music-album-layout">' . $nav . '<div class="music-album-body">' . $sections . '</div></div>';
}

// 来自插件 WordsCounter
// https://github.com/elatisy/Typecho_WordsCounter
function allOfCharacters() {
    $chars = 0;
    $db = Typecho_Db::get();
    $select = $db ->select('text')
                  ->from('table.contents')
                  ->where('table.contents.status = ?','publish');
    $rows = $db->fetchAll($select);
    foreach ($rows as $row){
        $chars += mb_strlen($row['text'], 'UTF-8');
    }
    $unit = '';
    if ($chars >= 10000) {
        $chars /= 10000;
        $unit = 'W';
    } else if($chars >= 1000) {
        $chars /= 1000;
        $unit = 'K';
    }
    $out = sprintf('%.2lf%s',$chars, $unit);
    echo $out;
}

// 来自插件 IPLocation
function showLocation($ip) {
    require_once 'include/IP/IP.php';
    $addresses = IP::find($ip);
    $address = '';
    if ($addresses==='N/A'){
        $address = '';
    } else if (!empty($addresses)) {
        $addresses = array_unique($addresses);
        $address = implode('', $addresses);
        $address = str_replace('中国', '', $address);
    }
    echo $address;
}

// 来自插件 UserAgent
function getUAImg($type, $name, $title) {
    global $url_img;
    $img = "<img nogallery class='icon-ua' src='" . $url_img . $type . $name . ".svg' title='" . $title . "' alt='" . $title . "' height=16px style='vertical-align:-2px;' />";
    return $img;
}

function showUserAgent($ua) {
    global $url_img;
    // 使用 Typecho_Common::url() 获取正确的主题URL（包含网站根目录）
    $options = Helper::options();
    $url_img = Typecho_Common::url('/include/UserAgent/img/', $options->themeUrl);

    /* OS */
    require_once 'include/UserAgent/get_os.php';
    $Os = get_os($ua);
    $OsImg = getUAImg("os/", $Os['code'], $Os['title']);

    /* Browser */
    require_once 'include/UserAgent/get_browser_name.php';
    $Browser = get_browser_name($ua);
    $BrowserImg = getUAImg("browser/", $Browser['code'], $Browser['title']);

    echo "&nbsp;" . $OsImg . "&nbsp;" . $BrowserImg;
}

/**
 * 分类筛选组件辅助函数
 */

/**
 * Theme初始化函数，用于修改Archive查询
 * 注意：Typecho的themeInit函数在Archive Widget初始化后调用
 * 此时查询已经执行，所以这里主要用于其他初始化工作
 */
function themeInit($archive) {
    // 可以在这里添加其他初始化逻辑
}

/**
 * 在查询层应用筛选条件（分类、标签、搜索）
 * 使用 query 钩子，它在 functions.php 加载后、查询执行前调用，可以修改查询对象
 * 注意：需要在查询执行前修改 $select，同时也要修改 $archive->countSql
 */
Typecho_Plugin::factory('Widget_Archive')->query = function($archive, $select) {
    // 读取URL参数
    $request = Typecho_Request::getInstance();
    $currentCategory = $request->get('cat', '');
    $currentTags = $request->get('tags', '');
    $currentSearch = $request->get('search', '');
    
    // 如果没有筛选条件，需要手动执行默认查询
    // 因为 query 钩子已注册，$queryPlugged 会被设置为 true，阻止默认查询执行
    if (!$currentCategory && !$currentTags && !$currentSearch) {
        // 手动执行默认查询并推送结果，确保"全部"选项能正常显示文章
        try {
            $db = Typecho_Db::get();
            $db->fetchAll($select, [$archive, 'push']);
            // 返回 true 表示已经处理了查询，阻止默认查询执行
            return true;
        } catch (Exception $e) {
            // 如果查询失败，返回 null 让默认查询执行（虽然可能不会执行）
            return null;
        }
    }
    
    // 排除single类型页面（文章详情页、独立页面等不应该应用筛选）
    $archiveType = $archive->parameter->type ?? '';
    $singleTypes = ['single', 'page', 'post', 'attachment', 'comment_page'];
    if (in_array($archiveType, $singleTypes)) {
        return;
    }
    
    // 检查是否有cid参数（single页面通常有cid参数）
    if ($request->get('cid')) {
        return;
    }
    
    // 404页面也不应该应用筛选
    if ($archiveType === '404') {
        return;
    }
    
    $db = Typecho_Db::get();
    $categoryMid = null;
    $tagMids = [];
    
    // 获取分类mid
    if ($currentCategory) {
        $category = $db->fetchRow($db->select('mid')
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->where('slug = ?', $currentCategory)
            ->limit(1));
        
        if ($category) {
            $categoryMid = $category['mid'];
        }
    }
    
    // 获取标签mids
    if ($currentTags) {
        // 处理标签参数（逗号分隔）
        $decodedTags = urldecode($currentTags);
        $selectedTags = array_map('trim', explode(',', $decodedTags));
        $selectedTags = array_filter($selectedTags);
        
        if (!empty($selectedTags)) {
            foreach ($selectedTags as $tagName) {
                $tag = $db->fetchRow($db->select('mid')
                    ->from('table.metas')
                    ->where('type = ?', 'tag')
                    ->where('name = ?', $tagName)
                    ->limit(1));
                if ($tag) {
                    $tagMids[] = $tag['mid'];
                }
            }
        }
    }
    
    // 如果同时有分类和标签筛选，先获取分类下的文章ID
    $categoryPostIds = null;
    if ($categoryMid !== null && !empty($tagMids)) {
        $categoryPosts = $db->fetchAll($db->select('table.contents.cid')
            ->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid = ?', $categoryMid)
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish'));
        
        if (!empty($categoryPosts)) {
            $categoryPostIds = array_column($categoryPosts, 'cid');
        } else {
            // 分类下没有文章，直接返回空结果
            $select->where('1 = 0');
            // 设置countSql也为空结果
            $countSql = clone $select;
            $archive->setCountSql($countSql);
            return;
        }
    }
    
    // 获取符合条件的文章ID（用于主查询）
    // 对于标签筛选，需要先获取同时拥有所有指定标签的文章ID
    $mainFilteredPostIds = null;
    
    if (!empty($tagMids)) {
        // 对于标签筛选，需要先获取同时拥有所有指定标签的文章ID
        $tagPostIds = $db->fetchAll($db->select('table.relationships.cid')
            ->from('table.relationships')
            ->where('table.relationships.mid IN ?', $tagMids)
            ->group('table.relationships.cid')
            ->having('COUNT(DISTINCT table.relationships.mid) = ?', count($tagMids)));
        
        if (!empty($tagPostIds)) {
            $tagPostIdArray = array_column($tagPostIds, 'cid');
            
            if ($categoryPostIds !== null) {
                // 同时有分类和标签，取交集
                $mainFilteredPostIds = array_intersect($categoryPostIds, $tagPostIdArray);
            } else {
                // 只有标签筛选
                $mainFilteredPostIds = $tagPostIdArray;
            }
        } else {
            // 没有符合条件的文章
            $mainFilteredPostIds = [];
        }
    } else if ($categoryPostIds !== null) {
        // 只有分类筛选
        $mainFilteredPostIds = $categoryPostIds;
    }
    
    // 应用筛选条件到主查询
    if ($mainFilteredPostIds !== null) {
        if (empty($mainFilteredPostIds)) {
            // 没有符合条件的文章，设置空结果
            $select->where('1 = 0');
        } else {
            // 使用IN查询，避免JOIN和GROUP BY
            $select->where('table.contents.cid IN ?', $mainFilteredPostIds);
        }
    } else {
        // 没有标签筛选，只有分类筛选
        if ($categoryMid !== null) {
            // 先获取分类下的文章ID，然后使用IN查询（避免JOIN冲突）
            $categoryPosts = $db->fetchAll($db->select('table.contents.cid')
                ->from('table.contents')
                ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                ->where('table.relationships.mid = ?', $categoryMid)
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish'));
            
            if (!empty($categoryPosts)) {
                $categoryPostIds = array_column($categoryPosts, 'cid');
                // 应用筛选条件到主查询
                $select->where('table.contents.cid IN ?', $categoryPostIds);
            } else {
                // 分类下没有文章，设置空结果
                $select->where('1 = 0');
            }
        }
    }
    
    // 应用搜索筛选（搜索筛选总是需要应用到主查询）
    if ($currentSearch) {
        $searchKeyword = urldecode($currentSearch);
        $searchPattern = '%' . $searchKeyword . '%';
        $select->where('(table.contents.title LIKE ? OR table.contents.text LIKE ?)', $searchPattern, $searchPattern);
    }
    
    // 构建专门用于计数的查询（不使用GROUP BY）
    // Typecho的size()方法会使用COUNT(DISTINCT table.contents.cid)，所以我们需要确保countSql包含正确的JOIN和WHERE条件
    // 但对于多标签AND逻辑，我们需要先获取符合条件的文章ID，然后使用IN查询
    
    // 获取符合条件的文章ID（用于计数查询）
    // 注意：搜索筛选需要在计数查询中应用，因为搜索是基于标题和内容的，不能预先获取ID
    $filteredPostIds = null;
    
    if (!empty($tagMids)) {
        // 对于标签筛选，需要先获取同时拥有所有指定标签的文章ID
        $tagPostIds = $db->fetchAll($db->select('table.relationships.cid')
            ->from('table.relationships')
            ->where('table.relationships.mid IN ?', $tagMids)
            ->group('table.relationships.cid')
            ->having('COUNT(DISTINCT table.relationships.mid) = ?', count($tagMids)));
        
        if (!empty($tagPostIds)) {
            $tagPostIdArray = array_column($tagPostIds, 'cid');
            
            if ($categoryPostIds !== null) {
                // 同时有分类和标签，取交集
                $filteredPostIds = array_intersect($categoryPostIds, $tagPostIdArray);
            } else {
                // 只有标签筛选
                $filteredPostIds = $tagPostIdArray;
            }
        } else {
            // 没有符合条件的文章
            $filteredPostIds = [];
        }
    } else if ($categoryPostIds !== null) {
        // 只有分类筛选
        $filteredPostIds = $categoryPostIds;
    }
    
    // 构建计数查询
    $countSelect = $archive->select()
        ->where('table.contents.type = ?', 'post')
        ->where('table.contents.status = ?', 'publish');
    
    // 如果有筛选后的文章ID（分类+标签），直接使用IN查询（避免JOIN和GROUP BY）
    if ($filteredPostIds !== null) {
        if (empty($filteredPostIds)) {
            // 没有符合条件的文章，设置空结果
            $countSelect->where('1 = 0');
        } else {
            $countSelect->where('table.contents.cid IN ?', $filteredPostIds);
        }
    } else {
        // 没有标签筛选，只有分类或搜索筛选
        if ($categoryMid !== null) {
            // 先获取分类下的文章ID，然后使用IN查询（与主查询保持一致）
            $categoryPosts = $db->fetchAll($db->select('table.contents.cid')
                ->from('table.contents')
                ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                ->where('table.relationships.mid = ?', $categoryMid)
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish'));
            
            if (!empty($categoryPosts)) {
                $categoryPostIds = array_column($categoryPosts, 'cid');
                $countSelect->where('table.contents.cid IN ?', $categoryPostIds);
            } else {
                // 分类下没有文章，设置空结果
                $countSelect->where('1 = 0');
            }
        }
    }
    
    // 应用搜索筛选到计数查询（搜索筛选总是需要应用到计数查询）
    if ($currentSearch) {
        $searchKeyword = urldecode($currentSearch);
        $searchPattern = '%' . $searchKeyword . '%';
        $countSelect->where('(table.contents.title LIKE ? OR table.contents.text LIKE ?)', $searchPattern, $searchPattern);
    }
    
    // 设置countSql，确保分页计算正确
    $archive->setCountSql($countSelect);
    
    // 手动执行查询并推送结果
    // 因为 Typecho 的 query 钩子机制，如果钩子返回非空值，默认查询不会执行
    // 我们需要手动执行查询并推送结果到 Archive 对象
    try {
        $db->fetchAll($select, [$archive, 'push']);
        // 返回 true 表示已经处理了查询，阻止默认查询执行
        return true;
    } catch (Exception $e) {
        // 如果手动查询失败，返回 null 让默认查询执行
        return null;
    }
};

/**
 * 判断是否为archive页面
 * 注意：此函数需要在Archive Widget上下文中调用
 * @param object|null $archive Archive Widget对象，如果为null则从全局获取
 * @return bool
 */
function isArchivePage($archive = null) {
    // 如果传入了Archive对象，直接使用
    if ($archive && method_exists($archive, 'is')) {
        return $archive->is('archive');
    }
    
    // 否则通过路径判断
    $request = Typecho_Request::getInstance();
    $pathInfo = $request->getPathInfo();
    $options = Helper::options();
    
    // 获取archive路由URL
    $archiveUrl = $options->routingTable['archive']['url'] ?? '/blog/';
    $archivePath = parse_url($archiveUrl, PHP_URL_PATH);
    if (!$archivePath) {
        $archivePath = '/blog/';
    }
    
    // 规范化路径
    $currentPath = rtrim($pathInfo, '/') ?: '/';
    $archivePath = rtrim($archivePath, '/') ?: '/';
    
    // 检查是否匹配archive路径
    if ($currentPath === $archivePath) {
        return true;
    }
    
    // 检查是否以archive路径开头（处理分页等情况）
    if (strpos($currentPath, $archivePath) === 0) {
        return true;
    }
    
    return false;
}

/**
 * 获取所有分类及图标
 * @return array
 */
function getCategoriesWithIcons() {
    $categories = \Widget\Metas\Category\Rows::alloc();
    $categories->execute();
    $result = [];
    
    while ($categories->next()) {
        // 检查是否隐藏（description以__HIDDEN__开头）
        $description = $categories->description ?: '';
        $isHidden = (strpos($description, '__HIDDEN__') === 0);
        
        // 如果隐藏，跳过此分类
        if ($isHidden) {
            continue;
        }
        
        // 从描述字段读取图标，默认为📁
        // 注意：如果description以__HIDDEN__开头，已经被过滤掉了，所以这里直接使用
        $icon = $description ?: '📁';
        
        $result[] = [
            'mid' => $categories->mid,
            'name' => $categories->name,
            'slug' => $categories->slug,
            'permalink' => $categories->permalink,
            'icon' => $icon,
            'count' => getCategoryPostCount($categories->mid)
        ];
    }
    
    return $result;
}

/**
 * 获取分类文章数量
 * @param int $mid 分类ID
 * @return int
 */
function getCategoryPostCount($mid) {
    $db = Typecho_Db::get();
    $count = $db->fetchObject($db->select('COUNT(DISTINCT table.contents.cid) as cnt')
        ->from('table.contents')
        ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
        ->where('table.relationships.mid = ?', $mid)
        ->where('table.contents.type = ?', 'post')
        ->where('table.contents.status = ?', 'publish'))->cnt;
    return intval($count);
}

/**
 * 根据筛选条件获取标签云数据
 * @param string|null $categorySlug 分类slug
 * @param string|null $searchKeyword 搜索关键词
 * @return array
 */
function getTagsByFilter($categorySlug = null, $searchKeyword = null) {
    $db = Typecho_Db::get();
    
    // 构建查询
    $select = $db->select('table.metas.mid', 'table.metas.name', 'table.metas.slug')
        ->from('table.metas')
        ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
        ->join('table.contents', 'table.relationships.cid = table.contents.cid')
        ->where('table.metas.type = ?', 'tag')
        ->where('table.contents.type = ?', 'post')
        ->where('table.contents.status = ?', 'publish')
        ->group('table.metas.mid');
    
    // 如果指定了分类
    if ($categorySlug) {
        $category = $db->fetchRow($db->select('mid')
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->where('slug = ?', $categorySlug)
            ->limit(1));
        
        if ($category) {
            // 获取该分类下的文章ID
            $postIds = $db->fetchAll($db->select('table.contents.cid')
                ->from('table.contents')
                ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                ->where('table.relationships.mid = ?', $category['mid'])
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish'));
            
            if (!empty($postIds)) {
                $postIdArray = array_column($postIds, 'cid');
                $select->where('table.contents.cid IN (' . implode(',', array_map('intval', $postIdArray)) . ')');
            } else {
                // 如果没有文章，返回空数组
                return [];
            }
        } else {
            // 分类不存在，返回空数组
            return [];
        }
    }
    
    // 保存原始搜索关键词（用于后续计算）
    $originalSearchKeyword = $searchKeyword;
    
    // 如果指定了搜索关键词
    if ($searchKeyword) {
        $searchPattern = '%' . $searchKeyword . '%';
        $select->where('(table.contents.title LIKE ? OR table.contents.text LIKE ?)', $searchPattern, $searchPattern);
    }
    
    // 获取标签及数量
    $tags = $db->fetchAll($select);
    $result = [];
    
    foreach ($tags as $tag) {
        // 计算每个标签的文章数量（应用相同的筛选条件）
        $countSelect = $db->select('COUNT(DISTINCT table.contents.cid) as cnt')
            ->from('table.contents')
            ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
            ->where('table.relationships.mid = ?', $tag['mid'])
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish');
        
        // 应用分类筛选条件
        if ($categorySlug && isset($category) && isset($postIdArray) && !empty($postIdArray)) {
            $countSelect->where('table.contents.cid IN (' . implode(',', array_map('intval', $postIdArray)) . ')');
        }
        
        // 应用搜索筛选条件
        if ($originalSearchKeyword) {
            $searchPattern = '%' . $originalSearchKeyword . '%';
            $countSelect->where('(table.contents.title LIKE ? OR table.contents.text LIKE ?)', $searchPattern, $searchPattern);
        }
        
        try {
            $countResult = $db->fetchObject($countSelect);
            $count = $countResult ? intval($countResult->cnt) : 0;
            
            if ($count > 0) {
                $result[] = [
                    'mid' => $tag['mid'],
                    'name' => $tag['name'],
                    'slug' => $tag['slug'],
                    'count' => $count
                ];
            }
        } catch (Exception $e) {
            // 查询失败，跳过此标签
            continue;
        }
    }
    
    // 按数量排序
    usort($result, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    return $result;
}

/**
 * 生成筛选URL
 * @param string|null $category 分类slug
 * @param array $tags 标签数组
 * @param string|null $search 搜索关键词
 * @param int|null $page 页码
 * @return string
 */
function getFilterUrl($category = null, $tags = [], $search = null, $page = null) {
    $params = [];
    
    if ($category) {
        $params['cat'] = $category;
    }
    
    if (!empty($tags)) {
        $params['tags'] = implode(',', array_map('urlencode', $tags));
    }
    
    if ($search) {
        $params['search'] = urlencode($search);
    }
    
    if ($page && $page > 1) {
        $params['page'] = $page;
    }
    
    $options = Helper::options();
    $archiveUrl = Typecho_Common::url($options->routingTable['archive']['url'], $options->index);
    
    if (!empty($params)) {
        return $archiveUrl . '?' . http_build_query($params);
    }
    
    return $archiveUrl;
}