<?php
/**
 * Daydream 是一个简洁轻盈的 Typecho 主题。
 * 
 * @package Daydream
 * @author SkyWT
 * @version 1.0
 * @link https://blog.skywt.cn/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
    $this->need('header.php');
?>

<?php
// 只在archive页面显示筛选组件
// 使用$this->is('archive')更可靠
if ($this->is('archive')):
    // 读取URL参数
    $request = Typecho_Request::getInstance();
    $currentCategory = $request->get('cat', '');
    $currentTags = $request->get('tags', '');
    $currentSearch = $request->get('search', '');
    $currentPage = $request->get('page', 1);
    
    // 处理标签参数（逗号分隔）
    $selectedTags = [];
    if ($currentTags) {
        // Typecho的Request对象可能已经解码，但为了安全还是手动解码一次
        $decodedTags = urldecode($currentTags);
        $selectedTags = array_map('trim', explode(',', $decodedTags));
        $selectedTags = array_filter($selectedTags);
    }
    
    // 处理搜索关键词
    $searchKeyword = $currentSearch ? urldecode($currentSearch) : null;
    
    // 获取分类列表
    $categories = getCategoriesWithIcons();
    
    // 获取全部文章数量
    $db = Typecho_Db::get();
    $totalPosts = $db->fetchObject($db->select('COUNT(*) as cnt')
        ->from('table.contents')
        ->where('type = ?', 'post')
        ->where('status = ?', 'publish'))->cnt;
    
    // 获取当前筛选条件下的标签云
    $tags = getTagsByFilter($currentCategory, $searchKeyword);
    
    // 计算当前筛选结果数量
    $filteredPostCount = $totalPosts;
    if ($currentCategory || !empty($selectedTags) || $searchKeyword) {
        // 如果有筛选条件，需要重新计算
        $db = Typecho_Db::get();
        $countSelect = $db->select('COUNT(DISTINCT table.contents.cid) as cnt')
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish');
        
        // 应用分类筛选
        if ($currentCategory) {
            $category = $db->fetchRow($db->select('mid')
                ->from('table.metas')
                ->where('type = ?', 'category')
                ->where('slug = ?', $currentCategory)
                ->limit(1));
            
            if ($category) {
                $countSelect->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                    ->where('table.relationships.mid = ?', $category['mid']);
            }
        }
        
        // 应用标签筛选
        if (!empty($selectedTags)) {
            $tagMids = [];
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
            
            if (!empty($tagMids)) {
                if (!$currentCategory) {
                    $countSelect->join('table.relationships', 'table.contents.cid = table.relationships.cid');
                }
                $countSelect->where('table.relationships.mid IN ?', $tagMids)
                    ->group('table.contents.cid')
                    ->having('COUNT(DISTINCT table.relationships.mid) = ?', count($tagMids));
            }
        }
        
        // 应用搜索筛选
        if ($searchKeyword) {
            $searchPattern = '%' . $searchKeyword . '%';
            $countSelect->where('(table.contents.title LIKE ? OR table.contents.text LIKE ?)', $searchPattern, $searchPattern);
        }
        
        try {
            $result = $db->fetchObject($countSelect);
            $filteredPostCount = $result ? intval($result->cnt) : 0;
        } catch (Exception $e) {
            $filteredPostCount = $totalPosts;
        }
    }
?>

<!-- 分类筛选组件 -->
<div class="filter-wrapper">
    <div class="filter-layout">
        <!-- 左侧分类侧边栏 -->
        <div class="filter-sidebar">
            <div class="category-tab <?php echo !$currentCategory ? 'active' : ''; ?>" 
                 data-category="" 
                 data-pjax>
                <span class="category-icon">📚</span>
                <span class="category-name">全部</span>
                <span class="category-count">(<?php echo $totalPosts; ?>)</span>
            </div>
            <?php foreach ($categories as $cat): ?>
                <div class="category-tab <?php echo ($currentCategory === $cat['slug']) ? 'active' : ''; ?>" 
                     data-category="<?php echo htmlspecialchars($cat['slug']); ?>" 
                     data-pjax>
                    <span class="category-icon"><?php echo htmlspecialchars($cat['icon']); ?></span>
                    <span class="category-name"><?php echo htmlspecialchars($cat['name']); ?></span>
                    <span class="category-count">(<?php echo $cat['count']; ?>)</span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 右侧主内容区 -->
        <div class="filter-main">
            <!-- 搜索框 -->
            <div class="search-input-wrapper">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input type="text" 
                       class="search-input" 
                       id="filter-search-input"
                       placeholder="在筛选结果中搜索文章标题或内容..." 
                       value="<?php echo htmlspecialchars($searchKeyword ?: ''); ?>">
            </div>
            
            <!-- 关键词云 -->
            <div class="tag-cloud" id="filter-tag-cloud">
                <?php 
                // 根据数量确定标签大小
                if (!empty($tags)) {
                    $maxCount = max(array_column($tags, 'count'));
                    foreach ($tags as $tag): 
                        $isActive = in_array($tag['name'], $selectedTags);
                        // 根据数量确定大小
                        $sizeClass = 'size-small';
                        if ($tag['count'] >= $maxCount * 0.7) {
                            $sizeClass = 'size-large';
                        } elseif ($tag['count'] >= $maxCount * 0.4) {
                            $sizeClass = 'size-medium';
                        }
                ?>
                    <div class="tag-bubble <?php echo $sizeClass . ($isActive ? ' active' : ''); ?>" 
                         data-tag="<?php echo htmlspecialchars($tag['name']); ?>"
                         data-pjax>
                        <span class="tag-text"><?php echo htmlspecialchars($tag['name']); ?></span>
                        <span class="tag-count">(<?php echo $tag['count']; ?>)</span>
                    </div>
                <?php endforeach; 
                } else {
                ?>
                    <div class="tag-cloud-empty">当前没有标签可供筛选~</div>
                <?php } ?>
            </div>
        </div>
    </div>
    
    <!-- 结果统计 - 右下角 -->
    <div class="result-stats">
        <span>找到 <strong class="result-count"><?php echo $filteredPostCount; ?></strong> 篇文章</span>
        <?php if ($currentCategory || !empty($selectedTags) || $searchKeyword): ?>
            <button class="clear-btn" id="filter-clear-btn">清除筛选</button>
        <?php endif; ?>
    </div>
</div>

<?php
endif; // $this->is('archive')

// 初始化筛选变量（如果不在archive页面）
if (!$this->is('archive')) {
    $currentCategory = '';
    $selectedTags = [];
    $searchKeyword = null;
    $hasFilter = false;
} else {
    $hasFilter = ($currentCategory || !empty($selectedTags) || $searchKeyword);
}

// 显示文章列表
// 注意：筛选已在查询层面完成（通过functions.php中的插件钩子），
// 所以这里直接显示查询结果即可，分页也会基于筛选后的结果

$hasPosts = false;
while ($this->next()): 
    $hasPosts = true;
    ?>
    <section itemscope itemtype="http://schema.org/BlogPosting">
        <?php if ($this->fields->headPic !=''): ?>
            <a data-fancybox="gallery" href="<?php $this->fields->headPic(); ?>" data-caption="<?php $this->title(); ?>">
                <img src=<?php $this->fields->headPic();?> class="shadow rounded" alt="<?php $this->title(); ?>" title="<?php $this->title(); ?>">
            </a>
        <?php endif; ?>
        <a itemprop="url" href="<?php $this->permalink();?>">
            <h1 itemprop="name headline"><?php $this->title();?></h1>
        </a>
        <div class="summary" itemprop="articleBody">
    		<?php $this->content('阅读全文...'); ?>
        </div>
    </section>
    <hr>
<?php 
endwhile;

// 如果没有显示任何文章且有筛选条件，显示提示
if ($hasFilter && !$hasPosts):
?>
    <section>
        <p style="text-align: center; color: var(--muted-color); padding: 40px 20px;">
            没有找到符合条件的文章
        </p>
    </section>
<?php endif; ?>

<nav>
    <?php 
    // 修改分页链接，包含筛选参数
    $pageNavParams = array(
        'wrapTag' => 'ul',
        'wrapClass' => '',
        'itemTag' => 'li',
        'currentClass' => 'active',
    );
    
    // 如果有筛选参数，需要在分页链接中添加
    // Typecho的pageNav函数不支持自定义URL，需要通过其他方式实现
    $this->pageNav('&laquo;', '&raquo;', 3, '...', $pageNavParams); 
    ?>
</nav>

<?php $this->need('footer.php'); ?>