<?php  if ( ! defined('ROOT')) exit('No direct script access allowed');
/**
 * Description of db
 *
 * @author QiuMS
 */
class Db {
	public $dbinfo = array();
	public $dbconf = array();
	public $dbattr = array();
    public $data_count = 0;
    public $usetbl;
    public $pk = 'id';
    public $insert_id;
	public $callback = NULL;
    private $linkid;
    private $queryid;
	private $active_group = array();
	private $active;

    static public function getInstance($active=''){
		if (!$_ENV['db']){
			$db = __CLASS__;
			$_ENV['db'] = new $db;
			unset($db);
		}
		return $_ENV['db']->config($active);
	}
	public function Db(){
		$this->dbinfo = array(
			'query_count' => 0,
			'debug' => gc('base.run_mode')=='debug',
			'extensions' => gc('database.type'),
			'sql'=> array(),
		);
		if (!extension_loaded($this->dbinfo['extensions']))
			$this->dbinfo['extensions'] = 'mysql';
		return $this;
	}
	private function config($active=''){
		if (!($dbconf = gc('database'))) show_error('Cannot load database configuration file.');
		if ($active){
			$this->active = $active;
		}else{
			if (!$this->active) $this->active = $dbconf['active_group'];
		}
        if (!$this->active) DbUtil::error('The database configuration error.');
		$this->dbconf = $dbconf[$this->active];
		return $this;
	}
	public function connect(){
        extract($this->dbconf, EXTR_PREFIX_ALL,'db');
        if (!$this->active_group[$this->active]){
            if (!$db_port) $this->dbconf['port'] = $db_port = 3306;
			if ('mysqli'==$this->dbinfo['extensions']){
				$this->dbi = new mysqli($db_host, $db_user, $db_pwd, $db_name, $db_port);
				if (!$this->dbi->connect_error) $this->dbi->linkid = TRUE;
			}elseif ('sqlite'==$this->dbinfo['extensions']){
				$this->dbi = new Dbsqlite($this->dbconf);
			}else{
				$this->dbi = new Dbmysql($this->dbconf);
			}
			if (!$this->dbi->linkid){
				return DbUtil::error("Cannot connect to Database server.");
			}
			$this->active_group[$this->active] = $this->dbi->linkid;
            $this->dbinfo['version'] = $this->dbi->server_info;
			if ('sqlite'==$this->dbinfo['extensions']) return $this->dbi;
			if ($this->dbinfo['version'] > "4.1"){
                if (!isset($db_charset)) $db_charset = gc('base.charset','utf8');
				$db_charset = str_replace("-", "", strtolower($db_charset));
				$this->dbi->query('SET character_set_connection='.$db_charset.', character_set_results='.$db_charset.', character_set_client=binary');
			}
			if ($this->dbinfo['version']>'5.0.1') $this->dbi->query("SET sql_mode=''");
			if (!$this->dbi->select_db($db_name)) return DbUtil::error("Cannot select database {$db_name}.");
        }
        return $this->dbi;
	}
	private function query($sql, $mode=MYSQLI_STORE_RESULT){
		$this->connect();
		$this->dbi->iud = FALSE;
		$time = microtime(TRUE);
		if (!($this->queryid = $this->dbi->query($sql, $mode))){
			return DbUtil::error($this->dbi->error);
		}
		$this->dbinfo['query_count']++;
		$this->dbinfo['sql'][] = $sql. ' ('. (microtime(TRUE)-$time). ')';
		if (!$this->dbattr['found'] && is_object($this->queryid)) {
			$this->data_count = $this->queryid->num_rows;
		}elseif ($this->dbattr['found']){
			$queryid = $this->dbi->query('SELECT FOUND_ROWS() AS `total`');
			$data = $this->result(1, 0, $queryid);
			$this->data_count = (int)$data['total'];
			unset($data);
		}
		$this->dbattr = array();
		return $this;
	}
	private function exec($sql, $lock=FALSE){
		if ($lock) $sql .= " FOR UPDATE ";
		$this->connect();
		$time = microtime_float();
		$this->dbi->iud = TRUE;
		$this->queryid = $this->dbi->query($sql);
		$this->dbinfo['sql'][] = $sql. ' ('. (microtime_float()-$time). ')';
		$this->data_count = $this->dbi->affected_rows;
		$this->insert_id = $this->dbi->insert_id;
		$this->dbattr = array();
		return $this->queryid;
	}
	private function cb(&$data, $cb=TRUE){
		if (!$cb OR !$this->callback) return '';
		call_user_func_array($this->callback, array(&$data));
	}
	private function result($type=0, $seek=0, $queryid=NULL){
		$result = array();
		$cb = is_null($queryid);
		if ($cb) $queryid = &$this->queryid;
		if (!$queryid) return $result;
		$func = "fetch_". gc('database.data_type', 'assoc');
		$i = 1;
		if ($type===1) {
            if ($queryid->num_rows>0 AND $queryid->data_seek($seek)) $result = $queryid->$func();
			$this->cb($result, $cb);
        }elseif ($type === 2) {
            while($rows = $queryid->$func()){
				$this->cb($rows, $cb);
                foreach ($rows as $name=>$val) {
                    if (!isset($result[$name])) $result[$name] = array();
                    $result[$name][] = $val;
                }
            }
            if ($queryid->num_rows>0) $queryid->data_seek(0);
        }elseif (3 === $type) {
			$pk = $this->dbattr['pk'];
			if (!$pk) $pk = 'id';
            while($rows = $queryid->$func()){
				$this->cb($rows, $cb);
				$rows['list__index'] = $i;
                $result[$rows[$pk]] = $rows;
				$i++;
            }
            if ($queryid->num_rows>0) $queryid->data_seek(0);
        }else{
            while($rows = $queryid->$func()){
				$this->cb($rows, $cb);
				$rows['list__index'] = $i;
                $result[] = $rows;
				$i++;
            }
            if ($queryid->num_rows>0) $queryid->data_seek(0);
        }
        $queryid->free();
        return $result;
	}
	public function run($sql){
		if(!$sql) return TRUE;
		$type = (int)$this->dbattr['datatype'];
        $ips = 'SET|INSERT|UPDATE|DELETE|REPLACE|'
				. 'CREATE|DROP|'
				. 'LOAD	DATA|SELECT	.* INTO|COPY|'
				. 'ALTER|GRANT|REVOKE|'
				. 'LOCK|UNLOCK';
        $sql = preg_replace('/\{PREFIX\}/', $this->dbconf['prefix'], $sql);
		$this->dbattr['lastsql'] = $sql;
		/*if (isset($this->dbattr['callback'])){
			$this->callback = $this->dbattr['callback'];
			unset($this->dbattr['callback']);
		}*/
		if (preg_match('/^\s*"?('.$ips.')\s+/i', $sql)) {//return TRUE;
			return $this->exec($sql);
		}
		$this->query($sql);
		$res = $this->result($type);
		$this->callback = NULL;
		return $res;
    }
    public function findAll($table=''){
		return $this->run($this->parse('select', '', $table));
    }
	// archives.id,arcdata.aid,article.aid
    public function find($table=''){
		$this->attr('datatype', 1);
		return $this->limit(1)
			->run($this->parse('select', '', $table));
    }
    public function count($table=NULL){
		if (is_null($table)) return $this->data_count;
		$data = $this->attr('fields', 'COUNT(*)|total')->find($table);
		return $data['total'];
    }
	public function insert($table, $data, $dup=''){
		if ($dup){
			$dup[$this->pk] = 'LAST_INSERT_ID()';
			$this->attr('updata', $dup);
		}
		$this->run($this->parse('insert', $data, $table));
		return $this->insert_id;
	}
	public function update($table, $data, $cond=''){
		$this->attr('updata', $data);
		return $this->where($cond)->run($this->parse('update', '', $table));
	}
	public function delete($table, $cond=''){
		return $this->where($cond)->run($this->parse('delete', '', $table));
	}
	public function attr($key, $value='', $append=FALSE){
		if (is_array($key)){
			$this->dbattr = array_merge($this->dbattr, $key);
		}else{
			if ($append){
                $this->dbattr[$key] .= $value;
            }else{
                $this->dbattr[$key] = $value;
            }
		}
		return $this;
	}
	public function limit($limit){
		if (is_numeric($limit)) $limit = intval($limit);
		if (1===$limit){
			$this->attr('type', 1);
		}elseif (!is_int($limit)){
			$this->attr('found', 'SQL_CALC_FOUND_ROWS');
		}
		$this->attr('limit', ' LIMIT '. (string)$limit);
		return $this;
	}
	public function where($key, $value=''){
		if (!$key AND !$value) return $this;
		if (!is_array($key)) $key = array($key=>$value);
		return $this->attr('cond', array_merge((array)$this->dbattr['cond'], $key));
	}
	public function order($order=NULL, $way='ASC'){
		if (!$way) $way = 'ASC';
		if (!is_null($order)){
			if (!is_array($order)){
				if (FALSE!==strpos($order, ',')){
					$order = str2array($order, '', ',');
				}else{
					$order = array($order => $way);
				}
			}
		}
		return $this->attr('order', $order);
	}
	private function parse($type, $data=array(), $table=''){
		$table AND $this->attr('table', $table);
		if (!$this->dbattr['table']) return DbUtil::error('Table name is empty.');
		$this->attr('data', DbUtil::parse_data($this->dbattr['table'], $data));
		$ac = "parse_{$type}";
		return $this->get_updata()
			->get_table()
			->get_fields()
			->get_join()
			->get_where()
			->get_order()
			->attr('table', DbUtil::parse_field($this->dbattr['table']))
			->$ac($data);
	}
	private function parse_insert(){
		if (!$this->dbattr['data']) return '';
		$fields = array_keys($this->dbattr['data']);
        $sql = "INSERT INTO {$this->dbattr['table']} (".join(', ',$fields).") VALUES (". join(', ', array_values($this->dbattr['data'])).")";
		if ($this->dbattr['updata']){
			$sql .= " ON DUPLICATE KEY UPDATE ". $this->dbattr['updata'];
		}
		return $sql;
	}
	private function parse_select(){
		$sql = trim("SELECT {$this->dbattr['found']}"). " {$this->dbattr['fields']} FROM {$this->dbattr['table']}"
			. "{$this->dbattr['join']}{$this->dbattr['cond']}{$this->dbattr['order']}{$this->dbattr['limit']}";
		return $sql;
	}
	private function parse_update(){
		$sql = trim("UPDATE {$this->dbattr['table']} "). "{$this->dbattr['join']} SET {$this->dbattr['updata']}"
			. "{$this->dbattr['cond']}{$this->dbattr['order']}{$this->dbattr['limit']}";
		return $sql;
	}
	private function parse_delete(){
		$join = join(', ', (array)$this->dbattr['jointabs']);
		if ($join) $join = " {$join}";
		$sql = trim("DELETE{$join} FROM {$this->dbattr['table']} ")
			. "{$this->dbattr['join']}{$this->dbattr['cond']}{$this->dbattr['order']}{$this->dbattr['limit']}";
		return $sql;
	}
	private function parse_where($cond='', $lo='AND'){
		if (!$cond) return '1';
		if (is_string($cond)) return $cond;
		$pk = $this->dbattr['pk'];
		if (!$pk) $pk = 'id';
		if (is_numeric($cond)) $cond = array($pk => $cond);
		$where = array();
		foreach ($cond as $key=>$val){
			$opr = '=';
			$pattern = '';
			if (is_scalar($val) AND 'or'==strtolower($val)){
				$lo = $val;
				continue;
			}elseif (preg_match('/[\|\&]{1}/is', $key)){
				$tmp = preg_split('/[\|\&]{1}/', $key);
				foreach ($tmp as $name){
					$c[$name] = $val;
				}
				$where[] = $this->parse_where($c, FALSE!==strpos($key, '|') ? 'or' : 'and');
				unset($tmp, $c, $name);
				continue;
			}elseif (is_numeric($key) AND is_scalar($val) OR is_null($key)){
				continue;
			}elseif (is_array($val)){
				if ('match'=== strtolower($val[0])){
					$sc = 'match_'. str_replace(array('`', '.', ','), array('', '_', '_'), $key);
					$keys = explode(',', $key);
					foreach ($keys as &$k){
						$k = DbUtil::parse_field($this->parse_fields($k));
					}
					$match = 'MATCH('.join(', ', $keys).') AGAINST (\''.trim(DbUtil::parse_value($val[1]),'\'').'\' IN BOOLEAN MODE)';
					$where[] = $match;
					$this->attr('fields', ", {$match} AS `{$sc}`", TRUE);
					$this->dbattr['order'] = array_merge(array("|{$sc}|" => 'desc'), (array)$this->dbattr['order']);
					continue;;
				}
				$o = 'AND';
				if ('or'===strtolower($val[0])){
					$o = 'OR';
					unset($val[0]);
				}
				if (is_numeric($key)){
					$where[] = $this->parse_where($val, $o);
					continue;
				}elseif (isset($val['pattern'])){
					$pattern = $val['pattern'];
					$val = $val['value'];
				}else{
					$val = "IN ". join(', ', $val);
				}
			}elseif (is_null($val)){
				$opr = ' IS NULL';
			}
			$opr = strtoupper($opr);
			$key = DbUtil::parse_field($this->parse_fields($key));
			if ($pattern) $key = str_replace('[field]', $key, $pattern);
			extract($this->parse_logic($val));
			$where[] = rtrim("{$key} {$opr} {$val}");
		}
		return '( '. join(' '.strtoupper($lo).' ', $where). ' )';
	}
	private function parse_logic($value){
		if (preg_match('/^BETWEEN/i',$value)) return array('opr'=>$value,'val'=>'');
		preg_match('/^(\<\>|\<\=|\>\=|\<|\>|LIKE|IN)\s{1}(.*)/i', $value, $matches);
		if (empty($matches[1])) return array('opr'=>'=','val'=>DbUtil::parse_value($value));
		$opr = $matches[1];
		$value = $matches[2];
		return array('opr'=>$opr,'val'=>('IN'==$opr?"($value)":DbUtil::parse_value($value)));
	}
	private function parse_fields($key, $tbl=''){
		if (!$tbl) $tbl = $this->dbattr['table'];
		return (FALSE===strpos($key, '.')) ? "{$tbl}.{$key}" : "{$this->dbconf['prefix']}{$key}";
	}
	private function get_where(){
		if (!$this->dbattr['cond']) return $this;
		return $this->attr('cond', ' WHERE '. $this->parse_where($this->dbattr['cond']).' ');
	}
	/*
	array('views'=>'arcdata.totalview/desc','id/desc','imgcount/asc');
	*/
	private function get_order(){
		$order = $this->dbattr['order'];
		if (!$order OR !is_array($order)) return $this;
		foreach ($order as $key=>$way){
			if (!$way) $way = 'ASC';
			unset($order[$key]);
			if (FALSE===strpos($key, '|')){
				$key = $this->parse_fields($key);
			}else{
				$key = trim($key, '|');
			}
			$order[] = DbUtil::parse_field($key). ' '. strtoupper($way);
		}
		return $this->attr('order', ' ORDER BY '. join(', ', $order));
	}
	private function get_updata(){
		$data = DbUtil::parse_data($this->dbattr['table'], $this->dbattr['updata']);
		if (!$data) return $this;
		foreach ($data as $key=>$val){
			$a = trim($val, '\'');
			if (is_scalar($a) AND preg_match('/^\[(\+|\-)\]/', $a)){
				$logic = substr($a, 0, 2);
				$val = DbUtil::parse_value(substr($a, 3));
				if ('[+]'==$logic AND !is_numeric($a)){
					$val = "CONCAT({$key}, {$val})";
				}else{
					$val = "{$key} ". substr($logic, 1, 1). " {$val}";
				}
			}
			unset($a);
			$data[$key] = "{$key} = {$val}";
		}
		return $this->attr('updata', join(', ', $data));
	}
	//on: archives.mid,article.mid,archives.sid,1
	private function get_join(){
		if (!$this->dbattr['join']) return $this->attr('join', '');
		$type = strtoupper($this->dbattr['jointype']);
		if (!$type) $type = 'LEFT';
		$type = " {$type} JOIN ";
		$on = (array)$this->dbattr['on'];
		$array = array();
		$index = 0;
		foreach ($this->dbattr['join'] as $tbl=>$join){
			$a = isset($on[$index]) ? $on[$index] : (isset($on[$tbl]) ? $on[$tbl] : NULL);
			if (!is_null($a)){
				if (!is_array($a)) $a = str2array($a, '', ',');
				foreach ($a as $key=>$val){
					if (!$val OR $key==$val) $val = "{$tbl}.{$key}";
					$key = DbUtil::parse_field($this->parse_fields($key));
					if (FALSE!==strpos($val, '#')){
						$val = DbUtil::parse_value(trim($val, '#'));
					}else{
						$val = DbUtil::parse_field($this->parse_fields($val));
					}
					$join .= " AND {$key} = {$val}";
				}
			}
			$tbl = DbUtil::parse_field($this->dbconf['prefix']. $tbl);
			$array[] = "{$tbl} ON ( {$join} )";
			$index++;
		}
		return $this->attr('join', " {$type} ". join($type, $array));
	}
	private function get_table(){
		$table = $this->dbattr['table'];
		$prefix = $this->dbconf['prefix'];
		if (FALSE!==($index = strpos($table, '.'))){
			$pk = substr($table, $index+1);
			$table = substr($table, 0, $index);
		}else{
			$pk = $this->dbattr['pk'];
			if (!$pk) $pk = 'id';
		}
		$this->attr('table', $prefix. $table);
		if (!is_array($this->dbattr['join'])) $this->attr('join', array());
		$jointabs = array(DbUtil::parse_field($this->dbattr['table']));
		foreach ($this->dbattr['join'] as $key=>$tbl){
			unset($this->dbattr['join'][$key]);
			if (FALSE===($index = strpos($tbl, '.'))) continue;
			$jpk = explode('~', substr($tbl, $index+1));
			$rpk = isset($jpk[1]) ? $jpk[1] : $pk;
			$tbl = substr($tbl, 0, $index);
			$jointabs[] = DbUtil::parse_field($prefix. $tbl);
			$this->dbattr['join'][$tbl] = DbUtil::parse_field($this->parse_fields($rpk)).' = '. DbUtil::parse_field("{$prefix}{$tbl}.{$jpk[0]}");
		}
		unset($jpk, $rpk, $tbl);
		return $this->attr('jointabs', $jointabs);
	}
	private function get_fields(){
		$value = $this->dbattr['fields'];
		$table = $this->dbattr['table'];
        if (is_null($value)) $value = '*';
		if ('*'==$value){
			return $this->attr('fields', DbUtil::parse_field("{$table}.{$value}"));
		}
		$value = explode(',', $value);
		$keys = array();
		$fn = 'SUM|CONCAT|COUNT|CONV|ELT|LCASE|LEFT|LOWER|REPEAT|RIGHT|SUBSTRING|TRIM|UCASE|ABS|FORMAT|FROM_UNIXTIME|MAX|MIN|REPLACE|UPPER';
		foreach ($value as $val){
			$as = '';
			if (FALSE!==strpos($val, '.') AND !$this->dbattr['join']) continue;
			if (FALSE!==($pos=strpos($val, '|'))){
				$as = ' AS '. DbUtil::parse_field(substr($val,$pos+1));
				$val = substr($val,0, $pos);
			}
			preg_match('/^('.$fn.')\(([\s\S]*)\)$/is',$val,$matches);
			if (!empty($matches[1])){
				$myfn = $matches[1];
				$val = str_replace('%',',',$matches[2]);
			}
			if ('*' === $val OR FALSE!==strpos($val, ',')){
				$key = (isset($matches[1]) AND 'count'===strtolower($matches[1])) ? $val : $table.'.'.$val;
			}else{
				$val = $this->parse_fields($val);
				$key = DbUtil::parse_field($val);
			}
			if (isset($myfn)) $key = $myfn.'('.$key.')';
			$keys[] = $key. $as;
		}
		return $this->attr('fields', join(', ', $keys));
	}
}
class MysqlQuery{
	private $queryid;
	public $num_rows = 0;

