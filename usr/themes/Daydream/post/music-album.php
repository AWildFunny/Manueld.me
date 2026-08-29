<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
/**
 * 音乐相册文章模板
 * 启用：自定义字段 articleTemplate = music-album
 * 用法见主题根目录 MUSIC-ALBUM.md
 */
$this->need('header.php');
?>

<link rel="stylesheet" href="<?php $this->options->themeUrl('/assets/css/music-album.css'); ?>?ver=2.3.5">

<?php if ($this->fields->linkTo): ?>
    <script type="text/javascript">window.location.href = '<?php echo $this->fields->linkTo; ?>';</script>
<?php endif; ?>

<article class="music-album-article">
    <?php if ($this->fields->headPic != ''): ?>
        <a data-fancybox="gallery" href="<?php $this->fields->headPic(); ?>" data-caption="<?php $this->title(); ?>">
            <img src="<?php $this->fields->headPic(); ?>" class="music-album-cover shadow rounded" alt="<?php $this->title(); ?>" title="<?php $this->title(); ?>">
        </a>
    <?php endif; ?>

    <header class="music-album-header">
        <h1><?php $this->title(); ?></h1>
        <div class="meta-info">
            <i class="czs-calendar"></i>
            <time datetime="<?php $this->date('c'); ?>"><?php $this->date('Y-m-d D h:iA'); ?></time>
            <a href="<?php $this->options->siteUrl(); ?>admin/write-post.php?cid=<?php $this->cid(); ?>" title="编辑">
                <i class="czs-pen-write"></i> 编辑
            </a>
            <?php if ($this->fields->pubPlace != ''): ?>
                <br>
                <i class="czs-location"></i>
                <?php echo $this->fields->pubPlace; ?>
            <?php endif; ?>
        </div>
    </header>

    <?php
    $date1 = date_create(date('c', $this->date->timeStamp));
    $date2 = date_create(date('c'));
    $days = date_diff($date1, $date2);
    ?>
    <?php if ($this->options->oldPosts != '' && $days->format('%a') > $this->options->oldPosts): ?>
        <div class="alert" role="alert">
            <i class="czs-time"></i> 这是一篇发布于 <?php echo $days->format('%a'); ?> 天以前的旧文。其中的部分内容可能已经过时。
        </div>
    <?php endif; ?>

    <div class="post-content music-album-content">
        <?php echo exContentAlbum($this->content); ?>
    </div>
</article>

<hr>

<ul>
    <li>
        协议：
        <a rel="license" href="http://creativecommons.org/licenses/by-sa/4.0/">Creative Commons BY-SA 4.0</a>
    </li>
    <li>
        分类：
        <?php foreach ($this->categories as $categories): ?>
            <a href="<?php print($categories['permalink']); ?>"><?php print($categories['name']); ?></a>
        <?php endforeach; ?>
    </li>
    <?php if (count($this->tags) > 0): ?>
        <li>
            标签：
            <?php foreach ($this->tags as $tags): ?>
                <a href="<?php print($tags['permalink']); ?>"><?php print($tags['name']); ?></a>
            <?php endforeach; ?>
        </li>
    <?php endif; ?>
    <?php if ($this->commentsNum > 0): ?>
        <li>
            <?php echo $this->commentsNum; ?> 条评论
        </li>
    <?php endif; ?>
</ul>

<?php $this->need('comments.php'); ?>

<script src="<?php $this->options->themeUrl('/assets/js/music-album.js'); ?>?ver=2.3.4" defer></script>

<?php $this->need('footer.php'); ?>
