/**
 * 分类隐藏功能 - 后台管理
 * 在分类编辑页面添加"隐藏分类"复选框
 */

(function() {
    'use strict';
    
    // 检查是否在分类编辑页面
    if (window.location.pathname.indexOf('/admin/category.php') === -1) {
        return;
    }
    
    // 等待页面加载完成
    function initCategoryHidden() {
        const $form = $('form[action*="metas-category-edit"]');
        if (!$form.length) {
            return;
        }
        
        // 查找描述字段
        const $descriptionField = $form.find('textarea[name="description"]').closest('.typecho-option');
        if (!$descriptionField.length) {
            return;
        }
        
        // 检查是否已经添加了隐藏复选框
        if ($form.find('input[name="category_hidden"]').length > 0) {
            return;
        }
        
        // 获取当前描述值
        const descriptionValue = $form.find('textarea[name="description"]').val() || '';
        const isHidden = descriptionValue.indexOf('__HIDDEN__') === 0;
        
        // 创建隐藏复选框
        const $hiddenField = $('<div class="typecho-option">' +
            '<label class="typecho-label">隐藏分类</label>' +
            '<p><label>' +
            '<input type="checkbox" name="category_hidden" value="1" ' + (isHidden ? 'checked' : '') + '> ' +
            '隐藏此分类（不在前端分类列表中显示）' +
            '</label></p>' +
            '</div>');
        
        // 插入到描述字段之后
        $descriptionField.after($hiddenField);
        
        // 拦截表单提交
        $form.on('submit', function(e) {
            const $hiddenCheckbox = $form.find('input[name="category_hidden"]');
            const $descriptionTextarea = $form.find('textarea[name="description"]');
            const isChecked = $hiddenCheckbox.is(':checked');
            let description = $descriptionTextarea.val() || '';
            
            // 如果勾选了隐藏
            if (isChecked) {
                // 如果description不以__HIDDEN__开头，则添加
                if (description.indexOf('__HIDDEN__') !== 0) {
                    description = '__HIDDEN__' + description;
                    $descriptionTextarea.val(description);
                }
            } else {
                // 如果未勾选隐藏，移除__HIDDEN__标记
                if (description.indexOf('__HIDDEN__') === 0) {
                    description = description.substring(11); // 移除 '__HIDDEN__' (11个字符)
                    $descriptionTextarea.val(description);
                }
            }
        });
    }
    
    // 页面加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCategoryHidden);
    } else {
        initCategoryHidden();
    }
    
    // 如果使用jQuery，也监听jQuery ready事件
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(initCategoryHidden);
    }
})();

