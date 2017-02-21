<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


class Gek_Utils {
    const RECORD_BEG = "<_record_>\r\n";
    const RECORD_END = "</_record_>\r\n\r\n";
    
    public static function object2Array($d)
    {
        if (is_object($d))
        {
            $d = get_object_vars($d);
        }

        if (is_array($d))
        {
            return array_map(array('Gek_Utils',__FUNCTION__)  , $d);
        }
        else
        {
            return $d;
        }
    }
    
    public static function record_xml2array($record)
    {
        $sr = array();

        $rb = strlen(self::RECORD_BEG);
        $re = strlen(self::RECORD_END);
        $rl = strlen($record);
        $r = substr($record, $rb, $rl - $rb - $re);

        $b = 0;
        while(($e = strpos($r, '>', $b)) !== false) {
            $tag = substr($r, $b+1, $e-$b-1);
            $close_tag = "</$tag>\r\n";
            $b = $e+1;
            $e = strpos($r, $close_tag, $b);
            $sr[$tag] = substr($r, $b, $e-$b);

            $b = $e + strlen($close_tag);
        }

        unset($record);
        unset($r);
        return $sr;
    }

    public static function record_array2xml($record)
    {
        $xml = self::RECORD_BEG;
        foreach($record as $key => $value) {
            if($value === '') {
                continue;
            }
            $xml .= ("<$key>$value</$key>\r\n");
        }
        $xml .= self::RECORD_END;
        return $xml;
    }
    
   /**
    * 把XML转为数组
    * 注意：xml记录中不能有空值的字段，比如<name></name>，
    * 因为simplexml_load_string在处理"<name></name>"时,返回了一个空simplexml对象,而不是''空字符串.
    */
   public static function xml_to_array($xml) 
   {
       $obj = simplexml_load_string($xml);
       $json = json_encode($obj);
       return json_decode($json, true);
   }

    /**
     * 把xml转换成数组：适合xml记录中有空值的字段的情况
     */
    public static function xml_to_array2($xml) 
    {
        $object = simplexml_load_string($xml);
        if( count($object) == 0 ) {
            return trim((string)$object);
        }

        $result = array();
        $object = is_object($object) ? get_object_vars($object) : $object;
        foreach ($object as $key => $val) {
            $val = (is_object($val) || is_array($val)) ? objectToArray($val) : $val;
            $result[$key] = $val;
        }
        return $result;
    }

    public static function xml_to_array3($xml) 
    { 
        $array = (array)(simplexml_load_string($xml)); 
        foreach ($array as $key=>$item){ 
            $array[$key] = self::struct_to_array((array)$item); 
        } 
        return $array; 
    } 

    private static function struct_to_array($item) 
    { 
        if(!is_string($item)) { 
            $item = (array)$item; 
            foreach ($item as $key=>$val){ 
                $item[$key] = self::struct_to_array($val); 
            } 
        } 
        return $item; 
    }

    /**
     * 构造URL，用于输出页面中的URL。
     * @param type $base_url
     * @param type $base_url_has_params
     * @param type $params
     * @param type $exclude_params
     * @return type
     */
    public static function get_url($base_url, $base_url_has_params, $params, $exclude_params='')
    {
        if($exclude_params && is_string($exclude_params)) {
            $exclude_params = array($exclude_params);
        }
        
        $url = $base_url;
        $url_has_params = $base_url_has_params;
        foreach($params as $name => $value) {
            if($exclude_params && in_array($name, $exclude_params)) {
                continue;
            }
            
            if($url_has_params) {
                $url .= ('&' . "$name=$value");
            } else {
                $url .= ('?' . "$name=$value");
                $url_has_params = true;
            }
        }
        
        return array('v' => $url, 'p' => $url_has_params);
    }
    
