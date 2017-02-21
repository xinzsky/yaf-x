<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */

/**
 * 输出类：用于安全的把数据输出到页面上。
 * 凡是需要assign的变量和其取值都要通过output->set(var,value)方法存放到output池中，最后
 * 通过assign(output->data());统一输出。
 * 
 * 输入输出模型：
 * request->input（validator) -> .... -> output -> view -> response
 * 
 * 利用输出类可以：
 * (1)对输出值进行htmlencode、 urlencode、 url协议过滤，避免xss攻击。
 * (2)输出一些html内容，比如表单、表格、图片、链接等。
 * (3)输出分页。
 *
 * ***** 重要 ****
 * 输出的数据就用在页面的下面几个地方：
 * （1）HTML标签内容。 -- htmlencode
 * （2）HTML标签属性值。 -- url协议过滤，过滤掉不允许的协议：javascript vbscript...
 *                    -- htmlencode，特别是单双引号
 *    $href = 'javascript:alert(document.cookie)';
 *    模板中： <a href='$href'>link</a>  ==>
 *    <a href='javascript:alert(document.cookie)'>link</a>
 *    比如： <input name="xss" value="<?php echo $xss;?>">
 *    如果 $xss='" onfocus="alert(/xss/)"'里面的双引号没有转码则会被xss攻击。
 * （3）URL属性值。 --urlencode
 * （4）页面的js代码中。 --引号转义
 *     比如： <script>var num = {$var};</script>
 *     如果$var取值为 1; alert(/xss/) 则有xss漏洞了。  
 *     注意这种情况下用户输入没有特殊标签，所以要确保输出变量在引号里面即 var num='{$var};'，
 *     输出用户数据时需要把引号转义表示，即 \' \"。
 */

class Gek_Output 
{
    private $_data;
    private $table_template = array (
						'table_open'			=> '<table border="0" cellpadding="4" cellspacing="0">',

						'thead_open'			=> '<thead>',
						'thead_close'			=> '</thead>',

						'heading_row_start'		=> '<tr>',
						'heading_row_end'		=> '</tr>',
						'heading_cell_start'	=> '<th>',
						'heading_cell_end'		=> '</th>',

						'tbody_open'			=> '<tbody>',
						'tbody_close'			=> '</tbody>',

						'row_start'				=> '<tr>',
						'row_end'				=> '</tr>',
						'cell_start'			=> '<td>',
						'cell_end'				=> '</td>',

						'row_alt_start'         => '<tr>', // 用于创建隔行颜色
						'row_alt_end'			=> '</tr>',
						'cell_alt_start'		=> '<td>',
						'cell_alt_end'			=> '</td>',

						'table_close'			=> '</table>'
					);
    private $table_set = array(
        'rows'          => array(),
        'heading'       => array(),
        'auto_heading'  => TRUE,
        'caption'       => NULL,
        'template'      => NULL,
        'newline'       => "\n",
        'empty_cells'   => ""
    );
    
    private $form = '';
    private static $kses = NULL;
    
    public function __construct() {
        $this->_data = array();
    }
    
    /**
     * 把HTML中的特殊字符： < >  &  ' "转换为对应的实体表示: &lt; &gt;  &amp; &#039; &quot;。
     * 注意：IE不支持&apos;
     */
    public static function htmlEncode($str)
    {
        if(empty($str)) {
            return $str;
        }
        
        $flags = ENT_COMPAT | ENT_HTML401 | ENT_QUOTES;
        $encoding = "UTF-8"; 
        
        $str = htmlspecialchars($str, $flags, $encoding);
        return $str;
    }
    
    /**
     * ** 当输出的数据是页面中URL一部分时把输出数据中特殊字符（包括汉字）用%HH编码表示。 **
     * 对字母、数字、'_' '-' '.'之外的符号进行十六进制编码，注意空格会用'+'代替。如果$raw=true，则空格采用%20表示。
     * 一般用于application/x-www-form-urlencoded表单对应URL的query string的变量名和变量取值部分。
     * 比如： http://www.domain.com/test.php?name=value&name1=value1
     * 只能对querystring中的name、value部分采用urlencode，不能对整个URL或querystring进行urlencode.
     * 也可以用于URL path部分，比如路径部分有汉字、URL保留字符、URL非安全字符，则需要采用编码表示。
     * 但要注意：由于路径部分允许出现+号，所以空格采用%20，此时$raw=true。
     * 
     * 空格用 '+' 还是 '20%'表示？
     * URL的querystring里的空格用 '+'表示， URL的PATH里的空格用 '20%'表示。
     */
    public static function urlEncode($str, $raw = false)
    {
        if(empty($str)) {
            return $str;
        }
        
        
        if($raw) {
            return rawurlencode($str);
        } else {
            return urlencode($str);
        }
    }
    
    /**
     * 把字符串的引号转义表示，即 \' \"。
     * 当输出的数据需要在页面的js部分时，需要把输出数据中引号转义表示。
     */
    public static function quoteEscape($str)
    {
        if(empty($str)) {
            return $str;
        } else {
            return str_replace(array("'",'"'), array("\'",'\"'), $str);
        }
    }
    
    /**
     * 当输出数据是页面中标签属性值时，需要过滤掉不允许的URL协议，比如javascript:.
     * 目前允许的URL协议：http https mailto ftp
     * @param type $str
     */
    public static function urlProtocolFilter($str)
    {
        if(empty($str)) {
            return $str;
        }
        
        if(self::$kses === NULL) {
            self::$kses = new Gek_Utils_Kses5();
        }
        $str = self::$kses->removeBadProtocols($str);
        return $str;
    }
    
    private static function _html_encode_callback(&$value)
    {
        if(is_string($value)) {
            $value = self::htmlEncode($value);
        }
    }
    
    private static function _url_protocol_filter_callback(&$value)
    {
        if(is_string($value)) {
            $value = self::urlProtocolFilter($value);
        }
    }
    
    private static function _url_encode_callback(&$value, $key, $raw)
    {
        if(is_string($value)) {
           $value = self::urlEncode($value, $raw);
        }
    }
    
    private static function _quote_escape_callback(&$value)
    {
        if(is_string($value)) {
           $value = self::quoteEscape($value);
        }
    }
    
    /**
     * 设置模板变量的取值。
     * @param type $var 
     * @param type $value       可以是任何类型，字符串需要html编码，多维数组需要递归处理。
     * @param type $html_encode $value是否需要进行HTML编码。
     */
    public function set($var, $value, $html_encode=true)
    {
        if($html_encode && is_string($value)) {
            $this->_data[$var] = self::htmlEncode($value);
        } else if($html_encode && is_array($value)){
            array_walk_recursive($value, array(self, '_html_encode_callback'));
            $this->_data[$var] = $value;
        } else {
            $this->_data[$var] = $value;
        }
        
        return true;
    }
    
