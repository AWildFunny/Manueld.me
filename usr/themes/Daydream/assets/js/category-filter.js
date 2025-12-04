/**
 * 分类筛选组件 JavaScript
 * 实现分类筛选、标签筛选、搜索功能和Pjax集成
 */

(function($) {
    'use strict';
    
    // 获取当前URL参数
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            cat: params.get('cat') ? decodeURIComponent(params.get('cat')) : '',
            tags: params.get('tags') ? decodeURIComponent(params.get('tags')) : '',
            search: params.get('search') ? decodeURIComponent(params.get('search')) : '',
            page: params.get('page') || '1'
        };
    }
    
    // 构建筛选URL
    function buildFilterUrl(category, tags, search, page) {
        const params = new URLSearchParams();
        
        if (category) {
            params.set('cat', category);
        }
        
        if (tags && tags.length > 0) {
            params.set('tags', tags.map(t => encodeURIComponent(t)).join(','));
        }
        
        if (search) {
            params.set('search', encodeURIComponent(search));
        }
        
        if (page && page > 1) {
            params.set('page', page);
        }
        
        // 获取archive URL - 从页面中的链接或当前URL推断
        let baseUrl = '/blog/';
        const $archiveLink = $('a[href*="/blog"]').first();
        if ($archiveLink.length) {
            const href = $archiveLink.attr('href');
            if (href) {
                baseUrl = href.split('?')[0];
            }
        } else {
            // 从当前URL推断
            const currentPath = window.location.pathname;
            if (currentPath.includes('/blog')) {
                baseUrl = currentPath.split('?')[0];
            }
        }
        
        // 确保baseUrl以/结尾
        if (!baseUrl.endsWith('/')) {
            baseUrl += '/';
        }
        
        const queryString = params.toString();
        return queryString ? baseUrl + '?' + queryString : baseUrl;
    }
    
    // 更新URL并触发Pjax
    function updateFilter(category, tags, search, page) {
        const url = buildFilterUrl(category, tags, search, page);
        
        // 使用Pjax加载新页面
        if ($.support.pjax) {
            $.pjax({
                url: url,
                container: '#pjax-container',
                timeout: 5000,
                scrollTo: false
            });
        } else {
            window.location.href = url;
        }
    }
    
    // 根据屏幕大小获取标签显示阈值
    function getTagThreshold() {
        const width = window.innerWidth;
        // 移动端 (< 576px): 8个标签
        // 平板 (576px - 992px): 15个标签
        // 桌面 (> 992px): 25个标签
        if (width < 576) {
            return 8;
        } else if (width < 992) {
            return 15;
        } else {
            return 25;
        }
    }
    
    // 初始化标签折叠功能
    function initTagCollapse() {
        const $tagCloud = $('#filter-tag-cloud');
        if (!$tagCloud.length) {
            return;
        }
        
        const $tags = $tagCloud.find('.tag-bubble');
        const totalTags = $tags.length;
        const threshold = getTagThreshold();
        
        // 如果标签数量不超过阈值，不需要折叠
        if (totalTags <= threshold) {
            return;
        }
        
        // 移除之前的"显示全部"按钮
        $tagCloud.find('.tag-show-more').remove();
        
        // 隐藏超出阈值的标签
        $tags.each(function(index) {
            if (index >= threshold) {
                $(this).hide();
            } else {
                $(this).show();
            }
        });
        
        // 添加"显示全部"按钮
        const $showMore = $('<span class="tag-show-more">点击显示全部标签</span>');
        $tagCloud.append($showMore);
        
        // 点击"显示全部"按钮
        $showMore.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // 显示所有标签
            $tags.show();
            
            // 移除"显示全部"按钮
            $(this).remove();
        });
    }
    
    // 初始化筛选组件
    function initCategoryFilter() {
        const $wrapper = $('.filter-wrapper');
        if (!$wrapper.length) {
            return; // 不在archive页面，不初始化
        }
        
        const params = getUrlParams();
        let currentCategory = params.cat;
        let currentTags = params.tags ? params.tags.split(',').map(t => decodeURIComponent(t.trim())).filter(t => t) : [];
        let currentSearch = params.search ? decodeURIComponent(params.search) : '';
        
        // 初始化标签折叠
        initTagCollapse();
        
        // 窗口大小改变时重新初始化标签折叠
        let resizeTimeout;
        $(window).on('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                initTagCollapse();
            }, 300);
        });
        
        // 分类点击事件
        $wrapper.on('click', '.category-tab', function(e) {
            e.preventDefault();
            const $tab = $(this);
            const category = $tab.data('category') || '';
            
            // 更新当前分类
            currentCategory = category;
            
            // 切换分类时清除已选择的标签
            currentTags = [];
            
            // 更新UI
            $wrapper.find('.category-tab').removeClass('active');
            $tab.addClass('active');
            $wrapper.find('.tag-bubble').removeClass('active');
            
            // 更新URL（清除标签参数）
            updateFilter(currentCategory, [], currentSearch, 1);
        });
        
        // 标签点击事件（多选）
        $wrapper.on('click', '.tag-bubble', function(e) {
            e.preventDefault();
            const $tag = $(this);
            const tagName = $tag.data('tag');
            
            // 切换标签选中状态
            const index = currentTags.indexOf(tagName);
            if (index > -1) {
                currentTags.splice(index, 1);
                $tag.removeClass('active');
            } else {
                currentTags.push(tagName);
                $tag.addClass('active');
            }
            
            // 更新URL
            updateFilter(currentCategory, currentTags, currentSearch, 1);
        });
        
        // 搜索输入事件（防抖）
        let searchTimeout;
        const $searchInput = $('#filter-search-input');
        if ($searchInput.length) {
            $searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                const $input = $(this);
                
                searchTimeout = setTimeout(function() {
                    currentSearch = $input.val().trim();
                    updateFilter(currentCategory, currentTags, currentSearch, 1);
                }, 500); // 500ms防抖
            });
            
            // 回车立即搜索
            $searchInput.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    clearTimeout(searchTimeout);
                    currentSearch = $(this).val().trim();
                    updateFilter(currentCategory, currentTags, currentSearch, 1);
                }
            });
        }
        
        // 清除筛选按钮
        $('#filter-clear-btn').on('click', function(e) {
            e.preventDefault();
            
            // 重置所有筛选条件
            currentCategory = '';
            currentTags = [];
            currentSearch = '';
            
            // 更新UI
            $wrapper.find('.category-tab').removeClass('active');
            $wrapper.find('.category-tab[data-category=""]').addClass('active');
            $wrapper.find('.tag-bubble').removeClass('active');
            $searchInput.val('');
            
            // 更新URL
            updateFilter('', [], '', 1);
        });
        
        // 阻止筛选链接的默认Pjax行为（我们手动处理）
        $wrapper.find('[data-pjax]').on('click', function(e) {
            e.preventDefault();
        });
    }
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        initCategoryFilter();
    });
    
    // Pjax完成后重新初始化
    $(document).on('pjax:complete', function() {
        initCategoryFilter();
        updatePaginationLinks();
        // 重新初始化标签折叠（因为Pjax会重新加载内容）
        setTimeout(function() {
            initTagCollapse();
        }, 100);
    });
    
    // 更新分页链接，添加筛选参数
    function updatePaginationLinks() {
        const params = getUrlParams();
        const $paginationLinks = $('nav a[href*="page"]');
        
        $paginationLinks.each(function() {
            const $link = $(this);
            const href = $link.attr('href');
            if (!href) return;
            
            try {
                const url = new URL(href, window.location.origin);
                
                // 添加筛选参数
                if (params.cat) {
                    url.searchParams.set('cat', params.cat);
                }
                if (params.tags) {
                    url.searchParams.set('tags', params.tags);
                }
                if (params.search) {
                    url.searchParams.set('search', params.search);
                }
                
                // 更新链接
                $link.attr('href', url.pathname + url.search);
            } catch (e) {
                // URL解析失败，跳过
            }
        });
    }
    
    // 页面加载完成后更新分页链接
    $(document).ready(function() {
        updatePaginationLinks();
    });
    
})(jQuery);