    /**
     * 点击日志记录。
     * @param array $params 参数说明：
     * obj: 点击对象，比如：favorite shop product 
     * name：具体对象名，比如：商家名、商品名..
     * url: URL
     * page: 所在页面，比如：index serp detail shop cfda_view cfda_search
     * mod:  所在模块 
     * pos:  位置 1.1 第几页第几个
     * tab： 所在tab，用于多标签模块
     * interest: 对象属性，用户点击的字段 name image price
     */
    public static function click_log($params,$host='',$action='click.gif') 
    {
        
        $log = '';
        foreach($params as $name => $value) {
            if($log != '')  {
                $log .= '&';
            }
            $log .= ($name . '=' . urlencode($value));
        }
        
        if(!empty($host)) { 
            $log = 'http://' . $host .'/'. $action . '?' . $log;
        } else {
            $log = '/'. $action . '?' . $log;
        }
        return $log;
    }
   
    // Convert an UTF-8 encoded string to a single-byte string suitable for
    // functions such as levenshtein.
    // 
    // The function simply uses (and updates) a tailored dynamic encoding
    // (in/out map parameter) where non-ascii characters are remapped to
    // the range [128-255] in order of appearance.
    //
    // Thus it supports up to 128 different multibyte code points max over
    // the whole set of strings sharing this encoding.
    //
    private static function utf8_to_extended_ascii($str, &$map)
    {
        // find all multibyte characters (cf. utf-8 encoding specs)
        $matches = array();
        if (!preg_match_all('/[\xC0-\xF7][\x80-\xBF]+/', $str, $matches))
            return $str; // plain ascii string

        // update the encoding map with the characters not already met
        foreach ($matches[0] as $mbc)
            if (!isset($map[$mbc]))
                $map[$mbc] = chr(128 + count($map));

        // finally remap non-ascii characters
        return strtr($str, $map);
    }

    // Didactic example showing the usage of the previous conversion function but,
    // for better performance, in a real application with a single input string
    // matched against many strings from a database, you will probably want to
    // pre-encode the input only once.
    //Results (for about 6000 calls)
    //- reference time core C function (single-byte) : 30 ms
    //- utf8 to ext-ascii conversion + core function : 90 ms
    //- full php implementation : 3000 ms
    public static function levenshtein_utf8($s1, $s2)
    {
        $charMap = array();
        $s1 = self::utf8_to_extended_ascii($s1, $charMap);
        $s2 = self::utf8_to_extended_ascii($s2, $charMap);

        return levenshtein($s1, $s2);
    }

    /// build a list of trigrams for a given keywords
    public static function getTrigrams($keyword )
    {
        $t = "__" . $keyword . "__";
        $trigrams = "";
        for ( $i=0; $i<mb_strlen($t, 'UTF-8')-2; $i++ ) {
            $trigrams .= mb_substr($t, $i, 3, 'UTF-8') . " ";
        }
        
        return $trigrams;
    }
    
    // 把一个字符串转换为一个64位的十进制整数
    // 注意只能在64位平台运行，32位平台返回科学计数法形式5.3615184559484E+18
    public static function fnv64($value) 
    {
        if(empty($value)) return 0;
        $b128s = md5($value);
        $b64s = substr($b128s,0,14); // 用7个字节，因为sphinx不支持uint64 
        $b64 = hexdec($b64s);        // 把一个十六进制的64位整数转换为十进制的64位整数
        return $b64;
    }
    
    public static function qj2bj($str) {
        $arr = array(
           '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
           '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
           'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
           'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
           'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
           'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
           'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
           'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
           'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
           'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
           'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
           'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
           'ｙ' => 'y', 'ｚ' => 'z',
           '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[',
           '】' => ']', '〖' => '[', '〗' => ']', '“' => '"', '”' => '"',
           '‘' => "'", '’' => "'", '｛' => '{', '｝' => '}', '《' => '<',
           '》' => '>', '＜' => '<', '＞' => '>', '［' => '[', '］' => ']',
           '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
           '：' => ':', '。' => '.', '、' => ',', '，' => ',', '．' => '.',
           '；' => ';', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
           '｜' => '|', '〃' => '"', '＂'=>'"', '　' => ' ', '／' => '/',
           '＼' => '\\', '｀' => '`', '＿' => '_', '＝' => '=',
           '＄'=>'$','＠'=>'@','＃'=>'#','＾'=>'^','＆'=>'&','＊'=>'*'
            );
 
        return strtr($str, $arr);
    }


