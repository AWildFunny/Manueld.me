<?php
/**
 * 贡献统计图表插件
 * 实现类似 GitHub 个人页面的贡献统计热力图
 * 
 * @package ContributionGraph
 * @version 1.0.0
 * @link http://your-website.com
 * @dependence 9.9.2-*
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class ContributionGraph_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法, 如果激活失败, 直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // 创建数据库表
        self::createTable();
        
        // 初始化历史贡献数据（可选）
        self::initHistoryContributions();
        
        // 注册钩子监听文章发布/修改
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('ContributionGraph_Plugin', 'recordContribution');
        
        // 注册钩子监听页面发布/修改
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array('ContributionGraph_Plugin', 'recordContribution');
        
        // 注册钩子监听评论发布
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('ContributionGraph_Plugin', 'recordContribution');
        
        // 解析短代码
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('ContributionGraph_Plugin', 'parse');
        
        // 在页面头部输出CSS
        Typecho_Plugin::factory('Widget_Archive')->header = array('ContributionGraph_Plugin', 'header');
        
        // 在页面底部输出JavaScript
        Typecho_Plugin::factory('Widget_Archive')->footer = array('ContributionGraph_Plugin', 'footer');
        
        return _t('贡献统计图表插件已激活');
    }
    
    /**
     * 禁用插件方法, 如果禁用失败, 直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        // 可选：禁用时删除数据表
        // self::dropTable();
    }
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 是否追踪文章发布
        $trackPostPublish = new Typecho_Widget_Helper_Form_Element_Radio(
            'trackPostPublish',
            array('1' => '是', '0' => '否'),
            '1',
            _t('追踪文章发布'),
            _t('是否记录文章发布操作作为贡献')
        );
        $form->addInput($trackPostPublish);
        
        // 是否追踪文章修改
        $trackPostModify = new Typecho_Widget_Helper_Form_Element_Radio(
            'trackPostModify',
            array('1' => '是', '0' => '否'),
            '1',
            _t('追踪文章修改'),
            _t('是否记录文章修改操作作为贡献')
        );
        $form->addInput($trackPostModify);
        
        // 是否追踪页面发布/修改
        $trackPage = new Typecho_Widget_Helper_Form_Element_Radio(
            'trackPage',
            array('1' => '是', '0' => '否'),
            '1',
            _t('追踪页面发布/修改'),
            _t('是否记录页面发布/修改操作作为贡献')
        );
        $form->addInput($trackPage);
        
        // 是否追踪评论
        $trackComment = new Typecho_Widget_Helper_Form_Element_Radio(
            'trackComment',
            array('1' => '是', '0' => '否'),
            '0',
            _t('追踪评论'),
            _t('是否记录评论发布操作作为贡献')
        );
        $form->addInput($trackComment);
        
        // 默认显示年份
        $defaultYear = new Typecho_Widget_Helper_Form_Element_Text(
            'defaultYear',
            null,
            date('Y'),
            _t('默认显示年份'),
            _t('短代码中未指定年份时使用的默认年份')
        );
        $form->addInput($defaultYear);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 创建数据库表
     * 
     * @access private
     * @return void
     */
    private static function createTable()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}contributions` (
            `date` DATE NOT NULL PRIMARY KEY,
            `count` INT UNSIGNED DEFAULT 1,
            INDEX `idx_date` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $db->query($sql);
        } catch (Exception $e) {
            throw new Typecho_Plugin_Exception('创建贡献统计表失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 删除数据库表（可选）
     * 
     * @access private
     * @return void
     */
    private static function dropTable()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $db->query("DROP TABLE IF EXISTS `{$prefix}contributions`");
    }
    
    /**
     * 初始化历史贡献数据
     * 从现有数据库记录中统计历史贡献
     * 
     * @access private
     * @return void
     */
    private static function initHistoryContributions()
    {
        $db = Typecho_Db::get();
        
        // 检查是否已经初始化过（通过检查表中是否有数据）
        $existing = $db->fetchRow($db->select('COUNT(*) as cnt')
            ->from('table.contributions')
            ->limit(1));
        
        if ($existing && $existing['cnt'] > 0) {
            // 已经有数据，跳过初始化
            return;
        }
        
        // 尝试获取插件配置，如果不存在则使用默认值
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $pluginOptions = $options->plugin('ContributionGraph');
            $trackPostPublish = !empty($pluginOptions->trackPostPublish);
            $trackPostModify = !empty($pluginOptions->trackPostModify);
            $trackPage = !empty($pluginOptions->trackPage);
            $trackComment = !empty($pluginOptions->trackComment);
        } catch (Exception $e) {
            // 如果配置不存在，使用默认值（全部启用）
            $trackPostPublish = true;
            $trackPostModify = true;
            $trackPage = true;
            $trackComment = false;
        }
        
        $contributions = array();
        
        // 统计文章发布（如果启用）
        if ($trackPostPublish) {
            $posts = $db->fetchAll($db->select('created')
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish'));
            
            foreach ($posts as $post) {
                if ($post['created'] > 0) {
                    $date = date('Y-m-d', $post['created']);
                    if (!isset($contributions[$date])) {
                        $contributions[$date] = 0;
                    }
                    $contributions[$date]++;
                }
            }
        }
        
        // 统计文章修改（如果启用）
        if ($trackPostModify) {
            $posts = $db->fetchAll($db->select('modified', 'created')
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish'));
            
            foreach ($posts as $post) {
                if ($post['modified'] > 0 && $post['modified'] != $post['created']) {
                    $date = date('Y-m-d', $post['modified']);
                    if (!isset($contributions[$date])) {
                        $contributions[$date] = 0;
                    }
                    $contributions[$date]++;
                }
            }
        }
        
        // 统计页面发布/修改（如果启用）
        if ($trackPage) {
            $pages = $db->fetchAll($db->select('created', 'modified')
                ->from('table.contents')
                ->where('type = ?', 'page')
                ->where('status = ?', 'publish'));
            
            foreach ($pages as $page) {
                if ($page['created'] > 0) {
                    $date = date('Y-m-d', $page['created']);
                    if (!isset($contributions[$date])) {
                        $contributions[$date] = 0;
                    }
                    $contributions[$date]++;
                }
                
                if ($page['modified'] > 0 && $page['modified'] != $page['created']) {
                    $date = date('Y-m-d', $page['modified']);
                    if (!isset($contributions[$date])) {
                        $contributions[$date] = 0;
                    }
                    $contributions[$date]++;
                }
            }
        }
        
        // 统计评论（如果启用）
        if ($trackComment) {
            $comments = $db->fetchAll($db->select('created')
                ->from('table.comments')
                ->where('status = ?', 'approved'));
            
            foreach ($comments as $comment) {
                if ($comment['created'] > 0) {
                    $date = date('Y-m-d', $comment['created']);
                    if (!isset($contributions[$date])) {
                        $contributions[$date] = 0;
                    }
                    $contributions[$date]++;
                }
            }
        }
        
        // 批量插入贡献数据
        foreach ($contributions as $date => $count) {
            try {
                $db->query($db->insert('table.contributions')
                    ->rows(array(
                        'date' => $date,
                        'count' => $count
                    )));
            } catch (Exception $e) {
                // 如果日期已存在，则更新计数
                $db->query($db->update('table.contributions')
                    ->rows(array('count' => new Typecho_Db_Expr('count + ' . $count)))
                    ->where('date = ?', $date));
            }
        }
    }
    
    /**
     * 记录贡献
     * 
     * @access public
     * @param mixed $content 内容对象（对于finishComment钩子，此参数为null）
     * @param mixed $widget 组件对象
     * @return void
     */
    public static function recordContribution($content, $widget)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('ContributionGraph');
        
        // 检查是否应该追踪此操作
        $shouldTrack = false;
        $timestamp = $options->time;
        
        // 处理评论钩子（finishComment只接收一个参数）
        if ($content === null && $widget instanceof Widget_Feedback) {
            // 评论操作
            if (!empty($pluginOptions->trackComment)) {
                $shouldTrack = true;
                // 从评论对象获取创建时间
                if (isset($widget->row['created']) && $widget->row['created'] > 0) {
                    $timestamp = $widget->row['created'];
                }
            }
        } elseif ($widget instanceof Widget_Contents_Post_Edit) {
            // 文章操作
            // 检查是新建还是修改：如果widget已经有cid且created时间与当前时间相差较大，说明是修改
            if ($widget->have() && $widget->cid) {
                $created = $widget->created;
                $now = $options->time;
                // 如果创建时间与当前时间相差超过1小时，认为是修改
                if ($created > 0 && ($now - $created) > 3600) {
                    // 修改操作
                    if (!empty($pluginOptions->trackPostModify)) {
                        $shouldTrack = true;
                    }
                } else {
                    // 新建操作
                    if (!empty($pluginOptions->trackPostPublish)) {
                        $shouldTrack = true;
                    }
                }
            } else {
                // 新建操作
                if (!empty($pluginOptions->trackPostPublish)) {
                    $shouldTrack = true;
                }
            }
        } elseif ($widget instanceof Widget_Contents_Page_Edit) {
            // 页面操作
            if (!empty($pluginOptions->trackPage)) {
                $shouldTrack = true;
            }
        }
        
        if (!$shouldTrack) {
            return;
        }
        
        // 获取操作日期（Y-m-d格式）
        $date = date('Y-m-d', $timestamp);
        
        // 记录到数据库
        $db = Typecho_Db::get();
        
        // 检查该日期是否已存在
        $existing = $db->fetchRow($db->select('count')
            ->from('table.contributions')
            ->where('date = ?', $date)
            ->limit(1));
        
        if ($existing) {
            // 更新计数
            $db->query($db->update('table.contributions')
                ->rows(array('count' => new Typecho_Db_Expr('count + 1')))
                ->where('date = ?', $date));
        } else {
            // 插入新记录
            $db->query($db->insert('table.contributions')
                ->rows(array(
                    'date' => $date,
                    'count' => 1
                )));
        }
    }
    
    /**
     * 解析内容里的短代码
     * 
     * @access public
     * @param string $content 文章内容
     * @param Widget_Abstract_Contents $widget 文章组件
     * @param string $lastResult 上一个插件处理的结果
     * @return string
     */
    public static function parse($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;
        
        // 匹配短代码 [ContributionGraph] 或 [ContributionGraph year="2025"]
        $pattern = '/\[ContributionGraph(?:\s+year=["\']?(\d{4})["\']?)?\]/i';
        
        $content = preg_replace_callback($pattern, function($matches) {
            $year = !empty($matches[1]) ? intval($matches[1]) : null;
            return self::renderGraph($year);
        }, $content);
        
        return $content;
    }
    
    /**
     * 渲染贡献图表
     * 
     * @access private
     * @param int|null $year 年份，null表示使用默认年份
     * @return string HTML代码
     */
    private static function renderGraph($year = null)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginOptions = $options->plugin('ContributionGraph');
        
        if ($year === null) {
            $year = !empty($pluginOptions->defaultYear) ? intval($pluginOptions->defaultYear) : date('Y');
        }
        
        // 获取该年份的贡献数据
        $contributions = self::getContributions($year);
        
        // 生成唯一ID
        $graphId = 'contribution-graph-' . uniqid();
        
        // 生成HTML
        $html = '<div class="contribution-graph-container" id="' . htmlspecialchars($graphId) . '" data-year="' . $year . '">';
        $html .= '<div class="contribution-graph-header">';
        $html .= '<h3 class="contribution-graph-title">' . $year . ' 年贡献统计</h3>';
        $html .= '<div class="contribution-graph-total">共 ' . array_sum($contributions) . ' 次贡献</div>';
        $html .= '</div>';
        $html .= '<div class="contribution-graph">';
        $html .= self::generateHeatmap($contributions, $year);
        $html .= '</div>';
        $html .= '<div class="contribution-graph-legend">';
        $html .= '<span class="legend-label">少</span>';
        $html .= '<div class="legend-squares">';
        $html .= '<div class="legend-square" data-level="0"></div>';
        $html .= '<div class="legend-square" data-level="1"></div>';
        $html .= '<div class="legend-square" data-level="2"></div>';
        $html .= '<div class="legend-square" data-level="3"></div>';
        $html .= '</div>';
        $html .= '<span class="legend-label">多</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 获取指定年份的贡献数据
     * 
     * @access private
     * @param int $year 年份
     * @return array 日期 => 贡献次数的数组
     */
    private static function getContributions($year)
    {
        $db = Typecho_Db::get();
        
        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';
        
        $rows = $db->fetchAll($db->select('date', 'count')
            ->from('table.contributions')
            ->where('date >= ?', $startDate)
            ->where('date <= ?', $endDate));
        
        $contributions = array();
        foreach ($rows as $row) {
            $contributions[$row['date']] = intval($row['count']);
        }
        
        return $contributions;
    }
    
    /**
     * 生成热力图
     * 
     * @access private
     * @param array $contributions 贡献数据
     * @param int $year 年份
     * @return string HTML代码
     */
    private static function generateHeatmap($contributions, $year)
    {
        // 计算该年份的第一天是星期几（0=周日, 1=周一, ...）
        $firstDay = strtotime($year . '-01-01');
        $dayOfWeek = date('w', $firstDay); // 0-6, 0是周日
        // 转换为周一为0的格式
        $offset = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
        
        // 计算该年份有多少天
        $daysInYear = date('z', strtotime($year . '-12-31')) + 1;
        
        // 计算实际需要的周数（考虑偏移）
        $totalWeeks = ceil(($daysInYear + $offset) / 7);
        if ($totalWeeks > 53) {
            $totalWeeks = 53;
        }
        
        // 生成月份标签
        $months = array('1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月');
        $monthLabels = array();
        $lastMonth = -1;
        for ($i = 0; $i < $totalWeeks; $i++) {
            $weekStartDay = $i * 7 - $offset;
            if ($weekStartDay >= 0 && $weekStartDay < $daysInYear) {
                $date = date('Y-m-d', strtotime($year . '-01-01 +' . $weekStartDay . ' days'));
                $month = intval(date('n', strtotime($date))) - 1;
                // 只在月份变化时或第一周显示标签
                if ($month != $lastMonth || $i == 0) {
                    $monthLabels[$i] = $months[$month];
                    $lastMonth = $month;
                }
            }
        }
        
        $html = '<div class="contribution-graph-months">';
        for ($i = 0; $i < $totalWeeks; $i++) {
            $html .= '<div class="month-label">' . (isset($monthLabels[$i]) ? htmlspecialchars($monthLabels[$i]) : '') . '</div>';
        }
        $html .= '</div>';
        
        // 生成星期标签（只在左侧显示一次）
        $weekLabels = array('周一', '周三', '周五');
        $html .= '<div class="contribution-graph-weeks">';
        foreach ($weekLabels as $label) {
            $html .= '<div class="week-label">' . htmlspecialchars($label) . '</div>';
        }
        $html .= '</div>';
        
        // 生成热力图网格
        $html .= '<div class="contribution-graph-grid">';
        
        for ($week = 0; $week < $totalWeeks; $week++) {
            for ($day = 0; $day < 7; $day++) {
                $dayIndex = $week * 7 + $day - $offset;
                
                if ($dayIndex < 0 || $dayIndex >= $daysInYear) {
                    // 不在该年份范围内的日期，显示空白
                    $html .= '<div class="contribution-day empty"></div>';
                } else {
                    $date = date('Y-m-d', strtotime($year . '-01-01 +' . $dayIndex . ' days'));
                    $count = isset($contributions[$date]) ? $contributions[$date] : 0;
                    $level = self::getContributionLevel($count);
                    
                    $html .= '<div class="contribution-day level-' . $level . '" ';
                    $html .= 'data-date="' . htmlspecialchars($date) . '" ';
                    $html .= 'data-count="' . $count . '" ';
                    $html .= 'title="' . htmlspecialchars($date . ': ' . $count . ' 次贡献') . '">';
                    $html .= '</div>';
                }
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 获取贡献等级（0-3）
     * 
     * @access private
     * @param int $count 贡献次数
     * @return int 等级 0-3
     */
    private static function getContributionLevel($count)
    {
        if ($count == 0) {
            return 0;
        } elseif ($count <= 3) {
            return 1;
        } elseif ($count <= 9) {
            return 2;
        } else {
            return 3;
        }
    }
    
    /**
     * 在头部输出CSS
     * 
     * @access public
     * @return void
     */
    public static function header()
    {
        echo self::getStyles();
    }
    
    /**
     * 在底部输出JavaScript
     * 
     * @access public
     * @return void
     */
    public static function footer()
    {
        echo self::getScripts();
    }
    
    /**
     * 获取CSS样式
     * 
     * @access private
     * @return string CSS代码
     */
    private static function getStyles()
    {
        return '<style>
.contribution-graph-container {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border-radius: 6px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
}

.contribution-graph-header {
    margin-bottom: 15px;
}

.contribution-graph-title {
    margin: 0 0 5px 0;
    font-size: 16px;
    font-weight: 600;
    color: #24292e;
}

.contribution-graph-total {
    font-size: 14px;
    color: #586069;
}

.contribution-graph {
    position: relative;
    overflow-x: auto;
}

.contribution-graph-months {
    display: flex;
    margin-bottom: 5px;
    padding-left: 30px;
    width: calc(53 * 15px); /* 与grid宽度一致 */
    max-width: 100%;
}

.month-label {
    width: 15px; /* 11px + 2px*2 margin */
    font-size: 12px;
    color: #586069;
    text-align: left;
    padding-left: 2px;
    flex-shrink: 0;
}

.contribution-graph-weeks {
    display: flex;
    flex-direction: column;
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    justify-content: space-around;
    padding-top: 15px;
}

.week-label {
    font-size: 12px;
    color: #586069;
    height: 11px;
    line-height: 11px;
}

.contribution-graph-grid {
    display: flex;
    flex-wrap: wrap;
    padding-left: 30px;
    width: calc(53 * 15px); /* 53周 * (11px + 2px*2 margin) */
    max-width: 100%;
}

.contribution-day {
    width: 11px;
    height: 11px;
    margin: 2px;
    border-radius: 2px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.contribution-day.empty {
    background: transparent;
}

.contribution-day.level-0 {
    background: #ebedf0;
}

.contribution-day.level-1 {
    background: #9be9a8;
}

.contribution-day.level-2 {
    background: #40c463;
}

.contribution-day.level-3 {
    background: #30a14e;
}

.contribution-day:hover {
    outline: 1px solid rgba(0, 0, 0, 0.5);
    outline-offset: -1px;
}

.contribution-graph-legend {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-top: 10px;
    font-size: 12px;
    color: #586069;
}

.legend-label {
    margin: 0 5px;
}

.legend-squares {
    display: flex;
    gap: 3px;
}

.legend-square {
    width: 11px;
    height: 11px;
    border-radius: 2px;
}

.legend-square[data-level="0"] {
    background: #ebedf0;
}

.legend-square[data-level="1"] {
    background: #9be9a8;
}

.legend-square[data-level="2"] {
    background: #40c463;
}

.legend-square[data-level="3"] {
    background: #30a14e;
}

@media (max-width: 768px) {
    .contribution-graph-container {
        padding: 15px;
    }
    
    .contribution-graph-grid {
        width: 100%;
        max-width: 583px;
    }
    
    .contribution-graph-months {
        padding-left: 25px;
    }
    
    .contribution-graph-weeks {
        padding-top: 12px;
    }
    
    .month-label, .week-label {
        font-size: 10px;
    }
}
</style>';
    }
    
    /**
     * 获取JavaScript脚本
     * 
     * @access private
     * @return string JavaScript代码
     */
    private static function getScripts()
    {
        return '<script>
(function() {
    function initContributionGraph() {
        const containers = document.querySelectorAll(".contribution-graph-container");
        
        containers.forEach(function(container) {
            if (container.dataset.initialized) return;
            container.dataset.initialized = "true";
            
            const days = container.querySelectorAll(".contribution-day:not(.empty)");
            
            days.forEach(function(day) {
                day.addEventListener("mouseenter", function(e) {
                    const date = this.dataset.date;
                    const count = parseInt(this.dataset.count) || 0;
                    const tooltip = document.createElement("div");
                    tooltip.className = "contribution-tooltip";
                    tooltip.textContent = date + ": " + count + " 次贡献";
                    tooltip.style.position = "absolute";
                    tooltip.style.background = "#24292e";
                    tooltip.style.color = "#fff";
                    tooltip.style.padding = "5px 8px";
                    tooltip.style.borderRadius = "3px";
                    tooltip.style.fontSize = "12px";
                    tooltip.style.whiteSpace = "nowrap";
                    tooltip.style.zIndex = "1000";
                    tooltip.style.pointerEvents = "none";
                    
                    document.body.appendChild(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + "px";
                    tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + "px";
                    
                    this._tooltip = tooltip;
                });
                
                day.addEventListener("mouseleave", function() {
                    if (this._tooltip) {
                        this._tooltip.remove();
                        this._tooltip = null;
                    }
                });
            });
        });
    }
    
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initContributionGraph);
    } else {
        initContributionGraph();
    }
    
    // 使用MutationObserver监听动态添加的内容
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === "childList") {
                initContributionGraph();
            }
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();
</script>';
    }
}
?>

