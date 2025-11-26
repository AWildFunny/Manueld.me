<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php $this->archiveTitle(array(
            'category'  =>  '分类 %s 下的文章',
            'search'    =>  '包含关键字 %s 的文章',
            'tag'       =>  '标签 %s 下的文章',
            'author'    =>  '%s 发布的文章'
        ), '', ' - '); ?><?php $this->options->title(); ?></title>
    <?php 
    // 获取 logoUrl，如果为空或路径错误（包含 /usr/themes/ 但不包含正确的站点 URL），使用默认路径
    $logoUrlValue = isset($this->options->logoUrl) ? trim($this->options->logoUrl) : '';
    $defaultLogoUrl = $this->options->themeUrl('/assets/avatar.png', $this->options->theme);
    
    // 如果 logoUrl 为空，或者路径中包含 /usr/themes/ 但不在正确的站点 URL 下，使用默认路径
    if (empty($logoUrlValue) || (strpos($logoUrlValue, '/usr/themes/') !== false && strpos($logoUrlValue, $this->options->siteUrl) === false)) {
        $logoUrl = $defaultLogoUrl;
    } else {
        $logoUrl = $logoUrlValue;
    }
    ?>
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logoUrl); ?>">
    <link rel="shortcut icon" href="<?php $this->options->themeUrl('/assets/favicon.ico')?>" />
    <link rel="bookmark" href="<?php $this->options->themeUrl('/assets/favicon.ico')?>" type="image/x-icon"/>

	<!-- Pico.css -->
	<link rel="stylesheet" href="<?php $this->options->themeUrl('/assets/css/pico.min.css');?>">
    <!-- Daydream CSS -->
    <link type="text/css" href="<?php $this->options->themeUrl('/assets/css/style.css')?>" rel="stylesheet">
    <!-- Animate.css -->
    <link type="text/css" href="<?php $this->options->themeUrl('/assets/css/animate.min.css')?>" rel="stylesheet">
    <!-- Fancybox.css -->
    <link type="text/css" href="<?php $this->options->themeUrl('/assets/css/jquery.fancybox.min.css')?>" rel="stylesheet">
    <!-- KaTeX.css -->
    <link type="text/css" href="<?php $this->options->themeUrl('/assets/css/katex.min.css')?>" rel="stylesheet">
    <!-- Highlight.js CSS -->
    <link type="text/css" href="<?php $this->options->themeUrl('/assets/css/atom-one-dark.min.css')?>" rel="stylesheet">
    <!-- Caomei Icons CSS -->
    <link rel="stylesheet" href="<?php $this->options->themeUrl('/assets/css/caomei.css')?>">
    <!-- Google Fonts -->
    <link href="https://fonts.loli.net/css2?family=Noto+Serif+SC:wght@200;300;400;500;600;700;900&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style><?php $this->options->cutsomCSS(); ?></style>

    <!-- jQuery.js -->
    <script src="<?php $this->options->themeUrl('/assets/js/jquery.min.js');?>"></script>
    <!-- jQuery.pjax.js -->
    <script src="<?php $this->options->themeUrl('/assets/js/jquery.pjax.js');?>"></script>
    <!-- Highlight.js -->
    <script src="<?php $this->options->themeUrl('/assets/js/highlight.min.js');?>"></script>
    <!-- Fancybox.js -->
    <script src="<?php $this->options->themeUrl('/assets/js/jquery.fancybox.min.js');?>"></script>
    <!-- KaTeX.js -->
    <script src="<?php $this->options->themeUrl('/assets/js/katex/katex.min.js');?>"></script>
    <script src="<?php $this->options->themeUrl('/assets/js/katex/auto-render.min.js');?>"></script>
    <!-- Category Filter -->
    <script src="<?php $this->options->themeUrl('/assets/js/category-filter.js');?>"></script>

    <?php $this->options->headerCode(); ?>

    <?php $this->header(); ?>
</head>
<!--[if lt IE 8]>
    当前网页不支持你正在使用的浏览器。为了正常访问, 请升级你的浏览器！
<![endif]-->
<body>

