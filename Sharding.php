<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


/**
 * sharding类实现：路由、读写分离、一主多从、从库负载均衡
 */

class Gek_Sharding
{
    private $config;
    private static $instance = NULL;
    
    public static function getInstance() 
    {
        return self::$instance;
    }
    
    /**
     * 构造函数。
     * @param array $config  sharding配置
     */
    public function __construct($db, $shard) 
    {
        $this->config = $this->parseConfig($db, $shard);
        self::$instance = $this;
    }

    public function __destruct() {
        unset($this->config);
    }
        
    private function parseDB($db)
    {
        $r = array();
        if(empty($db)) return $r;
        $dba = explode(',', $db);
        foreach($dba as $value) {
            $value = trim($value);
            if(empty($value)) continue;
            $dbw = explode(":", $value);
            if(count($dbw) == 1) {
                $r[$dbw[0]] = 1;
            } else {
                $r[$dbw[0]] = intval($dbw[1]);
                if($r[$dbw[0]] <= 0) $r[$dbw[0]] = 1;
            }
        }
        return $r;
    }
    
    private function parseModDB($db, $modlist) 
    {
        $modDB = array();
        $list = explode(',', $modlist);
        foreach ($list as $mod) {
            $mod = trim($mod);
            if(empty($mod) && $mod !== '0' && $mod !== 0) continue;
            $r = sscanf($mod, "%[^-]-%[^]]", $b, $e);
            if ($r == 2) {
                for ($i = $b; $i <= $e; $i++) {
                    $modDB[$i] = $db; 
                }
            } else {
                $modDB[$mod] = $db;
            }
        }

        return $modDB;
    }
    
    private function parseDateRange($dateRange)
    {
        $range = array();
        if ($dateRange == 'all') {
            $range[0] = 0;
            $range[1] = 0;
            return $range;
        }
        
        if(($date = stristr($dateRange, ' ago', true)) !== false) {
            $date = trim($date);
            $range[0] = 0;
            $range[1] = strtotime($date);
            if($range[1] === false || $range[1] <= 0)
                return false;
            else
                return $range;
        }

        $r = explode(',', $dateRange);
        if(count($r) == 1) {
            $range[1] = 0;
            $range[0] = strtotime(trim($r[0]));
            if($range[0] === false || $range[0] <= 0)
                return false;
            else
                return $range;
        } else if(count($r) == 2) {
            $range[0] = strtotime(trim($r[0]));
            if($range[0] === false || $range[0] <= 0)
                return false;
            $range[1] = strtotime(trim($r[1]));
            if($range[1] === false || $range[1] <= 0)
                return false;
            if($range[0] > $range[1])
                return false;
            else 
                return $range;
        } else {
            return false;
        }
    }
    
    private function parseHistory($history)
    {
        if(($num = stristr($history,'day', true)) !== false) {
            $num = intval(trim($num));
            if($num <= 0) return false;
            $time = "-{$num} day";
        } else if(($num = stristr($history,'month', true)) !== false) {
            $num = intval(trim($num));
            if($num <= 0) return false;
            $time = "-{$num} month";
        } else if(($num = stristr($history,'year', true)) !== false) {
            $num = intval(trim($num));
            if($num <= 0) return false;
            $time = "-{$num} year";
        } else {
            return false;
        }
        
        return $time;
    }
    
    private function parseRange($range)
    {
        $r = array();
        $list = explode(',', $range);
        foreach ($list as $item) {
            $item = trim(strtr($item,':','-'));
            $n = explode("-", $item);
            if(count($n) == 3) {
                $r[$n[0]][0] = intval($n[1]);
                $r[$n[0]][1] = intval($n[2]);
            } else if(count($n) == 2){
                $r[$n[0]][0] = intval($n[1]);
                $r[$n[0]][1] = 0;
            } else {
                return false;
            }       
        }
        return $r;
    }
    
    private function parseRangeDB($db, $rangelist) 
    {
        $rangeDB = array();
        $list = explode(',', $rangelist);
        foreach ($list as $range) {
            $range = trim($range);
            if(empty($range) && $range !== '0' && $range !== 0) 
                continue;
            else
                $rangeDB[$range] = $db;
        }

        return $rangeDB;
    }
    
