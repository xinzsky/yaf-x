<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */

class Gek_Session 
{ 
    private static $change_id = 0;
    private static $session_lifetime = 86400; //session有效时间，默认是一天。
    private static $cookie_domain = '';

    const TS = '__GEK_SESS_TS'; //timestamp
    const LT = '__GEK_SESS_LT'; //lifetime
    const CH = '__GEK_SESS_CH'; //change timestamp
    const TOKEN = 'GEKSESSTOKEN';
    const LOGIN = '__GEK_SESS_LOGIN';
    
    //必须在每一个请求的最开始的地方调用。比如在bootstrap中。
    public static function config($items=array())
    {
        $defaults = array(
            //用户可以设置的配置项：
            'save_path'         => 'localhost:11211',
            'gc_maxlifetime'    => 86400,       //session数据在memcached中有效时间，需要大于cookie的有效期。
            'cookie_lifetime '  => 0,
            'cookie_path'       => '/',
            'cookie_domain'     => '',
            'cookie_secure'     => 0,
            'cookie_httponly'   => 1,
            'cache_limiter'     => 'nocache',   //浏览器对页面缓存方法：none/nocache/private/private_no_expire/public
            'cache_expire'      => 0,           //浏览器缓存页面过期时间，单位：min
            
            //和php.ini默认值不同的配置项：
            'save_handler'      => 'memcached', 
            'name'              => 'GEKSESSID',
            'use_cookies'       => 1,
            'use_only_cookies'  => 1,
            'serialize_handler' => 'igbinary', //默认为php,需要安装igbinary扩展模块。
            'gc_probability'    => 0,          //不进行垃圾回收，因为memcached自身有过期机制
            'gc_divisor'        => 10000,      //和gc_probability一起来设置垃圾回收的概率，默认1/100即每一百次请求进行垃圾回收一次
            'hash_function'     => 1,          //生成session ID的散列算法。'0' 表示 MD5（128 位），'1' 表示 SHA-1（160 位）。
            'hash_bits_per_character' => 5,    
            
            //php.ini默认值，不需要重新设置。
            //'referer_check'     => '',          //检查每个Referer中是否包含指定的字符串，没有则session id是无效的。
            //'entropy_file'      => '/dev/urandom',
            //'entropy_length'    => 32,
            //'auto_start'        => 0,
            //'use_trans_sid'     => 0,
            //'url_rewriter.tags' => ''          //指定在使用透明 SID 支持时哪些 HTML 标记会被修改以加入会话 ID。
            
            );
        
        //memcached.ini中session的配置
        $memc = array(
            'sess_locking'      => 1,                // 是否使用session locking
            'sess_lock_wait'    => 150000,           // Session spin lock retry wait time in microseconds.
            'sess_prefix'       => 'memc.sess.key.', // memcached session key prefix
            'sess_binary'       => 0                 // memcached session binary mode
        );
        
        foreach($items as $key => $value) {
            if($key == 'memcached') {
                $defaults['save_path'] = $value;
            } else if($key == 'maxlifetime') {
                $defaults['gc_maxlifetime'] = $value;
                self::$session_lifetime = (int)$value;
            } else if($key == 'change_id') {
                self::$change_id = (int)$value;
            } else if($key == 'cookie_domain') {
                self::$cookie_domain = $value;
                $defaults[$key] = $value;
            } else {
                $defaults[$key] = $value;
            }
        }
        
        foreach($defaults as $key => $value) {
            ini_set('session.'.$key, $value);
        }
        
        $memc['sess_prefix'] = $defaults['name'] . '.';
        ini_set('memcached.sess_prefix', $memc['sess_prefix']);
    }
    
    // 判断session是否已经start。
    public static function isStart()
    {
        if ( php_sapi_name() !== 'cli' ) {
            if ( version_compare(phpversion(), '5.4.0', '>=') ) {
                return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
            } else {
                return session_id() === '' ? FALSE : TRUE;
            }
        } else {
            return FALSE;
        }
    }
   
