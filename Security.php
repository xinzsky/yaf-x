<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */

/**
 * 网站安全类
 * 网站安全问题：SQL注入、XSS攻击、CSRF、机器人、爬虫、密码存放、帐号安全
 * 防止SQL注入：SQL语句中所有来自用户输入的部分都需要quote、标识符保护(``)。
 * 防止XSS攻击：
 * （1）输入时做验证、处理、过滤。
 *     输入的地方包括：$_GET $_POST $_REQUEST $_COOKIE $_FILES
 * （2）输出时做HTML encode、js代码中引号(单、双引号)转义。
 *     输出的地方包括：HTML页面（包括js代码）、JSON数据，不能直接使用echo等方法输出。
 * （3）前端js不能直接使用用户输入的数据，比如显示在页面上、构造js语句，只能使用服务器端返回的数据。
 *     包括不要通过window.location.href获取URL，如果需要则必须做过滤或转码。
 * （4）url跳转：只对可信域和白名单进行跳转。
 * 
 * 对HTML特殊字符(< > & ' ")的处理：
 *     输入时不进行HTML encode处理，都原封不动的保存到db中，输出时都htmlencode（包括单引号、双引号）。
 *     特别是需要输出到表单元素中的内容，比如：<input name="xss" value="<?php echo $xss;?>">
 *     如果 $xss='"onfocus="alert(/xss/)"'里面的双引号没有转码则会被xss攻击。
 * 
 * 对富文本编辑器输入数据的处理：
 *     基于白名单对HTML标签进行过滤：除了指定的标签（属性、属性值都符合要求），其他标签都过滤掉。
 *     过滤掉不需要的标签，但标签之间的内容需要保留，并且其中的HTML特殊字符需要encode。
 *     白名单中不使用不安全的标签、属性(事件属性、style属性等)、属性值。
 *     保证在数据库存放的就是安全的，输出时不需要做任何处理。
 * 
 * 数据存储和显示的一致性问题：
 *     必须考虑显示和存储数据的一致性问题，即显示在浏览器端和存储在服务器端后台的数据可能因为转义而变得不一致。
 *     譬如存储在服务器端的后台原始数据包含了5种HTML特殊字符，但是没有转义，为了防止 XSS 攻击，在浏览器端输出时对 HTML 特殊字符进行了转义。
 *     当再度将表单提交时，存储的内容将会变成转义后的值。
 *     当使用 JavaScript 操作表单元素，需要使用到表单元素的值时，必须考虑到值可能已经被转义。
 * 
 * 保证帐号安全：
 * （1）cookie使用httponly特性。
 * （2）在一些关键功能，完全不能信任cookie，必需要用户输入口令。
 *     如：修改口令，支付，修改电子邮件，查看用户的敏感信息等等。
 * （3）限制cookie的过期时间。
 * （4）使用https协议
 * 
 * 上传文件的安全措施：
 * （1）检查文件扩展名、文件MIME类型、文件大小、图片尺寸、图片内容。
 * （2）如果使用用户端的文件名，则需要检查文件名长度、组成字符，并且对文件名进行一下处理和过滤。
 * 
 * 两次请求模型：
 * 重要的操作都需要至少发送两次请求：一次告诉网站你是谁，你想做什么操作，第二次才是该操作的请求。
 * 网站会根据你的状态和操作，对这些操作做些要求：
 * （1）如果该操作需要登录，你未登录，必须先登录。
 * （2）如果该操作需要token，就会生成一个token：防止CRSF、重复提交。
 * （3）验证码：防止机器人。
 * （4）密码：防止cookie/session id泄漏。
 * （5）短信/邮件：防止非本人操作。
 * 
 * php-5.4:
 * PHP 5.4.0 废除了register_globals, magic_quotes以及安全模式
 * register_globals:是否自动注册EGPCS (Environment, GET, POST, Cookie, Server) 变量为全局变量。         
 * Magic Quote：当打开时，所有的 '（单引号），"（双引号），\（反斜线）和 NULL 字符都会被自动加上一个反斜线进行转义。
 *              这和 addslashes() 作用完全相同。
 * */