    /**
     * 如果$value用于标签的属性值，需要调用此方法，因为设置标签属性值都要进行URL协议过滤。
     * 注意：标签属性值里的 ' "要特别注意要HTML编码。
     * @param type $var
     * @param type $value      可以是任何类型，字符串需要html编码，多维数组需要递归处理。
     * @param type $html_encode
     */
    public function setAttrValue($var, $value, $html_encode=true)
    {
        if(is_string($value)) {
            $value = self::urlProtocolFilter($value);
        } else if(is_array($value)){
            array_walk_recursive($value, array(self, '_url_protocol_filter_callback'));
        }
        
        return $this->set($var, $value, $html_encode);
    }
    
    /**
     * URL中的$value需要urlencode，但不要进行htmlencode,因为URL querystring里有&符号，html会编码为&amp;
     * @param type $var
     * @param type $value
     * @param type $raw  指明空格是采用'+' 还是 %20
     */
    public function setUrlValue($var, $value, $raw=false)
    {
        if(is_string($value)) {
            $this->_data[$var] = self::urlEncode($value, $raw);
        } else if(is_array($value)){
            array_walk_recursive($value, array(self, '_url_encode_callback'), $raw);
            $this->_data[$var] = $value;
        } else {
            $this->_data[$var] = $value;
        }
        
        return true;
    }
    
    /**
     * 如果输出数据是js的变量值，就需要对输出数据中的引号进行转义处理。此时js代码中必须用引号把这些值包括起来。
     * @param type $var
     * @param type $value
     * @return boolean
     */
    public function setJavascriptValue($var, $value)
    {
        if(is_string($value)) {
            $this->_data[$var] = self::quoteEscape($value);
        } else if(is_array($value)) {
            array_walk_recursive($value, array(self, '_quote_escape_callback'));
            $this->_data[$var] = $value;
        } else {
            $this->_data[$var] = $value;
        }
        return true;
    }
    
    public function get($var)
    {
        if(isset($this->_data[$var])) {
            return $this->_data[$var];
        } else {
            return false;
        }
    }
    
    public function data()
    {
        return $this->_data;
    }
    
    public function setPagination($var, $params=array()) 
    {
        $pg = new Gek_Pagination($params);
        $pagination = $pg->make();
        $this->_data[$var] = $pagination;
        return true;
    }
    
    /**
    * Heading
    *
    * Generates an HTML heading tag.  First param is the data.
    * Second param is the size of the heading tag.
    * @param	string
    * @param	integer
    * @return	string
    */
    public function setHeading($var, $value, $h = '1', $attributes = '', $html_encode=true)
	{
        if($html_encode) {
            $value = self::htmlEncode($value);
        }
		$attributes = ($attributes != '') ? ' '.$attributes : $attributes;
        $this->_data[$var] = "<h".$h.$attributes.">".$value."</h".$h.">";
		return true;
	}
    
    /**
    * Unordered List
    *
    * Generates an HTML unordered list from an single or multi-dimensional array.
    * 参数$value如果不是数组，则不生成列表。参数$value可以是一维或多维数组。
    * 参数$attributes用来指明<UL>标签的属性，可以是字符串或者数组：
    * $attributes = array(
                   'class' => 'boldlist',
                   'id'    => 'mylist'
                   );
    */
	public function setUList($var, $value, $attributes = '', $html_encode=true)
	{
		$this->_data[$var] = _list('ul', $value, $attributes, $html_encode);
        return true;
	}
    
    /**
     * Ordered List
     *
     * Generates an HTML ordered list from an single or multi-dimensional array.
     * 参数$value如果不是数组，则不生成列表。参数$value可以是一维或多维数组。
     * 参数$attributes用来指明<OL>标签的属性，可以是字符串或者数组：
     * $attributes = array(
                    'class' => 'boldlist',
                    'id'    => 'mylist'
                    );
     *
     */

	public function setOList($var, $value, $attributes = '', $html_encode=true)
	{
		$this->_data[$var] = _list('ol', $value, $attributes, $html_encode);
        return true;
	}

    /**
     * Generates the list
     * Generates an HTML ordered list from an single or multi-dimensional array.
     */
	private function _list($type = 'ul', $list = '', $attributes = '', $depth = 0, $html_encode=true)
	{
		// If an array wasn't submitted there's nothing to do...
		if ( ! is_array($list))
		{
			return $list;
		}

		// Set the indentation based on the depth
		$out = str_repeat(" ", $depth);

		// Were any attributes submitted?  If so generate a string
		if (is_array($attributes))
		{
			$atts = '';
			foreach ($attributes as $key => $val)
			{
				$atts .= ' ' . $key . '="' . $val . '"';
			}
			$attributes = $atts;
		}
		elseif (is_string($attributes) AND strlen($attributes) > 0)
		{
			$attributes = ' '. $attributes;
		}

		// Write the opening list tag
		$out .= "<".$type.$attributes.">\n";

		// Cycle through the list elements.  If an array is
		// encountered we will recursively call _list()

		static $_last_list_item = '';
		foreach ($list as $key => $val)
		{
			$_last_list_item = $key;

			$out .= str_repeat(" ", $depth + 2);
			$out .= "<li>";

			if ( ! is_array($val))
			{
                if($html_encode) {
                    $val = self::htmlEncode($val);
                }
				$out .= $val;
			}
			else
			{
				$out .= $_last_list_item."\n";
				$out .= _list($type, $val, '', $depth + 4, $html_encode);
				$out .= str_repeat(" ", $depth + 2);
			}

			$out .= "</li>\n";
		}

		// Set the indentation for the closing tag
		$out .= str_repeat(" ", $depth);

		// Write the closing list tag
		$out .= "</".$type.">\n";

		return $out;
	}
    
    /**
     * Image
     *
     * Generates an <img /> element
     * 参数src可以是字符串，也可以是关联数组，用来实现对所有属性和值的完全控制。
        $image_properties = array(
          'src' => 'images/picture.jpg',
          'alt' => 'Me, demonstrating how to eat 4 slices of pizza at one time',
          'class' => 'post_images',
          'width' => '200',
          'height' => '200',
          'title' => 'That was quite a night',
          'rel' => 'lightbox');
     */
	public function setImg($var, $src = '', $alt='')
	{
		if ( ! is_array($src) ) {
			$src = array('src' => $src, 'alt'=> $alt);
		}

		// If there is no alt attribute defined, set it to an empty string
		if ( ! isset($src['alt'])) {
			$src['alt'] = '';
		}

		$img = '<img';

		foreach ($src as $k=>$v) {
            $v = self::urlProtocolFilter($v);
            $v = self::htmlEncode($v);
            $img .= " $k=\"$v\"";
		}

		$img .= '/>';
        $this->_data[$var] = $img;
		return true;
	}
    