     /**
     * 把HTML中的特殊字符： < >  &  ' "转换为对应的实体表示: &lt; &gt;  &amp; &#039; &quot;。
     * @param type $str
     * @return type
     */
    public static function html_escape($str)
    {
        $flags = ENT_COMPAT | ENT_HTML401 | ENT_QUOTES;
        $encoding = "UTF-8"; 
        
        $str = htmlspecialchars($str, $flags, $encoding);
        return $str;
    }
    
    public static function html_unescape($str)
    {
        $flags = ENT_COMPAT | ENT_HTML401 | ENT_QUOTES;
        $str = htmlspecialchars_decode($str, $flags);
        return $str;
    }
    
    /**
    * Remove Invisible Characters
    * This prevents sandwiching null characters between ascii characters, like Java\0script.
    * 删除不可见字符，这样能防止在asscii字符之间插入NULL字符，比如Java\0script.
    * 但要注意：CR(0a) and LF(0b) and TAB(9)这些不可见字符是需要保留的。
    * @param	string $str 需要处理的字符串
    * @parma   boolean $url_encoded 字符串是不是进行了urlencoded，encoded的不可见字符也会被删除。
    * @return	string
    */
    public static function remove_invisible_characters($str, $url_encoded = TRUE)
	{
		$non_displayables = array();
		
		// every control character except newline (dec 10)
		// carriage return (dec 13), and horizontal tab (dec 09)
		
		if ($url_encoded)
		{
			$non_displayables[] = '/%0[0-8bcef]/';	// url encoded 00-08, 11, 12, 14, 15
			$non_displayables[] = '/%1[0-9a-f]/';	// url encoded 16-31
		}
		
		$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

		do
		{
			$str = preg_replace($non_displayables, '', $str, -1, $count);
		}
		while ($count);

		return $str;
	}
    
    // standardizes newline characters to \n，这样可以统一对表单里换行进行处理。
    public static function standardize_newlines($str)
    {
        if (strpos($str, "\r") !== FALSE)
        {
            $str = str_replace(array("\r\n", "\r", "\r\n\n"), "\n", $str);
        }
        return $str;
    }
    
    /*
    * Convert all tabs to spaces
    *
    * This prevents strings like this: ja	vascript
    * NOTE: we deal with spaces between characters later.
    * NOTE: preg_replace was found to be amazingly slow here on
    * large blocks of data, so we use str_replace.
    */
    public static function tabs_to_spaces($str)
    {
		if (strpos($str, "\t") !== FALSE)
		{
			$str = str_replace("\t", ' ', $str);
		}
        
        return $str;
    }
    
    // legal name 
    public static function is_legal_name($name)
	{
        return preg_match("/^[a-z0-9:_\/-]+$/i", $name);
    }
    
    /**
	 * Filename Security
	 *
	 * @param	string
	 * @param 	bool
	 * @return	string
	 */
	public static function sanitize_filename($str, $relative_path = FALSE)
	{
		$bad = array(
			"../",
			"<!--",
			"-->",
			"<",
			">",
			"'",
			'"',
			'&',
			'$',
			'#',
			'{',
			'}',
			'[',
			']',
			'=',
			';',
			'?',
			"%20",
			"%22",
			"%3c",		// <
			"%253c",	// <
			"%3e",		// >
			"%0e",		// >
			"%28",		// (
			"%29",		// )
			"%2528",	// (
			"%26",		// &
			"%24",		// $
			"%3f",		// ?
			"%3b",		// ;
			"%3d"		// =
		);

		if ( ! $relative_path)
		{
			$bad[] = './';
			$bad[] = '/';
		}

		$str = self::remove_invisible_characters($str, FALSE);
		return stripslashes(str_replace($bad, '', $str));
	}
    
