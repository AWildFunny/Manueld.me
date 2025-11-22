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
        
        // /** 防止跨站 */
        // $referer = $this->request->getReferer();
        // $addStep('获取 Referer', array('referer' => $referer ?: '(空)'));
        
        // if (empty($referer)) {
        //     $addStep('错误: Referer 为空，终止执行');
        //     $outputDebugHeader();
        //     exit;
        // }
        
        // $refererPart = parse_url($referer);
        // $siteUrl = Helper::options()->siteUrl;
        // $currentPart = parse_url($siteUrl);
        
        // $addStep('解析 URL', array(
        //     'siteUrl' => $siteUrl,
        //     'refererPart' => $refererPart,
        //     'currentPart' => $currentPart
        // ));
        
        // // 安全获取路径，如果不存在或为空则使用默认值 '/'
        // $refererPath = isset($refererPart['path']) && !empty($refererPart['path']) ? $refererPart['path'] : '/';
        // $currentPath = isset($currentPart['path']) && !empty($currentPart['path']) ? $currentPart['path'] : '/';
        
        // // 确保路径以 '/' 开头，便于比较（路径已保证不为空）
        // if ($refererPath[0] !== '/') {
        //     $refererPath = '/' . $refererPath;
        // }
        // if ($currentPath[0] !== '/') {
        //     $currentPath = '/' . $currentPath;
        // }
        
        // // 检查主机名和路径前缀
        // $hostCheck = isset($refererPart['host']) && isset($currentPart['host']);
        // $hostMatch = $hostCheck && ($refererPart['host'] == $currentPart['host']);
        // $pathCheck = 0 === strpos($refererPath, $currentPath);
        
        // $addStep('跨站检查', array(
        //     'refererPath' => $refererPath,
        //     'currentPath' => $currentPath,
        //     'hostCheck' => $hostCheck,
        //     'hostMatch' => $hostMatch,
        //     'refererHost' => $hostCheck ? $refererPart['host'] : null,
        //     'currentHost' => $hostCheck ? $currentPart['host'] : null,
        //     'pathCheck' => $pathCheck,
        //     'strposResult' => strpos($refererPath, $currentPath)
        // ));
        
        // if (!isset($refererPart['host']) || !isset($currentPart['host']) ||
        //     $refererPart['host'] != $currentPart['host'] ||
        //     0 !== strpos($refererPath, $currentPath)) {
        //     $addStep('错误: 跨站检查失败，终止执行');
        //     $outputDebugHeader();
        //     exit;
        // }

        // $addStep('跨站检查通过，继续执行');
    
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
            // 将警告转为异常，以便捕获
            throw new Exception("GD Error: $errstr in $errfile on line $errline");
        });
        
        // 先输出调试信息到响应头（注释掉以避免提前发送头）
        // $outputDebugHeader();
        
        // 禁用 Typecho 的自动响应处理（如果可能）
        if (method_exists($this->response, 'enableAutoSendHeaders')) {
            $this->response->enableAutoSendHeaders(false);
        }
        
        // 在调用 show() 之前，先输出调试信息（因为 show() 会立即 exit）（注释掉）
        // $addStep('开始调用 $img->show()');
        
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
            $gdInfo = gd_info();
            $addStep('GD 信息', array(
                'version' => $gdInfo['GD Version'],
                'freetype' => isset($gdInfo['FreeType Support']) ? $gdInfo['FreeType Support'] : false
            ));
            if (!isset($gdInfo['FreeType Support']) || !$gdInfo['FreeType Support']) {
                $addStep('警告: GD 未启用 FreeType 支持，使用备用模式');
                $img->ttf_file = ''; // 禁用 TTF，使用 GD 内置字体
            }

            // 输出调试信息（临时启用以捕获信息）
            $outputDebugHeader();
        }
        
        // 输出调试信息（注释掉，避免在图片路径发送头）
        // $outputDebugHeader();
        
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
            // 输出调试信息（注释掉）
            // $outputDebugHeader();
            
            // 关键：完全禁用 Typecho 的响应处理机制
            // 注意：$this->response 是 Typecho\Widget\Response 包装类
            // 真正的 Response 对象在内部的 $response 属性中，或者使用 Response::getInstance()
            
            // 1. 获取真正的 Response 对象
            // $this->response 是 Typecho\Widget\Response 包装类
            // 真正的 Response 对象是 Typecho\Response，可以通过 getInstance() 获取
            $realResponse = null;
            try {
                // 方法1：通过 Response::getInstance() 获取单例（推荐）
                $realResponse = \Typecho\Response::getInstance();
                $addStep('获取 Response 对象成功（通过 getInstance()）');
            } catch (Exception $e) {
                // 方法2：如果方法1失败，通过反射访问包装类内部的 response 属性
                try {
                    $widgetResponseReflection = new ReflectionClass($this->response);
                    if ($widgetResponseReflection->hasProperty('response')) {
                        $responseProp = $widgetResponseReflection->getProperty('response');
                        $responseProp->setAccessible(true);
                        $realResponse = $responseProp->getValue($this->response);
                        $addStep('获取 Response 对象成功（通过反射）');
                    }
                } catch (Exception $e2) {
                    $addStep('警告: 无法获取 Response 对象', array('error' => $e2->getMessage()));
                }
            }
            
            // 2. 清空 Response 对象的响应头设置，防止 Content-Type 被覆盖
            // 即使 sandbox 模式启用，如果 Response 对象中有响应头设置，仍然可能被发送
            if ($realResponse) {
                try {
                    $responseReflection = new ReflectionClass($realResponse);
                    // 清空 headers
                    if ($responseReflection->hasProperty('headers')) {
                        $headersProp = $responseReflection->getProperty('headers');
                        $headersProp->setAccessible(true);
                        $headersProp->setValue($realResponse, array());
                    }
                    // 清空 contentType
                    if ($responseReflection->hasProperty('contentType')) {
                        $contentTypeProp = $responseReflection->getProperty('contentType');
                        $contentTypeProp->setAccessible(true);
                        $contentTypeProp->setValue($realResponse, null);
                    }
                    $addStep('Response 对象的响应头已清空');
                } catch (Exception $e) {
                    $addStep('警告: 清空响应头失败', array('error' => $e->getMessage()));
                }
            }
            
            // 3. 禁用自动发送响应头
            if ($realResponse && method_exists($realResponse, 'enableAutoSendHeaders')) {
                $realResponse->enableAutoSendHeaders(false);
            } else if (method_exists($this->response, 'enableAutoSendHeaders')) {
                $this->response->enableAutoSendHeaders(false);
            }
            
            // 4. 启用 sandbox 模式，完全禁用 Response 对象的响应头发送
            // 这是最关键的：sandbox 模式下，sendHeaders() 会直接返回，不会发送任何响应头
            if ($realResponse && method_exists($realResponse, 'beginSandbox')) {
                $realResponse->beginSandbox();
                $addStep('Sandbox 模式已启用（通过 Response::getInstance()）');
            } else {
                // 如果方法不存在，使用反射设置 sandbox 属性
                try {
                    $targetResponse = $realResponse ?: $this->response;
                    $responseReflection = new ReflectionClass($targetResponse);
                    if ($responseReflection->hasProperty('sandbox')) {
                        $sandboxProp = $responseReflection->getProperty('sandbox');
                        $sandboxProp->setAccessible(true);
                        $sandboxProp->setValue($targetResponse, true);
                        $addStep('Sandbox 模式已启用（通过反射）');
                    } else {
                        $addStep('警告: 无法找到 sandbox 属性');
                    }
                } catch (Exception $e) {
                    $addStep('警告: 设置 sandbox 失败', array('error' => $e->getMessage()));
                }
            }
            
            // 注意：在清除输出缓冲之前，先记录状态，但不调用 outputDebugHeader()
            // 因为 outputDebugHeader() 会调用 header()，可能导致响应头被提前发送
            $addStep('Sandbox 模式设置完成，准备清除输出缓冲');
            
            // 3. 清除所有输出缓冲，包括 Typecho 的输出缓冲回调
            // 使用 ob_end_clean() 清除，这样回调不会被触发
            try {
                $obLevel = ob_get_level();
                $addStep('清除输出缓冲', array('obLevel' => $obLevel));
                for ($i = 0; $i < $obLevel; $i++) {
                    ob_end_clean();
                }
                $addStep('输出缓冲清除完成');
            } catch (Exception $e) {
                $addStep('警告: 清除输出缓冲时发生异常', array('error' => $e->getMessage()));
            }
            
            // 新增：清除后立即强制设置Content-Type，覆盖任何可能由回调设置的头
            header('Content-Type: image/png', true);
            
            // 4. 确保响应头不会被提前发送
            // 如果响应头已经被发送，show() 的输出可能会失败
            if (headers_sent($file, $line)) {
                restore_error_handler();
                $addStep('错误: 响应头已被发送', array('file' => $file, 'line' => $line));
                $outputDebugHeader();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array(
                    'success' => false,
                    'error' => '响应头已被发送: ' . $file . ':' . $line,
                    'debug' => $debugInfo
                ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
            
            // 5. 记录调用 show() 前的最终状态
            $addStep('准备调用 show()', array(
                'obLevel' => ob_get_level(),
                'headersSent' => headers_sent(),
                'sandboxEnabled' => $realResponse ? (function() use ($realResponse) {
                    try {
                        $reflection = new ReflectionClass($realResponse);
                        if ($reflection->hasProperty('sandbox')) {
                            $prop = $reflection->getProperty('sandbox');
                            $prop->setAccessible(true);
                            return $prop->getValue($realResponse);
                        }
                    } catch (Exception $e) {
                        return null;
                    }
                    return null;
                })() : null
            ));
            
            // 关键：在调用 show() 之前，不要调用 outputDebugHeader()
            // 因为 outputDebugHeader() 会调用 header()，可能导致响应头被提前发送
            // 我们只在出错时才输出调试信息
            
            // 6. 直接调用 show()，让它自己处理所有事情
            // 关键：show() 会立即设置响应头并输出图片，然后 exit()
            // 由于 sandbox 模式已启用，Typecho 的 sendHeaders() 不会发送响应头
            // 由于输出缓冲已被清除，show() 的输出会直接发送到浏览器
            // 由于 exit()，Typecho 的响应处理机制不会被触发
            restore_error_handler(); // 恢复错误处理，避免干扰 show()
            
            // 最后检查：确认 sandbox 模式确实已启用
            if ($realResponse) {
                try {
                    $reflection = new ReflectionClass($realResponse);
                    if ($reflection->hasProperty('sandbox')) {
                        $sandboxProp = $reflection->getProperty('sandbox');
                        $sandboxProp->setAccessible(true);
                        $sandboxValue = $sandboxProp->getValue($realResponse);
                        if (!$sandboxValue) {
                            // 如果 sandbox 模式没有启用，强制启用
                            $sandboxProp->setValue($realResponse, true);
                            $addStep('警告: Sandbox 模式未启用，已强制启用');
                        }
                    }
                } catch (Exception $e) {
                    // 忽略错误
                }
            }
            
            // 关键：直接调用 doImage() 和 output()，绕过 show() 的调用链
            // 这样可以确保响应头设置和输出在同一个执行流程中完成
            // 注意：doImage() 会生成图片，output() 会设置响应头并输出图片，然后 exit()
            try {
                // 使用反射调用 protected 方法
                $reflection = new ReflectionClass($img);
                
                // 先调用 doImage() 生成图片
                $doImageMethod = $reflection->getMethod('doImage');
                $doImageMethod->setAccessible(true);
                
                $addStep('开始调用 doImage()（通过反射）');
                // 注意：不调用 outputDebugHeader()，避免响应头被提前发送
                
                // 调用 doImage()，它会生成图片并调用 output()
                // output() 会设置响应头并输出图片，然后 exit()

                // 新增：启动输出缓冲捕获 doImage 的输出
                ob_start();
                $doImageMethod->invoke($img);
                $generatedOutput = ob_get_contents();
                ob_end_clean();

                // 检查捕获的输出是否为空
                if (empty($generatedOutput)) {
                    restore_error_handler();
                    $addStep('错误: 图片生成无输出');
                    $outputDebugHeader();
                    
                    // 输出备用错误图片
                    header('Content-Type: image/png', true);
                    $errorImg = imagecreatetruecolor(250, 100);
                    $bg = imagecolorallocate($errorImg, 255, 255, 255);
                    $fg = imagecolorallocate($errorImg, 255, 0, 0);
                    imagefill($errorImg, 0, 0, $bg);
                    imagestring($errorImg, 5, 10, 40, 'No Image Output', $fg);
                    imagepng($errorImg);
                    imagedestroy($errorImg);
                    exit;
                } else {
                    // 如果有输出，直接 echo 并 exit
                    echo $generatedOutput;
                    exit;
                }
                
                // doImage() 内部会调用 output()，output() 会 exit()
                // 如果执行到这里，说明 output() 没有正常退出（不应该发生）
                restore_error_handler();
                $addStep('警告: doImage() 执行完成但未退出');
                $outputDebugHeader();
                exit;
            } catch (ReflectionException $e) {
                // 如果反射失败，回退到使用 show() 方法
                restore_error_handler();
                $addStep('警告: 反射调用失败，回退到 show() 方法', array('error' => $e->getMessage()));
                $outputDebugHeader();
                
                // 在调用 show() 之前，先手动设置响应头
                // 这样可以确保即使输出缓冲回调被触发，响应头也不会被覆盖
                header('Content-Type: image/png', true);
                
                $addStep('开始调用 show() 方法');
                // 注意：不调用 outputDebugHeader()，避免响应头被提前发送
                
                // 调用 show()，它会 exit()
                // show() 内部会调用 doImage() -> output()
                // output() 会设置响应头（包括 Content-Type: image/png）并输出图片，然后 exit()
                $img->show('');
            } catch (Exception $e) {
                // 捕获所有其他异常
                restore_error_handler();
                $addStep('错误: 调用图片生成方法时发生异常', array(
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ));
                $outputDebugHeader();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array(
                    'success' => false,
                    'error' => '图片生成失败: ' . $e->getMessage(),
                    'debug' => $debugInfo
                ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
            
            // 如果执行到这里，说明 show() 没有正常退出（不应该发生）
            restore_error_handler();
            $addStep('警告: $img->show() 执行完成但未退出');
            $outputDebugHeader();
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