    /**
    * Anchor Link 生成一个anchor链接
    *
    * Creates an anchor based on the local URL.
    *
    * @param	string	the URL
    * @param	string	the link title
    * @param	mixed	any attributes
    * @return	string
    */
	public function setAnchor($var, $value, $uri, $title = '', $attributes = '', $html_encode=true)
	{
		if ($attributes != '')
		{
			$attributes = $this->_parse_attributes($attributes);
		}
        
        if($html_encode) {
            $value = self::htmlEncode($value);
        }
        $title = self::urlProtocolFilter($title);
        $title = self::htmlEncode($title);
        if($title === '' || $title === NULL || $title === false) {
            $this->_data[$var] = '<a href="'.$uri.'"'.$attributes.'>'.$value.'</a>';
        } else {
            $this->_data[$var] = '<a href="'.$uri.'"'.$attributes." title=\"{$title}\"". '>'.$value.'</a>';
        }
        
		return true;
	}
    
    /**
    * Anchor Link - Pop-up version
    *
    * Creates an anchor based on the local URL. The link
    * opens a new window based on the attributes specified.
    *
    * 创建一个弹出窗口的链接，点击该链接会在新窗口打开链接。
    * 可以通过$attributes参数来指定JavaScript窗口属性来控制窗口的打开方式。
    * 如果参数$attributes设置为false，则根据你的浏览器设置打开新窗口。
    * 如果参数$attributes设置为空数组，则采用下面的默认值来弹出窗口：
    * $atts = array(
              'width'      => '800',
              'height'     => '600',
              'scrollbars' => 'yes',
              'status'     => 'yes',
              'resizable'  => 'yes',
              'screenx'    => '0',
              'screeny'    => '0'
            );
    * @param	string	the URL
    * @param	string	the link title
    * @param	mixed	any attributes
    * @return	string
    */

    public function setPopupAnchor($var, $value, $uri = '', $attributes = FALSE, $html_encode=true)
    {
       
        if ($attributes === FALSE)
        {
            $this->_data[$var] = "<a href='javascript:void(0);' onclick=\"window.open('".$uri."', '_blank');\">".$value."</a>";
            return true;
        }

        if ( ! is_array($attributes))
        {
            $attributes = array();
        }

        foreach (array('width' => '800', 'height' => '600', 'scrollbars' => 'yes', 'status' => 'yes', 'resizable' => 'yes', 'screenx' => '0', 'screeny' => '0', ) as $key => $val)
        {
            $atts[$key] = ( ! isset($attributes[$key])) ? $val : $attributes[$key];
            unset($attributes[$key]);
        }

        if ($attributes != '')
        {
            $attributes = $this->_parse_attributes($attributes);
        }
        
        if($html_encode) {
            $value  = self::htmlEncode($value);
        }
        $this->_data[$var] = "<a href='javascript:void(0);' onclick=\"window.open('".$uri."', '_blank', '".  $this->_parse_attributes($atts, TRUE)."');\"$attributes>".$value."</a>";
        return true;
    }
    
    /**
    * Mailto Link
    *
    * @access	public
    * @param	string	the email address
    * @param	string	the link title
    * @param	mixed	any attributes
    * @return	string
    */
    
	public function setMailto($var, $email, $title='', $attributes = '', $html_encode=true)
	{
		$title = (string) $title;

		if ($title == "")
		{
			$title = $email;
		}
        
        if($html_encode) {
            $title = self::htmlEncode($title);
        }
        $email = self::urlProtocolFilter($email);
        $email = self::htmlEncode($email);

		$attributes = $this->_parse_attributes($attributes);

		$this->_data[$var] = '<a href="mailto:'.$email.'"'.$attributes.'>'.$title.'</a>';
        return true;
	}
    
    /**
    * Encoded Mailto Link
    *
    * Create a spam-protected mailto link written in Javascript
    * 用JavaScript写了基于顺序号码的不易识别的mailto版本标签,可以阻止email地址被垃圾邮件截获.

    * @param	string	the email address
    * @param	string	the link title
    * @param	mixed	any attributes
    * @return	string
    */
	public function setSafeMailto($var, $email, $title = '', $attributes = '', $html_encode=true)
	{
		$title = (string) $title;

		if ($title == "")
		{
			$title = $email;
		}
        
        if($html_encode) {
            $title = self::htmlEncode($title);
        }
        $email = self::urlProtocolFilter($email);
        $email = self::htmlEncode($email);

		for ($i = 0; $i < 16; $i++)
		{
			$x[] = substr('<a href="mailto:', $i, 1);
		}

		for ($i = 0; $i < strlen($email); $i++)
		{
			$x[] = "|".ord(substr($email, $i, 1));
		}

		$x[] = '"';

		if ($attributes != '')
		{
			if (is_array($attributes))
			{
				foreach ($attributes as $key => $val)
				{
					$x[] =  ' '.$key.'="';
					for ($i = 0; $i < strlen($val); $i++)
					{
						$x[] = "|".ord(substr($val, $i, 1));
					}
					$x[] = '"';
				}
			}
			else
			{
				for ($i = 0; $i < strlen($attributes); $i++)
				{
					$x[] = substr($attributes, $i, 1);
				}
			}
		}

		$x[] = '>';

		$temp = array();
		for ($i = 0; $i < strlen($title); $i++)
		{
			$ordinal = ord($title[$i]);

			if ($ordinal < 128)
			{
				$x[] = "|".$ordinal;
			}
			else
			{
				if (count($temp) == 0)
				{
					$count = ($ordinal < 224) ? 2 : 3;
				}

				$temp[] = $ordinal;
				if (count($temp) == $count)
				{
					$number = ($count == 3) ? (($temp['0'] % 16) * 4096) + (($temp['1'] % 64) * 64) + ($temp['2'] % 64) : (($temp['0'] % 32) * 64) + ($temp['1'] % 64);
					$x[] = "|".$number;
					$count = 1;
					$temp = array();
				}
			}
		}

		$x[] = '<'; $x[] = '/'; $x[] = 'a'; $x[] = '>';

		$x = array_reverse($x);
		ob_start();

	?><script type="text/javascript">
	//<![CDATA[
	var l=new Array();
	<?php
	$i = 0;
	foreach ($x as $val){ ?>l[<?php echo $i++; ?>]='<?php echo $val; ?>';<?php } ?>

	for (var i = l.length-1; i >= 0; i=i-1){
	if (l[i].substring(0, 1) == '|') document.write("&#"+unescape(l[i].substring(1))+";");
	else document.write(unescape(l[i]));}
	//]]>
	</script><?php

		$buffer = ob_get_contents();
		ob_end_clean();
        
        if(!empty($var)) {
            $this->_data[$var] = $buffer;
            return true;
        } else {
            return $buffer;
        }
	}
    
