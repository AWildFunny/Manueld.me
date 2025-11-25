<?php
/**
 * 首页自定义模板
 * 用于显示 alert公告、推荐阅读、最新文章
 * 
 * 使用方法：
 * 1. 在 Typecho 后台创建独立页面，slug 设置为 "home"
 * 2. 在"设置" -> "阅读"中，选择"使用 [首页] 页面作为首页"
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 重定向逻辑：如果通过页面路径访问首页，重定向到根路径
// 检查是否是首页页面（通过检查 parameter->type 是否为 page 且 makeSinglePageAsFrontPage 为 true）
// 或者通过检查 frontPage 设置和当前页面的 cid
$frontPageParts = explode(':', $this->options->frontPage);
$frontPageType = $frontPageParts[0];
$frontPageValue = count($frontPageParts) > 1 ? $frontPageParts[1] : '';

// 检查是否是首页页面
$isHomePage = false;
if ($frontPageType == 'page' && !empty($frontPageValue)) {
    // 方法1：检查 makeSinglePageAsFrontPage（当通过根路径访问时，这个会被设置）
    // 方法2：检查当前页面的 cid 是否匹配首页页面的 cid
    if (isset($this->request->cid) && $this->request->cid == intval($frontPageValue)) {
        $isHomePage = true;
    }
}

if ($isHomePage) {
    // 获取当前请求的完整 URL
    $currentUrl = $this->request->getRequestUrl();
    
    // 获取站点根 URL（使用 index 属性，这是最可靠的方法）
    $siteUrl = $this->options->index;
    if (empty($siteUrl)) {
        // 如果 index 为空，尝试使用 siteUrl
        $siteUrl = $this->options->siteUrl ?: '/';
    }
    
    // 解析 URL 获取路径部分
    $currentPath = parse_url($currentUrl, PHP_URL_PATH);
    $rootPath = parse_url($siteUrl, PHP_URL_PATH);
    
    // 规范化路径（移除末尾斜杠，除非是根路径）
    $currentPath = rtrim($currentPath, '/') ?: '/';
    $rootPath = rtrim($rootPath, '/') ?: '/';
    
    // 如果当前路径不是根路径，重定向到根路径
    // 例如：/home.html 或 /page/home/ 应该重定向到 /
    if ($currentPath != $rootPath && $currentPath != '/') {
        // 确保重定向 URL 是有效的字符串
        $redirectUrl = (string)$siteUrl;
        if (empty($redirectUrl) || $redirectUrl === 'null') {
            $redirectUrl = '/';
        }
        
        // 使用 301 永久重定向（SEO 友好）
        $this->response->redirect($redirectUrl, true);
        exit;
    }
}

$this->need('header.php');
?>

<?php
// 1. Alert 公告（使用主题设置中的 notification）
if ($this->options->notification != ''): ?>
    <div class="alert">
        <?php $this->options->notification(); ?>
    </div>
<?php endif; ?>

<?php
// 2. 推荐阅读（分类为"首页推荐"的文章）
// 查找分类 slug 为 "首页推荐" 或 name 为 "首页推荐" 的分类
$db = Typecho_Db::get();
$category = $db->fetchRow($db->select('mid', 'name', 'slug')
    ->from('table.metas')
    ->where('type = ?', 'category')
    ->where('(slug = ? OR name = ?)', '首页推荐', '首页推荐')
    ->limit(1));

if ($category):
    // 使用 Widget 获取该分类下的文章
    Typecho_Widget::widget('Widget_Archive', array(
        'type' => 'category',
        'mid' => $category['mid'],
        'pageSize' => 10
    ))->to($recommended);
    
    if ($recommended->have()): ?>
        <section class="recommended-posts">
            <h2><i class="czs-star"></i> 推荐阅读</h2>
            <div class="posts-list">
                <?php while ($recommended->next()): ?>
                    <article class="post-item">
                        <h3><a href="<?php $recommended->permalink(); ?>"><?php $recommended->title(); ?></a></h3>
                        <p class="post-excerpt"><?php $recommended->excerpt(150, '...'); ?></p>
                        <div class="post-meta">
                            <time datetime="<?php $recommended->date('c'); ?>">
                                <?php $recommended->date('Y-m-d'); ?>
                            </time>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        </section>
        <hr>
    <?php endif;
endif;
?>

<?php
// 3. 最新文章（5篇，不翻页）
Typecho_Widget::widget('Widget_Contents_Post_Recent', 'pageSize=5')->to($recent);

if ($recent->have()): ?>
    <section class="recent-posts">
        <h2><i class="czs-paper"></i> 最新文章</h2>
        <div class="posts-list">
            <?php while ($recent->next()): ?>
                <article class="post-item">
                    <h3><a href="<?php $recent->permalink(); ?>"><?php $recent->title(); ?></a></h3>
                    <p class="post-excerpt"><?php $recent->excerpt(150, '...'); ?></p>
                    <div class="post-meta">
                        <time datetime="<?php $recent->date('c'); ?>">
                            <?php $recent->date('Y-m-d'); ?>
                        </time>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    </section>
<?php endif; ?>

<?php
// 4. 页面自定义内容（如果页面有内容，显示在最后）
if ($this->content): ?>
    <section class="page-content">
        <div class="post-content">
            <?php echo exContent($this->content); ?>
        </div>
    </section>
<?php endif; ?>

<?php $this->need('footer.php'); ?>

