<?php

class Captcha_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        // 调试信息数组（用于输出到响应头）
        $debugInfo = array();
        $debugInfo['timestamp'] = date('Y-m-d H:i:s');
        $debugInfo['steps'] = array();
        
        // 添加调试步骤的辅助函数
        $addStep = function($step, $data = null) use (&$debugInfo) {
            $debugInfo['steps'][] = array(
                'step' => $step,
                'data' => $data,
                'time' => microtime(true)
            );
        };
        
        // 输出调试信息到响应头的辅助函数
        $outputDebugHeader = function() use (&$debugInfo) {
            $debugJson = json_encode($debugInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // 响应头有长度限制，如果太长则截断
            if (strlen($debugJson) > 8000) {
                $debugJson = substr($debugJson, 0, 8000) . '...(截断)';
            }
            header('X-Captcha-Debug: ' . base64_encode($debugJson));
        };
        
        $addStep('开始执行');
        
        /** 防止跨站 */
        $referer = $this->request->getReferer();
        $addStep('获取 Referer', array('referer' => $referer ?: '(空)'));
        
        if (empty($referer)) {
            $addStep('错误: Referer 为空，终止执行');
            $outputDebugHeader();
            exit;
        }
        
        $refererPart = parse_url($referer);
        $siteUrl = Helper::options()->siteUrl;
        $currentPart = parse_url($siteUrl);
        
        $addStep('解析 URL', array(
            'siteUrl' => $siteUrl,
            'refererPart' => $refererPart,
            'currentPart' => $currentPart
        ));
        
        // 安全获取路径，如果不存在或为空则使用默认值 '/'
        $refererPath = isset($refererPart['path']) && !empty($refererPart['path']) ? $refererPart['path'] : '/';
        $currentPath = isset($currentPart['path']) && !empty($currentPart['path']) ? $currentPart['path'] : '/';
        
        // 确保路径以 '/' 开头，便于比较（路径已保证不为空）
        if ($refererPath[0] !== '/') {
            $refererPath = '/' . $refererPath;
        }
        if ($currentPath[0] !== '/') {
            $currentPath = '/' . $currentPath;
        }
        
        // 检查主机名和路径前缀
        $hostCheck = isset($refererPart['host']) && isset($currentPart['host']);
        $hostMatch = $hostCheck && ($refererPart['host'] == $currentPart['host']);
        $pathCheck = 0 === strpos($refererPath, $currentPath);
        
        $addStep('跨站检查', array(
            'refererPath' => $refererPath,
            'currentPath' => $currentPath,
            'hostCheck' => $hostCheck,
            'hostMatch' => $hostMatch,
            'refererHost' => $hostCheck ? $refererPart['host'] : null,
            'currentHost' => $hostCheck ? $currentPart['host'] : null,
            'pathCheck' => $pathCheck,
            'strposResult' => strpos($refererPath, $currentPath)
        ));
        
        if (!isset($refererPart['host']) || !isset($currentPart['host']) ||
            $refererPart['host'] != $currentPart['host'] ||
            0 !== strpos($refererPath, $currentPath)) {
            $addStep('错误: 跨站检查失败，终止执行');
            $outputDebugHeader();
            exit;
        }
        
        $addStep('跨站检查通过，继续执行');
    
        $dir = dirname(__FILE__) . '/securimage/';
        $addStep('准备加载 securimage 库', array('dir' => $dir));
        
        try {
            require_once dirname(__FILE__) . '/securimage/securimage.php';
            $addStep('securimage 库加载成功');
        } catch (Exception $e) {
            $addStep('错误: 加载 securimage 库失败', array('error' => $e->getMessage()));
            $outputDebugHeader();
            exit;
        } catch (\Throwable $e) {
            $addStep('错误: 加载 securimage 库失败 (Throwable)', array('error' => $e->getMessage()));
            $outputDebugHeader();
            exit;
        }
        
        try {
            $img = new securimage();
            $addStep('securimage 对象创建成功');
        } catch (Exception $e) {
            $addStep('错误: 创建 securimage 对象失败', array('error' => $e->getMessage()));
            $outputDebugHeader();
            exit;
        } catch (\Throwable $e) {
            $addStep('错误: 创建 securimage 对象失败 (Throwable)', array('error' => $e->getMessage()));
            $outputDebugHeader();
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

        // 检查字体文件
        $fontFile = $img->ttf_file;
        $fontExists = file_exists($fontFile);
        $fontReadable = $fontExists && is_readable($fontFile);
        
        $addStep('验证码配置完成', array(
            'fontFile' => $fontFile,
            'fontExists' => $fontExists,
            'fontReadable' => $fontReadable,
            'imageWidth' => $img->image_width,
            'imageHeight' => $img->image_height,
            'gdInfo' => function_exists('gd_info') ? gd_info() : 'GD 扩展未安装'
        ));
        
        // 检查 GD 库
        if (!function_exists('imagecreate')) {
            $addStep('错误: GD 库 imagecreate 函数不存在');
            $outputDebugHeader();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'error' => 'GD 库 imagecreate 函数不存在',
                'debug' => $debugInfo
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        
        // 检查字体文件
        if (!$fontExists) {
            $addStep('错误: 字体文件不存在', array('fontFile' => $fontFile));
            $outputDebugHeader();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'error' => '字体文件不存在: ' . $fontFile,
                'debug' => $debugInfo
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        
        if (!$fontReadable) {
            $addStep('错误: 字体文件不可读', array('fontFile' => $fontFile));
            $outputDebugHeader();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'error' => '字体文件不可读: ' . $fontFile,
                'debug' => $debugInfo
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        
        // 在输出图片前，先输出调试信息到响应头
        $addStep('准备调用 $img->show()');
        
        // 设置错误处理，捕获可能的警告
        $oldErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$debugInfo, &$addStep) {
            $addStep('PHP 警告/错误', array(
                'errno' => $errno,
                'errstr' => $errstr,
                'errfile' => $errfile,
                'errline' => $errline
            ));
            return false; // 继续执行默认错误处理
        });
        
        // 先输出调试信息到响应头
        $outputDebugHeader();
        
        // 禁用 Typecho 的自动响应处理（如果可能）
        if (method_exists($this->response, 'enableAutoSendHeaders')) {
            $this->response->enableAutoSendHeaders(false);
        }
        
        // 在调用 show() 之前，先输出调试信息（因为 show() 会立即 exit）
        $addStep('开始调用 $img->show()');
        
        // 注意：不清除输出缓冲，让 show() 方法自己处理
        // show() 内部的 output() 方法会设置正确的响应头并输出图片
        
        // 检查关键属性
        $addStep('检查图片属性', array(
            'imageWidth' => $img->image_width,
            'imageHeight' => $img->image_height,
            'imageType' => $img->image_type,
            'ttfFile' => $img->ttf_file,
            'ttfFileExists' => file_exists($img->ttf_file),
            'ttfFileReadable' => is_readable($img->ttf_file)
        ));
        
        // 测试 GD 库是否能正常创建图片
        $testImg = @imagecreate(10, 10);
        if ($testImg === false) {
            $addStep('错误: GD 库无法创建图片资源');
            $outputDebugHeader();
            restore_error_handler();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'error' => 'GD 库无法创建图片资源',
                'debug' => $debugInfo
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        } else {
            imagedestroy($testImg);
            $addStep('GD 库测试通过，可以创建图片资源');
        }
        
        $outputDebugHeader();
        
        // 立即调用 $img->show()，避免 Typecho 的响应处理介入
        try {
            // 注意：$img->show() 内部会调用 exit()，所以后面的代码不会执行
            // 直接调用，不等待任何其他处理
            
            // 使用反射来检查 show() 方法是否存在
            $reflection = new ReflectionClass($img);
            if (!$reflection->hasMethod('show')) {
                throw new Exception('securimage 对象没有 show() 方法');
            }
            
            $addStep('确认 show() 方法存在，开始调用');
            $outputDebugHeader();
            
            // 清除所有输出缓冲，避免 Typecho 的输出缓冲回调干扰图片输出
            $obLevel = ob_get_level();
            for ($i = 0; $i < $obLevel; $i++) {
                ob_end_clean();
            }
            
            // 使用反射直接调用 doImage() 的各个步骤，但不调用 output()
            // 这样可以完全控制输出过程，避免 Typecho 的响应处理机制干扰
            $reflection = new ReflectionClass($img);
            
            $addStep('开始生成图片');
            $outputDebugHeader();
            
            // 手动执行 doImage() 的各个步骤
            // 1. 创建图片资源
            if (($img->use_transparent_text == true || $img->bgimg != '') && function_exists('imagecreatetruecolor')) {
                $imagecreate = 'imagecreatetruecolor';
            } else {
                $imagecreate = 'imagecreate';
            }
            
            $img->im = $imagecreate($img->image_width, $img->image_height);
            $img->tmpimg = $imagecreate($img->image_width * $img->iscale, $img->image_height * $img->iscale);
            
            if ($img->im === false || $img->tmpimg === false) {
                restore_error_handler();
                $addStep('错误: 无法创建图片资源');
                $outputDebugHeader();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array(
                    'success' => false,
                    'error' => '无法创建图片资源',
                    'debug' => $debugInfo
                ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
            
            // 2. 分配颜色
            $allocateColorsMethod = $reflection->getMethod('allocateColors');
            $allocateColorsMethod->setAccessible(true);
            $allocateColorsMethod->invoke($img);
            imagepalettecopy($img->tmpimg, $img->im);
            
            // 3. 设置背景
            $setBackgroundMethod = $reflection->getMethod('setBackground');
            $setBackgroundMethod->setAccessible(true);
            $setBackgroundMethod->invoke($img);
            
            // 4. 创建验证码
            $createCodeMethod = $reflection->getMethod('createCode');
            $createCodeMethod->setAccessible(true);
            $createCodeMethod->invoke($img);
            
            // 5. 绘制干扰
            if ($img->noise_level > 0) {
                $drawNoiseMethod = $reflection->getMethod('drawNoise');
                $drawNoiseMethod->setAccessible(true);
                $drawNoiseMethod->invoke($img);
            }
            
            // 6. 绘制文字
            $drawWordMethod = $reflection->getMethod('drawWord');
            $drawWordMethod->setAccessible(true);
            $drawWordMethod->invoke($img);
            
            // 7. 扭曲
            if ($img->perturbation > 0 && is_readable($img->ttf_file)) {
                $distortedCopyMethod = $reflection->getMethod('distortedCopy');
                $distortedCopyMethod->setAccessible(true);
                $distortedCopyMethod->invoke($img);
            }
            
            // 8. 绘制干扰线
            if ($img->num_lines > 0) {
                $drawLinesMethod = $reflection->getMethod('drawLines');
                $drawLinesMethod->setAccessible(true);
                $drawLinesMethod->invoke($img);
            }
            
            // 9. 添加签名
            if (trim($img->image_signature) != '') {
                $addSignatureMethod = $reflection->getMethod('addSignature');
                $addSignatureMethod->setAccessible(true);
                $addSignatureMethod->invoke($img);
            }
            
            // 10. 手动输出图片，完全绕过 Typecho 的响应处理
            $addStep('手动输出图片');
            $outputDebugHeader();
            
            // 设置图片响应头
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header('Content-Type: image/png', true);
            
            // 输出图片
            imagepng($img->im);
            imagedestroy($img->im);
            
            // 直接退出，避免 Typecho 的响应处理机制
            restore_error_handler();
            exit;
            
            // 如果执行到这里，说明 show() 没有正常退出（不应该发生）
            restore_error_handler();
            $addStep('警告: $img->show() 执行完成但未退出');
            $outputDebugHeader();
            exit;
        } catch (Exception $e) {
            restore_error_handler();
            $addStep('错误: 输出图片失败', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            $outputDebugHeader();
            // 输出 JSON 错误信息而不是图片
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'error' => '图片生成失败: ' . $e->getMessage(),
                'debug' => $debugInfo
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        } catch (\Throwable $e) {
            restore_error_handler();
            $addStep('错误: 输出图片失败 (Throwable)', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            $outputDebugHeader();
            // 输出 JSON 错误信息而不是图片
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'error' => '图片生成失败: ' . $e->getMessage(),
                'debug' => $debugInfo
            ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }
}
