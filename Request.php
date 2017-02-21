<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


/* 对yaf request类进行扩展。
 * 可以通过yaf request类以及扩展类获得用户输入数据。
 * 说明：
 * 用户输入的数据是通过一个HTTP请求发到服务端的，请求的方法有GET、POST等。
 * GET请求：用户输入数据采用application/x-www-form-urlencoded编码格式（即name=value&...）
 *         且放在请求URL的QueryString部分传递到服务端webserver，webserver通过环境变量或FCGI_PARAM
 *         方式传递给PHP-FPM，PHP接收并处理后存放到一个全局数组$_GET和$_REQUEST中。
 *         PHP程序就可以从预定义的$_GET和$_REQUEST中获得用户输入数据。
 * POST请求：用户输入数据采用application/x-www-form-urlencoded 或者 multipart/form-data编码格式
 *          且放在请求的body部分传递到服务端webserver（此时请求头会设置content_type content_length）
 *          webserver通过标准输入或FCGI_PARAM方式传递给PHP-FPM,PHP接收并处理后存放到一个全局数组$_POST和$_REQUEST中。
 *          PHP程序就可以从预定义的$_POST和$_REQUEST中获得用户输入数据。
 *          注意：如果POST URL里有QueryString，这样PHP同样会处理后存放到$_GET和$_REQUEST中。
 *          此时如果QueryString和body部分有同名的变量，$_REQUEST则会按照一定的优先级进行覆盖。
 * 请求头信息： 不管是GET还是POST请求，其头部信息都是通过环境变量或FCGI_PARAM传递给php-fpm,php接收后
 *            都会存放到全局数组$_SERVER中。
 *            常用的请求头信息有： REMOTE_ADDR  HTTP_USER_AGENT HTTP_REFERER 
 * WEBSERVEFR和CGI信息：webserver会把服务器和CGI方面的信息通过环境变量或FCGI_PARAM传递给php-fpm,php接收后
 *            都会存放到全局数组$_SERVER中。比如 SERVER_PROTOCOL  如HTTP/1.0  
 * COOKIE: 用户cookie也是通过http请求头cookie（可以是一个或多个）传递到服务端的，webserver通过环境变量或FCGI——PARAMS
 *         HTTP_COOKIE传递给php-fpm,php获得并处理后存放在全局数组$_COOKIE中。
 * 上传文件： 上传文件只能通过POST方法，且编码格式只能为multipart/form-data。php接收并处理存放到全局数组$_FILES中。
 * 
 * 总结：         
 * PHP程序可以通过下面预定义数组来获得相关数据：
	$_SERVER    webserver和CGI环境信息（$_SERVER["REQUEST_URI"]和HTTP请求头中的一模一样，不做任何处理）
	$_GET       GET/POST querystring里的用户数据（对变量名和值都URL decode,变量名中的空格用'_'代替）
	$_POST      POST方式提交的用户数据（URL decode ...）
	$_REQUEST   $_GET $_POST里的用户数据
	$_COOKIE    一个或多个cookie（URL decode ...）
  $_FILES     上传文件
	
 * 注意：
 * （1）以上变量都是预定义的，所以不需要通过isset()来检测，但可以通过empty()函数来检测这些预定义变量是否为空，
 *     以及isset()来检测某个元素是否存在。
 * （2）$_REQUEST里怎么会同时有$_GET $_POST里的变量呢？ 如果POST http://domain.com/action?q=xxx  此时q变量就在$_GET数组中。
	<form method= 'post' action = 'req.php?a=xxx'>
	<input type='hidden' name='a' value='yyy'/>
	<input type='submit' name='submit' value='submit'/>
	</form>
	如果对请求动作的方法有限制的话，GET方法从$_GET数组里取，POST方法从$_POST数组里取，不要从_REQUEST里取。
 *  如果对请求方法没有限制的话，则可以从_REQUEST里取。
 * 
 * YAF Request类功能如下：
 * (1) 对$_GET $_POST $_SERVER $_COOKIE $_FILES $_REQUEST(getRequest())全局数组的封装.
 *     这样不用先检查变量是不是存在。 
 * (2) 请求方法获得和判断
 * (3) 是不是ajax请求？ 检查服务器头HTTP_X_REQUESTED_WITH是否被设置
 * (4) 路由信息：base_uri、模块、控制器、动作、参数
 * (5) 当前请求的希望接受的语言（来自请求头Accept-Language）
 * (6) Request URI(HTTP请求中URL部分，最原始的)
 * 
 * 对YAF Request类扩展的功能：
 * (1) UserIP RemoteIP  
 * (2) UserAgent Platform browser mobile robot
 * (3) 客户端可以接受哪些语言和字符集
 * (4) Refferer 用户在搜索引擎中搜索关键词
 * (5) Cookie
*/