    /**
	 * HTML Entities Decode
	 *
	 * This function is a replacement for html_entity_decode()
	 *
	 * The reason we are not using html_entity_decode() by itself is because
	 * while it is not technically correct to leave out the semicolon
	 * at the end of an entity most browsers will still interpret the entity
	 * correctly.  html_entity_decode() does not convert entities without
	 * semicolons, so we are left with our own little solution here. Bummer.
	 *
     * 当实体后没有写分号时，大部分浏览器都能够正确的解释，但是，html_entity_decode将不会解释它。
     * html_entity_decode()不会转换没有分号的实体，所以使用自己的解决方案。
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	public static function htmlentity_decode($str, $charset='UTF-8')
	{
		if (stristr($str, '&') === FALSE)
		{
			return $str;
		}

		$str = html_entity_decode($str, ENT_COMPAT, $charset);
		$str = preg_replace('~&#x(0*[0-9a-f]{2,5})~ei', 'chr(hexdec("\\1"))', $str);
		return preg_replace('~&#([0-9]{2,4})~e', 'chr(\\1)', $str);
	}
    
    
    public static function elapsed($start=false) {
        static $start_time;
        if($start) {
            $start_time = microtime(TRUE);
            return $start_time;
        } else {
            $elapsed = sprintf('%.3f', microtime(TRUE) - $start_time);
            return $elapsed;
        }
    }
    
    public static function write_log($msg, $logpath) 
    {
        error_log(date('Y-m-d H:i:s') . " " . $msg . "\n", 3, $logpath);
    }
    
    public static function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    public static function aes_encrypt($keytext,$password)
    {
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $password, $keytext, MCRYPT_MODE_CBC, $iv);
        # prepend the IV for it to be available for decryption
        $ciphertext = $iv . $ciphertext;
        $base64text = self::base64url_encode($ciphertext);
        return $base64text;
    }

    public static function aes_decrypt($keybase64,$password)
    {
        $ciphertext = self::base64url_decode($keybase64);
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
        # retrieves the IV, iv_size should be created using mcrypt_get_iv_size()
        $iv = substr($ciphertext, 0, $iv_size);
        # retrieves the cipher text (everything except the $iv_size in the front)
        $ciphertext = substr($ciphertext, $iv_size);
        $keytext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $password, $ciphertext, MCRYPT_MODE_CBC, $iv);
        return $keytext;
    }

    /**
    * Create URL Title
    *
    * Takes a "title" string as input and creates a
    * human-friendly URL string with a "separator" string 
    * as the word separator.
    *
    * 输入一个字符串并且创建用户友好的URL字串
    * @param	string	the string
    * @param	string	the separator
    * @return	string
    */
	public static function url_title($str, $separator = '-', $lowercase = FALSE)
	{
		if ($separator == 'dash') 
		{
		    $separator = '-';
		}
		else if ($separator == 'underscore')
		{
		    $separator = '_';
		}
		
		$q_separator = preg_quote($separator);

		$trans = array(
			'&.+?;'                 => '',
			'[^a-z0-9 _-]'          => '',
			'\s+'                   => $separator,
			'('.$q_separator.')+'   => $separator
		);

		$str = strip_tags($str);

		foreach ($trans as $key => $val)
		{
			$str = preg_replace("#".$key."#i", $val, $str);
		}

		if ($lowercase === TRUE)
		{
			$str = strtolower($str);
		}

		return trim($str, $separator);
	}
    
    /**
    * Prep URL 在URL中没有http://的情况下,这个函数可以附加上.
    *
    * Simply adds the http:// part if no scheme is included
    *
    * @access	public
    * @param	string	the URL
    * @return	string
    */
	public static function prep_url($str = '')
	{
		if ($str == 'http://' OR $str == '')
		{
			return '';
		}

		$url = parse_url($str);

		if ( ! $url OR ! isset($url['scheme']))
		{
			$str = 'http://'.$str;
		}

		return $str;
	}
    
    public static function is_php($version = '5.0.0')
	{
		static $_is_php;
		$version = (string)$version;

		if ( ! isset($_is_php[$version]))
		{
			$_is_php[$version] = (version_compare(PHP_VERSION, $version) < 0) ? FALSE : TRUE;
		}

		return $_is_php[$version];
	}
    
