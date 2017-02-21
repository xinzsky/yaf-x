<?php
/*
 +----------------------------------------------------------------------+
 | Author: Xingzhi Liu  <dudubird2006@163.com>                          |
 +----------------------------------------------------------------------+
 */


/**
 *  封装MySQL 5 PDO ：
 *  连接：重连 持久链接 waitTimeout ping utf-8 异常处理 assoc 模拟prepare...
 *  事务: only use for InnoDB
 *  查询: 支持语句参数、防止SQL注入、多SELECT语句查询、查询失败会重连、结果集采用关联数组、禁止服务器端结果集
 *  CURD：批量插入
 *  字段meta：数据表中各个字段meta信息。
 *  单例:一个DB可以只实例化一次。不同的DB是不同的实例。
 *  标识符保护：内容构造SQL语句中涉及的表名、字段名等都加上反引号，但不支持 xxx.yyy形式的标识符。
 *  注意区分： insert replace update的区别: replace是先删除原记录再insert一条新记录。
 * 
 * @author liuxingzhi@2014.5
 */

class Gek_Db {
    private $pdo;
    private $dsn;
    private $user;
    private $password;
    private $is_persistent;
    private $wait_timeout;
    
    private static $instances = array(); 
    
    public static function getInstance($dsn) 
    {
        if(is_string($dsn)) {
            $db = md5($dsn);
        } else {
            $db = md5(implode(':', $dsn));
        }
        
        if (!isset(self::$instances[$db])) {
            self::$instances[$db] = new Gek_Db($dsn);
            return self::$instances[$db];
        } else {
            return self::$instances[$db];
        }
    }
    
    /**
     * $dsn 可以是host:port:user:password:database格式的字符串。
     * @param type $dsn
     * @param type $is_persistent
     * @param type $wait_timeout
     */
    public function __construct($dsn, $is_persistent=false, $wait_timeout=0)
    {
        $this->pdo = NULL;
        $this->user = '';
        $this->password = '';
        $this->is_persistent = $is_persistent;
        $this->wait_timeout = $wait_timeout;
        $this->dsn = 'mysql: charset=utf8;';
        
        if(is_string($dsn)) {
             $dsn = explode(':', $dsn);
             $dsn['host'] = $dsn[0];
             $dsn['port'] = $dsn[1];
             $dsn['user'] = $dsn[2];
             $dsn['password'] = $dsn[3];
             $dsn['db'] = $dsn[4];
        }
        
        if(isset($dsn['host']) && !empty($dsn['host'])) {
            $this->dsn .= ('host=' . $dsn['host'] . ';' );
        } else {
            $this->dsn .= ('host=localhost;');  
        }
        
        if(isset($dsn['port']) && !empty($dsn['port'])) {
            $this->dsn .=  ('port=' .  $dsn['port'] . ';' );
        } else {
            $this->dsn .=  ('port=3306;' );
        }
         
        if(isset($dsn['db']) && !empty($dsn['db'])) {
            $this->dsn .=  ('dbname=' . $dsn['db'] . ';' );
        }
        
        if(isset($dsn['user']) && !empty($dsn['user'])) {
            $this->user = $dsn['user'];
        }
        
        if(isset($dsn['password']) && !empty($dsn['password'])) {
            $this->password = $dsn['password'];
        }
        
        $this->connect($is_persistent, $wait_timeout);
    }
    
    public function __destruct() 
    {
        $this->pdo = NULL;
    }
    