class Gek_Request 
{
    private static $platforms = array (
        'windows nt 6.0'	=> 'Windows Longhorn',
        'windows nt 5.2'	=> 'Windows 2003',
        'windows nt 5.0'	=> 'Windows 2000',
        'windows nt 5.1'	=> 'Windows XP',
        'windows nt 4.0'	=> 'Windows NT 4.0',
        'winnt4.0'			=> 'Windows NT 4.0',
        'winnt 4.0'			=> 'Windows NT',
        'winnt'				=> 'Windows NT',
        'windows 98'		=> 'Windows 98',
        'win98'				=> 'Windows 98',
        'windows 95'		=> 'Windows 95',
        'win95'				=> 'Windows 95',
        'windows'			=> 'Unknown Windows OS',
        'os x'				=> 'Mac OS X',
        'ppc mac'			=> 'Power PC Mac',
        'freebsd'			=> 'FreeBSD',
        'ppc'				=> 'Macintosh',
        'linux'				=> 'Linux',
        'debian'			=> 'Debian',
        'sunos'				=> 'Sun Solaris',
        'beos'				=> 'BeOS',
        'apachebench'		=> 'ApacheBench',
        'aix'				=> 'AIX',
        'irix'				=> 'Irix',
        'osf'				=> 'DEC OSF',
        'hp-ux'				=> 'HP-UX',
        'netbsd'			=> 'NetBSD',
        'bsdi'				=> 'BSDi',
        'openbsd'			=> 'OpenBSD',
        'gnu'				=> 'GNU/Linux',
        'unix'				=> 'Unknown Unix OS'
    );


    // The order of this array should NOT be changed. Many browsers return
    // multiple browser types so we want to identify the sub-type first.
    private static $browsers = array(
        'Flock'				=> 'Flock',
        'Chrome'			=> 'Chrome',
        'Opera'				=> 'Opera',
        'MSIE'				=> 'Internet Explorer',
        'Internet Explorer'	=> 'Internet Explorer',
        'Shiira'			=> 'Shiira',
        'Firefox'			=> 'Firefox',
        'Chimera'			=> 'Chimera',
        'Phoenix'			=> 'Phoenix',
        'Firebird'			=> 'Firebird',
        'Camino'			=> 'Camino',
        'Netscape'			=> 'Netscape',
        'OmniWeb'			=> 'OmniWeb',
        'Safari'			=> 'Safari',
        'Mozilla'			=> 'Mozilla',
        'Konqueror'			=> 'Konqueror',
        'icab'				=> 'iCab',
        'Lynx'				=> 'Lynx',
        'Links'				=> 'Links',
        'hotjava'			=> 'HotJava',
        'amaya'				=> 'Amaya',
        'IBrowse'			=> 'IBrowse'
    );

