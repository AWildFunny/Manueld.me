<?php
/**
 * 在博客文章中嵌入自定义音乐播放器组件，支持手动播放功能
 * 这是一个Typecho插件，允许在文章中使用短代码插入音乐播放器
 * @package CustomMusicPlayer
 * @version 1.1.3
 * @link http://your-website.com
 * @dependence 9.9.2-*
 */

class CustomMusicPlayer_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法, 如果激活失败, 直接抛出异常
     * 该方法会将音乐播放器相关功能挂载到Typecho系统中
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // 将parse方法挂载到内容扩展点，用于处理文章内容中的短代码
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('CustomMusicPlayer_Plugin', 'parse');
        // 在页面头部输出CSS
        Typecho_Plugin::factory('Widget_Archive')->header = array('CustomMusicPlayer_Plugin', 'header');
        // 在页面底部输出JavaScript
        Typecho_Plugin::factory('Widget_Archive')->footer = array('CustomMusicPlayer_Plugin', 'footer');
    }
    
    /**
     * 禁用插件方法, 如果禁用失败, 直接抛出异常
     * 该方法用于禁用插件时清理挂载点
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 该方法用于在Typecho后台生成插件的配置界面
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 个人用户的配置面板
     * 该方法用于设置用户个性化配置
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 解析内容里的短代码
     * 该方法用于解析文章内容中的短代码，将其替换为HTML音乐播放器
     * 
     * @access public
     * @param string $content 文章内容
     * @param Widget_Abstract_Contents $widget 文章组件
     * @param string $lastResult 上一个插件处理的结果
     * @return string
     */
    public static function parse($content, $widget, $lastResult)
    {
        // 如果有上一个插件处理结果，则使用该结果作为内容，否则使用原内容
        $content = empty($lastResult) ? $content : $lastResult;
        
        // 如果是文章页面且为单篇文章，则进行短代码替换
        if ($widget instanceof Widget_Archive && $widget->is('single')) {
            // 正则表达式匹配短代码，并替换为HTML代码块
            $pattern = '/\[CustomMusicPlayer\s+audio="([^"]*?)"\s+cover="([^"]*?)"\s+title="([^"]*?)"\s+artist="([^"]*?)"\]/i';
            $replacement = '<div class="custom-music-player" id="customMusicPlayer">
                                <div class="player-container">
                                    <div class="cover-container">
                                        <img src="$2" alt="$3" class="cover-image">
                                    </div>
                                    <div class="info-container">
                                        <h3 class="song-title">$3</h3>
                                        <p class="artist-name">$4</p>
                                    </div>
                                    <div class="control-container">
                                        <button class="play-pause-btn" data-audio="$1" aria-label="播放/暂停">
                                            <svg class="play-icon" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            <svg class="pause-icon" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>';
            
            // 将短代码替换为实际HTML
            $content = preg_replace($pattern, $replacement, $content);
            
            // 在内容末尾添加自动播放提示的HTML代码
            $content .= '<div id="autoplayNotice" class="autoplay-notice">
                            <div class="notice-content">
                                <div class="notice-header">
                                    <span>本页面封面歌曲可播放</span>
                                    <button id="closeNotice" class="close-notice" aria-label="关闭提示">&times;</button>
                                </div>
                                <button id="playNoticeButton" class="play-notice-button">播放</button>
                                <div class="countdown-bar"></div>
                            </div>
                         </div>';
        }
        
        return $content;
    }
    
    /**
     * 在头部输出所需的CSS
     * 用于输出自定义音乐播放器的CSS样式
     * 
     * @access public
     * @return void
     */
    public static function header()
    {
        echo self::getStyles(); // 输出CSS样式
    }

    /**
     * 在底部输出所需的JavaScript
     * 用于输出自定义音乐播放器的JavaScript脚本
     * 
     * @access public
     * @return void
     */
    public static function footer()
    {
        echo self::getScripts(); // 输出JavaScript脚本
    }
    
    /**
     * 获取样式
     * 该方法返回用于播放器样式的CSS代码
     * 
     * @return string
     */
    private static function getStyles()
    {
        return '<style>
            .custom-music-player {
                width: 100%;
                max-width: 400px;
                margin: 20px auto;
                padding: 20px;
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); /* 添加阴影效果 */
                border-radius: 15px; /* 圆角边框 */
                background: #fff; /* 背景颜色为白色 */
                transition: all 0.3s ease; /* 过渡效果 */
            }
            .custom-music-player:hover {
                transform: translateY(-5px); /* 鼠标悬停时上移效果 */
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15); /* 增强阴影效果 */
            }
            .player-container {
                display: flex;
                align-items: center; /* 水平居中对齐 */
            }
            .cover-container {
                width: 100px;
                height: 100px;
                margin-right: 20px; /* 右边距 */
                border-radius: 10px; /* 圆角边框 */
                overflow: hidden; /* 隐藏超出部分 */
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); /* 图片阴影效果 */
            }
            .cover-image {
                width: 100%;
                height: 100%;
                object-fit: cover; /* 保持图像比例填充容器 */
                transition: transform 0.3s ease; /* 过渡效果 */
            }
            .cover-image:hover {
                transform: scale(1.05); /* 鼠标悬停时放大效果 */
            }
            .info-container {
                flex-grow: 1; /* 填充剩余空间 */
            }
            .song-title {
                margin: 0;
                font-size: 20px; /* 歌曲标题字体大小 */
                font-weight: bold; /* 字体加粗 */
                color: #333; /* 字体颜色 */
            }
            .artist-name {
                margin: 5px 0 0;
                font-size: 16px; /* 艺术家名字字体大小 */
                color: #666; /* 字体颜色 */
            }
            .control-container {
                margin-left: 20px; /* 左边距 */
            }
            .play-pause-btn {
                width: 60px;
                height: 60px;
                border-radius: 50%; /* 圆形按钮 */
                background: #f0f0f0; /* 背景颜色 */
                border: none; /* 无边框 */
                cursor: pointer; /* 鼠标指针样式 */
                transition: all 0.3s ease; /* 过渡效果 */
                display: flex;
                align-items: center; /* 水平居中对齐 */
                justify-content: center; /* 垂直居中对齐 */
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); /* 按钮阴影效果 */
            }
            .play-pause-btn:hover {
                background: #e6e6e6; /* 鼠标悬停时改变背景颜色 */
                transform: scale(1.05); /* 鼠标悬停时放大效果 */
            }
            .play-pause-btn:active {
                transform: scale(0.95); /* 点击时缩小效果 */
            }
            .play-pause-btn svg {
                width: 30px;
                height: 30px;
                fill: #333; /* 图标填充颜色 */
                transition: opacity 0.3s ease; /* 透明度过渡效果 */
            }
            .pause-icon {
                display: none; /* 默认隐藏暂停图标 */
            }
            .playing .play-icon {
                display: none; /* 播放状态时隐藏播放图标 */
            }
            .playing .pause-icon {
                display: block; /* 播放状态时显示暂停图标 */
            }
            @keyframes rotate {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .autoplay-notice {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #fff;
                color: #333;
                padding: 20px;
                border-radius: 15px;
                font-size: 14px;
                z-index: 1000; /* 确保提示框位于最前方 */
                width: 300px;
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); /* 提示框阴影效果 */
                opacity: 0; /* 默认透明 */
                transform: translateY(20px); /* 默认下移20px */
                transition: all 0.5s ease; /* 过渡效果 */
            }
            .autoplay-notice.show {
                opacity: 1; /* 显示时设置透明度为1 */
                transform: translateY(0); /* 复位到原始位置 */
            }
            .notice-content {
                display: flex;
                flex-direction: column; /* 垂直布局 */
            }
            .notice-header {
                display: flex;
                justify-content: space-between; /* 水平两端对齐 */
                align-items: center; /* 垂直居中对齐 */
                margin-bottom: 15px;
            }
            .close-notice {
                background: transparent;
                border: none; /* 无边框 */
                color: #333; /* 文字颜色 */
                font-size: 20px;
                cursor: pointer; /* 鼠标指针样式 */
                transition: color 0.3s ease; /* 过渡效果 */
            }
            .close-notice:hover {
                color: #ff5722; /* 鼠标悬停时改变颜色 */
            }
            .play-notice-button {
                background-color: #ff5722; /* 按钮背景颜色 */
                color: white; /* 按钮文字颜色 */
                border: none; /* 无边框 */
                padding: 12px 20px; /* 按钮内边距 */
                border-radius: 8px; /* 圆角 */
                cursor: pointer; /* 鼠标指针样式 */
                transition: all 0.3s ease; /* 过渡效果 */
                font-size: 16px; /* 字体大小 */
                font-weight: bold; /* 字体加粗 */
            }
            .play-notice-button:hover {
                background-color: #e64a19; /* 鼠标悬停时改变背景颜色 */
                transform: translateY(-2px); /* 鼠标悬停时上移效果 */
                box-shadow: 0 4px 10px rgba(230, 74, 25, 0.3); /* 按钮阴影效果 */
            }
            .countdown-bar {
                height: 4px;
                background-color: #ff5722; /* 进度条颜色 */
                margin-top: 15px;
                width: 100%; /* 宽度占满 */
                border-radius: 2px; /* 圆角 */
                transition: width 1s linear; /* 宽度过渡效果 */
            }
            @keyframes countdownAnimation {
                0% { width: 100%; }
                100% { width: 0%; }
            }
            .countdown-bar {
                animation: countdownAnimation 5s linear; /* 倒计时动画效果 */
            }
            @media (max-width: 480px) {
                .custom-music-player {
                    padding: 15px;
                }
                .cover-container {
                    width: 80px;
                    height: 80px;
                }
                .song-title {
                    font-size: 18px;
                }
                .artist-name {
                    font-size: 14px;
                }
                .play-pause-btn {
                    width: 50px;
                    height: 50px;
                }
                .play-pause-btn svg {
                    width: 25px;
                    height: 25px;
                }
            }
        </style>';
    }
    
    /**
     * 获取脚本
     * 该方法返回用于播放器功能的JavaScript代码
     * 
     * @return string
     */
    private static function getScripts()
    {
        return '<script>
            (function() {
                let countdownInterval = null;

                // 初始化音乐播放器
                function initializePlayers() {
                    const players = document.querySelectorAll(".custom-music-player");
                    players.forEach(player => {
                        if (player.dataset.initialized) return; // 防止重复初始化
                        
                        const audioUrl = player.querySelector(".play-pause-btn").dataset.audio; // 获取音频URL
                        const playPauseBtn = player.querySelector(".play-pause-btn"); // 获取播放按钮
                        const coverImage = player.querySelector(".cover-image"); // 获取封面图片

                        let playerAudio = new Audio(audioUrl); // 创建Audio对象
                        playerAudio.load(); // 预加载音频

                        // 点击播放/暂停按钮时，切换播放状态
                        playPauseBtn.addEventListener("click", function() {
                            togglePlay(playerAudio, playPauseBtn, coverImage);
                        });

                        player.dataset.initialized = "true"; // 标记播放器已经初始化
                    });
                }

                // 切换播放/暂停状态
                function togglePlay(playerAudio, playPauseBtn, coverImage) {
                    if (playerAudio.paused) {
                        playerAudio.play().then(() => {
                            playPauseBtn.classList.add("playing"); // 播放时添加playing类，切换图标
                            coverImage.style.animation = "rotate 10s linear infinite"; // 播放时封面旋转动画
                        }).catch(e => {
                            console.error("Error playing audio:", e); // 如果播放失败，输出错误信息
                            alert("无法播放音频，请检查音频文件是否有效。"); // 弹出提示框，提示播放失败
                        });
                    } else {
                        playerAudio.pause(); // 如果正在播放，则暂停音频
                        playPauseBtn.classList.remove("playing"); // 移除playing类，切换回播放图标
                        coverImage.style.animation = "none"; // 暂停时取消封面旋转动画
                    }
                }

                // 初始化播放提示功能
                function initializeNotice() {
                    const autoplayNotice = document.getElementById("autoplayNotice"); // 获取自动播放提示元素
                    if (!autoplayNotice || autoplayNotice.dataset.initialized) return; // 如果已经初始化，直接返回

                    const playNoticeButton = document.getElementById("playNoticeButton"); // 获取播放提示按钮
                    const closeNoticeButton = document.getElementById("closeNotice"); // 获取关闭提示按钮
                    const countdownElement = autoplayNotice.querySelector(".countdown-bar"); // 获取倒计时进度条
                    if (!playNoticeButton || !closeNoticeButton || !countdownElement) return;

                    let countdown = 5; // 设置倒计时初始值为5秒
                    autoplayNotice.dataset.initialized = "true"; // 标记提示框已经初始化

                    autoplayNotice.classList.add("show"); // 显示提示框

                    // 点击播放按钮时，滚动到播放器位置并开始播放
                    playNoticeButton.addEventListener("click", function(e) {
                        e.preventDefault();
                        scrollToPlayer(); // 滚动到播放器
                        const playPauseBtn = document.querySelector(".play-pause-btn");
                        if (playPauseBtn) {
                            playPauseBtn.click(); // 模拟点击播放按钮
                        }
                    });

                    // 点击关闭按钮时，清除提示框
                    closeNoticeButton.addEventListener("click", function(e) {
                        e.preventDefault();
                        clearNotice();
                    });

                    // 每秒更新一次倒计时
                    countdownInterval = setInterval(function() {
                        countdown--;
                        countdownElement.style.width = (countdown / 5 * 100) + "%"; // 更新进度条宽度
                        if (countdown <= 0) {
                            clearInterval(countdownInterval); // 清除倒计时计时器
                            autoplayNotice.classList.remove("show"); // 隐藏提示框
                        }
                    }, 1000);
                }

                // 清除提示框
                function clearNotice() {
                    clearInterval(countdownInterval); // 清除倒计时计时器
                    const autoplayNotice = document.getElementById("autoplayNotice");
                    if (autoplayNotice) {
                        autoplayNotice.classList.remove("show"); // 隐藏提示框
                    }
                }

                // 滚动到播放器位置，以便用户查看
                function scrollToPlayer() {
                    const player = document.getElementById("customMusicPlayer"); // 获取播放器元素
                    if (player) {
                        player.scrollIntoView({ behavior: "smooth", block: "center" }); // 平滑滚动到播放器位置
                    }
                }

                // 初始化现有播放器和提示
                function initializeWhenReady() {
                    if (document.readyState === "loading") {
                        // 如果页面正在加载，等待DOMContentLoaded事件再初始化
                        document.addEventListener("DOMContentLoaded", () => {
                            initializePlayers();
                            initializeNotice();
                        });
                    } else {
                        // 如果页面已加载，直接初始化
                        initializePlayers();
                        initializeNotice();
                    }
                }

                initializeWhenReady(); // 执行初始化

                // 使用MutationObserver监听DOM变化，以便在动态添加播放器时重新初始化
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === "childList") {
                            initializePlayers(); // 重新初始化播放器
                            initializeNotice(); // 重新初始化提示框
                        }
                    });
                });

                // 观察整个文档的子节点变化
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            })();
        </script>';
    }
}
?>