    private function parseMapDB($db, $tabidlist) 
    {
        $dbs = array();
        $list = explode(',', $tabidlist);
        foreach ($list as $tabid) {
            $tabid = trim($tabid);
            if(empty($tabid) && $tabid !== '0' && $tabid !== 0) continue;
            $r = sscanf($tabid, "%[^-]-%[^]]", $b, $e);
            if ($r == 2) {
                for ($i = $b; $i <= $e; $i++) {
                    $dbs[$db][] = intval($i); 
                }
            } else {
                $dbs[$db][] = intval($tabid);
            }
        }

        return $dbs;
    }
    
    private function parseUDF($func)
    {
        $pos = strpos($func,'(');
        if($pos === false) return false;
        $funcname = substr($func, 0, $pos);
        $funcname = trim($funcname);
        $end = strpos($func,')');
        if($end === false) return false;
        $funcarg = substr($func, $pos+1,$end-$pos-1);
        if($funcarg === false) {
            $funcargs = array();
        } else {
            $funcarg = trim($funcarg);
            $funcargs = explode(',', $funcarg);
            foreach ($funcargs as $key => $arg) {
                $arg = trim($arg);
                $funcargs[$key] = $arg; 
            }
        }
        $r[0] = $funcname;
        $r[1] = $funcargs;
        
        return $r;
    }

