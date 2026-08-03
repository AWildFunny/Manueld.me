<?php
/**
 * 文章内嵌唱片式音乐播放器，支持滚动自动播放与悬浮迷你条
 *
 * @package CustomMusicPlayer
 * @version 2.0.2
 * @dependence 9.9.2-*
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class CustomMusicPlayer_Plugin implements Typecho_Plugin_Interface
{
    /** @var bool */
    private static $needsAssets = false;

    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('CustomMusicPlayer_Plugin', 'parse');
        Typecho_Plugin::factory('Widget_Archive')->header = array('CustomMusicPlayer_Plugin', 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('CustomMusicPlayer_Plugin', 'footer');
    }

    public static function deactivate() {}

    public static function config(Typecho_Widget_Helper_Form $form) {}

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * @param string $content
     * @param Widget_Abstract_Contents $widget
     * @param string $lastResult
     * @return string
     */
    public static function parse($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;

        if (!($widget instanceof Widget_Archive) || !$widget->is('single')) {
            return $content;
        }

        if (strpos($content, '[music') === false && stripos($content, '[CustomMusicPlayer') === false) {
            return $content;
        }

        self::$needsAssets = true;

        $content = preg_replace_callback(
            '/\[(music|CustomMusicPlayer)\s+([^\]]+)\]/i',
            array('CustomMusicPlayer_Plugin', 'renderShortcode'),
            $content
        );

        return $content;
    }

    /**
     * @param array<int, string> $matches
     * @return string
     */
    public static function renderShortcode($matches)
    {
        $attrs = self::parseAttributes($matches[2]);

        $title = isset($attrs['title']) ? trim($attrs['title']) : '未命名曲目';
        $artist = isset($attrs['artist']) ? trim($attrs['artist']) : '';
        $src = '';
        if (isset($attrs['src'])) {
            $src = trim($attrs['src']);
        } elseif (isset($attrs['audio'])) {
            $src = trim($attrs['audio']);
        }
        $cover = isset($attrs['cover']) ? trim($attrs['cover']) : '';
        $mode = isset($attrs['mode']) ? strtolower(trim($attrs['mode'])) : 'click';

        if (!in_array($mode, array('click', 'scroll'), true)) {
            $mode = 'click';
        }

        if ($src === '') {
            return '<p class="music-player-error" role="alert">音乐播放器缺少音频地址（src）。</p>';
        }

        $id = 'mp-' . substr(md5($src . $title . uniqid('', true)), 0, 10);

        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $artistEsc = htmlspecialchars($artist, ENT_QUOTES, 'UTF-8');
        $srcEsc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $coverEsc = htmlspecialchars($cover, ENT_QUOTES, 'UTF-8');
        $modeEsc = htmlspecialchars($mode, ENT_QUOTES, 'UTF-8');

        $coverHtml = $cover !== ''
            ? '<img class="music-player-cover" src="' . $coverEsc . '" alt="' . $titleEsc . '" loading="lazy">'
            : '<div class="music-player-cover music-player-cover--placeholder" aria-hidden="true"></div>';

        $artistHtml = $artist !== ''
            ? '<p class="music-player-artist">' . $artistEsc . '</p>'
            : '';

        return '
<figure class="music-player" id="' . $id . '" data-mp-id="' . $id . '" data-src="' . $srcEsc . '" data-mode="' . $modeEsc . '" data-title="' . $titleEsc . '" data-cover="' . $coverEsc . '">
    <div class="music-player-body">
        <div class="music-player-vinyl-wrap">
            <div class="music-player-disc" aria-hidden="true">
                ' . $coverHtml . '
                <span class="music-player-disc-hole"></span>
            </div>
        </div>
        <figcaption class="music-player-meta">
            <p class="music-player-title">' . $titleEsc . '</p>
            ' . $artistHtml . '
            <div class="music-player-progress">
                <span class="music-player-time music-player-time--current" aria-hidden="true">0:00</span>
                <input type="range" class="music-player-seek" min="0" max="100" value="0" step="0.1" aria-label="播放进度">
                <span class="music-player-time music-player-time--total" aria-hidden="true">0:00</span>
            </div>
            <button type="button" class="music-player-toggle" aria-label="播放 ' . $titleEsc . '">
                <svg class="music-player-icon music-player-icon--play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                <svg class="music-player-icon music-player-icon--pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            </button>
        </figcaption>
    </div>
    <div class="music-player-sentinel" aria-hidden="true"></div>
</figure>';
    }

    /**
     * @param string $text
     * @return array<string, string>
     */
    private static function parseAttributes($text)
    {
        $attrs = array();
        if (preg_match_all('/(\w+)\s*=\s*(["\'])(.*?)\2/is', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs[strtolower($match[1])] = $match[3];
            }
        }
        return $attrs;
    }

    /**
     * header 早于 contentEx 执行，故在单篇页面预加载资源
     */
    private static function shouldLoadAssets()
    {
        if (self::$needsAssets) {
            return true;
        }

        $archive = Typecho_Widget::widget('Widget_Archive');
        return $archive->is('single');
    }

    public static function header()
    {
        if (!self::shouldLoadAssets()) {
            return;
        }

        $base = Helper::options()->pluginUrl . '/CustomMusicPlayer/assets/music-player.css';
        $url = $base . '?ver=2.0.2';
        echo '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function footer()
    {
        if (!self::shouldLoadAssets()) {
            return;
        }

        $base = Helper::options()->pluginUrl . '/CustomMusicPlayer/assets/music-player.js';
        $url = $base . '?ver=2.0.2';
        echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" defer></script>';
    }
}