// These constants may be changed without breaking existing hashes.
 define("PBKDF2_HASH_ALGORITHM", "sha256");
 define("PBKDF2_ITERATIONS", 1000);
 define("PBKDF2_SALT_BYTE_SIZE", 24);
 define("PBKDF2_HASH_BYTE_SIZE", 24);

 define("HASH_SECTIONS", 4);
 define("HASH_ALGORITHM_INDEX", 0);
 define("HASH_ITERATION_INDEX", 1);
 define("HASH_SALT_INDEX", 2);
 define("HASH_PBKDF2_INDEX", 3);
    
class Gek_Security {
    
    const TOKEN = '__GEK_TOKEN';
    
    /**
     * 基于kses的HTML过滤类。
     * 功能：
     * （1）过滤掉HTML文本中不想要的标签和属性，只保留指定的标签和属性。
     *     只删除标签，标签之间的内容仍保留。
     *     只删除允许的标签中不需要的属性，允许的属性仍保留。
     *     允许的属性的取值如果是非法的协议开始，比如javascript:alert(1);，会过滤javascript:，仍保留alert(1)部分。
     *     合法的协议http://同样会过滤掉http:部分。？？这是oop版本的bug，在oop/php5.class.kses的958行中，把string2参数换成string即可修复。
     * （2）会对属性值进行几个指定的检查。
     * 特点： 
     * （1）标签和属性名大小写不敏感。
     * （2）能正确的处理空白字符。
     * （3）属性值可以使用单引号、双引号、没有引号。
     * （4）支持无值属性，比如selected checked。
     * （5）支持xhtml的<img /> <br />
     * （6）没有引号的属性值会自动添加单引号。
     * （7）对不规范的HTML标签会规范化。
     * （8）会对多余的> <字符进行处理。
     * （9）支持对属性值的长度、取值范围做检查，避免出现缓冲区溢出和拒绝服务攻击。
     *     比如，你可以限制<iframe src= width= height=>的宽高在合理的范围。
     * （10）已经内置了URL协议的白名单，属性值只能以http: https: ftp: mailto:协议开始。
     *      不能是其他URL协议，比如javascript:, java:, about:, telnet:..
     *      并且对其中的空白、大小写、HTML entities("jav&#97;script:") and 
     * 
     * 允许的标签中禁止使用的属性：
     * （1）style
     * （2）事件属性
     * 
     * wordpress 对kses进行了完善，支持了对style属性的安全css属性的支持。
     * 
     * html_clean参数说明：
     * @param type $str：需要进行处理的字符串。
     * @param type $allowed_html: 允许的HTML元素及其属性，数组类型，格式如下：
     * $allowed_html = array(
     *           'b' => array(),            //空数组表示没有允许的属性。
     *           'p' => array('align' => 1),//表示只允许属性align
     *           'font' => array('size' => array('maxval' => 20)) //表示允许size属性，且属性值最大为20
     *           'a' => array('default' => 1)) //表示使用<a>标签默认的安全属性。
     * $allowed_html = array();          表示删除所有标签和属性。
     * $allowed_html = array('default'); 表示只允许默认的标签和属性。
     * 属性值支持的检查有：
     *  'maxlen', 'maxval','minlen', 'minval' and 'valueless'（取值y/n）.
     * 
     * 对HTML特殊字符的处理：
     *  对特殊字符< > &会转成对应的实体表示，但对 ' " 则不会。 
     *  bug: 如果字符串有 '<'存在，则会过滤掉 < ...<tag> 这些内容。 
     *  bug: <img src="url"/> 会过滤掉src属性， 而<img src="url" /> 则不会过滤掉src属性，已经修复该bug。
     * 
     * 安全的URL协议有:
     *  http, https, ftp, news, nntp, telnet, gopher and mailto.
     *  目前只允许http https ftp mailto
     */
    public static function html_clean($str, $allowed_html=array('default'))
    {
        $disallowd_tags = array(
            'style', 'script', 'iframe','frame', 'frameset', 'embed','applet','object',
            'base',  'basefont', 'bgsound', 'blink',    
            'html', 'head', 'meta', 'title', 'body', 'link',
            'ilayer',  'layer'
        );
        
        $default_allowed_tags = array(
		'address' => array(),
		'a' => array(
			'href' => true,
			'rel' => true,
			'rev' => true,
			'name' => true,
			'target' => true,
		),
		'abbr' => array(),
		'acronym' => array(),
		'area' => array(
			'alt' => true,
			'coords' => true,
			'href' => true,
			'nohref' => true,
			'shape' => true,
			'target' => true,
		),
		'article' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'aside' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'b' => array(),
		'big' => array(),
		'blockquote' => array(
			'cite' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'br' => array(),
		'button' => array(
			'disabled' => true,
			'name' => true,
			'type' => true,
			'value' => true,
		),
		'caption' => array(
			'align' => true,
		),
		'cite' => array(
			'dir' => true,
			'lang' => true,
		),
		'code' => array(),
		'col' => array(
			'align' => true,
			'char' => true,
			'charoff' => true,
			'span' => true,
			'dir' => true,
			'valign' => true,
			'width' => true,
		),
		'del' => array(
			'datetime' => true,
		),
		'dd' => array(),
		'dfn' => array(),
		'details' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'open' => true,
			'xml:lang' => true,
		),
		'div' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'dl' => array(),
		'dt' => array(),
		'em' => array(),
		'fieldset' => array(),
		'figure' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'figcaption' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'font' => array(
			'color' => true,
			'face' => true,
			'size' => true,
		),
		'footer' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'form' => array(
			'action' => true,
			'accept' => true,
			'accept-charset' => true,
			'enctype' => true,
			'method' => true,
			'name' => true,
			'target' => true,
		),
		'h1' => array(
			'align' => true,
		),
		'h2' => array(
			'align' => true,
		),
		'h3' => array(
			'align' => true,
		),
		'h4' => array(
			'align' => true,
		),
		'h5' => array(
			'align' => true,
		),
		'h6' => array(
			'align' => true,
		),
		'header' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'hgroup' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'hr' => array(
			'align' => true,
			'noshade' => true,
			'size' => true,
			'width' => true,
		),
		'i' => array(),
		'img' => array(
			'alt' => true,
			'align' => true,
			'border' => true,
			'height' => true,
			'hspace' => true,
			'longdesc' => true,
			'vspace' => true,
			'src' => true,
			'usemap' => true,
			'width' => true,
		),
		'ins' => array(
			'datetime' => true,
			'cite' => true,
		),
		'kbd' => array(),
		'label' => array(
			'for' => true,
		),
		'legend' => array(
			'align' => true,
		),
		'li' => array(
			'align' => true,
			'value' => true,
		),
		'map' => array(
			'name' => true,
		),
		'mark' => array(),
		'menu' => array(
			'type' => true,
		),
		'nav' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'p' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'pre' => array(
			'width' => true,
		),
		'q' => array(
			'cite' => true,
		),
		's' => array(),
		'samp' => array(),
		'span' => array(
			'dir' => true,
			'align' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'section' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'small' => array(),
		'strike' => array(),
		'strong' => array(),
		'sub' => array(),
		'summary' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'sup' => array(),
		'table' => array(
			'align' => true,
			'bgcolor' => true,
			'border' => true,
			'cellpadding' => true,
			'cellspacing' => true,
			'dir' => true,
			'rules' => true,
			'summary' => true,
			'width' => true,
		),
		'tbody' => array(
			'align' => true,
			'char' => true,
			'charoff' => true,
			'valign' => true,
		),
		'td' => array(
			'abbr' => true,
			'align' => true,
			'axis' => true,
			'bgcolor' => true,
			'char' => true,
			'charoff' => true,
			'colspan' => true,
			'dir' => true,
			'headers' => true,
			'height' => true,
			'nowrap' => true,
			'rowspan' => true,
			'scope' => true,
			'valign' => true,
			'width' => true,
		),
		'textarea' => array(
			'cols' => true,
			'rows' => true,
			'disabled' => true,
			'name' => true,
			'readonly' => true,
		),
		'tfoot' => array(
			'align' => true,
			'char' => true,
			'charoff' => true,
			'valign' => true,
		),
		'th' => array(
			'abbr' => true,
			'align' => true,
			'axis' => true,
			'bgcolor' => true,
			'char' => true,
			'charoff' => true,
			'colspan' => true,
			'headers' => true,
			'height' => true,
			'nowrap' => true,
			'rowspan' => true,
			'scope' => true,
			'valign' => true,
			'width' => true,
		),
		'thead' => array(
			'align' => true,
			'char' => true,
			'charoff' => true,
			'valign' => true,
		),
		'title' => array(),
		'tr' => array(
			'align' => true,
			'bgcolor' => true,
			'char' => true,
			'charoff' => true,
			'valign' => true,
		),
		'tt' => array(),
		'u' => array(),
		'ul' => array(
			'type' => true,
		),
		'ol' => array(
			'start' => true,
			'type' => true,
		),
		'var' => array(),
        );
        
        $kses = new Gek_Utils_Kses5();
        
        if(empty($allowed_html)) { // 删除所有标签和属性。
            return $kses->Parse($str);
        } else if(count($allowed_html) == 1 && $allowed_html[0] == 'default') {
            $kses->setAllowedHtml($default_allowed_tags);
            return $kses->Parse($str);
        } else {
            foreach($allowed_html as $tag => $attr) {
                $tag = strtolower($tag);
                
                if(isset($disallowd_tags[$tag])) { // 标签是不允许的则过滤掉。
                    continue;
                }
                
                if(count($attr) == 1 && $attr['default'] == 1) { //标签采用默认属性
                    if(isset($default_allowed_tags[$tag])) {
                        $kses->AddHTML($tag, $default_allowed_tags[$tag]);
                    } else {
                        $kses->AddHTML($tag,array());
                    }
                } else {
                    $kses->AddHTML($tag,$attr);
                }
            }
            return $kses->Parse($str);
        }
    }
    
    /**
     * TOKEN机制：
     * （1）根据请求的操作生成一个token，并存放到session中，包括其expire时间，并通过表单隐藏元素返回。
     * （2）用户提交请求，需要对token进行验证，包括过期时间。如果验证不通过则重新生成一个token。
     * 
     * 参数：$action：指明该token适用操作，必须是唯一的，最好是_MODULE_CONTROLLER_ACTION格式。
     *      $lifetime:token的生存时间，单位s,不同的操作最好指定不同的时间，特别是大表单。
     */
    public static function token_get($action, $lifetime=600)
    {
        $token = md5(uniqid(mt_rand(), TRUE));
        $_SESSION[self::TOKEN][$action]['value'] = $token;
        $_SESSION[self::TOKEN][$action]['expire'] = time() + $lifetime;
        return $token;
    }
    
    // 对token进行验证，如果验证失败则重新生成一个新的token并返回，验证成功则返回true。
    public static function token_verify($token, $action, $lifetime=600)
    {
        if(empty($token)) {
            return self::token_get($action, $lifetime);
        } 
        
        // token是否在session里存在
        if(!isset($_SESSION[self::TOKEN][$action]['value']) || !isset($_SESSION[self::TOKEN][$action]['expire']) ) {
            return self::token_get($action, $lifetime);
        } else if($_SESSION[self::TOKEN][$action]['expire'] < time()) { // token是否过期
            return self::token_get($action, $lifetime);
        } else if($_SESSION[self::TOKEN][$action]['value'] != $token) { // token是否正确
            return self::token_get($action, $lifetime);
        } else {
            unset($_SESSION[self::TOKEN][$action]); // 释放token
            return true;
        }
    }
    
    /**
     * 一般的跳转逻辑： A->B->C->A
     * （1）用户请求a.php，a.php检测用户未登录，根据自身URL+salt生成key，302重定向到show_login.php?from=url&key=xxx
     *     （URL需要encode）
     * （2）show_login.php对from url和key进行HTML编码（避免XSS攻击）后当作登录表单的两个隐藏元素返回。
     * （3）用户post登录请求给login.php,login.php检查登录成功，并验证from url+salt=key（需要先HTML解码），
     *     如果key匹配则302重定向from url。（这里还可以检查一下from url的域名是否符合要求）
     * http://www.2cto.com/News/200803/24346.html
     * 
     */
    public static function redirect_goto($to_url, $salt, $self_host='')
    {
        $req_uri = $_SERVER['REQUEST_URI'];
        
        // 用户通过代理服务器上网的，则HTTP_HOST值可能是代理服务器的IP或HOST。
        // HTTP_X_FORWARDED_HOST/HTTP_POST头是可以伪造的，用户也可以加入这样的header。
        if($self_host == '') {
            $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? 
                    $_SERVER['HTTP_X_FORWARDED_HOST'] : 
                    (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
            if($host == '') {
                $host = $_SERVER["SERVER_NAME"];
            }
        } else {
            $host = $self_host;
        }
        
        $port = '';
        if(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
            $port = (int)$_SERVER['SERVER_PORT'];
            $port = ':' . $port;
        }
        
        $scheme = 'http://';
        if(isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])) {
            $scheme = 'https://';
        }
        
        $self_url = $scheme . $host . $port . $req_uri;
        $key = sha1($self_url.$salt);
        $self_url = Gek_Utils::base64url_encode($self_url); //采用base64编码
        if(strpos($to_url, '?') === false) {
            $to_url .= '?';
        } else {
            $to_url .= '&';
        }
        
        $to_url .= "from={$self_url}&key={$key}";
        header("Location: $to_url"); // 302
        return true;
    }
    