    /**
    * Auto-linker 自动把包含URL和email地址的字串转换成链接 (目前对URL两边没有空格时识别不出来)
    *
    * Automatically links URL and Email addresses.
    * Note: There's a bit of extra code here to deal with
    * URLs or emails that end in a period.  We'll strip these
    * off and add them after the link.
    *
    * @access	public
    * @param	string	the string
    * @param	string	the type: email, url, or both
    * @param	bool	whether to create pop-up links
    * @return	string
    */
	public function setAutoLink($var, $str, $type = 'both', $popup = FALSE)
	{
		if ($type != 'email')
		{
			if (preg_match_all("#(^|\s|\()((http(s?)://)|(www\.))(\w+[^\s\)\<]+)#i", $str, $matches))
			{
				$pop = ($popup == TRUE) ? " target=\"_blank\" " : "";

				for ($i = 0; $i < count($matches['0']); $i++)
				{
					$period = '';
					if (preg_match("|\.$|", $matches['6'][$i]))
					{
						$period = '.';
						$matches['6'][$i] = substr($matches['6'][$i], 0, -1);
					}

					$str = str_replace($matches['0'][$i],
										$matches['1'][$i].'<a href="http'.
										$matches['4'][$i].'://'.
										$matches['5'][$i].
										$matches['6'][$i].'"'.$pop.'>http'.
										$matches['4'][$i].'://'.
										$matches['5'][$i].
										$matches['6'][$i].'</a>'.
										$period, $str);
				}
			}
		}

		if ($type != 'url')
		{
			if (preg_match_all("/([a-zA-Z0-9_\.\-\+]+)@([a-zA-Z0-9\-]+)\.([a-zA-Z0-9\-\.]*)/i", $str, $matches))
			{
				for ($i = 0; $i < count($matches['0']); $i++)
				{
					$period = '';
					if (preg_match("|\.$|", $matches['3'][$i]))
					{
						$period = '.';
						$matches['3'][$i] = substr($matches['3'][$i], 0, -1);
					}

					$str = str_replace($matches['0'][$i], $this->setSafeMailto('', $matches['1'][$i].'@'.$matches['2'][$i].'.'.$matches['3'][$i]).$period, $str);
				}
			}
		}
        
        $this->_data[$var] = $str;
		return true;
	}

    /**
     * Link Tag
     *
     * Generates link to a CSS file
     * 
     * 帮助你创建 HTML <link /> 标签。在链接样式表以及其他内容时非常有用。
     * 参数包括 href 以及可选的 rel, type, title, media。
     * link_tag('http://site.com/favicon.ico', 'shortcut icon', 'image/ico');
     * ==> <link href="http://site.com/favicon.ico" rel="shortcut icon" type="image/ico" /> 
     * 参数href还可以是一个关联数组，如：
     * $link = array(
          'href' => 'css/printer.css',
          'rel' => 'stylesheet',
          'type' => 'text/css',
          'media' => 'print');
     */
	public function setLinkTag($var, $href = '', $rel = 'stylesheet', $type = 'text/css', $title = '', $media = '')
	{
		$link = '<link ';
		if (is_array($href)) {
			foreach ($href as $k=>$v) {
				$link .= "$k=\"$v\" ";
			}

			$link .= "/>";
		} else {
			$link .= 'href="'.$href.'" ';
			$link .= 'rel="'.$rel.'" type="'.$type.'" ';
			if ($media	!= '') {
				$link .= 'media="'.$media.'" ';
			}

			if ($title	!= '') {
				$link .= 'title="'.$title.'" ';
			}

			$link .= '/>';
		}
        
        $this->_data[$var] = $link;
		return true;
	}

    /**
     * Generates meta tags from an array of key/values
     * 创建meta标签，
     * 参数$name可以是字符串、简单数组或者多维数组。
     * 参数$type可以是 "equiv" 或者 "name"
     * 
     * 举例如下：
     * meta('description', 'My Great site'); 生成: <meta name="description" content="My Great Site" />
     * meta('Content-type', 'text/html; charset=utf-8', 'equiv'); 
     * 生成: <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
     *  meta(array('name' => 'robots', 'content' => 'no-cache'));
     * 生成: <meta name="robots" content="no-cache" />
     * $meta = array(
        array('name' => 'robots', 'content' => 'no-cache'),
        array('name' => 'description', 'content' => 'My Great Site'),
        array('name' => 'keywords', 'content' => 'love, passion, intrigue, deception'),
        array('name' => 'robots', 'content' => 'no-cache'),
        array('name' => 'Content-type', 'content' => 'text/html; charset=utf-8', 'type' => 'equiv')
        );
     */
	public function setMeta($var, $name = '', $content = '', $type = 'name', $newline = "\n")
	{
		// Since we allow the data to be passes as a string, a simple array
		// or a multidimensional one, we need to do a little prepping.
		if ( ! is_array($name))
		{
			$name = array(array('name' => $name, 'content' => $content, 'type' => $type, 'newline' => $newline));
		}
		else
		{
			// Turn single array into multidimensional
			if (isset($name['name']))
			{
				$name = array($name);
			}
		}

		$str = '';
		foreach ($name as $meta)
		{
			$type		= ( ! isset($meta['type']) OR $meta['type'] == 'name') ? 'name' : 'http-equiv';
			$name		= ( ! isset($meta['name']))		? ''	: $meta['name'];
			$content	= ( ! isset($meta['content']))	? ''	: $meta['content'];
			$newline	= ( ! isset($meta['newline']))	? "\n"	: $meta['newline'];

            $name = self::urlProtocolFilter($name);
            $name = self::htmlEncode($name);
            $content = self::urlProtocolFilter($content);
            $content = self::htmlEncode($content);
			$str .= '<meta '.$type.'="'.$name.'" content="'.$content.'" />'.$newline;
		}

        $this->_data[$var] = $str;
		return true;
	}

    /**
     * Generates non-breaking space entities based on number supplied
     * 变量值的后面带有指定个数的空格(&nbsp;)。
     */
	public function setNbsp($var, $value, $num = 1, $html_encode=true)
	{
        if($html_encode) {
            $value = self::htmlEncode($value);
        }
        $this->_data[$var] = $value . str_repeat("&nbsp;", $num);
		return true;
	}
    
    /**
     * Generates HTML BR tags based on number supplied
     * 变量值后边带有指定个数的换行标签。
     */
	public function setBr($var, $value, $num = 1, $html_encode=true)
	{
        if($html_encode) {
            $value = self::htmlEncode($value);
        }
        
        $this->_data[$var] = $value . str_repeat("<br />", $num);
		return true;
	}
    
