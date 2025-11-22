<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<?php function threadedComments($comments, $options) {
    $commentClass = '';
    if ($comments->levels > 0) $commentClass .= ' comment-ml';
    ?>
    <?php if ($comments->type == 'pingback' || $comments->type == 'traceback'): ?>
	    <blockquote id="<?php $comments->theId(); ?>">
            被 <?php $comments->author(); ?> 引用。
            <br>
	        <small><?php $comments->date('F jS, Y'); ?> at <?php $comments->date('h:i a'); ?></small>
        </blockquote>
    <?php else: ?>
	    <div id="<?php $comments->theId(); ?>" class="<?php echo $commentClass; ?>">
            <?php
                # 如果是 QQ 邮箱则使用 QQ 头像，否则请求 Gravatar 头像
                $qq = str_replace('@qq.com', '', $comments->mail);
			    if (strstr($comments->mail, "qq.com") && is_numeric($qq) && strlen($qq) < 11 && strlen($qq) > 4){
			        $avatarUrl = 'https://q3.qlogo.cn/g?b=qq&nk='.$qq.'&s=100';
			    } else {
                    $avatarUrl = __TYPECHO_GRAVATAR_PREFIX__;
                    if (!empty($comments->mail)) $avatarUrl .= md5(strtolower(trim($comments->mail)));
                    $avatarUrl .= '?s='. '64' . '&amp;r=' . Helper::options()->commentsAvatarRating . '&amp;d=' . Helper::options()->themeUrl.'/assets/img/visitor.png';
                }
            ?>
            <img class="avatar circle" src="<?php echo $avatarUrl; ?>" alt="<?php echo $comments->author; ?>"/>
            <div>
                <b><?php $comments->author(); ?></b>
                <?php if ($comments->authorId == $comments->ownerId): ?>
                    <small><i class="czs-forum-l"></i> 博主</small>
                <?php endif; ?>
                <?php showUserAgent($comments->agent); ?>
                <small><?php showLocation($comments->ip); ?></small>
                <small><?php $comments->reply('<i class="czs-pen-write"></i> Reply'); ?></small>
                <?php if ($comments->status == 'waiting'): ?>
                    <small><i class="czs-talk-l"></i> 等待审核</small>
                <?php endif; ?>
                <br>
	            <small><?php $comments->date('F jS, Y'); ?> at <?php $comments->date('h:i a'); ?></small>
            </div>
            <p><?php $comments->content(); ?></p>
            <?php if ($comments->children): $comments->threadedComments($options); endif; ?>
        </div>
    <?php endif; ?>
<?php } ?>

