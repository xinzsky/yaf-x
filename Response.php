<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


/* 对yaf response基础上进行扩展。
 * 可以通过yaf response类以及扩展类来进行输出。
 * 说明：
 * 输出是通过一个HTTP响应发给客户端的，一般包括下面三个部分：
 * （1）响应的状态码：200 301 302 304 400 403 404 413 500 502 503 504
 *      200 OK
 *      301 Moved Permanently
 *      302 Found (作为HTTP1.0的标准,以前叫做Moved Temporarily ,现在叫Found.)
 *      304 Not Modified
 *      400 Bad Request
 *      403 Forbidden
 *      404 Not Found
 *      413 Request Entity Too Large
 *      500 Internal Server Error
 *      502 Bad Gateway
 *      503 Service Unavailable 网站系统错误都返回503，这样对搜索引擎友好。
 *      504 Gateway Timeout
 * （2）响应的header：MIME Cache Redirect Cookie...
 *      Set-Cookie: ....        设置cookie必须在output content-type之前。
 *      Content-Type: text/html 必须在输出html内容之前设置。
 *      Content-Length
 *      Last-Modified
 *      Location: ...           用于301 302状态码,该header必须在set-cookie之后输出，不需要输出content-type了
 *      P3P
 *      浏览器缓存header: Expires Pragma Cache-Control
 *      Expires: HTTP1.0定义的，告诉浏览器内容过期时间，绝对时间GMT格式。
 *               如果声明不缓存，则必须是过去的时间，比如Expires: Thu, 19 Nov 1981 08:52:00 GMT
 *      Pragma:  HTTP/1.0定义的，只能用来告诉浏览器不要缓存内容，取值为Pragma: no-cache，
 *               它相当于HTTP/1.1中的Cache-Control: no-cache。
 *      Cache-Control:  HTPP1.1定义的，能够更准确的来控制缓存。
 *                      如果Expires和Pragma Cache-Control同时存在，Cache-control的值优先。
 * （3）响应的body: HTML JSON XML JS
 * （4）输出顺序 cookie redirect content-type body
 * 
 * yaf reponse类的功能：
 * （1）可以设置响应状态码和header：
 *      bool setHeader($name, $value, [$replace = 0, $response_code])
 *      bool setAllHeaders(array $headers);
 *      eg. setHeader( 'Content-Type', 'text/html; charset=utf-8' );
 *          setHeader( 'HTTP/1.1', '404 Not Found' );
 *          setAllHeaders(array('HTTP/1.1' => '404 Not Found', 'Pragma' => 'no-cache'));
 *          setAllHeaders(array('HTTP/1.1' => '302 Found', 'Location' => 'http://www.sohu.com/')
 * （2）可以设置响应body：
 *      bool setBody(string $body);
 * （3）把响应发送到客户端：
 *      bool response(void);
 * （4）302重定向: 调用此方法则不需要调用reponse()方法了。
 *      bool setRedirect(string  $url);
 *      输出：HTTP/1.1 302 Moved Temporarily
 * 
 *  对YAF Reponse类扩展的功能：生成一些header和body。
 */
class Gek_Response 
{
    const CACHE_PUBLIC          = 1;
    const CACHE_PRIVATE         = 2;
    const CACHE_PRIVATENOEXPIRE = 3;
    const CACHE_NOCACHE         = 4;
    
    private static $status = array(
        200	=> 'OK',
        201	=> 'Created',
        202	=> 'Accepted',
        203	=> 'Non-Authoritative Information',
        204	=> 'No Content',
        205	=> 'Reset Content',
        206	=> 'Partial Content',

        300	=> 'Multiple Choices',
        301	=> 'Moved Permanently',
        302	=> 'Found',
        304	=> 'Not Modified',
        305	=> 'Use Proxy',
        307	=> 'Temporary Redirect',

        400	=> 'Bad Request',
        401	=> 'Unauthorized',
        403	=> 'Forbidden',
        404	=> 'Not Found',
        405	=> 'Method Not Allowed',
        406	=> 'Not Acceptable',
        407	=> 'Proxy Authentication Required',
        408	=> 'Request Timeout',
        409	=> 'Conflict',
        410	=> 'Gone',
        411	=> 'Length Required',
        412	=> 'Precondition Failed',
        413	=> 'Request Entity Too Large',
        414	=> 'Request-URI Too Long',
        415	=> 'Unsupported Media Type',
        416	=> 'Requested Range Not Satisfiable',
        417	=> 'Expectation Failed',

        500	=> 'Internal Server Error',
        501	=> 'Not Implemented',
        502	=> 'Bad Gateway',
        503	=> 'Service Unavailable',
        504	=> 'Gateway Timeout',
        505	=> 'HTTP Version Not Supported'
    );
    
