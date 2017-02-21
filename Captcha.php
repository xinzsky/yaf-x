<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


class Gek_Captcha {
    
    /**
     * 验证码机制：用来防止机器人。
     * 工作流程：
     * （1）用户请求操作，如果该操作需要验证码，则返回一个验证码图片链接<img src='getcaptcha.php?a=login&t=...'
     *     验证码图片链接URL里必须带上action，比如login register等，和创建时间，防止被浏览器缓存。
     * （2）由于<img>标签，浏览器会发送一个getcaptcha请求，这时服务端生成一个验证码和过期时间存放到session中。
     * （3）用户提交action请求，这时需要对验证码进行验证。
     */
    
    const CAPTCHA = '__GEK_CAPTCHA';
    
    public static function img_link($action, $url, $width, $height)
    {
        list($usec, $sec) = explode(" ", microtime());
		$now = ((float)$usec + (float)$sec);
        
        if(strpos($url, '?') === false) {
            $url .= "?action={$action}&time={$now}";
        } else {
            $url .= "&action={$action}&time={$now}";
        }
        
        return "<img src=\"{$url}\" alt=\"验证码\" width=\"{$width}\" height=\"{$height}\"";
    }
    
    public static function get($action, $lifetime=600, $params=array())
    {
        $captcha = self::generate($params);
        if($captcha) {
            return self::set_session($captcha, $action, $lifetime);
        } else {
            return false;
        }
    }
    
    /**
     * 把验证码写到用户的session中。
     */
    public static function set_session($captcha, $action, $lifetime=600)
    {
        $_SESSION[self::CAPTCHA][$action]['value'] = $captcha;
        $_SESSION[self::CAPTCHA][$action]['expire'] = time() + $lifetime;
        return $captcha;
    }
    
    public static function verify($captcha, $action)
    {
        if(empty($captcha) || empty($action)) {
            return false;
        } 
        
        // captcha是否在session里存在
        if(!isset($_SESSION[self::CAPTCHA][$action]['value']) || !isset($_SESSION[self::CAPTCHA][$action]['expire']) ) {
            return false;
        } else if($_SESSION[self::CAPTCHA][$action]['expire'] < time()) { // captcha是否过期
            return false;
        } else if($_SESSION[self::CAPTCHA][$action]['value'] != $captcha) { // captcha是否正确
            return false;
        } else {
            unset($_SESSION[self::CAPTCHA][$action]); // 释放captcha
            return true;
        }
    }
    
