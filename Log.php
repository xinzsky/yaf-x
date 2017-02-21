<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


class Gek_Log  {
    
    private static $levels = array(
        'NONE'  => 0,
        'FATAL' => 1, 
        'ERROR' => 2, 
        'WARN'  => 3,  
        'INFO'  => 4, 
        'DEBUG' => 5, 
        'ALL'   => 6
    );
    
    private static $logpath = '';
    private static $level = 'DEBUG';
    
    public static function init($logpath, $level='') 
    {
        self::$logpath = $logpath;
        if(!empty($level) ) {
            $level = strtoupper($level);
            if(isset(self::$levels[$level])) {
                self::$level = $level;
            }
        }
    }
    
    public static function write($msg, $level='ERROR')
    {
        if(empty(self::$logpath)) {
			return false;
		}

		$level = strtoupper($level);
		if(!isset(self::$levels[$level]) || (self::$levels[$level] > self::$levels[self::$level])) {
			return false;
		}

		if(!$fp = @fopen(self::$logpath, 'ab')) {
			return false;
		}
        
        $message  = '';
        $message .= $level.' '.(($level == 'INFO' || $level == 'WARN') ? ' -' : '-').' '.date('Y-m-d H:i:s'). ' --> '.$msg."\n";
        
 		flock($fp, LOCK_EX);
		fwrite($fp, $message);
		flock($fp, LOCK_UN);
		fclose($fp);

		@chmod(self::$logpath, 0666);
		return false;
    }
}
