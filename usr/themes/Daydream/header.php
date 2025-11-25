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
            $aboutPageUrl = !empty($firstPageUrl) ? $firstPageUrl : $this->options->siteUrl();
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
            <?php if (strpos($this->options->frontPage, 'file') !== FALSE):?>
                <li>
                    <a class="<?php echo ($this->is('index'))?'active':'';?>" href="<?php $this->options->siteUrl();?>"><i class="czs-home"></i> 首页 </a>
                </li>
                <li>
                    <a class="<?php echo ($this->is('archive') || $this->is('post') || $this->is('category') || $this->is('tag'))?'active':'';?>" href="<?php echo $this->options->siteUrl.$this->options->routingTable['archive']['url']; ?>"><i class="czs-book"></i> 文章 </a>
                </li>
            <?php else: ?>
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
    
    // 更新导航链接的 active 状态
    (function() {
        function normalizeUrl(url) {
            // 移除协议、域名和查询参数，只保留路径
            try {
                const urlObj = new URL(url, window.location.origin);
                return urlObj.pathname.replace(/\/$/, '') || '/';
            } catch (e) {
                // 如果是相对路径
                return url.split('?')[0].split('#')[0].replace(/\/$/, '') || '/';
            }
        }
        
        function updateNavbarActive() {
            const currentPath = normalizeUrl(window.location.href);
            const navLinks = document.querySelectorAll('.navbar a:not(.navbar-avatar-link)');
            
            // 移除所有 active 类
            navLinks.forEach(link => {
                link.classList.remove('active');
            });
            
            // 获取站点基础 URL
            const siteUrl = '<?php echo rtrim($this->options->siteUrl(), "/"); ?>';
            const sitePath = normalizeUrl(siteUrl);
            
            // 判断当前页面类型
            const isIndex = currentPath === '/' || currentPath === sitePath || 
                           currentPath === '/index.php' || currentPath === (sitePath + '/index.php');
            const isArchive = currentPath.includes('/archives/') || 
                             currentPath.match(/\/archives\/\d+/); // 文章详情页
            const isCategory = currentPath.includes('/category/');
            const isTag = currentPath.includes('/tag/');
            const isBlogArchive = currentPath.includes('/blog/') || currentPath.includes('/blog');
            
            // 应用高亮
            navLinks.forEach(link => {
                const href = link.getAttribute('href') || '';
                const linkPath = normalizeUrl(href);
                const linkText = link.textContent.trim();
                
                // 高亮首页/博客链接
                if (isIndex) {
                    if (linkPath === '/' || linkPath === sitePath || 
                        linkPath === '/index.php' || linkPath === (sitePath + '/index.php') ||
                        linkText.includes('首页') || linkText.includes('博客')) {
                        link.classList.add('active');
                    }
                }
                // 高亮文章链接（包括 archive、post、category、tag）
                else if (isArchive || isCategory || isTag || isBlogArchive) {
                    if (linkText.includes('文章') || 
                        linkPath.includes('/archives') || 
                        linkPath.includes('/blog/') ||
                        linkPath.includes('/blog')) {
                        link.classList.add('active');
                    }
                }
                // 高亮独立页面（通过比较 URL 路径）
                else {
                    // 提取当前页面的 slug（从 .html 或路径中）
                    const currentSlug = currentPath.match(/\/([^\/]+)\.html$/) ? 
                                       currentPath.match(/\/([^\/]+)\.html$/)[1] :
                                       currentPath.split('/').pop();
                    
                    // 提取链接的 slug
                    const linkSlug = linkPath.match(/\/([^\/]+)\.html$/) ? 
                                    linkPath.match(/\/([^\/]+)\.html$/)[1] :
                                    linkPath.split('/').pop();
                    
                    // 如果 slug 匹配，高亮该链接
                    if (currentSlug && linkSlug && currentSlug === linkSlug) {
                        link.classList.add('active');
                    }
                }
            });
        }
        
        // 页面加载时执行
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateNavbarActive);
        } else {
            updateNavbarActive();
        }
        
        // pjax 加载完成后执行
        $(document).on('pjax:complete', function() {
            setTimeout(updateNavbarActive, 50);
        });
    })();
</script>

<main class="container" id="pjax-container">