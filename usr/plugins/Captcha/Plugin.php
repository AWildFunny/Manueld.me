<?php
/**
 * 验证插件提供一个机器难以识别的图片，以防止机器人的评论攻击，同时也可以防止跨站的评论提交.
 * 验证码插件是基于<a href="http://www.phpcaptcha.org">securimage</a>开发的.
 * 
 * @package Typecho Captcha
 * @author qining & Kimi & m00nfly
 * @version 1.0.0
 * @link http://typecho.org
 */
class Captcha_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        if (!function_exists('gd_info')) {
            throw new Typecho_Plugin_Exception(_t('对不起, 您的主机不支持 gd 扩展, 无法正常使用此功能'));
        }
    
        // 加载 Action 类文件
        require_once dirname(__FILE__) . '/Action.php';
        require_once dirname(__FILE__) . '/VerifyAction.php';
    
        Typecho_Plugin::factory('Widget_Feedback')->comment = array('Captcha_Plugin', 'filter');
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('Captcha_Plugin', 'finishComment');
        Typecho_Plugin::factory('Widget_Feedback')->trackback = array('Captcha_Plugin', 'filter');
        Typecho_Plugin::factory('Widget_XmlRpc')->pingback = array('Captcha_Plugin', 'filter');
        
        Helper::addAction('captcha', 'Captcha_Action');
        Helper::addAction('captcha-verify', 'Captcha_VerifyAction');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {

        $dir = dirname(__FILE__) . '/securimage/';
        $fileList = getDirlist($dir . 'fonts');

        $image_height = new Typecho_Widget_Helper_Form_Element_Text('image_height', NULL, '80',
        _t('验证码图片高度'), _t('默认值为80'));
        $form->addInput($image_height);

        $image_width = new Typecho_Widget_Helper_Form_Element_Text('image_width', NULL, '215',
        _t('验证码图片宽度'), _t('默认值为215'));
        $form->addInput($image_width);

        $image_bg_color = new Typecho_Widget_Helper_Form_Element_Text('image_bg_color', NULL, '#ffffff',
        _t('背景颜色'), _t('默认值为#ffffff'));
        $form->addInput($image_bg_color);

        $is_background = new Typecho_Widget_Helper_Form_Element_Radio('is_background', 
        array('1' => '启用',
        '0' => '不启用'),
        1, '是否启用背景图片', '如果启用背景图片，请自行修改插件目录下securimage/backgrounds/目录中的背景图片');
        $form->addInput($is_background);

        $num_lines = new Typecho_Widget_Helper_Form_Element_Text('num_lines', NULL, '3',
        _t('干扰线数量'), _t('最小值为0，必须是整数'));
        $form->addInput($num_lines);

        $line_color = new Typecho_Widget_Helper_Form_Element_Text('line_color', NULL, '#666666',
        _t('干扰线颜色'), _t('默认值为#666666'));
        $form->addInput($line_color);

        $perturbation = new Typecho_Widget_Helper_Form_Element_Text('perturbation', NULL, '0.3',
        _t('验证码扭曲指数'), _t('取值范围从0-1.0，默认为0.3'));
        $form->addInput($perturbation);

        $text_color = new Typecho_Widget_Helper_Form_Element_Text('text_color', NULL, '#666666',
        _t('验证码颜色'), _t('默认值为#666666'));
        $form->addInput($text_color);

        $ttf_file = new Typecho_Widget_Helper_Form_Element_Select('ttf_file', $fileList,
        'stxingkai.ttf', '验证码字体', '如果使用中文验证码，请务必选择中文字体文件，否则无法演示验证码');
        $form->addInput($ttf_file);

	    $code_length = new Typecho_Widget_Helper_Form_Element_Text('code_length', NULL, '6',
        _t('验证码位数'), _t('最小值为1，默认为6，过大无法完整显示，仅对字母验证码有效'));
        $form->addInput($code_length);

        $image_signature = new Typecho_Widget_Helper_Form_Element_Text('image_signature', NULL, 'ccvita.com',
        _t('签名内容'));
        $form->addInput($image_signature);

        $signature_color = new Typecho_Widget_Helper_Form_Element_Text('signature_color', NULL, '#666666',
        _t('签名颜色'), _t('默认值为#666666'));
        $form->addInput($signature_color);

        $signature_font = new Typecho_Widget_Helper_Form_Element_Select('signature_font', $fileList,
        'AHGBold.ttf', '签名字体', '如果使用中文签名，请务必选择中文字体文件，否则无法演示验证码');
        $form->addInput($signature_font);


        $use_wordlist = new Typecho_Widget_Helper_Form_Element_Radio('use_wordlist', 
        array('1' => '启用',
        '0' => '不启用'),
        0, '是否自定义验证码文本', '如果自定义验证码文本');
        $form->addInput($use_wordlist);


        $wordlist = new Typecho_Widget_Helper_Form_Element_Textarea('wordlist', NULL, "中文\n验证码",
        _t('验证码文本'), _t('自定义验证码内容，每行一个<br><img src="' . Typecho_Common::url('/action/captcha', Helper::options()->index) 
        . '" alt="captcha" onclick="this.src = this.src + \'?\' + Math.random()" style="cursor: pointer" title="' . _t('点击图片刷新验证码') . '" /><hr>'));
        $form->addInput($wordlist);

        //是否启用Server酱通知
        $enable_ServerChain = new Typecho_Widget_Helper_Form_Element_Radio('enable_ServerChain',
                array('1' => '启用',
                '0' => '不启用'),
                0, 'Server酱 推送', _t('是否启用 <a href="https://sc3.ft07.com/">Server酱<sup>3</sup></a> (APP推送) 或 <a href="https://sct.ftqq.com/forward">Server酱<sup>Turbo</sup></a> (微信推送) 评论通知<br>
需要先注册并获取有效的 SendKey 或 SCKEY 才能进行设定。'));
        $form->addInput($enable_ServerChain);

        //设置 SendKey
        $sendKey = new Typecho_Widget_Helper_Form_Element_Text('sendKey', NULL, '',
                _t('SendKey'), _t('填写注册获取到的有效 Server酱 SendKey'));
        $form->addInput($sendKey);

        //设置消息分类 Tags
        $msgTags = new Typecho_Widget_Helper_Form_Element_Text('msgTags', NULL, 'Typecho评论',
                        _t('推送标签 Tags'), _t('设置消息推送的分类标签，仅对 Server酱<sup>3</sup> 有效，默认为 "Typecho评论" 多个标签使用 | 竖线分割'));
        $form->addInput($msgTags);
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    public static function output()
    {
        /** 修改如下 html 可自定义验证码和输入框的样式 **/
	    echo '<div id="captcha" class="row row-sm">'
           . '<div class="form-group col-sm-6 col-md-4">'
           . '<label>验证码 <span class="required text-danger">*</span></label>'
           . '<div><input type="text" class="form-control" name="captcha_code" maxlength="6" placeholder="图片上6个字母">'
           . '<a class="item-thumb-small lazy"><img src="' . Typecho_Common::url('/action/captcha', Helper::options()->index)
           . '" alt="captcha" onclick="this.src = this.src + \'?\' + Math.random()" style="cursor: pointer" title="点击图片刷新验证码"></a>'
           . '</div></div></div>';
    }
    
    /**
     * 评论过滤器
     * 
     * @access public
     * @param array $comment 评论结构
     * @param Typecho_Widget $post 被评论的文章
     * @param array $result 返回的结果上下文
     * @param string $api api地址
     * @return void
     */
    public static function filter($comment, $post, $result)
    {
        $captchaCode = Typecho_Request::getInstance()->captcha_code;
        if (empty($captchaCode)) {
            throw new Typecho_Widget_Exception(_t('请输入验证码'));
        }
        
        // 使用绝对路径加载 securimage.php（与 Action.php 保持一致）
        require_once dirname(__FILE__) . '/securimage/securimage.php';
        $img = new securimage();

        if (!$img->check($captchaCode)) {
            throw new Typecho_Widget_Exception(_t('验证码错误, 请重新输入'));
        }

        return $comment;
    }
    
    /**
     * 评论完成后的处理（评论成功插入数据库后调用）
     * 
     * @param Typecho_Widget $widget 评论组件
     * @return void
     */
    public static function finishComment($widget)
    {
        // 处理Server酱消息推送（在评论成功插入数据库后发送）
        $options = Typecho_Widget::widget('Widget_Options');
        if ($options->plugin('Captcha')->enable_ServerChain) {
            $sckey = $options->plugin('Captcha')->sendKey;
            $msgTags = $options->plugin('Captcha')->msgTags;
            $text = "主人，您的博客收到了新的评论，请注意查看哦！";
            
            // 获取评论信息（$widget 是 Feedback 实例，继承自 Comments）
            $comment = $widget->row;
            
            // 获取文章信息（通过 parentContent 属性）
            $parentContent = $widget->parentContent;
            if ($parentContent && isset($parentContent['title']) && isset($parentContent['permalink'])) {
                $postTitle = $parentContent['title'];
                $postPermalink = $parentContent['permalink'];
            } else {
                // 备用方案：从数据库查询
                $db = Typecho_Db::get();
                $postRow = $db->fetchRow($db->select('title', 'slug', 'type')
                    ->from('table.contents')
                    ->where('cid = ?', $comment['cid'])
                    ->limit(1));
                
                if ($postRow) {
                    $postTitle = $postRow['title'];
                    $router = Typecho_Router::url($postRow['type'] == 'page' ? 'page' : 'post', 
                        array('cid' => $comment['cid'], 'slug' => $postRow['slug']), 
                        $options->index);
                    $postPermalink = $options->siteUrl . ltrim($router, '/');
                } else {
                    $postTitle = '未知文章';
                    $postPermalink = $options->siteUrl;
                }
            }
            
            $desp = "**".$comment['author']."** 在 [「".$postTitle."」](".$postPermalink." \"".$postTitle."\") 中说到: \n\n > ".$comment['text'];
            $result = sc_send($text, $desp, $sckey, $msgTags);
        }
    }
}

function getDirlist($dirPath) {
	$fileList = array();

	if ( ($dh = opendir($dirPath)) !== false) {
		while (($file = readdir($dh)) !== false) {
			if ($file != '.' && $file != '..') {
				$fileList[$file] = $file;
			}
		}
		closedir($dh);
	}

	return $fileList;
}

/**
 * Server酱推送方法
 *
 * @param $text 消息内容
 * @param $desp 消息说明
 * @param $key Server酱SendKey
 * @return false|string
 */
function sc_send($text = '', $desp = '', $key = '[SENDKEY]', $tags = '')
{
    // 判断 $key 是否以 'sctp' 开头，并根据匹配到的数字部分拼接相应的 URL
    if (strpos($key, 'sctp') === 0) {
        // 使用正则表达式提取 sctp 开头后面的数字
        preg_match('/^sctp(\d+)t/', $key, $matches);
        $num = $matches[1];
        $url = "https://{$num}.push.ft07.com/send/{$key}.send";
        $postdata = http_build_query(array( 'text' => $text, 'desp' => $desp, 'tags' => $tags));
    } else {
        $url = "https://sctapi.ftqq.com/{$key}.send";
        $postdata = http_build_query(array( 'text' => $text, 'desp' => $desp, 'noip' => 1));
    }

    $opts = array('http' =>
    array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata));

    $context  = stream_context_create($opts);
    return file_get_contents($url, false, $context);
}