    private static $mime_types = array(	
        'hqx'	=>	'application/mac-binhex40',
        'cpt'	=>	'application/mac-compactpro',
        'csv'	=>	array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel'),
        'bin'	=>	'application/macbinary',
        'dms'	=>	'application/octet-stream',
        'lha'	=>	'application/octet-stream',
        'lzh'	=>	'application/octet-stream',
        'exe'	=>	array('application/octet-stream', 'application/x-msdownload'),
        'class'	=>	'application/octet-stream',
        'psd'	=>	'application/x-photoshop',
        'so'	=>	'application/octet-stream',
        'sea'	=>	'application/octet-stream',
        'dll'	=>	'application/octet-stream',
        'oda'	=>	'application/oda',
        'pdf'	=>	array('application/pdf', 'application/x-download'),
        'ai'	=>	'application/postscript',
        'eps'	=>	'application/postscript',
        'ps'	=>	'application/postscript',
        'smi'	=>	'application/smil',
        'smil'	=>	'application/smil',
        'mif'	=>	'application/vnd.mif',
        'xls'	=>	array('application/excel', 'application/vnd.ms-excel', 'application/msexcel'),
        'ppt'	=>	array('application/powerpoint', 'application/vnd.ms-powerpoint'),
        'wbxml'	=>	'application/wbxml',
        'wmlc'	=>	'application/wmlc',
        'dcr'	=>	'application/x-director',
        'dir'	=>	'application/x-director',
        'dxr'	=>	'application/x-director',
        'dvi'	=>	'application/x-dvi',
        'gtar'	=>	'application/x-gtar',
        'gz'	=>	'application/x-gzip',
        'php'	=>	'application/x-httpd-php',
        'php4'	=>	'application/x-httpd-php',
        'php3'	=>	'application/x-httpd-php',
        'phtml'	=>	'application/x-httpd-php',
        'phps'	=>	'application/x-httpd-php-source',
        'js'	=>	'application/x-javascript',
        'swf'	=>	'application/x-shockwave-flash',
        'sit'	=>	'application/x-stuffit',
        'tar'	=>	'application/x-tar',
        'tgz'	=>	array('application/x-tar', 'application/x-gzip-compressed'),
        'xhtml'	=>	'application/xhtml+xml',
        'xht'	=>	'application/xhtml+xml',
        'zip'	=>  array('application/x-zip', 'application/zip', 'application/x-zip-compressed'),
        'mid'	=>	'audio/midi',
        'midi'	=>	'audio/midi',
        'mpga'	=>	'audio/mpeg',
        'mp2'	=>	'audio/mpeg',
        'mp3'	=>	array('audio/mpeg', 'audio/mpg', 'audio/mpeg3', 'audio/mp3'),
        'aif'	=>	'audio/x-aiff',
        'aiff'	=>	'audio/x-aiff',
        'aifc'	=>	'audio/x-aiff',
        'ram'	=>	'audio/x-pn-realaudio',
        'rm'	=>	'audio/x-pn-realaudio',
        'rpm'	=>	'audio/x-pn-realaudio-plugin',
        'ra'	=>	'audio/x-realaudio',
        'rv'	=>	'video/vnd.rn-realvideo',
        'wav'	=>	array('audio/x-wav', 'audio/wave', 'audio/wav'),
        'bmp'	=>	array('image/bmp', 'image/x-windows-bmp'),
        'gif'	=>	'image/gif',
        'jpeg'	=>	array('image/jpeg', 'image/pjpeg'),
        'jpg'	=>	array('image/jpeg', 'image/pjpeg'),
        'jpe'	=>	array('image/jpeg', 'image/pjpeg'),
        'png'	=>	array('image/png',  'image/x-png'),
        'tiff'	=>	'image/tiff',
        'tif'	=>	'image/tiff',
        'css'	=>	'text/css',
        'html'	=>	'text/html',
        'htm'	=>	'text/html',
        'shtml'	=>	'text/html',
        'txt'	=>	'text/plain',
        'text'	=>	'text/plain',
        'log'	=>	array('text/plain', 'text/x-log'),
        'rtx'	=>	'text/richtext',
        'rtf'	=>	'text/rtf',
        'xml'	=>	'text/xml',
        'xsl'	=>	'text/xml',
        'mpeg'	=>	'video/mpeg',
        'mpg'	=>	'video/mpeg',
        'mpe'	=>	'video/mpeg',
        'qt'	=>	'video/quicktime',
        'mov'	=>	'video/quicktime',
        'avi'	=>	'video/x-msvideo',
        'movie'	=>	'video/x-sgi-movie',
        'doc'	=>	'application/msword',
        'docx'	=>	array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'),
        'xlsx'	=>	array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'),
        'word'	=>	array('application/msword', 'application/octet-stream'),
        'xl'	=>	'application/excel',
        'eml'	=>	'message/rfc822',
        'json' => array('application/json', 'text/json')
    );
       