    //session_start()会从$_COOKIE获得session id,从memcached获得session数据并反序列化等处理后存放到全局$_SESSION数组中。
    //如果没有session id或session数据在memcached中不存在，则创建一个session_id,然后把生成的session_id作为COOKIE的值传递到客户端.
    //注意此时有session id则不会修改。
    //session_start()会调用会话管理器的open 和 read 回调函数。
    //根据配置不同，本函数会设置cookie cache方面的几个 HTTP 响应头。
    //因此 session_cache_limiter()必须在此函数之前来设置。
    public static function start()
    {
        if(!self::isStart()) {
            session_start();
            
            //Session过期检查
            $now = time();
            if(!isset($_SESSION[self::TS])) { 
                $_SESSION[self::TS] = $now;
                $_SESSION[self::LT] = self::$session_lifetime;
            } else { 
                $ts = $_SESSION[self::TS];
                if(!isset($_SESSION[self::LT])) {
                    $lt = self::$session_lifetime;
                } else {
                    $lt = $_SESSION[self::LT];
                }
                if($now > $ts + $lt) {           //session过期  
                    session_regenerate_id(true); //重新生成sid 删除session data
                    $_SESSION = array();         //清空$_SESSION
                    $_SESSION[self::TS] = $now;
                    $_SESSION[self::LT] = self::$session_lifetime;
                }
            } 
            
            //动态修改Session ID
            if(self::$change_id > 0) {
                if(!isset($_SESSION[self::CH])) { 
                    $_SESSION[self::CH] = $now;
                }
                $ts = $_SESSION[self::CH];
                if($now > $ts + self::$change_id) {
                    //重新生成session id会发送cookie到客户端
                    session_regenerate_id(true);
                    $_SESSION[self::CH] = $now;
                }
            } 
            
            return true;
        } else {
            return true;
        }
    }
    
    // 登录成功后设置登录状态、Session
    // 参数$lifetime指明非自动登录时,session有效期。
    public static function setLogin($autologin, $lifetime=0)
    {
        if($autologin) {
            self::reset(); 
        } else {
            self::reset($lifetime);
            //如果不需要自动登录，此次登录应随着浏览器关闭而退出，所以应返回一个会话cookie。
            $token = md5(uniqid(mt_rand(), TRUE));
            setcookie(self::TOKEN, $token, 0, '/', self::$cookie_domain);
            self::set(self::TOKEN, $token);
        }
        self::set(self::LOGIN, 1);
    }
    
    //检查用户是否登录
    public static function isLogin()
    {
        if(!isset($_SESSION[self::LOGIN]) || empty($_SESSION[self::LOGIN])) {
            return false;
        } 
        
        //如果是非自动登录，还需要检查浏览器是否是关闭后再打开
        if(isset($_SESSION[self::TOKEN])) {
            if(!isset($_COOKIE[self::TOKEN]) || $_COOKIE[self::TOKEN] != $_SESSION[self::TOKEN]) {
                return false;
            }
        }
        
        return true;
    }

    // session start后重新生成session id并重置session
    public static function reset($lifetime=0)
    {
        session_regenerate_id(true); //重新生成sid 删除session data
        $_SESSION = array();         //清空$_SESSION
        $now = time();
        $_SESSION[self::TS] = $now;
        if($lifetime > 0) {
            $_SESSION[self::LT] = $lifetime;
        } else {
            $_SESSION[self::LT] = self::$session_lifetime;
        }
    }
    
    // 设置session生成时间，比如登录成功后重新生成新的session
    public static function setSessTimestamp($ts)
    {
        $_SESSION[self::TS] = $ts;
    }
    
    // 设置session有效时间，比如不需要自动登录时，设置session有效时间为1天。
    public static function setSessLifetime($lt)
    {
        $_SESSION[self::LT] = $lt;
    }
            
    public static function set($name, $value)
    {
        $_SESSION[$name] = $value;
    }
    
    //可以用于字符串、数组、int、float、类型的$value
    //如果$_SESSION[$name]是一个已经存在的数组，则把数组$value追加其后。
    //如果$_SESSION[$name]不存在，则设置其值为$value
    public static function append($name, $value)
    {
        if(isset($_SESSION[$name])){
            if(is_array($_SESSION[$name]) && is_array($value)) {
                $_SESSION[$name] = array_merge($_SESSION[$name],$value);
            } else if(is_string($_SESSION[$name]) && is_string($value)) {
                $_SESSION[$name] .= $value;
            } else if(is_numeric($_SESSION[$name]) && is_numeric($value)) {
                $_SESSION[$name] += $value;
            } else {
                $_SESSION[$name] = $value;
            }
        } else {
            $_SESSION[$name] = $value;
        }
    }
    
    public static function get($name)
    {
        if (isset($_SESSION[$name])) {
            return $_SESSION[$name];
        } 
    }
    
    public static function ishas($name)
    {
        if (isset($_SESSION[$name])) {
            return true;
        } else {
            return false;
        }
    }
    
    public static function del($name)
    {
        if (isset($_SESSION[$name])) {
            unset($_SESSION[$name]);
        } 
    }
    
    // 重置会话中的所有变量
    public static function clear()
    {
        $_SESSION = array();
    }