	public function MysqlQuery($queryid){
		if (!is_resource($queryid)) return $queryid;
		$this->queryid = $queryid;
		$this->num_rows = mysql_num_rows($queryid);
		return $queryid;
	}
	public function fetch_assoc(){
		return mysql_fetch_assoc($this->queryid);
	}
	public function fetch_object(){
		return mysql_fetch_object($this->queryid);
	}
	public function data_seek($seek=0){
		if (!$this->queryid) return ;
		return mysql_data_seek($this->queryid, $seek);
	}
	public function free(){
		if (empty($this->queryid)) return true;
		@mysql_free_result($this->queryid);
		$this->queryid = 0;
	}
}
class Dbmysql{
	private $dbconf;
	public $linkid;
	public $error;
	public $errno;
	public $iud = FALSE;
	public $server_info;
	public $affected_rows = 0;
	public $num_rows = 0;

	public function Dbmysql($dbconf){
		$this->dbconf = $dbconf;
		$this->linkid = mysql_connect($dbconf['host'].":".$dbconf['port'], $dbconf['user'], $dbconf['pwd']);
		$this->server_info = mysql_get_server_info($this->linkid);
		return $this;
	}
    public function select_db($name){
		return mysql_select_db($name, $this->linkid);
	}
    public function close() {
        if (!empty($this->queryid)) mysql_free_result($this->queryid);
		foreach ($this->active_group as $linkid){
			@mysql_close($linkid);
		}
        $this->linkid = 0;
    }
    public function query($sql, $mode=''){
		if (empty($sql)) return TRUE;
		$func = (isset($this->dbconf['queryfn'])
			AND strtoupper($this->dbconf['queryfn']) == "UNBUFFERED" AND function_exists("mysql_unbuffered_query")) ?
			"mysql_unbuffered_query" : "mysql_query";
		if (!($queryid = $func($sql, $this->linkid))){
			$this->error = mysql_error($this->linkid);
			$this->errno = mysql_errno($this->linkid);
			return FALSE;
		}
		$this->insert_id = mysql_insert_id($this->linkid);
		if (is_resource($queryid) AND $this->iud){
			$this->affected_rows = mysql_affected_rows($queryid);
		}
		return new MysqlQuery($queryid);
	}
	public function real_escape_string($string){
		return mysql_real_escape_string($string, $this->linkid);
	}
}
class SqliteQuery{
	private $queryid;
	public $num_rows = 0;
	public $affected_rows = 0;

