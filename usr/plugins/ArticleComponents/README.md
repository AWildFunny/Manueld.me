# ArticleComponents

Typecho 文章「组件插入」面板：在写文章侧边栏统一插入可扩展区块。当前内置：

- **作者申明**（本插件短代码）
- **音乐播放器**（需同时启用 `CustomMusicPlayer`）

## 启用

1. 目录置于 `usr/plugins/ArticleComponents/`
2. 后台 → 插件 → 启用 **ArticleComponents**
3. （可选）启用 **CustomMusicPlayer**，以便在同一面板插入音乐

启用本插件后，CustomMusicPlayer 自带的侧边栏入口会自动隐藏，避免重复。

## 作者申明短代码

```
[notice color="#1095c1" text="#ffffff" title="可选标题" radius="12" shadow="1"]
第一段

第二段，链接示例：[关于](/about.html)
[/notice]
```

| 属性 | 说明 |
|------|------|
| `title` | 可选标题行 |
| `color` | 背景色（`#hex` / `rgb()`） |
| `text` | 文字色 |
| `radius` | 圆角 px，默认 12 |
| `shadow` | `1` 悬浮阴影（默认）/ `0` 无阴影 |

正文空行分段；支持 `[文字](url)` 链接。仅在含 `[notice` 的单页加载样式。

## 后台

写文章 / 写页面 → 右侧 **组件插入** → **打开组件面板**：

1. 左侧用胶囊按钮选择组件，下方实时预览  
2. 右侧编辑参数；作者申明内置预设含「作者欢迎」「宇宙安全声明」「内容性质声明」「受众与互动提示」「作者趣味声明」「内容修订提示」  
3. **保存为预设** 写入浏览器 localStorage，下次可复用 / 删除  
4. **插入短代码**

## 扩展

后续新组件可在同一面板增加选项（改 `Plugin.php` 管理端 HTML + `admin-components.js`），或再拆独立渲染插件并由本面板调用。

## 版本

当前 **1.0.5**。