    /**
     * 和数据库建立连接或持久连接，$waitTimeout=0表示不设置，MySQL默认为28800s
     * @param type $is_persistent
     * @param type $wait_timeout
     * @return boolean
     * @throws Gek_Exception 
     */
    private function connect($is_persistent=false, $wait_timeout=0)
    {
        $connectNum = 2; //连接失败后再重连一次，最多两次。
        do {
            try {
                if($is_persistent) {
                    $this->pdo = new PDO($this->dsn, $this->user, $this->password, array(PDO::ATTR_PERSISTENT => true, PDO::ATTR_TIMEOUT => 2));
                } else {
                    $this->pdo = new PDO($this->dsn, $this->user, $this->password, array(PDO::ATTR_PERSISTENT => false, PDO::ATTR_TIMEOUT => 2));
                }
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); 
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); 
                $this->pdo->exec("SET NAMES utf8");
                if($wait_timeout > 0) {
                    $this->pdo->exec("SET SESSION wait_timeout={$wait_timeout}");
                }
                return true; 
            } catch (PDOException $e) {
                $connectNum--;
                if($connectNum == 0) {
                    throw new Gek_Exception(0, $e->getMessage(), $e->getCode());
                }
            }
        } while($connectNum > 0);
    }
    
    /**
     * 如果是MySQL服务器主动关闭连接，则会重连，连接成功返回true，连接失败抛出异常。
     * 如果不是MySQL服务器主动关闭连接，则返回false。
     */
    private function ping()
    {
        $server_info = $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO);
        if (!empty($server_info) && $server_info == 'MySQL server has gone away') {
            return $this->connect($this->is_persistent,  $this->wait_timeout);
        } else {
            return false;
        }
    }
    
    /**
     * 事务开始 for InnoDB
     * @return type
     * @throws Gek_Exception 
     */
    public function beginTransaction()
    {
        try {
            return $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            throw new Gek_Exception(0, $e->getMessage(), $e->getCode());
        }
    }
    
    /**
     * 提交事务
     * @return type
     * @throws Gek_Exception
     */
    public function commit()
    {
        try {
            return $this->pdo->commit();
        } catch (PDOException $e) {
            throw new Gek_Exception(0, $e->getMessage(), $e->getCode());
        }
    }
    
    /**
     * 回滚事务，如果一个事务执行其中任何一个语句抛出异常，则需要回滚该事务
     * @return type
     * @throws Gek_Exception
     */
    public function rollBack()
    {
        try {
            return $this->pdo->rollBack();
        } catch (PDOException $e) {
            throw new Gek_Exception(0, $e->getMessage(), $e->getCode());
        }
    }
    
    /**
     * SQL查询: select insert update delete ...
     * SQL语句可以有一个或多个参数，也可以没有参数，参数用'?'或:name表示
     * $params是输入参数取值数组， 可以是array(value1,value2,...), array(':name'=>value,...)
     * $params数组元素可以是NULL或空字符串。
     * $result指明返回的结果类型： 
     * count: 返回insert update delete语句受影响的行数(int)。
     * all: 返回select结果集中所有行（二维数组、空数组、false），适用于小结果集。
     * row: 只返回select结果集中第一行（一维数组、空数组、false），不支持逐行fetch，不建议把结果集放在服务器端。
     * col: 只返回select结果集中第一行第一列（string），不存在则返回false。
     * lastid: 返回最后插入行的ID。注意如果是一次插入多条，则返回最后一条的id。(string)
     * 返回值： 失败返回fasle 或 抛出异常。如果没有指定result类型，成功返回true。
     *         select查询返回结果都是字符串，且返回结果集中的字段取值可能为NULL 或 '' 空字符串
     * 注意： 
     * (1)不支持SELECT列绑定变量的方式。
     * (2)不支持多语句查询。
     * (3)IN查询必须用多个参数 eg. IN(?,?,?)
     * 
     * 特别注意：
     * 构造SQL语句时需要防止SQL注入：
     *  a. 使用Gek_Db::bq()函数把表名、字段名、关键词保护起来。
     *  b. 使用Gek_Db::qt()函数把字符串用单引号把包裹起来 'string', 还对字符串里特殊字符进行转义表示 \'.
     */
    public function query($sql, $params=array(), $result='')
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            try {
                $stmt->execute($params);
            } catch (PDOException $e) {
                if($this->ping()) {
                    $stmt->execute($params);
                } else {
                    throw $e;
                }
            }
            
            switch (strtolower($result)) {
                case 'count':
                    return $stmt->rowCount();
                case 'all':
                    return $stmt->fetchAll();
                case 'row':
                    $r = $stmt->fetch();
                    $stmt->closeCursor();
                    return $r;
                case 'col':
                    return $stmt->fetchColumn();
                case 'lastid':
                    $row_num = $stmt->rowCount();
                    $last_id = $this->pdo->lastInsertId();
                    return ($last_id + $row_num - 1);
                default:
                    return true;
            }
        } catch (PDOException $e) {
            // code is string, not int.
            throw new Gek_Exception(0, $e->getMessage(), $e->getCode());
        }
    }
    
    /**
     * 多语句查询（SELECT），参数$sql可以是字符串，或数组（一个元素就是一条语句）
     * 返回： 结果集数组（三维），错误抛出异常。
     * 注意： PDO MySQL驱动不能采用mysqlnd驱动，因为mysqlnd驱动不支持nextRowset方法，libmysqlclient驱动则支持。
     */
    public function multiQuery($sql) 
    {
        if(!empty($sql) && is_array($sql)) {
            $sql = implode(';', $sql);
        } if(empty($sql) || !is_string($sql)) {
            return array();
        }
        
        try {
            try {
                $stmt = $this->pdo->query($sql);
            } catch (PDOException $e) {
                if($this->ping()) {
                    $stmt = $this->pdo->query($sql);
                } else {
                    throw $e;
                }
            }
            
            $resultsets = array();
            do {
                $resultset = $stmt->fetchAll();
                array_push($resultsets, $resultset);
                unset($resultset);
            } while ($stmt->nextRowset());

            return $resultsets;
        } catch(PDOException $e) {
            throw new Gek_Exception(0, $e->getMessage(), $e->getCode());
        }
    }
    
    public function qt($str, $notype=false, $islike=false) 
    {
        return $this->quote($str, $notype, $islike);
    }
    
    public function bq($identifier)
    {
        return $this->backquote($identifier);
    }
    
    /**
     * 对字符串加引号: I'm => 'I\'m' 
     * 字符串中的特殊字符转义: ' -> \'   \ -> \\
     * quote()不会对MySQL LIKE通配符('%', '_')进行转义： ('\%', '\_')
     * quote()会把NULL值、false转换为空字符串''。
     * quote()不会进行类型判断，都会转成字符串。
     * @param type $str
     * @param type $notype 忽略数据类型，都当作字符串处理，这样NULL=>'' int/float=>'int/flost' true=>'1' false=>'0'
     * @param type $islike 是like操作对象，需要对like通配符进行转义
     * @return type
     */
    public function quote($str, $notype=false, $islike=false) 
    {
        if($notype || is_string($str)) { 
            if($notype && $str === false) {
                $str = "'0'";
            } else {
                $str = $this->pdo->quote($str); 
            }

            // escape LIKE condition wildcards
            if($islike === true) {
                $str = str_replace(	array('%', '_'), array('\%', '\_'), $str);
            }
        } else if(is_null($str)) {
            $str = 'NULL';
        } else if(is_bool($str)) {
            $str = ($str === FALSE) ? 0 : 1;
        } else {
            // int float直接返回
        }
        
        return $str; 
    }
    
   /**
     * 对表名、字段名等标识符进行保护。
     * @param type $identifier
     * @return type 
     */
    public function backquote($identifier)
    {
        return '`' . $identifier . '`';
    }

    /**
     * 支持单条或批量插入记录
     * @param type $table   
     * @param type $rows    插入列值，$rows可以是一维数组，也可以是二维数组（批量插入）,$row还可以是关联数组。
     * @param array $cols   列名数组，如果$rows是关联数组，则$cols可以为空。 注意，列名不能带表名，即不能是tabname.colname
     * @param type $result  count/lastid 返回结果类型
     * @param type $action  可以是INSERT or REPLACE
     * @return type         成功插入返回count/lastid，失败抛出异常。
     * @throws Gek_Exception
     */
    public function insert($table, $rows, $cols=NULL, $result='count', $action='INSERT') 
    {
        if(!is_array($rows) || (!empty($cols) && !is_array($cols))) {
            throw new Gek_Exception(0,"$action parameters isn't array");
        }
        
        $valueslist = array();
        if(isset($rows[0]) && is_array($rows[0])) {
            $valueslist = $rows;
        } else {
            array_push($valueslist, $rows);
        }
        
        if(empty($cols)) {
            $cols = array();
            foreach($valueslist[0] as $col => $value) {
                array_push($cols, $col);
            }
        }
        
        foreach($valueslist as $values) {
            if(count($cols) != count($values)) {
                throw new Gek_Exception(0,"$action parameters mismatch");
            }
        }
        
        foreach($cols as &$col) {
            $col = $this->backquote($col);
        }
        unset($col);
        $collist = implode(',', $cols);
        $table = $this->backquote($table);
        
        $sql = "$action INTO $table ( $collist ) VALUES ";
        foreach($valueslist as $values) {
            foreach($values as $key => $value) {
                $values[$key] = $this->quote($value);
            }
            $sql = $sql . '(' . implode(',', $values) . '),';
        }
        $sql = rtrim($sql, ',');
        return $this->query($sql,array(),$result);
    }
    
    public function replace($table, $rows, $cols=NULL, $result='count')
    {
        return $this->insert($table, $rows, $cols, $result, 'REPLACE');
    }
        
    /**
	 * 支持Update语句：
     * UPDATE tablename SET colname=colvalue,... [WHERE condition] [ORDER BY...][LIMIT num]
     * WHERE条件为空则数据表里的所有记录都会被更新。
     * ORDER BY 指明按一个或多个字段排序，UPDATE将会按照排序的顺序依次修改数据行。
     * LIMIT num 指明最多修改前num个数据行。
	 * @param	string	$table the table name
	 * @param	array	$values the update data 关联数组
	 * @param	mixed   $where  the where clause
     *          string  where条件,需要自己对字符串加引号和特殊字符转义。
     *          array   col op => value 一个元素就是一个where条件，多个条件之间是'AND'关系
	 * @param	$orderby array	the orderby clause 多字段排序，一个元素就是一个排序方式(col ASC/DESC)
	 * @param	$limit string	the limit clause
	 * @return	int  实际修改数据行的个数，如果数据行更新前后都没有变化则不会统计在内。
	 */
	public function update($table, $values, $where, $orderby = array(), $limit = FALSE)
	{
        $table = $this->backquote($table);
		foreach ($values as $key => $val) {
			$valstr[] = $this->backquote($key) . " = " . $this->quote($val);
		}
        
        if(!empty($where) && is_array($where)) {
            $w = '';
            foreach ($where as $key => $val) {
                if(!empty($w)) {
                    $w .= ' AND ';
                }
                
                $ss = explode(' ', $key);
                $key = $this->backquote(trim($ss[0]));
                $op = trim($ss[1]);
                $w .= ($key . $op);
				if ($val !== '') {
					$val = ' ' . $this->quote($val);
				}

				$w .= $val;
			}
            
            $where = $w;
        }
        
        foreach($orderby as &$order) {
            $ss = explode(' ', $order);
            $order = $this->backquote(trim($ss[0]));
            if(isset($ss[1])) {
                $order .= ' ' . trim($ss[1]);
            }
        }
        unset($order);
        $orderby = (count($orderby) >= 1) ? ' ORDER BY '. implode(", ", $orderby) : '';
		$limit = (!$limit) ? '' : ' LIMIT '. (int)$limit;

		$sql = "UPDATE " . $table . " SET " . implode(', ', $valstr);
		$sql .= (empty($where)) ? '' : " WHERE " . $where;
		$sql .= $orderby . $limit;
        
		return $this->query($sql, array(), 'count');
	}
	
     /**
	 * 支持Delete语句：
     * DELETE FROM tablename WHERE condition [ORDER BY...][LIMIT num]
     * WHERE条件不能为空, 避免误把数据表里的所有记录都删除。
     * 防止 DELETE FROM tablename WHERE true/1 ...把所有数据都删除了
     * ORDER BY 指明按一个或多个字段排序。
     * LIMIT num 指明最多删除前num个数据行。
	 * @param	string	the table name
	 * @param	mixed   the where clause
     *          string  where条件,需要自己对字符串加引号和特殊字符转义。
     *          array   col op => value 一个元素就是一个where条件，多个条件之间是'AND'关系
	 * @param	array	the orderby clause 多字段排序，一个元素就是一个排序方式(col ASC/DESC)
	 * @param	string	the limit clause
	 * @return	int  实际删除数据行的个数。
	 */
	public function delete($table, $where, $orderby = array(), $limit = FALSE)
	{
        $table = $this->backquote($table);
        if(empty($where)) {
            throw new Gek_Exception(0,"Where clause missing for Delete");
        }
        
        if(is_array($where)) {
            $w = '';
            foreach ($where as $key => $val) {
                if(!empty($w)) {
                    $w .= ' AND ';
                }
                
                $ss = explode(' ', $key);
                $key = $this->backquote(trim($ss[0]));
                $op = trim($ss[1]);
                $w .= ($key . $op);
				if ($val !== '') {
					$val = ' ' . $this->quote($val);
				}

				$w .= $val;
			}
            
            $where = $w;
        }
        
        foreach($orderby as &$order) {
            $ss = explode(' ', $order);
            $order = $this->backquote(trim($ss[0]));
            if(isset($ss[1])) {
                $order .= ' ' . trim($ss[1]);
            }
        }
        unset($order);
        $orderby = (count($orderby) >= 1) ? ' ORDER BY '. implode(", ", $orderby) : '';
		$limit = (!$limit) ? '' : ' LIMIT '. (int)$limit;

		$sql = "DELETE FROM " . $table;
		$sql .= " WHERE " . $where;
		$sql .= $orderby . $limit;
        
		return $this->query($sql, array(), 'count');
	}
    
    // 清空表里所有记录
    public function truncate($table)
    {
        $table = $this->backquote($table);
        $sql = "TRUNCATE TABLE $table";
        return $this->query($sql, array(), 'count');
    }
    
    public function getIDRange($table, $id, $where='')
    {
        $IDRange = array();
        if(empty($table) || empty($id)) {
            return false;
        }
        $table = $this->backquote($table);
        $id = $this->backquote($id);
        $sql = "SELECT MIN({$id}) as min_id,MAX({$id}) as max_id FROM $table";
        if(!empty($where)) {
            $sql = $sql . ' WHERE ' . $where; 
        }
        $result = $this->query($sql, array(), 'all');
        if($result) {
            $IDRange[0] = intval($result[0]['min_id']);
            $IDRange[1] = intval($result[0]['max_id']); 
        }
                
        return $IDRange;
    }
    
    public function rangeQuery($table, $id, $start, $end, $cols, $where='')
    {
        $table = $this->backquote($table);
        $id = $this->backquote($id);
        if(is_array($cols)) {
            foreach($cols as &$col) {
                $col = $this->backquote($col);
            }
            unset($col);
            $cols = implode(',', $cols);
        } 
        
        $sql = "SELECT $cols FROM $table WHERE $id >= $start AND $id <= $end";
        if(!empty($where)) {
            $sql = $sql . ' AND ' . $where; 
        }

        return $this->query($sql, array(), 'all');
    }
    
     // $list可以是字符串或数组形式。
    public function mget($table, $id, $list, $cols, $where='')
    {
        $table = $this->backquote($table);
        $id = $this->backquote($id);
        if(is_array($cols)) {
            foreach($cols as &$col) {
                $col = $this->backquote($col);
            }
            unset($col);            
            $cols = implode(',', $cols);
        } 
        
        if(is_array($list)) {
            $lists = implode(',', $list);
            $sql = "SELECT $cols FROM $table WHERE $id IN ( $lists )";
        } else {
            $sql = "SELECT $cols FROM $table WHERE $id = $list";
        }
        if(!empty($where)) {
            $sql = $sql . ' AND ' . $where; 
        }
        
        return $this->query($sql, array(), 'all');
    }
    
    public function get($table, $pk, $pkv, $cols, $where='')
    {
        $table = $this->backquote($table);
        $pk = $this->backquote($pk);
        //$pkv = (int)$pkv; pkv取值范围超过带符号数的最大值时则不能在php中进行类型转化。
        if(is_array($cols)) {
            foreach($cols as &$col) {
                $col = $this->backquote($col);
            }
            unset($col);            
            $cols = implode(',', $cols);
        } 
        
        $sql = "SELECT $cols FROM $table WHERE $pk = ?";
        if(!empty($where)) {
            $sql = $sql . ' AND ' . $where; 
        }

        $result = $this->query($sql, array($pkv), 'all');
        if(!empty($result)) {
            return $result[0];
        } else {
            return $result;
        }
    }
    
    /**
     * 查看数据表每个字段的meta信息.
     * PDO getColumnMeta() 该函数是实验性质的，可以通过SHOW COLUMNS FROM [table]语句来代替该函数。
     * http://www.sitepoint.com/forums/showthread.php?497257-PDO-getColumnMeta-bug
     * @return: 返回一个二维数组 array(
     *  field_name => array(
     *      'type'      => '...', 必有
     *      'maxlength' => ...,   char varchar才有此属性
     *      'prikey'    => true,  可选，主键才有
     *      'required'  => true   必填字段才有此属性
     *      'default'   => '...', 默认值不为NULL '' false才有此属性
     *      'attr'      => '',    可选
     *      'pdotype'  => ...
     *                     );
     * )
     * 
     */
    public function getColumnMeta($table)
    {
        $meta = array();
        $sql = "SHOW COLUMNS FROM " . $this->backquote($table);

        $rows = $this->query($sql, array(), 'all');
        foreach($rows as $row) {
            $colname = $row['Field'];
            $meta[$colname] = $this->_parseColumnType($row['Type']);
             
            if($row['Key'] == "PRI") {
                $meta[$colname]['prikey'] = true;
            }
            
            if($row['Null'] == 'NO' && !isset($row['Default'])) {
                $meta[$colname]['required'] = true;
            }
            
            if( isset($row['Default'])   && 
               !is_null($row['Default']) && 
                $row['Default'] !== ''   &&
                $row['Default'] !== false ) {
                $meta[$colname]['default'] = $row['Default'];
            }
        }
        
        return $meta;
    }  

    private function _parseColumnType($colType)
    {
        $_pdoBindTypes = array(
            'char' => PDO::PARAM_STR,
            'int' => PDO::PARAM_INT,
            'bool' => PDO::PARAM_BOOL,
            'date' => PDO::PARAM_STR,
            'time' => PDO::PARAM_INT,
            'text' => PDO::PARAM_STR,
            'blob' => PDO::PARAM_LOB,
            'binary' => PDO::PARAM_LOB
        );  
        
        $colInfo = array();
        $colParts = explode(" ", $colType);
        if(($fparen = strpos($colParts[0], "(")) !== false) {
            $colInfo['type'] = substr($colParts[0], 0, $fparen);
            $colInfo['pdotype'] = '';
            if($colInfo['type'] == 'char' || $colInfo['type'] == 'varchar') {
                $colInfo['maxlength']  = str_replace(")", "", substr($colParts[0], $fparen+1));
            }
            $colInfo['attr'] = isset($colParts[1]) ? $colParts[1] : NULL;
        } else {
            $colInfo['type'] = $colParts[0];
        }
        
        // PDO Bind types
        foreach($_pdoBindTypes as $pKey => $pType) {
            if(strpos(' '.strtolower($colInfo['type']).' ', $pKey)) {
                $colInfo['pdotype'] = $pType;
                break;
            } else {
                $colInfo['pdotype'] = PDO::PARAM_STR;
            }
        }
        
        return $colInfo;
    }  

}
