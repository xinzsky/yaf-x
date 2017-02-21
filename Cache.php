<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


/**
 * 基于memcached和ketama分布式一致性hash算法的缓存类
 * liuxingzhi@2013.12
 */

class Gek_Cache
{
    private $mc;
    private static $instances = array(); //一个cache集群中可以存放不同的prefix的key，但不同Keyprefix都只创建一个实例(单例)
             
    public static function getInstance($servers, $keyprefix = '', $distribution = true) 
    {
        if($keyprefix === '') {
            $prefix = '@NoKeyPrefix';
        } else {
            $prefix = $keyprefix;
        }
        
        if (!isset(self::$instances[$prefix])) {
            self::$instances[$prefix] = new Gek_Cache_Mem($servers, $keyprefix, $distribution);
            return self::$instances[$prefix];
        } else {
            return self::$instances[$prefix];
        }
    }
            
    /**
     * 构造函数。
     * @param array  $servers       memcached服务地址，格式为： array('host:port:weight',...)
     * @param string  $keyprefix     key的前缀。
     * @param boolean $distribution  是否采用分布式。
     */
    public function __construct($servers, $keyprefix = '', $distribution = true)
    {
        $this->mc = new Memcached();
 
        if($keyprefix !== '') {
            $this->mc->setOption(Memcached::OPT_PREFIX_KEY, $keyprefix);
        }
        
        if ($distribution) { 
            $this->mc->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);//开启一致性哈希算法
            $this->mc->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);  //开启ketama算法兼容
            $this->mc->setOption(Memcached::OPT_REMOVE_FAILED_SERVERS, true); //移除失效服务器
            
        }
        
        $this->mc->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_IGBINARY); //编译memcached模块时需要指定--enable-memcached-igbinary
        $this->mc->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
        $this->mc->setOption(Memcached::OPT_TCP_NODELAY, true);       //关闭延迟
        $this->mc->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 2); //重连次数
        
        foreach($servers as $i => $server) {
            $servers[$i] = $this->parseServer($server);
        }
        $this->mc->addServers($servers);  //将服务器增加到连接池
    }
    
    private function parseServer($server) 
    {
        $p = explode(':', $server);
        $s['host'] = trim($p[0]);
        $s['port'] = 11211;
        $s['weight'] = 1;
        if(isset($p[1]) && !empty($p[1])) {
            $s['port'] = intval(trim($p[1]));
        } 
        if(isset($p[2]) && !empty($p[2])) {
            $s['weight'] = intval(trim($p[2]));
            if($s['weight'] <= 0) $s['weight'] = 1;
        }
        
        return $s;
    }
    
    /**
     * 把(key,value)存放到缓存。
     * @param string $key
     * @param mixed  $value
     * @param int    $expire 过期时间，单位s，默认为0，永不过期。
     * @return boolean 
     */
    public function set($key, $value, $expire = 0)
    {
        return $this->mc->set($key, $value, $expire);
    }
    
    /**
     * 从缓存里取出key对应的值。
     * @param string $key
     * @return false: error, NULL: key not found.
     */
    public function get($key)
    {
        $value = $this->mc->get($key);         // Note that this function can return NULL as FALSE
        $rescode = $this->mc->getResultCode(); 
        if(!$value && $rescode == Memcached::RES_NOTFOUND)
            return NULL;
        else if($rescode == Memcached::RES_SUCCESS)
            return $value;
        else
            return false;
    }
    
     /**
     * 从缓存里删除key。
     * @param string $key
     * @return true: ok, false: error, NULL: key not found.
     */
    public function delete($key)
    {
        $r = $this->mc->delete($key);
        $rescode = $this->mc->getResultCode();
        if(!$r && $rescode == Memcached::RES_NOTFOUND)
            return NULL;
        else if(!$r)
            return false;
        else
            return $r;
    }
    
     /**
     * 将多个键值对映射到同一台服务器上，避免批量get的时候串行从多个服务器get
     * 如果需要在分布式缓存中一次get很多key，建议采用方法来set
     * @param string  $server_key 本键名用于识别储存和读取值的服务器。
     * @param array   $items      存放在服务器上的键／值对数组。
     * @param int     $expire     到期时间，默认为 0
     * @return boolean
     */
    public function setMultiByKey($server_key, $items, $expire = 0)
    {
        return $this->mc->setMultiByKey($server_key, $items, $expire);
    }
    
     /**
     * 从指定的服务器一次get多个数据
     * @param string $server_key 本键名用于识别储存和读取值的服务器。
     * @param array $keys        要检索的key的数组
     * @return array             返回检索到的元素的数组 或者在失败时返回 FALSE
     */
    public function getMultiByKey($server_key, $keys)
    {
        return $this->mc->getMultiByKey($server_key, $keys);
    }
}