	public function SqliteQuery($queryid, $iud){
		if (!is_resource($queryid)) return $queryid;
		$this->queryid = $queryid;
		if (!$iud){
			$this->num_rows = sqlite_num_rows($this->queryid);
		}else{
			$this->affected_rows = sqlite_affected_rows($this->queryid);
		}
		return $queryid;
	}
	public function fetch_assoc(){
		return sqlite_fetch_assoc($this->queryid);
	}
	public function fetch_object(){
		return sqlite_fetch_object($this->queryid);
	}
	public function data_seek($seek=0){
        if (!$this->num_rows) return array();
		return sqlite_seek($this->queryid, $seek);
	}
	public function free(){
		if (empty($this->queryid)) return true;
		@mysql_free_result($this->queryid);
		$this->queryid = 0;
	}
}
class Dbsqlite {
	private $dbconf;
	public $linkid;
	public $error;
	public $errno;
	public $iud = FALSE;
	public $server_info;

	public function Dbsqlite($dbconf){
		if (!is_file($dbconf['name'])) return FALSE;
		$this->dbconf = $dbconf;
		$this->linkid = sqlite_open($dbconf['name']);
		$this->server_info = sqlite_libversion();
		return $this;
	}
	public function query($query){
		if (empty($query)) return TRUE;
		if (preg_match('/^show/is', $query)) return $this->util($query);
		if ($this->iud){
			$func = 'sqlite_exec';
		}else{
			$func = (isset($this->dbconf['queryfn'])
				AND strtoupper($this->dbconf['queryfn']) == "UNBUFFERED" AND function_exists("sqlite_unbuffered_query")) ?
				"sqlite_unbuffered_query" : "sqlite_query";
		}
		if (!($queryid = $func($this->linkid, $query))){
			$this->errno = sqlite_last_error($this->linkid);
			$this->error = sqlite_error_string($this->errno);
			return FALSE;
		}
		$this->insert_id = @sqlite_last_insert_rowid($this->linkid);
		return new SqliteQuery($queryid, $this->iud);
	}
	public function close() {
        if (!empty($this->queryid)) mysql_free_result($this->queryid);
		@sqlite_close($this->linkid);
        $this->linkid = 0;
    }
	public function real_escape_string($string){
		return sqlite_escape_string($string, $this->linkid);
	}
	public function util($sql){
		preg_match_match('/show columns from (\w+)/is', $sql, $matches);
		if ($matches){
			return sqlite_fetch_column_types($matches[1], $this->linkid, SQLITE_ASSOC);
		}
		if (FALSE!==strpos($sql, 'SHOW TABLES')){
			return filesize($this->dbconf['name']);
		}
		return NULL;
	}
}
class DbUtil{
	static $cache_fields=array();
	static public function parse_field($value, $add=FALSE){
		if (empty($value)) $value = '*';
		if (FALSE === strpos($value,'`')){
			if ('*' == trim($value)) return trim($value);
			if (FALSE !== strpos($value, '.')){
				$ar = explode('.', $value);
				//if ($add) $ar[0] = $this->parseTable($ar[0]);
				if ('*' == trim($ar[1])) return '`'.trim($ar[0]).'`.*';
				$value = '`'.trim($ar[0]).'`.`'. trim($ar[1]). '`';
			}else{
				$value = '`'.trim($value).'`';
			}
		}
		//if (FALSE == strpos($value, '.') && $add) $value = "`{$this->useTable}`.$value";
		return $value;
	}
	static public function parse_value($value){
		if(is_array($value)) $value=join(',', $value);
		if(is_numeric($value)){
			$value = floatval($value);
		}elseif(preg_match('/^\(\w*(\+|\-|\*|\/)?\w*\)$/i',$value)){
			$value = self::escape($value);
		}elseif (is_array($value)){
			$value = self::parse_value(join(',', $value));
        }else{
			$value = '\''.self::escape($value).'\'';
		}
		return $value;
	}
    static public function parse_data($table, $data){
		if (!is_array($data)) return $data;
		$table = array($table);
		$prefix = Db::getInstance()->dbconf['prefix'];
        foreach ($data as $key=>$val){
			if (FALSE!==($index = strpos($key, '.'))) $table[] = substr($key, $index+1);
			//$table[] = basename(str_replace('.', '/', $key));
		}
		$table = array_unique($table);
		foreach ($table as $tbl){
			$table[$tbl] = self::table_fields($tbl);
		}
		$fn = 'LAST_INSERT_ID()';
		foreach ($data as $key=>$val){
			$tbl = current($table);
			if (FALSE!==($index = strpos($key, '.'))){
				$tbl = substr($key, $index+1);
				$key = substr($key, 0, $index);
			}
			$fields = $table[$tbl];
			unset($data[$key]);
			if (is_numeric($key) || !isset($fields[$key]) || $fields[$key]['autoinc']){
				continue;
			}
			$key = self::parse_field("{$prefix}{$tbl}.{$key}");
			$data[$key] = ($val AND strpos($fn, $val)!==FALSE) ? $val : self::parse_value($val);
		}
		return $data;
    }
    static public function table_size(){
		$rs = Db::getInstance()->run('SHOW TABLE STATUS LIKE \'{PREFIX}%\'');
		if (!is_array($rs)) return $rs;
		$dbsize = 0;
		foreach ($rs as $val){
			$dbsize += ($val['Data_length'] + $val['Index_length']);
		}
		return $dbsize;
	}
    static public function table_fields($table, $update=0){
		$db = Db::getInstance();
		$table = $db->dbconf['prefix']. $table;
		if (isset(self::$cache_fields[$table])) return self::$cache_fields[$table];
        if (!$update AND FALSE !==($rs = cache::fq('dbfields.'.$table))){
			self::$cache_fields[$table] = $rs;
			return $rs;
		}
		$attr = $db->dbattr;
        $rs = $db->run('SHOW COLUMNS FROM `'.$table.'`');
		Db::getInstance()->dbattr = $attr;
		unset($db, $attr);
        $info = array();
		foreach ($rs as $key => $val) {
			if(is_object($val)) $val = get_object_vars($val);
			$info[$val['Field']] = array(
				'name' => $val['Field'],
				'type' => $val['Type'],
				'notnull' => (bool)	($val['Null'] === ''), // not null is empty, null is yes
				'default' => $val['Default'],
				'primary' => (strtolower($val['Key']) == 'pri'),
				'autoinc' => (strtolower($val['Extra'])	== 'auto_increment'),
			);
		}
		self::$cache_fields[$table] = $info;
		cache::fq('dbfields.'.$table, $info);
		return $info;
    }
    static public function escape($value, $linkid=''){
        return Db::getInstance()->connect()->real_escape_string($value);
    }
	static public function error($error){
		$db = Db::getInstance();
		if ($db->dbinfo['debug']) $error .= "<br />{$db->dbattr['lastsql']}";
		echo response::cprint(0, $error);
		exit;
	}
}
