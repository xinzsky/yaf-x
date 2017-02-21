<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


// 未完成 ...

class Gek_Utils_Unihan
{
    // 此方法依赖于mbstring扩展。
    public static function fan2jian($value)
    {
        global $Unihan;
        
        if($value === '') return '';
        $r = '';
        $len = mb_strlen($value,'UTF-8'); 
        for($i=0; $i<$len; $i++){
            $c = mb_substr($value,$i,1,'UTF-8');
            if(isset($Unihan[$c])) $c = $Unihan[$c];
            $r .= $c;
        }
        
        return $r;
    }
}