    private static function _ip_strtoarray($ip)	
    {
        $ipArr = explode('.', $ip);
        for($i=0;$i<4;$i++)	{
            $ipArr[$i] = intval($ipArr[$i]);
        }
        return $ipArr;
    }
   
    /**
     * 检查IP是否在指定IP段。IP段表示方法：
     *  subnet mask:  172.16.8.0/255.255.248.0 
     *  cidr:  172.16.8.0/21
     *  single IP:  127.0.0.1
     * @param type $ip
     * @param type $ipBlock
     * @return boolean
     */
    public static function is_allowed_ip($ip, $ipBlock)
    {
        if(!strrchr($ipBlock,'/')) {  // single IP
            if(strcmp($ip,$ipBlock) == 0)
                return true;
            else
                return false;
        }

        $ipBlockArr = explode('/', $ipBlock);
        $ipStart = &$ipBlockArr[0];
        $ipMask = &$ipBlockArr[1];

        $maskList = array();
        if(strpos($ipMask, '.') !== false)
        {	// subnet
            $maskList = self::_ip_strtoarray($ipMask);
            //for($i=0;$i<4;$i++)
            //    $maskList[$i] = intval($maskList[$i]);
        } else  {   // cidr
            for($i=0; $i<4; $i++) {
                if($ipMask == 0) {
                    $maskList[$i] = 0;
                } else if($ipMask < 8) {
                    $maskList[$i] = bindec(str_pad(str_pad('', $ipMask, '1', STR_PAD_RIGHT), 8, '0', STR_PAD_RIGHT));
                    $ipMask = 0;
                } else {
                    $maskList[$i] = 255;
                    $ipMask -= 8;
                }
            }
        }

        $ipArr = self::_ip_strtoarray($ip);
        $ipStartArr = self::_ip_strtoarray($ipStart);
        for($i=0; $i<4; $i++) {
            if($maskList[$i] == 255) {
                if($ipArr[$i] != $ipStartArr[$i])
                    return false;
            } else if($maskList[$i] == 0) {
                    //continue;
            } else	{
                if(($ipStartArr[$i] & $ipArr[$i] & $maskList[$i]) != $ipStartArr[$i])	{
                    return false;
                }
            }
        }

        return true;
    }
    
    // 如果图片是支持的类型(jpeg,gif,png)，则返回图片类型，否则返回false
    public static function get_img_type($imgFile)
    {
        $imageType = exif_imagetype($imgFile);
        switch ($imageType) 
        {
            case IMAGETYPE_GIF:
                $type = 'GIF';
                break;
            case IMAGETYPE_JPEG:
                $type = 'JPEG';
                break;
            case IMAGETYPE_PNG:
                $type = 'PNG';
                break;
            default:
                return false;
        }

        return $type;
    }
    
    // 根据图片文件扩展名确定图片类型
    public static function get_img_type_byext($imgExt)
    {
        switch(strtolower($imgExt))
        {
            case 'jpg':
            case 'jpeg':
                $imgType = 'JPEG';
                break;
            case 'gif':
                $imgType = 'GIF';
                break;
            case 'png':
                $imgType = 'PNG';
                break;
            default:
                $imgType = 'JPEG';
        }

        return $imgType;
    }
    
    public static function get_img_mime($imgType)
    {
        switch (strtoupper($imgType)) 
        {
           case 'GIF':
               $mime = image_type_to_mime_type(IMAGETYPE_GIF);
               break;
           case 'JPG':
           case 'JPEG':
               $mime = image_type_to_mime_type(IMAGETYPE_JPEG);
               break;
           case 'PNG':
               $mime = image_type_to_mime_type(IMAGETYPE_PNG);
               break;
           default:
               return false;
        }

        return $mime;
    }
    
    public static function is_valid_imgext($ext)
    {
        $ext = strtolower($ext);
        if(strcmp($ext,'jpg') == 0 || strcmp($ext,'jpeg') == 0 ||
           strcmp($ext,'gif') == 0 || strcmp($ext,'png') == 0 )
           return true;
        else
            return false;
    }
    
    //获得文件的扩展名，没有扩展名则返回空串。
    public static function get_fileext($file)
    {
        return pathinfo($file, PATHINFO_EXTENSION);
    }
    