    public function __construct() {
    }
    
    public static function status($code = 200, $text = '')
	{
        $header = array();
        
		if ($code == '' OR ! is_numeric($code)) {
			$code = 200;
		}

		if (isset(self::$status[$code]) AND $text == '') {
			$text = self::$status[$code];
		}

		if ($text == '') {
			$text = 'OK';
		}

		$server_protocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        if ($server_protocol == 'HTTP/1.1' OR $server_protocol == 'HTTP/1.0') {
            $header[$server_protocol] = "{$code} {$text}";
		} else {
            $header["HTTP/1.1"] = "{$code} {$text}";
		}
        
        return $header;
    }

    // 如果$mime_type是MIME值则直接生成一个Content-Type header。
    // 如果$mime_type是扩展名，则根据输出内容的扩展名生成Content-Type header
	public static function contentType($mime_type, $charset='')
	{
        $header = array();
        
		if (strpos($mime_type, '/') === FALSE) {
			$extension = ltrim($mime_type, '.');

			// Is this extension supported?
			if (isset(self::$mime_types[$extension])) {
				$mime_type =& self::$mime_types[$extension];

				if (is_array($mime_type)) {
					$mime_type = current($mime_type);
				}
			}
		}
        
        if(empty($charset)) {
            $header['Content-Type'] = $mime_type;
        } else {
            $header['Content-Type'] = $mime_type . "; charset=" . $charset;
        }
		
		return $header;
	}
    
    //P3P: policyref="http://googleads.g.doubleclick.net/pagead/gcn_p3p_.xml", CP="CURa ADMa DEVa TAIo PSAo PSDo OUR IND UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"
    public static function p3p($cp, $policyref='')
    {
        $header = array();
        
        if(empty($policyref)) {
            $header['P3P'] = "CP=\"{$cp}\"";
        } else {
            $header['P3P'] = "policyref=\"{$policyref}\", CP=\"{$cp}\"";
        }
        
        return $header;
    }
    
    public static function redirect301($url)
    {
        $headers = array();
        
        $header = self::status(301);
        $headers = array_merge($headers, $header);
        $headers['Location'] = $url;
        return $headers;
    }
    
    public static function redirect302($url)
    {
        $headers = array();
        
        $header = self::status(302);
        $headers = array_merge($headers, $header);
        $headers['Location'] = $url;
        return $headers;
    }
    
    public static function lastModified($last_update) 
    {
        $header = array();
        $header['Last-Modified'] = gmdate('D, d M Y H:i:s', $last_update).' GMT';
        return $header;
    }
    
    //过期时间: 相对时间，秒数
    public static function expire($secondsToLive)
    {
        $now = time();
        $expire = $now + $secondsToLive;
        $header = array();
        $header['Expires'] = gmdate('D, d M Y H:i:s', $expire).' GMT';
        return $header;
    }
    
    public static function pragma() 
    {
        $header = array();
        $header['Pragma'] = 'no-cache';
        return $header;
    }

    // 参数$type指明cache类型，$expire指明过期时间（相对时间，秒数）
    public static function cacheControl($type, $expire) 
    {
        $headers = array();
        
        switch ($type) {
            case self::CACHE_PUBLIC:
                //public 指示响应数据可以被任何客户端缓存 ，包括proxy和浏览器等。
                $header = self::expire($expire);
                $headers = array_merge($headers,$header);
                // max-age指明缓存过期时间，单位为s，从现在开始经过多少秒就过期，是相对时间。
                $headers['Cache-Control'] = "public, max-age={$expire}";
                break;
            case self::CACHE_PRIVATE:
                //private 告诉proxy不要缓存，但是浏览器可使用private cache进行缓存。
                $headers['Expires'] = 'Thu, 19 Nov 1981 08:52:00 GMT'; //告诉一些支持HTTP/1.0的proxy不要缓存
                //pre-check/post-check 微软的特殊扩展，一般需要关闭： pre-check=0, post-check=0 。
                $headers['Cache-Control'] = "private, max-age={$expire}, pre-check={$expire}";
                break;
            case self::CACHE_PRIVATENOEXPIRE:
                $headers['Cache-Control'] = "private, max-age={$expire}, pre-check={$expire}";
                break;
            case self::CACHE_NOCACHE:
                $headers['Expires'] = 'Thu, 19 Nov 1981 08:52:00 GMT';
                // For HTTP/1.1 conforming clients and the rest (MSIE 5)
                $headers['Cache-Control'] = "no-store, no-cache, must-revalidate, post-check=0, pre-check=0";
                // For HTTP/1.0 conforming clients
                $headers['Pragma'] = "no-cache";
                break;
            default:
                break;
        }
        
        return $headers;
    }