    /**
     * 生成一个验证码
     * @param array $params 生成验证码的参数。
     *  $params包含的参数：
      *     word: 验证码，一般采用随机字符串，如果没有提供则自己生成。
     *      img_width: 验证码图片宽度，必须不小于60px，默认为60px，
     *                 0表示会自动根据验证码长度自动计算
     *                 避免自定义宽度所显示的数字超出边框的问题。
     *      img_height: 验证码图片高度，默认为30px。
     *      font_path: 字体路径，默认采用GD自带字体。
     *      font_size: 字体大小，GD自带字体默认为5,用户提供字体默认为16.增加字体大小，图片宽度也得相应增加。
     *      word_len: 验证码长度，默认为4个字符。增加字符数，图片宽度也得相应增加。
     *      is_debug: 用于调试，调试模式下会在/tmp路径下生成验证码图片。
     * @return 错误返回false, 成功返回验证码。
     * 注意：
     *      必须需要GD库，如果未提供TRUE TYPE字体的路径, 将会使用GD自带的字体.
     */
    public static function generate($params=array())
    {
        if ( ! extension_loaded('gd')) {
			return FALSE;
		}
        
        $defaults = array('word'        => '',
                          'img_width'   => '60', 
                          'img_height'  => '30', 
                          'font_path'   => '', 
                          'font_size'   => 5, 
                          'word_len'    => 4,
                          'is_debug'    => 0);

		foreach ($defaults as $key => $val) {
            $$key = ( ! isset($params[$key])) ? $val : $params[$key];
		}
        
        if($font_path != '') {
            $font_size = 16;
        }
         
        list($usec, $sec) = explode(" ", microtime());
		$now = ((float)$usec + (float)$sec);
        
        if($word == '') {
            //去掉了0和o O 1 l
            $pool = '23456789abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ';
            $str = '';
            for ($i = 0; $i < $word_len; $i++)
            {
                $str .= substr($pool, mt_rand(0, strlen($pool) -1), 1);
            }

            $word = $str;
        }

		// -----------------------------------
		// Determine angle and position
		// -----------------------------------

		$length	= strlen($word);
		$angle	= ($length >= 6) ? rand(-($length-6), ($length-6)) : 0;
		$x_axis	= rand(6, (360/$length)-16);
		$y_axis = ($angle >= 0 ) ? rand($img_height, $img_width) : rand(6, $img_height);
       
		// -----------------------------------
		// Create image
		// -----------------------------------

		// PHP.net recommends imagecreatetruecolor(), but it isn't always available
		if (function_exists('imagecreatetruecolor'))
		{
			$im = imagecreatetruecolor($img_width, $img_height);
		}
		else
		{
			$im = imagecreate($img_width, $img_height);
		}

		// -----------------------------------
		//  Assign colors
		// -----------------------------------
        // 默认颜色
		//$bg_color		= imagecolorallocate ($im, 255, 255, 255);
		//$border_color	= imagecolorallocate ($im, 153, 102, 102);
		//$text_color		= imagecolorallocate ($im, 204, 153, 153);
		//$grid_color		= imagecolorallocate($im, 255, 182, 182);
		//$shadow_color	= imagecolorallocate($im, 255, 240, 240);
        
        $bg_color		= imagecolorallocate ($im, 255, 255, 255);
		$border_color	= imagecolorallocate ($im, 255, 255, 255);
		$text_color		= imagecolorallocate ($im, 51, 51, 51);
		$grid_color		= imagecolorallocate($im, 102, 153, 153);
		$shadow_color	= imagecolorallocate($im, 102, 102, 102);
        
		// -----------------------------------
		//  Create the rectangle
		// -----------------------------------

		ImageFilledRectangle($im, 0, 0, $img_width, $img_height, $bg_color);

		// -----------------------------------
		//  Create the spiral pattern
		// -----------------------------------

		$theta		= 1;
		$thetac		= 7;
		$radius		= 16;
		$circles	= 20;
		$points		= 32;

		for ($i = 0; $i < ($circles * $points) - 1; $i++)
		{
			$theta = $theta + $thetac;
			$rad = $radius * ($i / $points );
			$x = ($rad * cos($theta)) + $x_axis;
			$y = ($rad * sin($theta)) + $y_axis;
			$theta = $theta + $thetac;
			$rad1 = $radius * (($i + 1) / $points);
			$x1 = ($rad1 * cos($theta)) + $x_axis;
			$y1 = ($rad1 * sin($theta )) + $y_axis;
			imageline($im, $x, $y, $x1, $y1, $grid_color);
			$theta = $theta - $thetac;
		}

		// -----------------------------------
		//  Write the text
		// -----------------------------------

		$use_font = ($font_path != '' AND file_exists($font_path) AND function_exists('imagettftext')) ? TRUE : FALSE;

		if ($use_font == FALSE)
		{
			//$font_size = 5;
            if($length == 4) {
                $x = rand(0,18); //特别注意,避免所显示的数字超出边框。
            } else { 
                $x = rand(0, $img_width/($length/3));
            }
			$y = 0;
		}
		else
		{
			//$font_size	= 16;
			$x = rand(0, $img_width/($length/1.5));
			$y = $font_size+2;
		}

		for ($i = 0; $i < strlen($word); $i++)
		{
			if ($use_font == FALSE)
			{
				$y = rand(0 , $img_height/2);
				imagestring($im, $font_size, $x, $y, substr($word, $i, 1), $text_color);
				$x += ($font_size*2); // 字符宽度、间距
			}
			else
			{
				$y = rand($img_height/2, $img_height-3);
				imagettftext($im, $font_size, $angle, $x, $y, $text_color, $font_path, substr($word, $i, 1));
				$x += $font_size;
			}
		}

		// -----------------------------------
		//  Create the border
		// -----------------------------------

		imagerectangle($im, 0, 0, $img_width-1, $img_height-1, $border_color);

		// -----------------------------------
		//  Generate the image
		// -----------------------------------
        
        if($is_debug) {
            $img = '/tmp/'. $now. '.jpg';
            ImageJPEG($im, $img);
        } else {
            //避免验证码图片被缓存，或者验证码URL的querystring加随机数。
            header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            header('Pragma: no-cache');       
            // Set the content type header - in this case image/jpeg
            header('Content-Type: image/jpeg');
            // Output the image
            imagejpeg($im);
        }
        
		ImageDestroy($im);
		return $word;
	}
}
