<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

define('__TYPECHO_GRAVATAR_PREFIX__', 'https://gravatar.loli.net/avatar/');

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

function themeFields($layout) {
    $headPic = new Typecho_Widget_Helper_Form_Element_Text('headPic', NULL, NULL, '文章头图地址', '仅对文章有效。在这里填入一个图片 URL 地址, 就可以让文章加上头图。留空则不显示头图。');
    $layout->addItem($headPic);

    $pubPlace = new Typecho_Widget_Helper_Form_Element_Text('pubPlace', NULL, NULL, '文章发布地点', '仅对文章有效。在这里输入一个地点的名字，文章头部会显示。留空则不显示发布地点。');
    $layout->addItem($pubPlace);

    $pageIcon = new Typecho_Widget_Helper_Form_Element_Text('pageIcon', NULL, NULL, '页面图标', '仅对非隐藏的页面有效。在这里为页面填入一个草莓图标库的代码，在菜单栏链接前会显示图标。草莓图标库是 2.0.0 Free 版本，参见<a href="https://chuangzaoshi.com/icon/" target="_blank">草莓图标库</a>。留空则不显示图标。');
    $layout->addItem($pageIcon);

    $linkTo = new Typecho_Widget_Helper_Form_Element_Text('linkTo', NULL, NULL, '重定向至', '在这里输入一个 URL，打开该页面或文章时会自动重定向到这个 URL，可以用于定制菜单栏。留空则不重定向。');
    $layout->addItem($linkTo);
}

function exContent($content){

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

    // 文章 TOC 功能
    if (preg_match_all('/<h(\d)>(.*)<\/h\d>/isU', $content, $outarr)){
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
    // 筛选逻辑在index.php中处理
}

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
        $icon = $categories->description ?: '📁'; // 从描述字段读取图标，默认为📁
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