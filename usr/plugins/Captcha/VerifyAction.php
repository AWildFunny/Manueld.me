<?php

class Captcha_VerifyAction extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        // 关闭错误显示，确保只输出 JSON
        $oldErrorReporting = error_reporting(E_ALL);
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        
        try {
            $request = $this->request;
            $captchaCode = $request->get('captcha_code');
            
            // 设置响应头为 JSON
            $response = $this->response;
            $response->setContentType('application/json');
            
            if (empty($captchaCode)) {
                $response->throwJson(array(
                    'success' => false,
                    'message' => '请输入验证码'
                ));
                return;
            }
            
            // 获取插件目录路径 - 使用与 Action.php 完全相同的方法（已验证可行）
            $dir = dirname(__FILE__) . '/securimage/';
            require_once dirname(__FILE__) . '/securimage/securimage.php';
            $img = new securimage();
            
            // securimage 会自动启动 session，所以不需要手动启动
            
            // 手动验证验证码，不调用 check() 方法（因为 check() 会清除验证码）
            // 我们需要验证但不清除，因为后续表单提交还需要验证
            $storedCode = '';
            $namespace = $img->namespace;
            $expiryTime = isset($img->expiry_time) && is_numeric($img->expiry_time) && $img->expiry_time > 0 
                          ? $img->expiry_time 
                          : 900; // 默认 900 秒（15分钟）
            
            // 从 Session 获取存储的验证码
            if (isset($_SESSION['securimage_code_value'][$namespace]) &&
                trim($_SESSION['securimage_code_value'][$namespace]) != '') {
                // 检查验证码是否过期
                if (isset($_SESSION['securimage_code_ctime'][$namespace])) {
                    $ctime = $_SESSION['securimage_code_ctime'][$namespace];
                    if (time() - $ctime < $expiryTime) {
                        $storedCode = $_SESSION['securimage_code_value'][$namespace];
                    }
                } else {
                    // 如果没有创建时间，假设未过期（兼容旧版本）
                    $storedCode = $_SESSION['securimage_code_value'][$namespace];
                }
            }
            
            if (empty($storedCode)) {
                $response->throwJson(array(
                    'success' => false,
                    'message' => '验证码已过期，请刷新后重试'
                ));
                return;
            }
            
            // 比较验证码（考虑大小写敏感性）
            $codeEntered = trim($captchaCode);
            $codeStored = trim($storedCode);
            
            // 检查是否需要区分大小写
            $caseSensitive = isset($img->case_sensitive) ? $img->case_sensitive : false;
            if (!$caseSensitive) {
                $codeEntered = strtolower($codeEntered);
                $codeStored = strtolower($codeStored);
            }
            
            if ($codeEntered !== $codeStored) {
                $response->throwJson(array(
                    'success' => false,
                    'message' => '验证码错误, 请重新输入'
                ));
                return;
            }
            
            // 验证成功，但不清除验证码（保留给后续表单提交验证）
            // filter() 方法会在表单提交时再次验证并清除
            
            $response->throwJson(array(
                'success' => true,
                'message' => '验证成功'
            ));
        } catch (Exception $e) {
            // 恢复错误设置
            error_reporting($oldErrorReporting);
            ini_set('display_errors', $oldDisplayErrors);
            
            // 捕获异常并返回错误信息
            try {
                $response = $this->response;
                $response->setContentType('application/json');
                $response->throwJson(array(
                    'success' => false,
                    'message' => '验证失败：' . $e->getMessage()
                ));
            } catch (Exception $e2) {
                // 如果响应对象也无法使用，直接输出 JSON
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => false,
                    'message' => '验证失败：' . $e->getMessage()
                ));
                exit;
            }
        } catch (\Throwable $e) {
            // 恢复错误设置
            error_reporting($oldErrorReporting);
            ini_set('display_errors', $oldDisplayErrors);
            
            // 捕获所有错误（包括 PHP 7+ 的 Error）
            try {
                $response = $this->response;
                $response->setContentType('application/json');
                $response->throwJson(array(
                    'success' => false,
                    'message' => '验证失败：' . $e->getMessage()
                ));
            } catch (\Throwable $e2) {
                // 如果响应对象也无法使用，直接输出 JSON
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => false,
                    'message' => '验证失败：' . $e->getMessage()
                ));
                exit;
            }
        } finally {
            // 确保恢复错误设置
            error_reporting($oldErrorReporting);
            ini_set('display_errors', $oldDisplayErrors);
        }
    }
}