    /**
     * @param string $db $shard配置数组
     * @return array sharding配置数组
     * $config = array('@db@' => array(dbname => array('host','port','user','password','db'),...)
     *                 tablename => array('shardtype' =>,
     *                                    'difftable' =>,
     *                                    'masters' => array(dbname => weight,...)
     *                                    'slaves' => array(masterdb => array(dbname => weight,...))
     *                                    'mod' =>,
     *                                    'db' => array(mod=>dbname,...),
     *                                    'date' =>,
     *                                    'db' => array(masterdb => array(beg,end));
     *                                    'history' => n days ago / n months ago / n years ago
     *                                    'status' => ...
     *                                    'conds' => both/any
     *                                    'db' => array('current' => dbname, 'history' => dbname)
     *                                    'range' => array(rangename => array(min,max), ...)
     *                                    'db' => array(rangename => dbname,...)
     *                                    'map' => array(host,port,dbnum)
     *                                    'db' => array(masterdb => array(tabid,...))
     *                                    'udf' => array(funcname,array(arg1,arg2,...))
     */
    private function parseConfig($shard, $db=array()) 
    {
        $config = array();
        
        // DB
        if(is_array($db) && !empty($db)) {
            foreach($db as $k => $v) {
                $t = explode(':', $v);
                $r = array();
                $r['host'] = $t[0];
                $r['port'] = intval($t[1]);
                $r['user'] = $t[2];
                $r['password'] = $t[3];
                if(isset($t[4])) $r['db'] = $t[4];
                $config['@db@'][$k] = $r;
           }
        }
        
        // shard 
        foreach($shard as $table => $keys) {
            if(isset($keys['shardtype']) && !empty($keys['shardtype'])) {
                $config[$table]['shartype'] = strtolower($keys['shardtype']);
            } else {
                $config[$table]['shardtype'] = 'none';
            }
            
            $config[$table]['difftable'] = 0;
            if(isset($keys['difftable']) && !empty($keys['difftable'])) {
                $config[$table]['difftable'] = intval($keys['difftable']);
            }
            
            if(isset($keys['masters']) && !empty($keys['masters'])) {
                $config[$table]['masters'] = $this->parseDB($keys['masters']);
                foreach($config[$table]['masters'] as $db => $weight) {
                    if(isset($keys['slaves'][$db]) && !empty($keys['slaves'][$db])) {
                        $config[$table]['slaves'][$db] = $this->parseDB($keys['slaves'][$db]);
                    }
                }
            }
            
            if($config[$table]['shardtype'] != 'udf' && !isset($config[$table]['masters'])) {
                throw new Gek_Exception(0, "$table masters isn't set");
            }
        }
        
        // get table sharding
        foreach($shard as $table => $keys) {
            switch($config[$table]['shardtype']) {
                case 'none':
                    break;
                case 'mod':
                    if(isset($keys['storebymod']) && !empty($keys['storebymod'])) {
                        $config[$table]['mod'] = intval($keys['storebymod']);
                    } else {
                        $config[$table]['mod'] = 1;
                    }
                    
                    if(isset($keys['mod']) && !empty($keys['mod'])) {
                        $config[$table]['db'] = array();
                        foreach($keys['mod'] as $db => $value) {
                            $config[$table]['db'] += $this->parseModDB($db, $value);
                        }
                    } else {
                        throw new Gek_Exception(0,"$table shardbymod parameters error");
                    }
                    break;
                case 'date':
                    if(isset($keys['storebydate']) && !empty($keys['storebydate'])) {
                        $config[$table]['date'] = strtolower($keys['storebydate']);    
                    } 
                    
                    if(!isset($config[$table]['date']) || 
                       (   $config[$table]['date'] != 'year'  
                        && $config[$table]['date'] != 'half-year' 
                        && $config[$table]['date'] != 'season' 
                        && $config[$table]['date'] != 'month')) {
                            throw new Gek_Exception(0,"$table storebydate set error");
                        }
                    
                    if(isset($keys['date']) && !empty($keys['date'])) {
                        foreach($keys['date'] as $db => $value) {
                            $config[$table]['db'][$db] = $this->parseDateRange($value);
                            if($config[$table]['db'][$db] === false) {
                                throw new Gek_Exception(0,"$table $db date set error");
                            }
                        }
                    } else {
                        throw new Gek_Exception(0,"$table shardbydate parameters error");
                    }
                    break;
                case 'history':
                    if(isset($keys['storebyhistory']) && !empty($keys['storebyhistory'])) {
                        $config[$table]['history'] = $this->parseHistory($keys['storebyhistory']);    
                    } 
                    
                    if(isset($keys['storebystatus']) && !empty($keys['storebystatus'])) {
                        $config[$table]['status'] = $keys['storebystatus'];    
                    } 
                    
                    if(isset($keys['storebyconds']) && !empty($keys['storebyconds'])) {
                        $config[$table]['conds'] = strtolower($keys['storebyconds']);
                        if($config[$table]['conds'] != 'both' && $config[$table]['conds'] != 'any') {
                            throw new Gek_Exception(0, "$table storebyconds set error"); 
                        }
                    } 
                    
                    if(isset($keys['history']) && !empty($keys['history'])) {
                        foreach($keys['history'] as $db => $value) {
                            if(strtolower($value) == 'current') {
                                $config[$table]['db']['current'] = $db;
                            } else if(strtolower($value) == 'history') { 
                                $config[$table]['db']['history'] = $db;
                            } else {
                                throw new Gek_Exception(0, "$table database set error");
                            }   
                        }
                    } else {
                        throw new Gek_Exception(0,"$table storebyhistory parameters error");
                    }
                    break;
                case 'range':
                    if(isset($keys['storebyrange']) && !empty($keys['storebyrange'])) {
                        $config[$table]['range'] = $this->parseRange($keys['storebyrange']);    
                    } 
                    
                    if(isset($keys['range']) && !empty($keys['range'])) {
                        $config[$table]['db'] = array();
                        foreach($keys['range'] as $db => $value) {
                            $config[$table]['db'] += $this->parseRangeDB($db, $value);
                        }
                    } else {
                        throw new Gek_Exception(0,"$table shardbyrange parameters error");
                    }
                    
                    break;
                case 'map':
                    if(isset($keys['storebymap']) && !empty($keys['storebymap'])) {
                        $config[$table]['map'] = explode(':', $keys['storebymap']);    
                    } 
                    
                    if(isset($keys['map']) && !empty($keys['map'])) {
                        $config[$table]['db'] = array();
                        foreach($keys['map'] as $db => $value) {
                            $config[$table]['db'] += $this->parseMapDB($db, $value);
                        }
                    } else {
                        throw new Gek_Exception(0,"$table shardbymap parameters error");
                    }
                   
                    break;
                case 'udf':
                    if(isset($keys['udf']) && !empty($keys['udf'])) {
                        $config[$table]['udf'] = $this->parseUDF($keys['udf']);   
                        if($config[$table]['udf'] === false) {
                            throw new Gek_Exception(0, "$table udf set error");
                        }
                    } else {
                        throw new Gek_Exception(0,"$table shardbyudf parameters error");
                    }
                    
                    break;
                default:
                    throw new Gek_Exception(0, "$table shard type set error");
           }
        }
        
        return $config;
    }
    