    private static $mobiles = array(
        'iphone'			=> "Apple iPhone",
        'ipad'				=> "iPad",
        'ipod'				=> "Apple iPod Touch",
        'android'           => "Android",
        'windows phone'     => "Windows Phone",
        'blackberry'		=> "BlackBerry",
        'ucweb'             => "UCWeb",
        'ucbrowser'         => "UCBrowser",
        'mqqbrowser'        => "MQQBrowser",
        'sogoumobilebrowser'=> "SogouMobileBrowser",
        'sogoumse'          => "SogouMSE",
        'miuibrowser'       => "MiuiBrowser",
        
        'huawei'            => "Huawei",
        'coolpad'           => "Coolpad",
        'lenovo'            => "Lenovo",
        'meizu'             => "Meizu",
        'xiaomi'            => "Xiaomi",
        
        // legacy array, old values commented out
        'mobileexplorer'	=> 'Mobile Explorer',
        'palmsource'		=> 'Palm',
        'palmscape'			=> 'Palmscape',

        // Phones and Manufacturers
        'motorola'			=> "Motorola",
        'nokia'				=> "Nokia",
        'palm'				=> "Palm",
        'sony'				=> "Sony Ericsson",
        'ericsson'			=> "Sony Ericsson",
        'cocoon'			=> "O2 Cocoon",
        'blazer'			=> "Treo",
        'lg'				=> "LG",
        'amoi'				=> "Amoi",
        'xda'				=> "XDA",
        'mda'				=> "MDA",
        'vario'				=> "Vario",
        'htc'				=> "HTC",
        'samsung'			=> "Samsung",
        'sharp'				=> "Sharp",
        'sie-'				=> "Siemens",
        'alcatel'			=> "Alcatel",
        'benq'				=> "BenQ",
        'ipaq'				=> "HP iPaq",
        'mot-'				=> "Motorola",
        'playstation portable'	=> "PlayStation Portable",
        'hiptop'			=> "Danger Hiptop",
        'nec-'				=> "NEC",
        'panasonic'			=> "Panasonic",
        'philips'			=> "Philips",
        'sagem'				=> "Sagem",
        'sanyo'				=> "Sanyo",
        'spv'				=> "SPV",
        'zte'				=> "ZTE",
        'sendo'				=> "Sendo",

        // Operating Systems
        'symbian'				=> "Symbian",
        'SymbianOS'				=> "SymbianOS",
        'elaine'				=> "Palm",
        'palm'					=> "Palm",
        'series60'				=> "Symbian S60",
        'windows ce'			=> "Windows CE",

        // Browsers
        'obigo'					=> "Obigo",
        'netfront'				=> "Netfront Browser",
        'openwave'				=> "Openwave Browser",
        'mobilexplorer'			=> "Mobile Explorer",
        'operamini'				=> "Opera Mini",
        'opera mini'			=> "Opera Mini",

        // Other
        'digital paths'			=> "Digital Paths",
        'avantgo'				=> "AvantGo",
        'xiino'					=> "Xiino",
        'novarra'				=> "Novarra Transcoder",
        'vodafone'				=> "Vodafone",
        'docomo'				=> "NTT DoCoMo",
        'o2'					=> "O2",

        // Fallback
        'mobile'				=> "Generic Mobile",
        'wireless'				=> "Generic Mobile",
        'j2me'					=> "Generic Mobile",
        'midp'					=> "Generic Mobile",
        'cldc'					=> "Generic Mobile",
        'up.link'				=> "Generic Mobile",
        'up.browser'			=> "Generic Mobile",
        'smartphone'			=> "Generic Mobile",
        'cellphone'				=> "Generic Mobile"
    );

    // There are hundreds of bots but these are the most common.
    private static $robots = array(
        'baiduspider'       => 'BaiduSpider',
        'googlebot'			=> 'Googlebot',
        'msnbot'			=> 'MSNBot',
        'slurp'				=> 'Inktomi Slurp',
        'yahoo'				=> 'Yahoo',
        'askjeeves'			=> 'AskJeeves',
        'fastcrawler'		=> 'FastCrawler',
        'infoseek'			=> 'InfoSeek Robot 1.0',
        'lycos'				=> 'Lycos'
        );