    private function _parse_attributes($attributes, $javascript = FALSE)
	{
		if (is_string($attributes))
		{
			return ($attributes != '') ? ' '.$attributes : '';
		}

		$att = '';
		foreach ($attributes as $key => $val)
		{
			if ($javascript == TRUE)
			{
				$att .= $key . '=' . $val . ',';
			}
			else
			{
				$att .= ' ' . $key . '="' . $val . '"';
			}
		}

		if ($javascript == TRUE AND $att != '')
		{
			$att = substr($att, 0, -1);
		}

		return $att;
	}
    
    /**
     * HTML Table Generating Method
     *
     * Lets you create tables manually or from database result objects, or arrays.
     */

	 /**
	 * Set the table template
	 * @param	array
	 * @return	bool
	 */
	public function tableSetTemplate($template)
	{
		if ( ! is_array($template)) {
			return FALSE;
		}
        
		foreach (array('table_open', 'thead_open', 'thead_close', 'heading_row_start', 'heading_row_end', 'heading_cell_start', 'heading_cell_end', 'tbody_open', 'tbody_close', 'row_start', 'row_end', 'cell_start', 'cell_end', 'row_alt_start', 'row_alt_end', 'cell_alt_start', 'cell_alt_end', 'table_close') as $val)
		{
			if ( isset($template[$val])) {
				$this->table_set['template'][$val] = $template[$val];
			} else {
                $this->table_set['template'][$val] = $this->table_template[$val];
            }
		}
        
        return TRUE;
	}
    
	/**
	 * Set the table heading
	 * Can be passed as an array or discreet params(可变参数)
     * 
     * 设置表格的表头。你可以提交一个数组或分开的参数:
     * tableSetHeading('Name', 'Color', 'Size');
     * tableSetHeading(array('Name', 'Color', 'Size'));
     * 
	 * @param	mixed
	 * @return	void
	 */
	public function tableSetHeading()
	{
		$args = func_get_args();
		$this->table_set['heading'] = $this->_prep_args($args);
	}
    
    /**
	 * Add a table caption 给表格添加一个标题
	 *
	 * @param	string
	 * @return	void
	 */
	public function tableSetCaption($caption)
	{
		$this->table_set['caption'] = $caption;
	}
    
     /**
	 * Set "empty" cells 
     * 设置内容为空的单元格的值。
     * 例如，你可以设置一个non-breaking space(用来防止表格边框破损的空格)
     * 
	 * @param	string
	 * @return	void
	 */
	public function tableSetEmpty($value)
	{
		$this->table_set['empty_cells'] = $value;
	}

	/**
	 * Make columns.  Takes a one-dimensional array as input and creates
	 * a multi-dimensional array with a depth equal to the number of
	 * columns.  This allows a single array with many elements to  be
	 * displayed in a table that has a fixed column count.
     * 
	 * 把一个一维数组的数据按照指定的列数采用表格输出。
     * 
	 * @param	array
	 * @param	int
	 * @return	array 返回一个二维数组，用于生成table。 错误返回false
	 */
	public function tableMakeColumns($array = array(), $col_limit = 0)
	{
		if ( ! is_array($array) OR count($array) == 0)
		{
			return FALSE;
		}

		// Turn off the auto-heading feature since it's doubtful we
		// will want headings from a one-dimensional array
		$this->table_set['auto_heading'] = FALSE;

		if ($col_limit == 0)
		{
			return $array;
		}

		$new = array();
		while (count($array) > 0)
		{
			$temp = array_splice($array, 0, $col_limit);

			if (count($temp) < $col_limit)
			{
				for ($i = count($temp); $i < $col_limit; $i++)
				{
					$temp[] = '&nbsp;';
				}
			}

			$new[] = $temp;
		}

		return $new;
	}

	/**
	 * Add a table row
	 * Can be passed as an array or discreet params
	 * 在表格中添加一行，可以提交一个数组或分开的参数。
     * 
     * 如果你想要单独设置一个单元格的属性，你可以使用一个关联数组。
     * 关联键名 'data' 定义了这个单元格的数据。
     * 关联键名 'htmlencode'定义了这个单元格的数据是不是要进行HTML编码。****
     * 其它的键值对 key => val 将会以 key='val' 的形式被添加为该单元格的属性:
     * $cell = array('data' => 'Blue', 'class' => 'highlight', 'colspan' => 2);
     * tableAddRow($cell, 'Red', 'Green');
     * 生成： <td class='highlight' colspan='2'>Blue</td><td>Red</td><td>Green</td>
	 * @param	mixed
	 * @return	void
	 */
	public function tableAddRow()
	{
		$args = func_get_args();
		$this->table_set['rows'][] = $this->_prep_args($args);
	}
    
     /**
	 * Clears the table arrays.  Useful if multiple tables are being generated
	 * 清除表格的表头和行中的数据。
     * 如果你需要显示多个有不同数据的表格，那么你需要在每个表格生成之后调用这个函数来清除之前表格的信息。
     * 注意：没有清除caption empty_cells template
	 * @access	public
	 * @return	void
	 */
	public function tableClear()
	{
        unset($this->table_set['rows']);
        unset($this->table_set['heading']);
        $this->table_set['rows'] = array();
        $this->table_set['heading'] = array();
        $this->table_set['auto_heading'] = TRUE;
	}
    
