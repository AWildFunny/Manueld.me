# 音乐相册模板（Daydream）

依赖主题自定义字段、`CustomMusicPlayer` 与（可选）`AlbumShot`。用于「章节 + 图文 + 内嵌播放器」的长文阅读页。

## 启用

1. 后台写文章 → 自定义字段 **文章模板** 选「音乐相册」（`articleTemplate=music-album`）
2. 正文用二级标题 `##` / `<h2>` 划分章节
3. 在章节内插入 `[music ...]` 短代码（见插件 README）
4. 章头图：用组件插入里的 **图文融合**，或在 `##` 下一行写 `[album-shot ...]`；若都不写，则该章第一张图按横竖自动排版

可选：封面图仍用主题字段 `headPic`。

## 正文约定

- 第一个 `##` 之前的内容视为导语（intro）
- 每个 `##` 开启一章
- 桌面：章节目录为屏幕**左缘细轨**，悬停或点击展开；不占正文栏宽。手机：顶部横向章名条
- 切换章节时，若该章有播放器，会自动播放该章第一首（需用户曾与页面有过交互时浏览器才允许自动播）

## 图文融合版式

短代码（需启用 AlbumShot）：

```
[album-shot layout="overlay" src="/usr/uploads/x.jpg" alt="现场"]
```

`layout`：`auto`（默认）/ `banner` / `overlay` / `split-left` / `split-right` / `float` / `custom`  
自定义可加 `pos`、`titlepos`、`wrap="1"`。详见 `usr/plugins/AlbumShot/README.md`。

## 布局要点

- 内容区约 768px 宽；目录为 overlay，不挤正文
- 相册内播放器为紧凑尺寸（不影响普通文章中的播放器）
- 需已启用 `CustomMusicPlayer` 才会渲染音乐短代码

## 涉及文件

- `post.php` → 路由到 `post/music-album.php`
- `functions.php`：`articleTemplate` 字段、`exContentAlbum()`
- `assets/css/music-album.css`、`assets/js/music-album.js`
- 插件 `usr/plugins/AlbumShot/`

当前资源版本：**2.3.0**。

章节导航（桌面）为左缘书脊：短刻度 + 当前章名竖排；悬停展开为文字目录，不作卡片。章节很多时刻度加密并可滚动。

图文：单图保持原比例；多图用 `[album-board]` 百分比画布，后台可拖拽。