    public function __construct() {
    }
    
    
    /**
     * scheme://host:port
     */
    public static function getUrlPrefix() {
        // 用户通过代理服务器上网的，则HTTP_HOST值可能是代理服务器的IP或HOST。
        // HTTP_X_FORWARDED_HOST/HTTP_POST头是可以伪造的，用户也可以加入这样的header。
        $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? 
                    $_SERVER['HTTP_X_FORWARDED_HOST'] : 
                    (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
         
        if($host == '') {
            $host = $_SERVER["SERVER_NAME"];
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
        
        return $scheme . $host . $port;
    }
    
    // 一般就是nginx/apache在虚拟主机里配置的ServerName名。
    // 所以如果nginx/apache配置很规范，就可以根据$_SERVER['SERVER_NAME']构造自身URL
    public static function getServerName() {
        if(!isset($_SERVER['SERVER_NAME'])) {
            return '';
        } else {
            return $_SERVER['SERVER_NAME'];
        }
    }
    
    // HTTP1.1协议，浏览器会把URL的host:port部分当作一个请求头发到服务器端。
    // 用户通过代理服务器上网的，则HTTP_HOST值可能是代理服务器的IP或HOST。
    // HTTP_X_FORWARDED_HOST/HTTP_POST头是可以伪造的，用户也可以加入这样的header。
    public static function getHost()
    {
        $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? 
                    $_SERVER['HTTP_X_FORWARDED_HOST'] : 
                    (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
        return $host;
    }
    
    //http请求中cookie头会包含所有未过期同时符合<domain,path>要求的cookie的name/value
    //子域和根域下存在相同名称的cookie时，在子域下请求时，浏览器会把子域和根域下的Cookie一起发送到服务器。
    //但此时PHP $_COOKIE只包含一个，包含的是子域下的Cookie还是根域下的Cookie，不同浏览器不一样。
    //也就说，同名Cookie对于服务端PHP来说，在请求头Cookie中，哪个在前哪个生效，后面的会被忽略。
    //如果想拿到全部的内容，可以用$_SERVER['HTTP_COOKIE'])获取
    //如果name相同，但path不同的cookie，则只返回最近path的cookie。
    //php会对cookie值进行urldecode.
    public static function getCookie($name)
    {
        if(!isset($_COOKIE[$name])) {
            return '';
        } else {
            return $_COOKIE[$name];
        }
    }
    
    public static function getUserAgent()
	{
        if(isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            return trim($_SERVER['HTTP_USER_AGENT']);
        } else {
            return '';
        }
    }
    
    public static function getUserPlatform() 
    {
        $agent = self::getUserAgent();
        if (!empty($agent) AND is_array(self::$platforms) AND count(self::$platforms) > 0) {
			foreach (self::$platforms as $key => $val) {
				if (preg_match("|".preg_quote($key)."|i", $agent)) {
					return $val;
				}
			}
		} else {
            return '';
        }
    }
    
    // 返回一个数组，包含了浏览器相关信息。
    // array(
    //       'name' => ...,
    //       'version' => browser version,
    //       'mobile' => ...)
    public static function getUserBrowser()
    {
        $r = array();
        
        $agent = self::getUserAgent();
        if (!empty($agent) AND is_array(self::$browsers) AND count(self::$browsers) > 0) {
			foreach (self::$browsers as $key => $val) {
				if (preg_match("|".preg_quote($key).".*?([0-9\.]+)|i", $agent, $match)) {
                    $r['name'] = $val;
                    $r['version'] = $match[1];
                    $r['mobile'] = self::getUserMobile();
                    return $r;
				}
			}
		} else {
            return array();
        }
    }
    
    public static function getUserMobile()
    {
        if(Yaf_Registry::has("isMobile")) {
            return true;
        } 
        
        $agent = self::getUserAgent();
        if (!empty($agent) AND is_array(self::$mobiles) AND count(self::$mobiles) > 0) {
			foreach (self::$mobiles as $key => $val) {
				if (FALSE !== (strpos(strtolower($agent), $key))) {
                    Yaf_Registry::set("isMobile", 1);
					return true;
				}
			}
		} else {
            return false;
        }
    }
    
    public static function getUserRobot()
    {
        $agent = self::getUserAgent();
        if (!empty($agent) AND is_array(self::$robots) AND count(self::$robots) > 0) {
			foreach (self::$robots as $key => $val) {
				if (preg_match("|".preg_quote($key)."|i", $agent)) {
					return $val;
				}
			}
		} else {
            return '';
        }
    }
    
    // 用户端设置接受多种语言，所以返回一个数组，如果用户端没有设置，则返回一个空数组。
    public static function getAcceptlanguages()
	{
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) AND $_SERVER['HTTP_ACCEPT_LANGUAGE'] != '')
		{
			$languages = preg_replace('/(;q=[0-9\.]+)/i', '', strtolower(trim($_SERVER['HTTP_ACCEPT_LANGUAGE'])));
			return explode(',', $languages);
		} else {
            return array('');
        }
	}
    
    // 用户端设置接受多种字符集，所以返回一个数组，如果用户端没有设置，则返回一个空数组。
    public static function getAcceptcharsets()
	{
		if (isset($_SERVER['HTTP_ACCEPT_CHARSET']) AND $_SERVER['HTTP_ACCEPT_CHARSET'] != '')
		{
			$charsets = preg_replace('/(;q=.+)/i', '', strtolower(trim($_SERVER['HTTP_ACCEPT_CHARSET'])));

			return explode(',', $charsets);
		} else {
            return array();
        }
	}
    
	public static function getReferer()
	{
		return ( ! isset($_SERVER['HTTP_REFERER']) OR $_SERVER['HTTP_REFERER'] == '') ? '' : trim($_SERVER['HTTP_REFERER']);
	}
    
   // --------------------------------------------------------------------
    
    // 不考虑用户使用代理的情况，用户使用代理的话则返回代理的IP。
    public static function getRomoteIP() 
    {
        return ( ! isset($_SERVER['REMOTE_ADDR'])) ? '0.0.0.0' : $_SERVER['REMOTE_ADDR'];
    }
    
    // 考虑用户使用代理的情况。失败返回无效的ip地址 '0.0.0.0'
    // HTTP_X_FORWARDED_FOR等http头都是可以伪造的，可以自己在请求中带上这些header，一定要对这些IP做验证。
	public static function getUserIP()
	{
        $ip_address = '';
        foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP') as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ip_address = $_SERVER[$header];
                // Some proxies typically list the whole chain of IP
                // addresses through which the client has reached us.
                // e.g. client_ip, proxy_ip1, proxy_ip2, etc.
                if (strpos($ip_address, ',') !== FALSE) {
                    $ip_address = explode(',', $ip_address, 2);
                    $ip_address = $ip_address[0];
                }

                if (self::valid_ip($ip_address)) {
                   break;
                } else {
                    $ip_address = '';
                }
            }
        }
        
