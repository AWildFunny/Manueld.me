# 组件插入（Daydream 内置）

写文章 / 写页面侧栏的「组件插入」由 **Daydream 主题默认提供**，无需单独启用插件。

## 位置

- PHP：`include/ComponentInserter/Registry.php`、`Shell.php`
- 资源：`assets/admin/component-inserter/`
- 在 `functions.php` 中注册 `admin/write-post.php` / `write-page.php` 钩子

## 业务插件如何注册

启用插件时：

```php
Typecho_Plugin::factory('ComponentInserter')->collect = array('Your_Plugin', 'registerComponent');
```

`registerComponent` 内调用 `ComponentInserter_Registry::register([...])`（`id` / `label` / `order` / `panelHtml` / 可选 `boot` `css` `js`）。

已接入：CustomMusicPlayer、AlbumShot（图文融合）、AuthorNotice、ContributionGraph。

## 主题色

组件列表激活态使用 `#4E7289`（与站点主色一致）。