<div class="header-navbar-wrapper container">
    <header class="site-header">
        <?php 
        // 获取 logoUrl，如果为空或路径错误（包含 /usr/themes/ 但不包含正确的站点 URL），使用默认路径
        $logoUrlValue = isset($this->options->logoUrl) ? trim($this->options->logoUrl) : '';
        $defaultLogoUrl = $this->options->themeUrl('/assets/avatar.png', $this->options->theme);
        
        // 如果 logoUrl 为空，或者路径中包含 /usr/themes/ 但不在正确的站点 URL 下，使用默认路径
        if (empty($logoUrlValue) || (strpos($logoUrlValue, '/usr/themes/') !== false && strpos($logoUrlValue, $this->options->siteUrl) === false)) {
            $logoUrl = $defaultLogoUrl;
        } else {
            $logoUrl = $logoUrlValue;
        }
        ?>
        <img class="headpic shadow" src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php $this->options->title() ?>" width=128 height=128>
        <hgroup>
            <h1><?php $this->options->title()?></h1>
            <h4><?php $this->options->description()?></h4>
        </hgroup>
    </header>

    <nav class="navbar">
        <div class="navbar-container">
        <?php 
        // 解析 frontPage 设置
        $frontPageParts = explode(':', $this->options->frontPage);
        $frontPageType = $frontPageParts[0];
        $frontPageValue = count($frontPageParts) > 1 ? $frontPageParts[1] : '';
        $isPageHomepage = ($frontPageType == 'page');
        $isFileHomepage = ($frontPageType == 'file');
        
        // 获取首页页面链接（如果首页是页面）
        $homePageUrl = '';
        if ($isPageHomepage && !empty($frontPageValue)) {
            // 使用 Widget 获取页面信息，这样可以正确生成 permalink
            Typecho_Widget::widget('Widget_Contents_Page_List')->to($homePageList);
            while ($homePageList->next()) {
                if ($homePageList->cid == intval($frontPageValue)) {
                    $homePageUrl = $homePageList->permalink;
                    break;
                }
            }
            // 如果 Widget 中没有找到（因为被排除了），直接查询数据库并使用 Router
            if (empty($homePageUrl)) {
                $db = Typecho_Db::get();
                $homePage = $db->fetchRow($db->select('slug')
                    ->from('table.contents')
                    ->where('cid = ?', intval($frontPageValue))
                    ->where('type = ?', 'page')
                    ->where('status = ?', 'publish')
                    ->limit(1));
                if ($homePage) {
                    // 使用 Typecho_Router 生成页面链接
                    $homePageUrl = Typecho_Router::url('page', array('slug' => $homePage['slug']), $this->options->index);
                }
            }
        }
        
        // 先获取所有页面列表，用于查找"关于"页面和渲染导航栏
        $this->widget('Widget_Contents_Page_List')->to($pagelist);
        
        // 查找"关于"页面的链接
        $aboutPageUrl = '';
        $firstPageUrl = '';
        $pageCount = 0;
        
        // 遍历页面列表，查找"关于"页面，同时记录第一个页面
        while ($pagelist->next()) {
            $pageCount++;
            // 记录第一个页面作为回退
            if ($pageCount == 1) {
                $firstPageUrl = $pagelist->permalink;
            }
            // 查找"关于"页面
            if (empty($aboutPageUrl) && (stripos($pagelist->title, '关于') !== false || stripos($pagelist->slug, 'about') !== false)) {
                $aboutPageUrl = $pagelist->permalink;
            }
        }
        
        // 如果没有找到"关于"页面，使用第一个页面或首页
        if (empty($aboutPageUrl)) {
            $aboutPageUrl = !empty($firstPageUrl) ? $firstPageUrl : ($homePageUrl ? $homePageUrl : $this->options->siteUrl());
        }
        
        // 重置页面列表迭代器，重新开始遍历以渲染导航栏
        $this->widget('Widget_Contents_Page_List')->to($pagelist);
        ?>
        <a href="<?php echo $aboutPageUrl; ?>" class="navbar-avatar-link" title="关于">
            <img class="navbar-avatar" src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php $this->options->title() ?>">
        </a>
        <ul>
            <?php if ($this->options->realHomepage): ?>
                <li><a class="<?php echo ($this->is('index'))?'active':'';?>" href="<?php $this->options->realHomepage();?>"><i class="czs-home"></i> 首页 </a></li>
            <?php endif; ?>
            <?php if ($isFileHomepage || $isPageHomepage): ?>
                <!-- 自定义文件首页或页面首页：显示"首页"和"文章"两个链接 -->
                <?php
                // 判断当前是否是首页页面
                $isHomePageActive = false;
                if ($isPageHomepage) {
                    // 检查是否是首页页面：通过 is('index') 或 is('page') 且 cid 匹配
                    if ($this->is('index') || ($this->is('page') && isset($this->request->cid) && $this->request->cid == intval($frontPageValue))) {
                        $isHomePageActive = true;
                    }
                } else {
                    // 文件首页：检查是否是 index
                    $isHomePageActive = $this->is('index');
                }
                ?>
                <li>
                    <a class="<?php echo $isHomePageActive ? 'active' : '';?>" 
                       href="<?php echo $isPageHomepage && $homePageUrl ? $homePageUrl : $this->options->siteUrl();?>">
                        <i class="czs-home"></i> 首页 
                    </a>
                </li>
                <li>
                    <a class="<?php echo ($this->is('archive') || $this->is('post') || $this->is('category') || $this->is('tag'))?'active':'';?>" 
                       href="<?php echo Typecho_Common::url($this->options->routingTable['archive']['url'], $this->options->index); ?>">
                        <i class="czs-book"></i> 文章 
                    </a>
                </li>
            <?php else: ?>
                <!-- 默认首页（recent）：只显示"博客"链接 -->
                <li>
                    <a class="<?php echo ($this->is('index'))?'active':'';?>" href="<?php $this->options->siteUrl();?>"><i class="czs-paper"></i> 博客 </a>
                </li>
            <?php endif; ?>
            <?php while ($pagelist->next()): ?>
                <li>
                    <a class="<?php echo ($this->is('page', $pagelist->slug))?'active':'';?>" href="<?php echo $pagelist->permalink; ?>">
                        <?php if ($pagelist->fields->pageIcon != ''): ?>
                            <i class="<?php echo $pagelist->fields->pageIcon; ?>"></i>
                        <?php endif; ?>
                        <?php echo $pagelist->title; ?>
                    </a>
                </li>
            <?php endwhile;?>
        </ul>
        </div>
    </nav>