    public static function redirect_comeback($salt, $domain_list=array())
    {
        // 支持GET POST方法
        if(!isset($_REQUEST['from']) || !isset($_REQUEST['key']) || 
            empty($_REQUEST['from']) || empty($_REQUEST['key'])) {
            return false;
        }
        
        // 输出到表单隐藏元素时为来避免xss，都会进行HTML转码。
        $from = Gek_Utils::base64url_decode($_REQUEST['from']);
        $key = $_REQUEST['key'];
        
        // 验证是不是合法的来源URL
        if(sha1($from.$salt) != $key) {
            return false;
        }
 
        // 如果存在跳转域名白名单，检查跳转URL的域名是否在白名单中。
        if(!empty($domain_list)) {
            $host = parse_url($from, PHP_URL_HOST);
            if($host === false) return false;
            trim($host, '.');
            $host = '.' . $host; // host.com -> .host.com
            $host_len = strlen($host);
            $is_in = false;
            foreach($domain_list as $domain) {
                trim($domain,'.');
                $domain = '.' . $domain; // domain.com -> .domain.com
                $domain_len = strlen($domain);
                if($host_len >= $domain_len) {
                    $dom = substr($host, $host_len - $domain_len);
                    if(strtolower($dom) == strtolower($domain)) {
                        $is_in = true;
                        break;
                    }
                }
            }
            
            if(!$is_in) {
                return false;
            }
        }
        
        header("Location: $from"); // 302
        return true;
    }
    