<div id="comments">
    <?php $this->comments()->to($comments); ?>
    <?php if ($comments->have()): ?>
    <hr>
    <?php $comments->listComments(array('before'=>'','after'=>'')); ?>
    <?php endif; ?>

    <!-- 评论提交区域 -->
    <?php if ($this->allow('comment')): ?>
        <hr>
        <div id="<?php $this->respondId(); ?>" class="respond">
            <div><?php $comments->cancelReply('<i class="czs-close"></i> 取消回复'); ?></div>
            <?php if ($this->options->commentsNotice !=''): ?>
                <div class="alert" role="alert"><?php $this->options->commentsNotice(); ?></div>
            <?php endif; ?>
        	<h2 id="response">添加新评论</h2>
        	<form method="post" action="<?php $this->commentUrl(); ?>" id="comment-form" role="form">
                <?php if ($this->user->hasLogin()): ?>
	    	    <p>登录身份：
                    <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a> | <a href="<?php $this->options->logoutUrl(); ?>" title="Logout">退出 &raquo;</a>
   	    	    </p>
                <?php else: ?>
                    <div class="grid">
                        <input type="text" name="author" id="author" placeholder="Name" value="<?php $this->remember('author'); ?>" required />
                        <input type="email" name="mail" id="mail" placeholder="Email" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?> />
                        <input type="url" name="url" id="url" placeholder="Website" value="<?php $this->remember('url'); ?>"<?php if ($this->options->commentsRequireURL): ?> required<?php endif; ?> />
                    </div>
                <?php endif; ?>
                <textarea rows="8" cols="50" name="text" id="textarea" placeholder="Say something!" required ><?php $this->remember('text'); ?></textarea>
                <?php if (class_exists('Captcha_Plugin')): ?>
                <!-- 隐藏的验证码输入框，用于表单提交 -->
                <input type="hidden" name="captcha_code" id="captcha_code" />
                <?php endif; ?>
                <button id="submit" class="shadow" type="submit">Submit</button>
                <!-- <label>
                    <input type="checkbox" role="switch" name="receiveMail" id="receiveMail" value="yes" checked />
                    <label for="receiveMail">接收邮件通知</label>
                </label> -->
        	</form>
        </div>
        <script> // 从 Typecho 源码中摘取的评论 js
            (function () {
                window.TypechoComment = {
                    dom : function (id) {
                        return document.getElementById(id);
                    },
                    create : function (tag, attr) {
                        var el = document.createElement(tag);
                        for (var key in attr) {
                            el.setAttribute(key, attr[key]);
                        }
                        return el;
                    },
                    reply : function (cid, coid) {
                        var comment = this.dom(cid), parent = comment.parentNode,
                            response = this.dom('<?php echo $this->respondId; ?>'), input = this.dom('comment-parent'),
                            form = 'form' == response.tagName ? response : response.getElementsByTagName('form')[0],
                            textarea = response.getElementsByTagName('textarea')[0];
                        if (null == input) {
                            input = this.create('input', {
                                'type' : 'hidden',
                                'name' : 'parent',
                                'id'   : 'comment-parent'
                            });
                            form.appendChild(input);
                        }
                        input.setAttribute('value', coid);
                        if (null == this.dom('comment-form-place-holder')) {
                            var holder = this.create('div', {
                                'id' : 'comment-form-place-holder'
                            });
                            response.parentNode.insertBefore(holder, response);
                        }
                        comment.appendChild(response);
                        this.dom('cancel-comment-reply-link').style.display = '';
                        if (null != textarea && 'text' == textarea.name) {
                            textarea.focus();
                        }
                        return false;
                    },
                    cancelReply : function () {
                        var response = this.dom('<?php echo $this->respondId; ?>'),
                        holder = this.dom('comment-form-place-holder'), input = this.dom('comment-parent');
                        if (null != input) {
                            input.parentNode.removeChild(input);
                        }
                        if (null == holder) {
                            return true;
                        }
                        this.dom('cancel-comment-reply-link').style.display = 'none';
                        holder.parentNode.insertBefore(response, holder);
                        return false;
                    }
                };
            })();
        </script>
        
        <?php if (class_exists('Captcha_Plugin')): ?>
        <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        #captcha-error.shake {
            animation: shake 0.5s ease-in-out;
        }
        /* 简单对话框样式 */
        #simple-dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--card-background-color, var(--background-color));
            padding: 2rem;
            border-radius: var(--border-radius, 8px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            z-index: 20000;
            display: none;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        #simple-dialog.show {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        #simple-dialog-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 19999;
            display: none;
        }
        #simple-dialog-overlay.show {
            display: block;
        }
        #simple-dialog-message {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            color: var(--color, var(--text-color));
        }
        #simple-dialog-ok {
            /* 使用主题默认按钮样式，与评论表单提交按钮一致 */
        }
        </style>
        <!-- 简单对话框 -->
        <div id="simple-dialog-overlay"></div>
        <div id="simple-dialog">
            <div id="simple-dialog-message"></div>
            <button id="simple-dialog-ok" role="button" class="shadow" type="button">确定</button>
        </div>
        <!-- 验证码弹窗 -->
        <div id="captcha-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 10000; align-items: center; justify-content: center;">
            <div class="shadow rounded" style="background: var(--card-background-color, var(--background-color)); padding: 2rem; max-width: 500px; width: 90%; position: relative;">
                <!-- 右上角关闭按钮 -->
                <button id="captcha-close" role="button" style="position: absolute; top: 1rem; right: 1rem; background: transparent; border: none; font-size: 1.5rem; line-height: 1; cursor: pointer; color: var(--muted-color); padding: 0; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.2s;" 
                        onmouseover="this.style.background='rgba(0,0,0,0.1)'; this.style.color='var(--color)'" 
                        onmouseout="this.style.background='transparent'; this.style.color='var(--muted-color)'"
                        title="关闭">×</button>
                
                <!-- 顶部提示文字 -->
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <p style="margin: 0; font-size: 1.1rem; color: var(--color, var(--text-color));">为防止机器人刷评，请输入验证码~</p>
                </div>
                
                <!-- 错误提示 -->
                <div id="captcha-error" style="color: var(--del-color, #d32f2f); margin-bottom: 1rem; display: none; padding: 0.75rem; background: rgba(211, 47, 47, 0.1); border-radius: var(--border-radius); border-left: 4px solid var(--del-color, #d32f2f); text-align: center;"></div>
                
                <!-- 验证码图片区域（中间，较大） -->
                <div style="text-align: center; margin-bottom: 1rem;">
                    <img id="captcha-image" 
                         src="<?php echo Typecho_Common::url('/action/captcha', Helper::options()->index); ?>" 
                         alt="验证码" 
                         onclick="refreshCaptcha()" 
                         class="shadow rounded"
                         style="cursor: pointer; height: 120px; width: auto; max-width: 100%; display: inline-block; border: 1px solid var(--border-color);" 
                         title="点击图片刷新验证码" />
                </div>
                
                <!-- 提示文字 -->
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <p style="margin: 0; font-size: 0.85rem; color: var(--muted-color); line-height: 1.6;">验证码可能加载较慢，请耐心等待 | 点击图片可刷新验证码</p>
                </div>
                
                <!-- 底部输入框和按钮（水平排列，1:1宽度） -->
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <input type="text" id="captcha-input" placeholder="请输入验证码" maxlength="6" style="flex: 1;" autocomplete="off" />
                    <button id="captcha-submit" role="button" class="shadow" style="flex: 1;">验证</button>
                </div>
            </div>
        </div>
        
        <script>
        (function() {
            var commentForm = document.getElementById('comment-form');
            if (!commentForm) {
                console.error('找不到评论表单');
                return;
            }
            var captchaModal = document.getElementById('captcha-modal');
            var captchaInput = document.getElementById('captcha-input');
            var captchaImage = document.getElementById('captcha-image');
            var captchaError = document.getElementById('captcha-error');
            var captchaSubmitBtn = document.getElementById('captcha-submit');
            var captchaCloseBtn = document.getElementById('captcha-close');
            var captchaCodeInput = document.getElementById('captcha_code');
            var isVerifying = false;
            var formSubmitHandlerRemoved = false; // 标记是否已移除事件监听器
            
            // 初始化图片加载监听器
            console.log('[CAPTCHA] 初始化验证码图片监听器');
            console.log('[CAPTCHA] 图片元素:', captchaImage);
            console.log('[CAPTCHA] 初始图片 URL:', captchaImage ? captchaImage.src : '(未找到)');
            
            if (captchaImage) {
                // 监听初始加载
                captchaImage.addEventListener('load', function() {
                    console.log('[CAPTCHA] ✅ 初始图片加载成功');
                    console.log('[CAPTCHA] 图片尺寸:', captchaImage.naturalWidth + 'x' + captchaImage.naturalHeight);
                });
                
                captchaImage.addEventListener('error', function(e) {
                    console.error('[CAPTCHA] ❌ 初始图片加载失败');
                    console.error('[CAPTCHA] 错误事件:', e);
                    console.error('[CAPTCHA] 失败的 URL:', captchaImage.src);
                });
            }
            
            // 简单对话框元素
            var simpleDialog = document.getElementById('simple-dialog');
            var simpleDialogOverlay = document.getElementById('simple-dialog-overlay');
            var simpleDialogMessage = document.getElementById('simple-dialog-message');
            var simpleDialogOk = document.getElementById('simple-dialog-ok');
            
            // 显示简单对话框
            function showSimpleDialog(message) {
                simpleDialogMessage.textContent = message;
                simpleDialog.classList.add('show');
                simpleDialogOverlay.classList.add('show');
            }
            
            // 隐藏简单对话框
            function hideSimpleDialog() {
                simpleDialog.classList.remove('show');
                simpleDialogOverlay.classList.remove('show');
            }
            
            // 对话框确定按钮事件
            simpleDialogOk.addEventListener('click', hideSimpleDialog);
            simpleDialogOverlay.addEventListener('click', hideSimpleDialog);
            
            // 刷新验证码图片
            function refreshCaptcha() {
                var oldSrc = captchaImage.src;
                var newSrc = captchaImage.src.split('?')[0] + '?' + Math.random();
                console.log('[CAPTCHA] 刷新验证码图片');
                console.log('[CAPTCHA] 旧 URL:', oldSrc);
                console.log('[CAPTCHA] 新 URL:', newSrc);
                
                // 清除之前的错误状态
                captchaInput.value = '';
                captchaError.style.display = 'none';
                
                // 添加加载事件监听器
                captchaImage.onload = function() {
                    console.log('[CAPTCHA] ✅ 图片加载成功');
                    console.log('[CAPTCHA] 图片尺寸:', captchaImage.naturalWidth + 'x' + captchaImage.naturalHeight);
                    console.log('[CAPTCHA] 图片实际显示尺寸:', captchaImage.offsetWidth + 'x' + captchaImage.offsetHeight);
                };
                
                captchaImage.onerror = function(e) {
                    console.error('[CAPTCHA] ❌ 图片加载失败');
                    console.error('[CAPTCHA] 错误事件:', e);
                    console.error('[CAPTCHA] 失败的 URL:', captchaImage.src);
                    console.error('[CAPTCHA] 图片状态:', {
                        complete: captchaImage.complete,
                        naturalWidth: captchaImage.naturalWidth,
                        naturalHeight: captchaImage.naturalHeight
                    });
                };
                
                // 设置新的图片源
                captchaImage.src = newSrc;
                
                // 检查图片是否立即加载（可能已缓存）
                setTimeout(function() {
                    if (captchaImage.complete) {
                        if (captchaImage.naturalWidth === 0 || captchaImage.naturalHeight === 0) {
                            console.warn('[CAPTCHA] ⚠️ 图片加载完成但尺寸为 0，可能是空响应');
                        } else {
                            console.log('[CAPTCHA] ✅ 图片已缓存，尺寸:', captchaImage.naturalWidth + 'x' + captchaImage.naturalHeight);
                        }
                    } else {
                        console.log('[CAPTCHA] ⏳ 图片正在加载中...');
                    }
                }, 100);
            }
            
            // 显示验证码弹窗
            function showCaptchaModal() {
                captchaModal.style.display = 'flex';
                captchaInput.value = '';
                captchaError.style.display = 'none';
                refreshCaptcha();
                setTimeout(function() {
                    captchaInput.focus();
                }, 100);
            }
            
            // 隐藏验证码弹窗
            function hideCaptchaModal() {
                captchaModal.style.display = 'none';
                captchaInput.value = '';
                captchaError.style.display = 'none';
            }
            
            // 验证验证码
            function verifyCaptcha() {
                var code = captchaInput.value.trim();
                
                console.log('开始验证验证码:', code);
                
                if (!code) {
                    captchaError.textContent = '请输入验证码';
                    captchaError.style.display = 'block';
                    return;
                }
                
                if (isVerifying) {
                    console.log('正在验证中，忽略重复请求');
                    return;
                }
                
                isVerifying = true;
                captchaSubmitBtn.disabled = true;
                captchaSubmitBtn.textContent = '验证中...';
                captchaError.style.display = 'none';
                
                // AJAX 验证验证码
                var xhr = new XMLHttpRequest();
                var verifyUrl = '<?php echo Typecho_Common::url('/action/captcha-verify', Helper::options()->index); ?>';
                console.log('验证 URL:', verifyUrl);
                
                xhr.open('POST', verifyUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onreadystatechange = function() {
                    console.log('AJAX 状态变化:', xhr.readyState, 'HTTP 状态:', xhr.status);
                    
                    if (xhr.readyState === 4) {
                        isVerifying = false;
                        captchaSubmitBtn.disabled = false;
                        captchaSubmitBtn.textContent = '验证';
                        
                        console.log('请求完成，状态码:', xhr.status);
                        console.log('响应内容:', xhr.responseText);
                        
                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                console.log('解析后的响应:', response);
                                
                                if (response.success) {
                                    console.log('验证成功！');
                                    // 显示成功对话框
                                    showSimpleDialog('评论成功，感谢您的支持！');
                                    
                                    // 验证成功，将验证码添加到隐藏输入框
                                    captchaCodeInput.value = code;
                                    hideCaptchaModal();
                                    
                                    // 移除事件监听器，避免无限循环
                                    if (!formSubmitHandlerRemoved) {
                                        commentForm.removeEventListener('submit', submitHandler);
                                        formSubmitHandlerRemoved = true;
                                    }
                                    
                                    // 延迟提交表单，让用户看到成功提示
                                    setTimeout(function() {
                                        hideSimpleDialog();
                                        // 提交表单 - 使用更可靠的方法
                                        console.log('提交表单...');
                                        // 方法1: 尝试使用原生 submit 方法
                                        try {
                                            // 获取表单的原型方法
                                            var formSubmit = HTMLFormElement.prototype.submit;
                                            if (formSubmit) {
                                                formSubmit.call(commentForm);
                                            } else {
                                                throw new Error('submit 方法不可用');
                                            }
                                        } catch (e) {
                                            console.log('使用备用方法提交表单');
                                            // 方法2: 创建一个隐藏的提交按钮并点击
                                            var submitBtn = document.createElement('button');
                                            submitBtn.type = 'submit';
                                            submitBtn.style.display = 'none';
                                            submitBtn.style.visibility = 'hidden';
                                            commentForm.appendChild(submitBtn);
                                            setTimeout(function() {
                                                submitBtn.click();
                                                setTimeout(function() {
                                                    if (commentForm.contains(submitBtn)) {
                                                        commentForm.removeChild(submitBtn);
                                                    }
                                                }, 100);
                                            }, 50);
                                        }
                                    }, 800); // 显示800ms后提交
                                } else {
                                    console.log('验证失败:', response.message);
                                    // 显示错误对话框
                                    showSimpleDialog('验证码错误！');
                                    
                                    // 显示友好的错误提示（弹窗内）
                                    captchaError.textContent = response.message || '验证码错误，请重新输入';
                                    captchaError.style.display = 'block';
                                    // 添加动画效果
                                    captchaError.classList.remove('shake');
                                    setTimeout(function() {
                                        captchaError.classList.add('shake');
                                    }, 10);
                                    // 清空输入框并刷新验证码
                                    captchaInput.value = '';
                                    setTimeout(function() {
                                        captchaInput.focus();
                                    }, 100);
                                    refreshCaptcha();
                                }
                            } catch (e) {
                                console.error('解析响应失败:', e);
                                captchaError.textContent = '验证失败，请重试';
                                captchaError.style.display = 'block';
                                refreshCaptcha();
                            }
                        } else {
                            // 显示详细的错误信息以便调试
                            var errorMsg = '网络错误，请重试';
                            if (xhr.status === 404) {
                                errorMsg = '验证接口不存在，请检查插件是否正确激活';
                            } else if (xhr.status === 500) {
                                errorMsg = '服务器错误，请稍后重试';
                            } else if (xhr.status === 0) {
                                errorMsg = '无法连接到服务器，请检查网络';
                            } else {
                                errorMsg = '网络错误（状态码：' + xhr.status + '），请重试';
                            }
                            captchaError.textContent = errorMsg;
                            captchaError.style.display = 'block';
                            console.error('验证码验证失败:', xhr.status, xhr.responseText);
                        }
                    }
                };
                
                xhr.onerror = function() {
                    console.error('AJAX 请求发生错误');
                    isVerifying = false;
                    captchaSubmitBtn.disabled = false;
                    captchaSubmitBtn.textContent = '验证';
                    captchaError.textContent = '网络错误，请检查网络连接';
                    captchaError.style.display = 'block';
                };
                
                var postData = 'captcha_code=' + encodeURIComponent(code);
                console.log('发送数据:', postData);
                xhr.send(postData);
            }
            
            // 表单提交处理函数
            var submitHandler = function(e) {
                e.preventDefault();
                console.log('表单提交被拦截');
                
                // 如果已经有验证码（可能是之前验证过的），直接提交
                if (captchaCodeInput.value) {
                    console.log('已有验证码，直接提交');
                    if (!formSubmitHandlerRemoved) {
                        commentForm.removeEventListener('submit', submitHandler);
                        formSubmitHandlerRemoved = true;
                    }
                    // 使用原生 submit 方法
                    try {
                        HTMLFormElement.prototype.submit.call(commentForm);
                    } catch (e) {
                        // 备用方法：创建提交按钮
                        var submitBtn = document.createElement('button');
                        submitBtn.type = 'submit';
                        submitBtn.style.display = 'none';
                        commentForm.appendChild(submitBtn);
                        submitBtn.click();
                        setTimeout(function() {
                            if (commentForm.contains(submitBtn)) {
                                commentForm.removeChild(submitBtn);
                            }
                        }, 100);
                    }
                    return;
                }
                
                // 显示验证码弹窗
                console.log('显示验证码弹窗');
                showCaptchaModal();
            };
            
            // 拦截表单提交
            commentForm.addEventListener('submit', submitHandler);
            
            // 弹窗中的验证按钮
            captchaSubmitBtn.addEventListener('click', verifyCaptcha);
            
            // 弹窗中的关闭按钮
            captchaCloseBtn.addEventListener('click', hideCaptchaModal);
            
            // 按 Enter 键提交验证码
            captchaInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    verifyCaptcha();
                }
            });
            
            // 点击弹窗背景关闭
            captchaModal.addEventListener('click', function(e) {
                if (e.target === captchaModal) {
                    hideCaptchaModal();
                }
            });
            
            // 全局函数，供图片点击刷新使用
            window.refreshCaptcha = refreshCaptcha;
            
            // 检查评论是否提交成功（Typecho提交成功后会跳转回页面并添加锚点）
            (function() {
                // 检查URL锚点（Typecho会在评论提交成功后添加 #comment-xxx 锚点）
                if (window.location.hash && window.location.hash.startsWith('#comment-')) {
                    // 检查是否是刚提交的评论（通过sessionStorage标记）
                    var commentSubmitted = sessionStorage.getItem('comment_submitted');
                    if (commentSubmitted === 'true') {
                        sessionStorage.removeItem('comment_submitted');
                        setTimeout(function() {
                            showSimpleDialog('评论成功，感谢您的支持！');
                        }, 500);
                    }
                }
                
                // 在表单提交前设置标记
                commentForm.addEventListener('submit', function() {
                    if (captchaCodeInput.value) {
                        sessionStorage.setItem('comment_submitted', 'true');
                    }
                }, true);
            })();
        })();
        </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