    /**
	 * Generate the table
	 * 生成一个table，参数是一个数组，也可以为空。table里的数据都会进行HTML encode.
     * 注意:数组的第一个元素将成为表头(或者你可以通过set_heading()函数自定义表头)。
     * 如果某单元格的数据不需要进行HTML编码，可以设置该单元格为关联数组：
     * 关联键名 'data' 定义了这个单元格的数据。
     * 关联键名 'htmlencode'定义了这个单元格的数据是不是要进行HTML编码。****
	 */
	public function setTable($var, $table_data = NULL)
	{
		// The table data can optionally be passed to this function
		// either as a database result object or an array
		if ( ! is_null($table_data) && is_array($table_data))
		{
            $set_heading = (count($this->table_set['heading']) == 0 AND $this->table_set['auto_heading'] == FALSE) ? FALSE : TRUE;
			$this->_set_from_array($table_data, $set_heading);
		}

		// Is there anything to display?  No?  Smite them!
		if (count($this->table_set['heading']) == 0 AND count($this->table_set['rows']) == 0)
		{
            $this->_data[$var] = '';
			return true;
		}

		// Build the table!
        if(!isset($this->table_set['template'])) {
            $this->table_set['template'] = $this->table_template;
        }
        
		$out = $this->table_set['template']['table_open'];
		$out .= $this->table_set['newline'];

		// Add any caption here
		if ($this->table_set['caption'])
		{
			$out .= $this->table_set['newline'];
			$out .= '<caption>' . self::htmlEncode($this->table_set['caption']) . '</caption>';
			$out .= $this->table_set['newline'];
		}

		// Is there a table heading to display?
		if (count($this->table_set['heading']) > 0)
		{
			$out .= $this->table_set['template']['thead_open'];
			$out .= $this->table_set['newline'];
			$out .= $this->table_set['template']['heading_row_start'];
			$out .= $this->table_set['newline'];

			foreach ($this->table_set['heading'] as $heading)
			{
				$temp = $this->table_set['template']['heading_cell_start'];

				foreach ($heading as $key => $val)
				{
					if ($key != 'data')
					{
						$temp = str_replace('<th', "<th $key='$val'", $temp);
					}
				}

				$out .= $temp;
				$out .= isset($heading['data']) ? self::htmlEncode($heading['data']) : '';
				$out .= $this->table_set['template']['heading_cell_end'];
			}

			$out .= $this->table_set['template']['heading_row_end'];
			$out .= $this->table_set['newline'];
			$out .= $this->table_set['template']['thead_close'];
			$out .= $this->table_set['newline'];
		}

		// Build the table rows
		if (count($this->table_set['rows']) > 0)
		{
			$out .= $this->table_set['template']['tbody_open'];
			$out .= $this->table_set['newline'];

			$i = 1;
			foreach ($this->table_set['rows'] as $row)
			{
				if ( ! is_array($row))
				{
					break;
				}

				// We use modulus to alternate the row colors
				$name = (fmod($i++, 2)) ? '' : 'alt_';

				$out .= $this->table_set['template']['row_'.$name.'start'];
				$out .= $this->table_set['newline'];

				foreach ($row as $cell)
				{
					$temp = $this->table_set['template']['cell_'.$name.'start'];
                    
                    $htmlencode = 1; 
					foreach ($cell as $key => $val)
					{
						if ($key != 'data' && $key != 'htmlencode')
						{
							$temp = str_replace('<td', "<td $key='$val'", $temp);
						}
                        
                        if($key == 'htmlencode') {
                            $htmlencode = intval($val);
                        }
					}
                    
                    if($htmlencode) {
                        $cell = isset($cell['data']) ? self::htmlEncode($cell['data']) : '';
                    } else {
                        $cell = isset($cell['data']) ? $cell['data'] : '';
                    }
					$out .= $temp;

					if ($cell === "" OR $cell === NULL)
					{
						$out .= self::htmlEncode($this->table_set['empty_cells']);
					}
					else
					{
                        $out .= $cell;
					}

					$out .= $this->table_set['template']['cell_'.$name.'end'];
				}

				$out .= $this->table_set['template']['row_'.$name.'end'];
				$out .= $this->table_set['newline'];
			}

			$out .= $this->table_set['template']['tbody_close'];
			$out .= $this->table_set['newline'];
		}

		$out .= $this->table_set['template']['table_close'];

		// Clear table class properties before generating the table
		$this->tableClear();
        $this->_data[$var] = $out;
		return true;
	}

	// --------------------------------------------------------------------

	/**
	 * Prep Args
	 *
	 * Ensures a standard associative array format for all cell data
	 *
	 * @access	public
	 * @param	type
	 * @return	type
	 */
	private  function _prep_args($args)
	{
		// If there is no $args[0], skip this and treat as an associative array
		// This can happen if there is only a single key, for example this is passed to table->generate
		// array(array('foo'=>'bar'))
		if (isset($args[0]) AND (count($args) == 1 && is_array($args[0])))
		{
			// args sent as indexed array
			if ( ! isset($args[0]['data']))
			{
				foreach ($args[0] as $key => $val)
				{
					if (is_array($val) && isset($val['data']))
					{
						$args[$key] = $val;
					}
					else
					{
						$args[$key] = array('data' => $val);
					}
				}
			}
		}
		else
		{
			foreach ($args as $key => $val)
			{
				if ( ! is_array($val))
				{
					$args[$key] = array('data' => $val);
				}
			}
		}

		return $args;
	}

	/**
	 * Set table data from an array
	 *
	 * @access	public
	 * @param	array
	 * @return	void
	 */
	private  function _set_from_array($data, $set_heading = TRUE)
	{
		if ( ! is_array($data) OR count($data) == 0)
		{
			return FALSE;
		}

		$i = 0;
		foreach ($data as $row)
		{
			// If a heading hasn't already been set we'll use the first row of the array as the heading
			if ($i == 0 AND count($data) > 1 AND count($this->table_set['heading']) == 0 AND $set_heading == TRUE)
			{
				$this->table_set['heading'] = $this->_prep_args($row);
			}
			else
			{
				$this->table_set['rows'][] = $this->_prep_args($row);
			}

			$i++;
		}
	}
    
    /**
     * 创建一个表单的开始部分。
     * @param   string  $action
     * @parma   string  $method     请求方法
     * @parma   boolean $multipart  enctyp是不是为'multipart/form-data'，默认为'application/x-www-form-urlencoded'
     * @param   array	$attributesa key/value pair of attributes 也可以是字符串
     * @return	string
     */
	public function formOpen($action, $method='post', $multipart=false, $attributes = '')
	{
		
		$form = "<form method=\"{$method}\" action=\"{$action}\" ";
        if($multipart) {
            $form .= 'enctype="multipart/form-data"';
        } else {
            $form .= 'enctype="application/x-www-form-urlencoded"';
        }

		$form .= $this->_attributes_to_string($attributes, TRUE);

		$form .= '>';

		$this->form .= $form;
		return $form;
	}
    
    public function formClose($extra = '')
	{
        $form = "</form>".$extra;
        $this->form = $form;
		return $form;
	}
    
    /**
    * Fieldset Tag
    * Fieldset标签可以用来给表单元素进行分组，分组说明使用legend元素。
     * 
    * Used to produce <fieldset><legend>text</legend>.  To close fieldset
    * use form_fieldset_close()
    *
    * @access	public
    * @param	string	The legend text
    * @param	string	Additional attributes
    * @return	string
    */
	public function formFieldsetOpen($legend_text = '', $attributes = array())
	{
		$fieldset = "<fieldset";

		$fieldset .= $this->_attributes_to_string($attributes, FALSE);

		$fieldset .= ">\n";

		if ($legend_text != '')
		{
			$fieldset .= "<legend>$legend_text</legend>\n";
		}
        
        $this->form .= $fieldset;
		return $fieldset;
	}
    
    /**
    * Fieldset Close Tag
    *
    * @access	public
    * @param	string
    * @return	string
    */
	public function formFieldsetClose($extra = '')
	{
        $fieldset = "</fieldset>".$extra;
        $this->form .= $fieldset;
		return $fieldset;
	}
    
    public function setForm($var)
    {
        $this->_data[$var] = $this->form;
        unset($this->form);
        $this->form = '';
        return true;
    }
    