    /**
    * 重定向到指定的 URL
    *
    * @param string $url 要重定向的 url
    * @param int $delay 等待多少秒以后跳转
    * @param bool $js 指示是否返回用于跳转的 JavaScript 代码
    * @param bool $jsWrapped 指示返回 JavaScript 代码时是否使用 <script> 标签进行包装
    * @param bool $return 指示是否返回生成的 JavaScript 代码
    */
   public static function redirect_delay($url, $delay = 0, $js = false, $jsWrapped = true, $return = false)
   {
       $delay = (int)$delay;
        if (!$js) {
           if (headers_sent() && $delay > 0) {
               echo <<<EOT
<html>
<head>
<meta http-equiv="refresh" content="{$delay};URL={$url}" />
</head>
</html>
EOT;
                exit;
            } else {
                header("Location: {$url}");
                exit;
            }
        }

       $out = '';
       if ($jsWrapped) {
           $out .= '<script language="JavaScript" type="text/javascript">';
       }
       $url = rawurlencode($url);
       if ($delay > 0) {
           $out .= "window.setTimeOut(function () { document.location='{$url}'; }, {$delay});";
       } else {
           $out .= "document.location='{$url}';";
       }
       if ($jsWrapped) {
           $out .= '</script>';
       }

       if ($return) {
           return $out;
       }

       echo $out;
       exit;
   }
   
