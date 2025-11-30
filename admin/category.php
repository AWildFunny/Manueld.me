<?php
include 'common.php';
include 'header.php';
include 'menu.php';
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="form">
            <div class="col-mb-12 col-tb-6 col-tb-offset-3">
                <?php \Widget\Metas\Category\Edit::alloc()->form()->render(); ?>
            </div>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
// 添加分类隐藏功能的JavaScript
$themeUrl = $options->themeUrl;
echo '<script src="' . $themeUrl . '/assets/js/admin-category-hidden.js"></script>';
include 'footer.php';
?>