    //根据DB的权重随机选择一个DB
    private function getDbByWeight($db)
    {
        $dbr = array();
        $totalweigth = array_sum($db);
        $start = 1;
        foreach($db as $name => $weight) {
            $dbr[$name][0] = $start;
            $dbr[$name][1] = intval($start + $weight/$totalweigth * 100 - 1);
            $start = $dbr[$name][1] + 1;
        }
        
        $r = mt_rand(1, $start-1);
        foreach($dbr as $name => $range) {
            if ($r >= $range[0] && $r <= $range[1]) {
                unset($dbr);
                return $name;
            }
        }
        
        unset($dbr);
        return $name;
    }
    
    private function packDBInfo($dbname, $tablename, $dbtype)
    {
        $db = $this->config['@db@'][$dbname];
        $db['table'] = $tablename;
        $db['type'] = $dbtype;
        return $db;
    }

    /**
     * 获取sharding db信息。
     * @param string $table: 表名。
     * @param string $key:   用于切分的key。如果切分方式为history,key取值格式为： date / @status / date@status
     * @param string $dbtype: 返回的数据库类型:  master,slave
     * @return array dbinfo = array('host'=>,'port'=>,'user'=>,'password'=>,'db'=>,'table'=>,'type'=>'master' or 'slave')
     */
    public function getDatabase($table, $key, $dbtype='master')
    {
        if(!isset($this->config[$table]) || empty($this->config[$table])) {
            throw new Gek_Exception(0, "$table sharding is unsupport.");
        }
        
        if($dbtype != 'master' && $dbtype != 'slave') {
            $dbtype = 'master';
        }
        
        $shard = $this->config[$table];
        $shardtype = $shard['shardtype'];
        if($shardtype != 'udf' && $shardtype != 'none') {
            $difftable = $shard['difftable'];
        }
        
        $db = '';
        switch ($shardtype) {
             case 'none':
                 reset($shard['masters']);
                 $db = key($shard['masters']);
                 break;
             case 'mod':
                 $mod = $key % $shard['mod'];
                 $db = $shard['db'][$mod];
                 if($difftable) $table .= "_{$mod}";
                 break;
             case 'date':
                 $time = strtotime($key);
                 if($time === false || $time <= 0) {
                     throw new Gek_Exception(0, "sharding $key is invalid date format");
                 }
                 
                 $db = '';
                 foreach($shard['db'] as $dbname => $date) {
                     if(($date[0] == 0 && $date[1] == 0) || 
                        ($time >= $date[0] && ($date[1] == 0 || $time <= $date[1]))) {
                         $db = $dbname;
                         break;
                     }
                 }
                 if(empty($db)) {
                     throw new Gek_Exception(0, "sharding $key out of date range");
                 }
 
                 if(!$difftable) break;
                 $parseDate = date_parse($key);
                 $year = $parseDate['year'];
                 $month = $parseDate['month'];
                 switch ($shard['date']) {
                    case 'year': 
                        $table .= "_{$year}";
                        break;
                    case 'half-year':
                        if($month <= 6) {
                            $table .= "_{$year}A";
                        } else {
                            $table .= "_{$year}B";
                        }
                        break;
                    case 'season': 
                        if($month <= 3) {
                            $table .= "_{$year}S1";
                        } else if($month <= 6) {
                            $table .= "_{$year}S2";
                        } else if($month <= 9) {
                            $table .= "_{$year}S3";
                        } else if($month <= 12) {
                            $table .= "_{$year}S4";
                        } 
                        break;
                    case 'month': // 月
                        if($month < 10)
                            $table .= "_{$year}0{$month}";
                        else
                            $table .= "_{$year}{$month}";
                        break;
                 }
                 break;
             case 'history':
                 $pos = strpos($key,'@'); // $key=date[@status] 
                 if($pos === false) {
                     $date = $key;
                     $status = '';
                 } else {
                     $date = substr($key,0,$pos);
                     $status = substr($key,$pos+1);
                 }
                 
                 $isHistory = 0;
                 if($shard['conds'] == 'any') {
                     if(isset($shard['histroy']) && !empty($shard['history']) && !empty($date)) {
                         $time = strtotime($date);
                         if($time === false || $time <= 0) {
                             throw new Gek_Exception(0, "sharding $key date set error.");
                         }
                         if($time < strtotime($shard['histroy'])) {
                             $isHistory = 1;
                         }
                     }
                     
                     if(!$isHistory && isset($shard['status']) && !empty($shard['status']) && !empty($status)) {
                         if($status == $shard['status']) {
                             $isHistory = 1;
                         }
                     }
                 } else if($shard['conds'] == 'both') {
                     if(isset($shard['history']) && !empty($shard['history']) && !empty($date) && 
                        isset($shard['status'])  && !empty($shard['status'])  && !empty($status)) {
                         $time = strtotime($date);
                         if($time === false || $time <= 0) {
                             throw new Gek_Exception(0, "sharding $key date set error.");
                         }
                         
                         $history = strtotime($shard['history']);
                         if($time <  $history && $status == $shard['status']) {
                             $isHistory = 1;
                         }
                     }
                 }
                 
                 if($isHistory) {
                     $db = $shard['db']['history'];
                     if($difftable) $table .= '_h';
                 } else {
                     $db = $shard['db']['current'];
                 }
                 break;
             case 'range':
                 foreach($shard['range'] as $name => $range) {
                    if($key >= $range[0] && ($range[1] == 0 || $key <= $range[1])) {
                        $db = $shard['db'][$name];
                        if($difftable)  $table .= "_{$name}";
                        break;
                    }
                 }
                 break;
             case 'map':
                 $keylen = strlen($key);
                 if($keylen <= 3) {
                     $hkey = $table . ':0';
                     $field = $key;
                 } else {
                     $seglen = $keylen - 3;
                     $hkey = $table . ':' . substr($key,0,$seglen);
                     $field = substr($key,$seglen);
                 }
                 
                $redis = new Redis();
                if (!$redis->connect($shard['map'][0], $shard['map'][1])) {
                    throw new Gek_Exception(0, "connect redis[{$shard['map'][0]}:{$shard['map'][1]}] failure.");
                }
                $value = $redis->hGet($hkey, $field);
                if ($value === false) {
                    $db = $this->getDbByWeight($shard['masters']);
                    if($difftable) {
                        $tabidnum = count($shard['db'][$db]);
                        $index = mt_rand(0, $tabidnum-1);
                        $tabid = $shard['db'][$db][$index];
                        $table .= "_{$tabid}";
                        $value = $db . ':' . $tabid;
                    } else {
                        $value = $db;
                    }
                    if($redis->hSet($hkey, $field, $value) === false) {
                        throw new Gek_Exception(0, "shardbymap store $key error.");
                    }
                } else if($difftable) {
                    $pos = strpos($value,':');
                    $db = substr($value, 0, $pos);
                    $tabid = strstr($value, $pos+1);
                    $table .= "_{$tabid}";
                } else {
                    $db = $value; 
                }
                $redis->close();
                break;
             case 'udf':
                 $udfargs = array();
                 $udfargs[0] = $table;
                 $udfargs[1] = $key;
                 $udfargs[2] = $dbtype;
                 $udfargs[3] = $shard['udf'][1];
                 $r = call_user_func_array(array($this, $shard['udf'][0]), $udfargs); 
                 if($r === false) {
                     throw new Gek_Exception(0, "sharding: call udf {$shard['udf'][0]} error");
                 }
                 return $r;
        }
        
        if($dbtype == 'master') {
            return $this->packDBInfo($db,$table,$dbtype);
        } else if($dbtype == 'slave') {
            if(!isset($shard['slaves']) || empty($shard['slaves'])) {
                throw new Gek_Exception(0, "sharding: $table slaves isn't set.");
            }
            $db = $this->getDbByWeight($shard['slave'][$db]);
            return $this->packDBInfo($db,$table,$dbtype);
        }
    }
    
    /**
     * TODO: support multi-sharding.
     * @param string $table
     * @param array  $key      array(key1,key2,key3,...); 每一个key用于一种切分方式。
     * @param string $dbtype
     */
    public function getDatabaseByMulti($table, $key, $dbtype='master')
    {  
    }
}