    /**
    * Text Input Field 创建一个文本输入框
    * text input属性: 
    *  name       
    *  value      默认值 
    *  size       文本框的宽度，单位是单个字符宽度
    *  maxlength  可输入的最大字符数。
    *  readonly   只读
    *  disabled   使域无效以防止输入文本。
    * @param    string  $var     模板变量名，如果为空，则不设置。
    * @param	mixed   $data    字符串表示name属性，关联数组则表示多个属性。
    * @param	string  $value   默认值
    * @param	string  $extra   额外属性,比如:id class style ...。
    * @return	string or bool 
    */
	public function setFormInputText($var, $data = '', $value = '', $extra = '')
	{
		$defaults = array('type' => 'text', 'name' => (( ! is_array($data)) ? $data : ''), 'value' => $value);
        
        $text = "<input ".  $this->_parse_form_attributes($data, $defaults).$extra." />";
		if(empty($var)) {
            $this->form .= $text;
            return $text;
        } else {
            $this->_data[$var] = $text;
            return true;
        }
	}
    
    /**
     * Password Field
     * Identical to the input function but adds the "password" type
     * 参数和属性和文本框一样。
     */
	public function setFormPassword($var, $data = '', $value = '', $extra = '')
	{
		if ( ! is_array($data))
		{
			$data = array('name' => $data);
		}

		$data['type'] = 'password';
		return $this->setFormInputText($var, $data, $value, $extra);
	}
    
    /**
     * Upload Field 生成一个文件上传框。参数含义和文本框一样。
     * Identical to the input function but adds the "file" type
     * 注意：表单标签中必须设置ENCTYPE="multipart/form-data"来确保文件被正确编码；
     *      表单的传送方式必须设置成POST。
     */
	public function setFormFileUpload($var, $data = '', $value = '', $extra = '')
	{
		if ( ! is_array($data))
		{
			$data = array('name' => $data);
		}

		$data['type'] = 'file';
		return $this->setFormInputText($var, $data, $value, $extra);
	}
    
    // 创建一个提交按钮
    public function setFormSubmit($var, $data = '', $value = '', $extra = '')
	{
		$defaults = array('type' => 'submit', 'name' => (( ! is_array($data)) ? $data : ''), 'value' => $value);
        $submit = "<input ".  $this->_parse_form_attributes($data, $defaults).$extra." />";
		if(empty($var)) {
            $this->form .= $submit;
            return $submit;
        } else {
            $this->_data[$var] = $submit;
            return true;
        } 
	}
    
    // 创建一个重置按钮
    public function setFormReset($var, $data = '', $value = '', $extra = '')
	{
		$defaults = array('type' => 'reset', 'name' => (( ! is_array($data)) ? $data : ''), 'value' => $value);
        $reset = "<input ".  $this->_parse_form_attributes($data, $defaults).$extra." />";
		if(empty($var)) {
            $this->form .= $reset;
            return $reset;
        } else {
            $this->_data[$var] = $reset;
            return true;
        }  
	}
    
    /**
     * 创建一个按钮, 属性有: name value type(button,submit,reset) disable
     * 默认button type: button
     * @param string $var       模板变量名，如果为空，则不设置。
     * @param mixed $data       字符串则表示name属性，关联数组则表示属性
     * @param string $content   按钮文本, <button>$content</button>
     * @param string $extra     额外属性
     * @return string or bool
     */
    public function setFormButton($var, $data = '', $content = '', $extra = '')
	{
		$defaults = array('name' => (( ! is_array($data)) ? $data : ''), 'type' => 'button');

		if ( is_array($data) AND isset($data['content']))
		{
			$content = $data['content'];
			unset($data['content']); // content is not an attribute
		}

		$button = "<button ".  $this->_parse_form_attributes($data, $defaults).$extra.">". self::htmlEncode($content)."</button>";
        if(empty($var)) {
            $this->form .= $button;
            return $button;
        } else {
            $this->_data[$var] = $button;
            return true;
        }
	}
    
    /**
     * Hidden Input Field 生成一个或多个隐藏输入字段
     *
     * Generates hidden fields.  You can pass a simple key/value string or an associative
     * array with multiple values.
     *
     * @param  string  $var     模板变量名，如果为空，则不设置。
     * @param	mixed  $name    字符串表示name属性，关联数组则表示创建多个隐藏字段。
     * @param	string $value
     * @return	string or bool 
     */

	public function setFormHidden($var, $name, $value = '')
	{
        if(!is_array($name)) {
            $name = array($name => $value);
        }
        
        $hidden = '';
        foreach ($name as $key => $value) {
            $hidden .= '<input type="hidden" name="'.$key.'" value="'. self::htmlEncode($value) .'" />'."\n";
        }
        $hidden = ltrim($hidden);
        
        if(empty($var)) {
            $this->form .= $hidden;
            return $hidden;
        } else {
            $this->_data[$var] = $hidden;
            return true;
        }
	}
    
    /**
    * Checkbox Field 生成一个复选框。
    * checkbox属性: name value checked readonly
    * @param    string  $var     模板变量名，如果为空，则不设置。
    * @param	mixed   $data    字符串表示name属性，关联数组则表示多个属性。
    * @param	string  $value   提交的值
    * @param	bool    $checked 复选框是否被默认选中。
    * @param	string  $extra   额外属性,比如:id class style ...。
    * @return	string or bool 
    */
	public function setFormCheckbox($var, $data = '', $value = '', $checked = FALSE, $extra = '')
	{
		$defaults = array('type' => 'checkbox', 'name' => (( ! is_array($data)) ? $data : ''), 'value' => $value);

		if (is_array($data) AND array_key_exists('checked', $data))
		{
			$checked = $data['checked'];

			if ($checked == FALSE)
			{
				unset($data['checked']);
			}
			else
			{
				$data['checked'] = 'checked';
			}
		}

		if ($checked == TRUE)
		{
			$defaults['checked'] = 'checked';
		}
		else
		{
			unset($defaults['checked']);
		}

		$checkbox = "<input ".  $this->_parse_form_attributes($data, $defaults).$extra." />";
        if(empty($var)) {
            $this->form .= $checkbox;
            return $checkbox;
        } else {
            $this->_data[$var] = $checkbox;
            return true;
        }
	}
    