    /**
    * Set cookie 注意：输出cookie之前不能有其他的输出。
    *
    * 一个响应中可以包含一个或多个set-cookie header。
    *    服务器可以在任意返回头(301 302 40x 50x ...)中添加 Set-Cookie头域。
    *    cookie组成：
    *    name: cookie名
    *    value: cookie值，需要urlencode/base64. 设置为空则会删除该cookie
    *    expire: 没有设置或设置为0，则表示session cookie，设置过去时间则会删除该cookie
    *    domain: 只能是该请求的host或其上级域名。其他域名则会被浏览器忽略该cookie。
    *    path： 如果path为空，path就是当前请求URL的path。
    *    secure: 告诉浏览器只在HTTPS连接回传该cookie，如果是 HTTP 连接则不回传该cookie。
    *    httponly：告诉浏览器不要在Document对象中提供cookie，这样当前页面里的js程序不能
    *              通过document.cookie 方法来获得当前cookie值。这样能提高cookie的安全性，不会被盗取。
    *    注意：domain、path和name相同的cookie将会覆盖，只保留最新的cookie。
    * 
    * @param	mixed   $name 可以是字符串和数组。
    * @param	string	$value the value of the cookie
    * @param	string	$expire the number of seconds until expiration
    *                   epoch: sets the Expires header to 1 January, 1970 00:00:01 GMT.
    *                     max: sets the Expires header to 31 December 2037 23:59:59 GMT
    *                  session: expire=0
    * @param	string	the cookie domain.  Usually:  .yourdomain.com
    * @param	string	the cookie path
    * @param	bool	true makes the cookie secure
    * @param    booltrue makes the cookie httponly
    * @return	void
    */
    
    public static function setCookie($name, $value = '', $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = false)
	{
        if (is_array($name))
		{
			// always leave 'name' in last place, as the loop will break otherwise, due to $$item
			foreach (array('value', 'expire', 'domain', 'path', 'prefix', 'secure', 'httponly', 'name') as $item)
			{
				if (isset($name[$item]))
				{
					$$item = $name[$item];
				}
			}
		}
        
        if ( ! is_numeric($expire) && $expire == 'session') {
            $expire = 0;
        } else if ( ! is_numeric($expire) && $expire == 'epoch') {
            $expire = strtotime('1982-01-06'); // for delete cookie
		} else if(! is_numeric($expire) && $expire == 'max') {
            $expire = strtotime('2037-12-13'); //Tue, 19 Jan 2038 03:14:07 GMT
        } else if(! is_numeric($expire)) {
            $expire = time() - (31536001*20); //20 years ago, for delete cookie
        }  else { 
			$expire = ($expire > 0) ? time() + $expire : 0;
		}

		setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
	}
    
    // 如果没有设置过期时间或设置为0，则表示这个cookie的生命期为浏览器会话期间，只要关闭浏览器窗口，cookie就消失了。
    // 这种生命期为浏览器会话期的cookie被称为session cookie。
    // sesson cookie一般不存储在硬盘上而是保存在内存里。
    public static function setSessionCookie($name, $value = '', $path = '/', $domain = '', $secure = false, $httponly = false)
    {
        if (is_array($name) && isset($name['expire']))
		{
            $name['expire'] = 0;
        }
        
        self::setCookie($name, $value, 0, $path, $domain, $secure, $httponly);
    }
    
    //删除硬盘上的cookie：
    //（1）将新的cookie的过期时间设置小于系统的当前时间(最好远远小于，因为可能用户的系统时间不准确)，
    //    浏览器会删除硬盘上与该cookie name和path相同的cookie。
    //（2)只设置cookie的name，而不设置cookie的value，这样也能删除。 php内部会做处理。
    public static function deleteCookie($name, $path = '/', $domain = '')
    {   
        self::setCookie($name, 'deleted', 'epoch', $path, $domain);
    }
}