   /**
    * Password Hashing With PBKDF2 (http://crackstation.net/hashing-security.htm).
    * Copyright (c) 2013, Taylor Hornby
    * All rights reserved.
    * 
    * 重要：保护密码的安全就是不能猜出密码的明文。
    * 方法：hash(salt.password)
    * salt:
    *     每个密码加入不同的salt，用户注册或者修改密码，都应该使用新的salt。
    *     salt的长度不能太短，最好和密码的hash值一样长。
    *     salt的最好采用mcrypt_create_iv() /dev/random or /dev/urandom来获得随机字符串。
    *     salt需要存放到数据库中（可以分字段存储，也可以和密码hash存在一起），用于校验密码是否正确。
    *     salt可以不需要保密。
    * 密码hash函数：可以采用sha256(32Byte),最好不要用md5 sha1. php中可以使用hash('sha256',...)
    * 建议密码长度至少为12个字符的密码，并且其中至少包括两个字母、两个数字和两个符号。
    */
    
    /**
     * 对密码进行hash，返回密码的hash值和salt，格式如下：
     * return format: algorithm:iterations:salt:hash
     * @param type $password
     * @return type
     */
    public static function password_hash($password)
    {
        $salt = base64_encode(mcrypt_create_iv(PBKDF2_SALT_BYTE_SIZE, MCRYPT_DEV_URANDOM));
        return PBKDF2_HASH_ALGORITHM . ":" . PBKDF2_ITERATIONS . ":" .  $salt . ":" . 
            base64_encode(self::pbkdf2(
                PBKDF2_HASH_ALGORITHM,
                $password,
                $salt,
                PBKDF2_ITERATIONS,
                PBKDF2_HASH_BYTE_SIZE,
                true
            ));
    }
    