    // 从memcached中删除session数据,但不会清空$_SESSION数组，也不会发送删除cookie。
    // session destroy()方法会结束session，即关闭和memchaced的连接，
    // 之后再更新$_SESSION到程序结束时也不会再写入memcached了，需要重新session_start。
    public static function destroy()
    {
        return session_destroy();
    }
    
    public static function destroyAll()
    {
        // clear session data
        $_SESSION = array();
        
        // delete user client's cookie
        $params = session_get_cookie_params();
        setcookie(session_name(), 'deleted', strtotime('1982-01-06'),
                  $params["path"], $params["domain"],$params["secure"], $params["httponly"]);
        
        // delete memcached's session data
        session_destroy();
    }
    
    
    //提交$_SESSION数据到memcached，之后还可以继续设置$_SESSION。(会重新session_start)
    public static function commit()
    {
        session_write_close();
        session_start();
    }
    
    //程序结束后会自动write $_SESSION to memcached并close connetion。
    //手动调用session_write_close()来保存session数据（write $_SESSION to memcached)
    //并结束session（close connetion)，不会清空$_SESSION。
    //之后再更新$_SESSION到程序结束时也不会再写入memcached了，需要重新session_start。
   public static function end()
   {
       session_write_close();
   }
   
   // 获得session name,比如GEKSESSID PHPSESSID
   // 如果参数$new_name不为空，则会设置新的session name，但必须在session start之前来设置。
   public static function name($new_name=NULL)
   {
       return session_name($new_name);
   }
   
   // 获得session id，只能在session_start之后才能获得session id.
   // session id不存在则返回空串。
   public static function Id()
   {
       return session_id();
   }

   //生成一个新的session id替换老的session id.此时会set-cookie头，所以调用该函数之前不能有任何输出。
   //参数指明是否删除memcached里老的session id及其数据。注意此时$_SESSION数组数据不变。
   //成功时返回 TRUE， 或者在失败时返回 FALSE。
   //出于安全目的，可以在一定的条件下更改用户的session id。
   //注意：立即删除旧的会话数据可能会带来其他影响， 比如在并发访问或者网络不稳定的情况下， 可能会导致会话无效。
   //（意指浏览器携带旧的会话 ID 发起了并发的请求，如果在第一个被服务器接受和处理的请求中删除了旧的会话数据，那么后续的请求将会产生会话无效的问题） 
   //可以在 $_SESSION 中设置一个很短的过期时间来代替直接删除旧的会话， 并且拒绝用户访问旧的会话（过期的会话）。
   public static function newId($delete_old_session = false) 
   {
       return session_regenerate_id($delete_old_session);
   }
  
   //设置cookie参数，注意只能在调用session_start()函数之前调用，并且每个请求都需要调用。
   //参数$lifetime单位为s
   //注意：session并不是每次请求都会响应cookie。只在创建session或重新生成session id时才发送一个cookie。
   //所以如果用户端已经存在cookie，修改cookie参数，不会马上生效。
   public static function setCookieParams($lifetime=0, $path='/', $domain='',$secure=false, $httponly=true)
   {
       session_set_cookie_params($lifetime,$path,$domain,$secure,$httponly);
   }
   
   //返回cookie的参数数组。
   public static function getCookieParams()
   {
       return session_get_cookie_params();
   }
   
   //读取/设置缓存方法。如果参数$type为空('')则获得当前缓存方法。
   //注意只能在调用session_start()函数之前调用，并且每个请求都需要调用。
   //在 private 模式下， 包括 Mozilla 在内的一些浏览器可能无法正确处理 Expire 响应头， 通过使用 private_no_expire 
   //模式可以解决这个问题：在这种模式下， 不会向客户端发送 Expire 响应头。
   //怎么让IE,特别是IE7浏览器不缓存的方法：
   //<META HTTP-EQUIV="Pragma" CONTENT="no-cache">
   //<META HTTP-EQUIV="Expires" CONTENT="-1">
   //注意：session_cache_limiter('') session_cache_limiter(NULL)都表示none，不设置缓存。
   public static function cacheLimiter($type='')
   {
       if($type === '') {
           return session_cache_limiter();
       } else {
           return session_cache_limiter($type);
       }
   }
   
   //读取/设置cache expire时间，单位为分钟。如果参数$expire为空则获得当前缓存的expire时间。
   //注意只能在调用session_start()函数之前调用，并且每个请求都需要调用。
   //session_cache_expire('') session_cache_expire（NULL）都表示过期时间为0.
   public static function cacheExpire($expire='')
   {
       if($expire === '') {
           return session_cache_expire();
       } else {
           return session_cache_expire($expire);
       }
   }
}