</div>

<!-- Navbar sticky behavior with avatar -->
<script>
    (function() {
        const navbarEl = document.querySelector('.navbar');
        const sentinelEl = document.querySelector('.site-header');
        
        if (!navbarEl || !sentinelEl) return;
        
        const handler = (entries) => {
            if (!entries[0].isIntersecting) {
                // Header 不可见时，navbar 固定在顶部，显示小头像
                navbarEl.classList.add('navbar-fixed');
                navbarEl.classList.add('shadow');
                document.body.classList.add('navbar-fixed-active');
            } else {
                // Header 可见时，navbar 恢复原样，隐藏小头像
                navbarEl.classList.remove('navbar-fixed');
                navbarEl.classList.remove('shadow');
                document.body.classList.remove('navbar-fixed-active');
            }
        };
        
        const observer = new window.IntersectionObserver(handler, {
            rootMargin: '-1px 0px 0px 0px',
            threshold: 0
        });
        
        observer.observe(sentinelEl);
        
        // 支持 pjax 重新初始化
        $(document).on('pjax:complete', function() {
            const newNavbarEl = document.querySelector('.navbar');
            const newSentinelEl = document.querySelector('.site-header');
            if (newNavbarEl && newSentinelEl) {
                observer.disconnect();
                observer.observe(newSentinelEl);
            }
        });
    })();
    
    // 更新导航链接的 active 状态 - 重新设计的可靠方案
    (function() {
        let lastUrl = window.location.href;
        
        // 规范化 URL，提取路径部分
        function getPath(url) {
            try {
                // 如果是完整 URL
                if (url.indexOf('://') > -1) {
                    const urlObj = new URL(url);
                    return urlObj.pathname.replace(/\/$/, '') || '/';
                }
                // 如果是相对路径
                const path = url.split('?')[0].split('#')[0];
                return path.replace(/\/$/, '') || '/';
            } catch (e) {
                // 如果解析失败，尝试简单提取
                const path = url.split('?')[0].split('#')[0];
                return path.replace(/\/$/, '') || '/';
            }
        }
        
        // 比较两个路径是否匹配
        function pathsMatch(path1, path2) {
            const p1 = getPath(path1);
            const p2 = getPath(path2);
            
            // 完全匹配
            if (p1 === p2) return true;
            
            // 处理首页情况
            if ((p1 === '/' || p1 === '/index.php') && (p2 === '/' || p2 === '/index.php')) {
                return true;
            }
            
            // 提取 slug 进行比较（用于独立页面）
            const getSlug = (path) => {
                const htmlMatch = path.match(/\/([^\/]+)\.html$/);
                if (htmlMatch) {
                    try {
                        return decodeURIComponent(htmlMatch[1]);
                    } catch (e) {
                        return htmlMatch[1];
                    }
                }
                const parts = path.split('/').filter(p => p && p !== 'index.php' && p !== '');
                return parts.length > 0 ? parts[parts.length - 1] : null;
            };
            
            const slug1 = getSlug(p1);
            const slug2 = getSlug(p2);
            if (slug1 && slug2 && slug1 === slug2) {
                return true;
            }
            
            return false;
        }
        
        // 更新导航高亮
        function updateNavbarActive() {
            const currentUrl = window.location.href;
            const currentPath = getPath(currentUrl);
            const navLinks = document.querySelectorAll('.navbar a:not(.navbar-avatar-link)');
            
            // 移除所有 active 类
            navLinks.forEach(link => {
                link.classList.remove('active');
            });
            
            // 获取首页页面的 URL（从 PHP 传递）
            const homePageUrl = <?php echo json_encode($isPageHomepage && $homePageUrl ? $homePageUrl : ''); ?>;
            const isPageHomepage = <?php echo $isPageHomepage ? 'true' : 'false'; ?>;
            
            // 判断当前页面类型
            const isIndex = currentPath === '/' || currentPath === '/index.php' || 
                           currentPath.match(/^\/myblog\/?$/i) || currentPath.match(/^\/myblog\/index\.php$/i);
            const isHomePage = isPageHomepage && (isIndex || (homePageUrl && pathsMatch(currentUrl, homePageUrl)));
            const isArchive = /\/archives\/\d+/.test(currentPath) || currentPath.includes('/archives/');
            const isCategory = currentPath.includes('/category/');
            const isTag = currentPath.includes('/tag/');
            const isBlogArchive = currentPath.includes('/blog');
            
            // 遍历所有导航链接，找到匹配的
            navLinks.forEach(link => {
                const href = link.getAttribute('href') || '';
                const linkText = link.textContent.trim();
                
                // 方法1：路径匹配
                if (pathsMatch(currentUrl, href)) {
                    link.classList.add('active');
                    return;
                }
                
                // 方法2：根据页面类型匹配
                if (isHomePage || isIndex) {
                    // 首页：匹配包含"首页"的链接，或者链接指向首页
                    if (linkText.includes('首页')) {
                        link.classList.add('active');
                        return;
                    }
                    // 如果是根路径且没有"首页"链接，匹配"博客"链接（向后兼容）
                    if (isIndex && !isPageHomepage && (linkText.includes('博客') || 
                        pathsMatch(href, '/') || pathsMatch(href, '/index.php'))) {
                        link.classList.add('active');
                        return;
                    }
                } else if (isArchive || isCategory || isTag || isBlogArchive) {
                    // 文章相关页面：匹配包含"文章"的链接，或者链接指向文章列表
                    if (linkText.includes('文章') || 
                        href.includes('/archives') || href.includes('/blog')) {
                        link.classList.add('active');
                        return;
                    }
                }
            });
        }
        
        // 立即执行一次
        updateNavbarActive();
        
        // 使用轮询 + 事件监听的双重保障
        let checkInterval = null;
        
        function startUrlCheck() {
            // 清除旧的定时器
            if (checkInterval) {
                clearInterval(checkInterval);
            }
            
            // 每 100ms 检查一次 URL 是否变化（持续 2 秒）
            let checkCount = 0;
            const maxChecks = 20; // 2秒 = 20 * 100ms
            
            checkInterval = setInterval(function() {
                const currentUrl = window.location.href;
                if (currentUrl !== lastUrl) {
                    lastUrl = currentUrl;
                    updateNavbarActive();
                    checkCount = maxChecks; // 找到变化后停止检查
                }
                checkCount++;
                if (checkCount >= maxChecks) {
                    clearInterval(checkInterval);
                    checkInterval = null;
                }
            }, 100);
        }
        
        // 监听所有可能的 pjax 事件
        $(document).on('pjax:send pjax:success pjax:complete pjax:end', function() {
            startUrlCheck();
        });
        
        // 监听浏览器前进/后退
        window.addEventListener('popstate', function() {
            setTimeout(function() {
                lastUrl = window.location.href;
                updateNavbarActive();
            }, 50);
        });
        
        // 监听所有链接点击（包括非 pjax 链接）
        $(document).on('click', 'a', function() {
            const href = this.getAttribute('href');
            if (href && href.indexOf('#') !== 0) {
                setTimeout(function() {
                    updateNavbarActive();
                }, 100);
            }
        });
    })();
</script>

<main class="container" id="pjax-container">