    //创建一个单选按钮。 参数含义和checkbox一样。
    //注意：单选框都是以组为单位使用的，在同一组中的单选项都必须name相同，在同一组中，它们的value必须是不同的。
    public function setFormRadio($var, $data = '', $value = '', $checked = FALSE, $extra = '')
	{
		if ( ! is_array($data))
		{
			$data = array('name' => $data);
		}

		$data['type'] = 'radio';
		return $this->setFormCheckbox($var, $data, $value, $checked, $extra);
	}

    
    /**
     * 创建一个标准的下拉列表字段。
     * @param type $var       模板变量名，如果为空，则不设置。
     * @param type $name      字段名
     * @param type $options   包含各个选项的关联数组，如果$options参数是一个多维数组，则会使用数组的键作为label值生成一个 <optgroup> 标签。
     * @param type $selected  string 默认被选中的值,如果是数组，则会创建一个MULTIPLE下拉列表，支持多选。
     * @param string $extra   select元素的其他属性，字符串类型。
     *                        常用额外属性：
     *                        size 显示的元素数目，若SIZE值存在，select变成滚动列表。若SIZE值不存在，select为弹出式菜单
     * @return string or bool
     */
    public function setFormDropdown($var, $name = '', $options = array(), $selected = array(), $extra = '')
	{
		if ( ! is_array($selected))
		{
			$selected = array($selected);
		}

		if ($extra != '') $extra = ' '.$extra;

		$multiple = (count($selected) > 1 && strpos($extra, 'multiple') === FALSE) ? ' multiple="multiple"' : '';

		$form = '<select name="'.$name.'"'.$extra.$multiple.">\n";

		foreach ($options as $key => $val)
		{
			$key = (string) $key;

			if (is_array($val) && ! empty($val))
			{
				$form .= '<optgroup label="'.$key.'">'."\n";

				foreach ($val as $optgroup_key => $optgroup_val)
				{
					$sel = (in_array($optgroup_key, $selected)) ? ' selected="selected"' : '';

					$form .= '<option value="'.$optgroup_key.'"'.$sel.'>'.self::htmlEncode((string) $optgroup_val)."</option>\n";
				}

				$form .= '</optgroup>'."\n";
			}
			else
			{
				$sel = (in_array($key, $selected)) ? ' selected="selected"' : '';

				$form .= '<option value="'.$key.'"'.$sel.'>'.self::htmlEncode((string) $val)."</option>\n";
			}
		}

		$form .= '</select>';
        
        if(empty($var)) {
            $this->form .= $form;
            return $form;
        } else {
            $this->_data[$var] = $form;
            return true;
        }
	}
    
    /**
    * Multi-select menu,支持采用ctrl键来选择多个选项。
    */
    public function setFormMultiselect($var, $name = '', $options = array(), $selected = array(), $extra = '')
	{
		if ( ! strpos($extra, 'multiple'))
		{
			$extra .= ' multiple="multiple"';
		}

		return $this->setFormDropdown($name, $options, $selected, $extra);
	}
    
    /**
    * 创建一个多行文本框。
    *
    * @param type $var       模板变量名，如果为空，则不设置。
    * @param mixed $data     字符串则表示name属性，关联数组则表示属性
    * @param string $value   
    * @param string $extra   如果$data不是关联数组，则表示额外的属性：
    *                        rows="…" 文本区域的显示行数,默认设置为10
    *                        cols="…" 文本域的显示列(字符)数，默认设置为40
    *                        wrap="…" 控制文本换行，可能的值为OFF，VIRTUAL，PHYSICAL，默认为VIRTUAL
    * @return string or bool
    * wrap属性取值说明：
    * Off，用来避免文本换行，当输入的内容超过文本域右边界时，文本将向左滚动，必须用Return才能将插入点移到下一行；
    * Virtual，允许文本自动换行。当输入内容超过文本域的右边界时会自动转到下一行，而数据在被提交处理时自动换行的地方不会有换行符出现；
    * Physical，让文本换行，当数据被提交处理时换行符也将被一起提交处理。
    */
	public function setFormTextarea($var, $data = '', $value = '', $extra = '')
	{
		$defaults = array('name' => (( ! is_array($data)) ? $data : ''), 'cols' => '40', 'rows' => '10');

		if ( ! is_array($data) OR ! isset($data['value']))
		{
			$val = $value;
		}
		else
		{
			$val = $data['value'];
			unset($data['value']); // textareas don't use the value attribute
		}

		$name = (is_array($data)) ? $data['name'] : $data;
        
		$textarea = "<textarea ".  $this->_parse_form_attributes($data, $defaults).$extra.">".self::htmlEncode($val)."</textarea>";
        
        if(empty($var)) {
            $this->form .= $textarea;
            return $textarea;
        } else {
            $this->_data[$var] = $textarea;
            return true;
        }
	}
    
    /**
    * 给表单控件加入标签说明。通过for属性将表单控件和标签关联起来。
    * 比如： <label for="id">$label_text</label>
    *       <input type="text" id="id" name="fname" value="">
    * label标签的效果： 使用for属性使其与表单组件关联起来，效果为单击文本标签，光标显示在相对应的表单组件内了。
    * @param string $var       模板变量名，如果为空，则不设置。
    * @param	string	The text to appear onscreen
    * @param	string	The id the label applies to
    * @param	string	Additional attributes
    * @return	string or bool
    */
	public function setFormLabel($var, $label_text = '', $id = '', $attributes = array())
	{
		$label = '<label';

		if ($id != '')
		{
			$label .= " for=\"$id\"";
		}

		if (is_array($attributes) AND count($attributes) > 0)
		{
			foreach ($attributes as $key => $val)
			{
				$label .= ' '.$key.'="'.$val.'"';
			}
		}

		$label .= ">self::htmlEncode($label_text)</label>";

		if(empty($var)) {
            $this->form .= $label;
            return $label;
        } else {
            $this->_data[$var] = $label;
            return true;
        }
	}
    
    private function _parse_form_attributes($attributes, $default)
	{
		if (is_array($attributes))
		{
			foreach ($default as $key => $val)
			{
				if (isset($attributes[$key]))
				{
					$default[$key] = $attributes[$key];
					unset($attributes[$key]);
				}
			}

			if (count($attributes) > 0)
			{
				$default = array_merge($default, $attributes);
			}
		}

		$att = '';

		foreach ($default as $key => $val)
		{
			if ($key == 'value')
			{
				$val = self::htmlEncode($val);
			}

			$att .= $key . '="' . $val . '" ';
		}

		return $att;
	}

    private function _attributes_to_string($attributes, $formtag = FALSE)
	{
		if (is_string($attributes) AND strlen($attributes) > 0)
		{
			if ($formtag == TRUE AND strpos($attributes, 'accept-charset=') === FALSE)
			{
				$attributes .= ' accept-charset="'.'utf-8'.'"';
			}

            return ' '.$attributes;
		}

		if (is_object($attributes) AND count($attributes) > 0)
		{
			$attributes = (array)$attributes;
		}

		if (is_array($attributes) AND count($attributes) > 0)
		{
			$atts = '';

			if ( ! isset($attributes['accept-charset']) AND $formtag === TRUE)
			{
				$atts .= ' accept-charset="'.'utf-8'.'"';
			}

			foreach ($attributes as $key => $val)
			{
				$atts .= ' '.$key.'="'.$val.'"';
			}

			return $atts;
		}
	}
}