    //如果文件存在扩展名，则把文件扩展名修改为$dstExt，扩展名需要'.'
    //如果文件不存在扩展名，则添加扩展名$dstExt。
    public static function change_fileext($file, $dstExt)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if(empty($ext))
           return $file . $dstExt;

        $extLen = strlen($ext);
        $fileLen = strlen($file);

        $fn = substr($file,0,$fileLen - $extLen -1);
        return $fn . $dstExt;
    }
    
    /**
    [normal]
    foo = bar
    ; use quotes to keep your key as it is
    'foo.with.dots' = true

    [array]
    foo[] = 1
    foo[] = 2

    [dictionary]
    foo[debug] = false
    foo[path] = /some/path

    [multi]
    foo.data.config.debug = true
    foo.data.password = 123456
    */
    public static function parse_ini_file_multi($file, $process_sections = false, $scanner_mode = INI_SCANNER_NORMAL) 
    {
        $explode_str = '.';
        $escape_char = "'";
        // load ini file the normal way
        $data = parse_ini_file($file, $process_sections, $scanner_mode);
        if (!$process_sections) {
            $data = array($data);
        }
        foreach ($data as $section_key => $section) {
            // loop inside the section
            foreach ($section as $key => $value) {
                if (strpos($key, $explode_str)) {
                    if (substr($key, 0, 1) !== $escape_char) {
                        // key has a dot. Explode on it, then parse each subkeys
                        // and set value at the right place thanks to references
                        $sub_keys = explode($explode_str, $key);
                        $subs =& $data[$section_key];
                        foreach ($sub_keys as $sub_key) {
                            if (!isset($subs[$sub_key])) {
                                $subs[$sub_key] = [];
                            }
                            $subs =& $subs[$sub_key];
                        }
                        // set the value at the right place
                        $subs = $value;
                        // unset the dotted key, we don't need it anymore
                        unset($data[$section_key][$key]);
                    }
                    // we have escaped the key, so we keep dots as they are
                    else {
                        $new_key = trim($key, $escape_char);
                        $data[$section_key][$new_key] = $value;
                        unset($data[$section_key][$key]);
                    }
                }
            }
        }
        if (!$process_sections) {
            $data = $data[0];
        }
        return $data;
    }

    // 只支持单一、单层继承。
    public static function parse_ini_file_extended($filename) 
    {
        $p_ini = parse_ini_file($filename, true);
        if($p_ini === false)  {
            return false;
        }
        $config = array();
        foreach($p_ini as $namespace => $properties) {
            $info = explode(':', $namespace);
            if ($info === false || empty($info) || empty($info[0]))
                return false;
            if (empty($info[1])) {
                $name = $info[0];
                $extends = "";
            } else {
                list($name, $extends) = $info;
            }
            $name = trim($name);
            $extends = trim($extends);
            // create namespace if necessary
            if(!isset($config[$name])) $config[$name] = array();
            // inherit base namespace
            if(isset($p_ini[$extends])) {
                foreach($p_ini[$extends] as $prop => $val)
                $config[$name][$prop] = $val;
            }
            // overwrite / set current namespace values
            foreach($properties as $prop => $val)
                $config[$name][$prop] = $val;
        }
        return $config;
    }
    
    public static function json_error_msg() 
    {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $errmsg = 'No errors';
                break;
            case JSON_ERROR_DEPTH:
                $errmsg = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $errmsg = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $errmsg = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $errmsg = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $errmsg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $errmsg = 'Unknown error';
                break;
        }
        
        return $errmsg;
    }
    
    public static function json_clean_decode($json, $assoc = false, $depth = 512, $options = 0)
    {
        // search and remove comments like /* */ and //
        $json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);

        if(version_compare(phpversion(), '5.4.0', '>=')) {
            $json = json_decode($json, $assoc, $depth, $options);
        }
        elseif(version_compare(phpversion(), '5.3.0', '>=')) {
            $json = json_decode($json, $assoc, $depth);
        }
        else {
            $json = json_decode($json, $assoc);
        }

        return $json;
    }
}
