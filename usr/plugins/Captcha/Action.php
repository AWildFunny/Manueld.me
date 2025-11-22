<?php

class Captcha_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        // 调试日志文件路径
        $debugLog = dirname(__FILE__) . '/debug.log';
        
        // 记录调试信息
        $debugInfo = array();
        $debugInfo[] = '[' . date('Y-m-d H:i:s') . '] CAPTCHA Action 开始执行';
        
        /** 防止跨站 */
        $referer = $this->request->getReferer();
        $debugInfo[] = 'Referer: ' . ($referer ?: '(空)');
        
        if (empty($referer)) {
            $debugInfo[] = '错误: Referer 为空，终止执行';
            file_put_contents($debugLog, implode("\n", $debugInfo) . "\n\n", FILE_APPEND);
            exit;
        }
        
        $refererPart = parse_url($referer);
        $siteUrl = Helper::options()->siteUrl;
        $currentPart = parse_url($siteUrl);
        
        $debugInfo[] = '站点 URL: ' . $siteUrl;
        $debugInfo[] = 'Referer 解析: ' . json_encode($refererPart);
        $debugInfo[] = '站点 URL 解析: ' . json_encode($currentPart);
        
        // 安全获取路径，如果不存在或为空则使用默认值 '/'
        $refererPath = isset($refererPart['path']) && !empty($refererPart['path']) ? $refererPart['path'] : '/';
        $currentPath = isset($currentPart['path']) && !empty($currentPart['path']) ? $currentPart['path'] : '/';
        
        $debugInfo[] = 'Referer 路径: ' . $refererPath;
        $debugInfo[] = '站点路径: ' . $currentPath;
        
        // 确保路径以 '/' 开头，便于比较（路径已保证不为空）
        if ($refererPath[0] !== '/') {
            $refererPath = '/' . $refererPath;
        }
        if ($currentPath[0] !== '/') {
            $currentPath = '/' . $currentPath;
        }
        
        $debugInfo[] = '规范化后的 Referer 路径: ' . $refererPath;
        $debugInfo[] = '规范化后的站点路径: ' . $currentPath;
        
        // 检查主机名和路径前缀
        $hostCheck = isset($refererPart['host']) && isset($currentPart['host']);
        $hostMatch = $hostCheck && ($refererPart['host'] == $currentPart['host']);
        $pathCheck = 0 === strpos($refererPath, $currentPath);
        
        $debugInfo[] = '主机名检查: ' . ($hostCheck ? '通过' : '失败');
        $debugInfo[] = '主机名匹配: ' . ($hostMatch ? '是' : '否');
        if ($hostCheck) {
            $debugInfo[] = 'Referer 主机: ' . $refererPart['host'];
            $debugInfo[] = '站点主机: ' . $currentPart['host'];
        }
        $debugInfo[] = '路径前缀检查: ' . ($pathCheck ? '通过' : '失败');
        $debugInfo[] = 'strpos 结果: ' . strpos($refererPath, $currentPath);
        
        if (!isset($refererPart['host']) || !isset($currentPart['host']) ||
            $refererPart['host'] != $currentPart['host'] ||
            0 !== strpos($refererPath, $currentPath)) {
            $debugInfo[] = '错误: 跨站检查失败，终止执行';
            file_put_contents($debugLog, implode("\n", $debugInfo) . "\n\n", FILE_APPEND);
            exit;
        }
        
        $debugInfo[] = '跨站检查通过，继续执行';
    
        $dir = dirname(__FILE__) . '/securimage/';
        $debugInfo[] = '开始加载 securimage 库，路径: ' . $dir;
        
        try {
            require_once dirname(__FILE__) . '/securimage/securimage.php';
            $debugInfo[] = 'securimage 库加载成功';
        } catch (Exception $e) {
            $debugInfo[] = '错误: 加载 securimage 库失败 - ' . $e->getMessage();
            file_put_contents($debugLog, implode("\n", $debugInfo) . "\n\n", FILE_APPEND);
            exit;
        }
        
        try {
            $img = new securimage();
            $debugInfo[] = 'securimage 对象创建成功';
        } catch (Exception $e) {
            $debugInfo[] = '错误: 创建 securimage 对象失败 - ' . $e->getMessage();
            file_put_contents($debugLog, implode("\n", $debugInfo) . "\n\n", FILE_APPEND);
            exit;
        }

        $options = Typecho_Widget::widget('Widget_Options');

        $fontsArray = array('04b03.ttf', 'AHGBold.ttf', 'atkinsoutlinemedium-regular.ttf', 'decorative-stylisticblackout-regular.ttf', 'okrienhmk.ttf', 'ttstepha.ttf', 'vtckomixationhand.ttf');
        $fontsKey = array_rand($fontsArray);
        $fontsFile = $dir . 'fonts/' . $fontsArray[$fontsKey];


        //验证码字体 - 使用 ShortBaby 字体作为默认（更清晰易读）
        // 如果当前字体是行楷等艺术字体，改用 ShortBaby 字体
        $currentFont = $options->plugin('Captcha')->ttf_file;
        // 如果使用的是行楷字体（stxingkai.ttf），改用 ShortBaby 字体
        if ($currentFont == 'stxingkai.ttf') {
            // 使用 ShortBaby.ttf 作为默认字体（更清晰易读）
            $fontsFile = $dir . 'fonts/ShortBaby.ttf';
        } else {
            $fontsFile = $dir . 'fonts/'.$currentFont;
        }
        $img->ttf_file = $fontsFile;

        //验证码背景 - 恢复背景图片设置（如果用户配置了）
        if($options->plugin('Captcha')->is_background) {
            $img->background_directory = $dir . '/backgrounds/';
        }
        //背景颜色
        $img->image_bg_color = new Securimage_Color($options->plugin('Captcha')->image_bg_color);

        //验证码颜色 - 使用更深的颜色以提高对比度
        $img->text_color = new Securimage_Color('#000000'); // 使用纯黑色，对比度最高

	//验证码位数
	$img->code_length = $options->plugin('Captcha')->code_length;

        //自定义验证码
        $img->use_wordlist = $options->plugin('Captcha')->use_wordlist;
        $img->wordlist = explode("\n", $options->plugin('Captcha')->wordlist);
        $img->wordlist_file = $dir . 'words/words.txt';

        //干扰线颜色 - 使用更浅的颜色
        $img->line_color = new Securimage_Color('#cccccc');

        //干扰线、扭曲度 - 大幅减少干扰以提高字体可读性
        // 减少干扰线数量（从默认3条减少到0-1条）
        $numLines = isset($options->plugin('Captcha')->num_lines) ? intval($options->plugin('Captcha')->num_lines) : 3;
        $img->num_lines = max(0, min(1, $numLines)); // 限制在0-1条之间，大幅减少干扰
        
        // 大幅降低扭曲度（从默认0.3降低到0.05-0.1），让字体更清晰
        $perturbation = isset($options->plugin('Captcha')->perturbation) ? floatval($options->plugin('Captcha')->perturbation) : 0.3;
        $img->perturbation = max(0.05, min(0.1, $perturbation * 0.3)); // 降低到原来的30%，限制在0.05-0.1之间，让字体几乎不扭曲

        //签名内容、颜色、字体
        $img->signature_color = new Securimage_Color($options->plugin('Captcha')->signature_color);
        $img->image_signature = $options->plugin('Captcha')->image_signature;
        $img->signature_font = $dir . 'fonts/'.$options->plugin('Captcha')->signature_font;

        //高度宽度 - 增大尺寸以提高可读性（字体大小会根据高度自动计算，约为高度的40%）
        $imageHeight = isset($options->plugin('Captcha')->image_height) ? intval($options->plugin('Captcha')->image_height) : 80;
        $imageWidth = isset($options->plugin('Captcha')->image_width) ? intval($options->plugin('Captcha')->image_width) : 215;
        // 确保最小尺寸，增大高度以间接增大字体
        $img->image_height = max(100, $imageHeight); // 最小100px高度，字体约为40px
        $img->image_width = max(250, $imageWidth); // 最小250px宽度，确保有足够空间

        $debugInfo[] = '验证码配置完成，准备输出图片';
        $debugInfo[] = '字体文件: ' . $img->ttf_file;
        $debugInfo[] = '图片尺寸: ' . $img->image_width . 'x' . $img->image_height;
        
        try {
            $debugInfo[] = '开始调用 $img->show()';
            file_put_contents($debugLog, implode("\n", $debugInfo) . "\n", FILE_APPEND);
            $img->show('');
            $debugInfo[] = '图片输出完成';
            file_put_contents($debugLog, implode("\n", $debugInfo) . "\n\n", FILE_APPEND);
        } catch (Exception $e) {
            $debugInfo[] = '错误: 输出图片失败 - ' . $e->getMessage();
            $debugInfo[] = '错误堆栈: ' . $e->getTraceAsString();
            file_put_contents($debugLog, implode("\n", $debugInfo) . "\n\n", FILE_APPEND);
            exit;
        } catch (\Throwable $e) {
            $debugInfo[] = '错误: 输出图片失败 (Throwable) - ' . $e->getMessage();
            $debugInfo[] = '错误堆栈: ' . $e->getTraceAsString();
            file_put_contents($debugLog, implode("\n", $debugInfo) . "\n\n", FILE_APPEND);
            exit;
        }
    }
}