        if(empty($ip_address)) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
            if (!self::valid_ip($ip_address)) {
                $ip_address = '0.0.0.0';
            }
        }
        
		return $ip_address;
	}
    
	// --------------------------------------------------------------------

	/**
	* Validate IP Address
	*
	* @access	public
	* @param	string
	* @param	string	ipv4 or ipv6
	* @return	bool
	*/
	public static function valid_ip($ip, $which = '')
	{
		$which = strtolower($which);

		if ($which !== 'ipv6' && $which !== 'ipv4')
		{
			if (strpos($ip, ':') !== FALSE)
			{
				$which = 'ipv6';
			}
			elseif (strpos($ip, '.') !== FALSE)
			{
				$which = 'ipv4';
			}
			else
			{
				return FALSE;
			}
		}

		$func = '_valid_'.$which;
		return self::$func($ip);
	}

	// --------------------------------------------------------------------

	/**
	* Validate IPv4 Address
	*
	* Updated version suggested by Geert De Deckere
	*
	* @access	protected
	* @param	string
	* @return	bool
	*/
	protected static function _valid_ipv4($ip)
	{
		$ip_segments = explode('.', $ip);

		// Always 4 segments needed
		if (count($ip_segments) !== 4)
		{
			return FALSE;
		}
		// IP can not start with 0
		if ($ip_segments[0][0] == '0')
		{
			return FALSE;
		}

		// Check each segment
		foreach ($ip_segments as $segment)
		{
			// IP segments must be digits and can not be
			// longer than 3 digits or greater then 255
			if ($segment == '' OR preg_match("/[^0-9]/", $segment) OR $segment > 255 OR strlen($segment) > 3)
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	* Validate IPv6 Address
	*
	* @access	protected
	* @param	string
	* @return	bool
	*/
	protected static function _valid_ipv6($str)
	{
		// 8 groups, separated by :
		// 0-ffff per group
		// one set of consecutive 0 groups can be collapsed to ::

		$groups = 8;
		$collapsed = FALSE;

		$chunks = array_filter(
			preg_split('/(:{1,2})/', $str, NULL, PREG_SPLIT_DELIM_CAPTURE)
		);

		// Rule out easy nonsense
		if (current($chunks) == ':' OR end($chunks) == ':')
		{
			return FALSE;
		}

		// PHP supports IPv4-mapped IPv6 addresses, so we'll expect those as well
		if (strpos(end($chunks), '.') !== FALSE)
		{
			$ipv4 = array_pop($chunks);

			if ( !self::_valid_ipv4($ipv4))
			{
				return FALSE;
			}

			$groups--;
		}

		while ($seg = array_pop($chunks))
		{
			if ($seg[0] == ':')
			{
				if (--$groups == 0)
				{
					return FALSE;	// too many groups
				}

				if (strlen($seg) > 2)
				{
					return FALSE;	// long separator
				}

				if ($seg == '::')
				{
					if ($collapsed)
					{
						return FALSE;	// multiple collapsed
					}

					$collapsed = TRUE;
				}
			}
			elseif (preg_match("/[^0-9a-f]/i", $seg) OR strlen($seg) > 4)
			{
				return FALSE; // invalid segment
			}
		}

		return $collapsed OR $groups == 1;
	}
}