    /**
     * 对用户输入的密码进行验证。
     * @param type $password     用户输入密码
     * @param type $correct_hash 数据库里存放的正确密码
     * @return boolean
     */
    public static function password_verify($password, $correct_hash)
    {
        $params = explode(":", $correct_hash);
        if(count($params) < HASH_SECTIONS)
           return false; 
        $pbkdf2 = base64_decode($params[HASH_PBKDF2_INDEX]);
        return self::slow_equals(
            $pbkdf2,
          self::pbkdf2(
                $params[HASH_ALGORITHM_INDEX],
                $password,
                $params[HASH_SALT_INDEX],
                (int)$params[HASH_ITERATION_INDEX],
                strlen($pbkdf2),
                true
            )
        );
    }

    // Compares two strings $a and $b in length-constant time.
    // 大概5～10ms
    public static function slow_equals($a, $b)
    {
        $diff = strlen($a) ^ strlen($b);
        for($i = 0; $i < strlen($a) && $i < strlen($b); $i++)
        {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $diff === 0; 
    }

    /*
     * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
     * $algorithm - The hash algorithm to use. Recommended: SHA256
     * $password - The password.
     * $salt - A salt that is unique to the password.
     * $count - Iteration count. Higher is better, but slower. Recommended: At least 1000.
     * $key_length - The length of the derived key in bytes.
     * $raw_output - If true, the key is returned in raw binary format. Hex encoded otherwise.
     * Returns: A $key_length-byte key derived from the password and salt.
     *
     * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
     *
     * This implementation of PBKDF2 was originally created by https://defuse.ca
     * With improvements by http://www.variations-of-shadow.com
     */
    public static function pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false)
    {
        $algorithm = strtolower($algorithm);
        if(!in_array($algorithm, hash_algos(), true))
            trigger_error('PBKDF2 ERROR: Invalid hash algorithm.', E_USER_ERROR);
        if($count <= 0 || $key_length <= 0)
            trigger_error('PBKDF2 ERROR: Invalid parameters.', E_USER_ERROR);

        if (function_exists("hash_pbkdf2")) {
            // The output length is in NIBBLES (4-bits) if $raw_output is false!
            if (!$raw_output) {
                $key_length = $key_length * 2;
            }
            return hash_pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output);
        }

        $hash_length = strlen(hash($algorithm, "", true));
        $block_count = ceil($key_length / $hash_length);

        $output = "";
        for($i = 1; $i <= $block_count; $i++) {
            // $i encoded as 4 bytes, big endian.
            $last = $salt . pack("N", $i);
            // first iteration
            $last = $xorsum = hash_hmac($algorithm, $last, $password, true);
            // perform the other $count - 1 iterations
            for ($j = 1; $j < $count; $j++) {
                $xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true));
            }
            $output .= $xorsum;
        }

        if($raw_output)
            return substr($output, 0, $key_length);
        else
            return bin2hex(substr($output, 0, $key_length));
    }
}
