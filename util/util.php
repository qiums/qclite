<?php if ( ! defined('ROOT')) exit('No direct script access allowed');
/*********************
Cache class
*********************/
class qccache {
	private $file_cache = array();
    private $head_string = "<?php if ( ! defined('ROOT')) exit('No direct script access allowed');\r\n//";
    private $gzip;

	function qccache(){
		set_class_property($this, gc('cache.iccache'));
        if ($this->gzip) $this->gzip = function_exists('gzcompress') AND function_exists('gzuncompress');
	}
    function add($path, $data='',$auth=FALSE, $exp=-1){
        $this->set($path, $data,$auth, $exp);
    }
	/* cache.db */
	function set($path, $data='',$auth=FALSE, $exp=-1){
		$fpath = CACHE_PATH. (!strpos($path, '.php') ? str_replace('.', '/', $path). '.php' : $path);
		if (is_null($data)) {
			if (TRUE === ($return = @unlink($fpath))){
				unset($this->file_cache[$path]);
			}
			return $return;
		}elseif ('' != $data){
			is_string($data) AND $data = trim($data);
            if (TRUE===$this->gzip) $data = gzcompress($data, 9);
			$fdata = $this->head_string . sprintf('%012d',$exp). "\r\n";
			$fdata .= "return ".var_export($data, TRUE).";\r\n?>";
			$this->file_cache[$path] = $data;
			return io::qw($fpath, $fdata);
		}
	}
	function get($path){
		if (isset($this->file_cache[$path])) return $this->file_cache[$path];
		$fpath = CACHE_PATH. (!strpos($path, '.php') ? str_replace('.', DS, $path). '.php' : $path);
		if (FALSE !== ($data = io::read($fpath))){
            $headlen = strlen($this->head_string);
            if (TRUE === $this->gzip) $data = gzuncompress($data);
			$exp = (float)substr($data, $headlen, 12);
			if ($exp > 0 && time() > filemtime($fpath) + $exp){
				unlink($fpath);
				return false;
			}//print_r(trim(substr($data, $headlen+13, -2)));
			$cont = eval(trim(substr($data, $headlen+13, -2)));
			empty($cont) && $cont = FALSE;
			$this->file_cache[$path] = $cont;
			return $cont;
		}
		return FALSE;
	}
	function delete($path){
		$this->set($path, NULL);
	}
	function flush(){
	}
}
class eaccelerator{
    var $support;
    function eaccelerator(){
        $this->support = (function_exists('eaccelerator_put')) AND function_exists('eaccelerator_get');
    }
    function set($key, $value, $auth=FALSE,$expire=0){
        eaccelerator_put($key, $value, $expire);
    }
    function add($key, $value, $auth=FALSE,$expire=0){
        return $this->set($key, $data,$auth, $expire);
    }
    function get($key){
        return eaccelerator_get($key);
    }
    function delete($key){
        return eaccelerator_rm($key);
    }
    function flush(){
        return eaccelerator_gc();
    }
}
class cache{
	static private function factory(){
		static $instance = NULL;
		if (is_null($instance)){
			$conf = gc('cache');
			$handle = strtolower($conf['handle']);
            $mems = !isset($conf['memcache']['server']) ? 0 : count($conf['memcache']['server']);
			if ($mems AND $handle == 'memcache' AND class_exists('Memcache', false)) {
				$instance = new Memcache();
				foreach ($conf['memcache']['server'] as $one){
					$instance->addServer($one['host'], $one['port'], TRUE, $one['weight'], 1, 15, TRUE, array('cache','failure_callback'));
				}
                $mems -= self::failure_callback();
			}
            if ($handle== 'eaccelerator'){
                $instance = new eaccelerator();
                $mems = $instance->support;
            }
			if (!$mems || $handle == 'qccache'){
				$instance = new qccache();
				$GLOBALS['config']['cache']['handle'] = 'qccache';
			}
			if (!$instance) system_error('Cannot load util (cache).');
		}
		return $instance;
	}
	static public function failure_callback($ip='', $port=''){
		static $index = -1;
		$index++;
		return $index;
	}
	static private function bind($key='', $data=NULL){
		static $cache = array();
        if (is_null($key)) {
            $cache = array();
            return ;
        }
        if (empty($key)) return $cache;
		if (is_null($data)) return isset($cache[$key]) ? $cache[$key] : FALSE;
		$cache[$key] = $data;
	}
	static public function q($key, $data='',$exp=-1){
		if (!$key OR !is_scalar($key)) return $data;
		if (gc('cache.handle')!='qccache'){
			if ($exp == -1) $exp = 0;
			if (strpos($key, '.php')){
				$key = str_replace(array(CACHEPATH,'.php','/','\\'),array('','','_','_'),$key);
			}
		}
		$me = self::factory();
		if (''!==$data){
			if (is_null($data)) return $me->delete($key);
			return $me->set($key, $data, FALSE, $exp);
		}else{
			return $me->get($key);
		}
	}
	static public function fq($key, $data='', $exp=-1){
		$handle = gc('cache.handle');
		if ('qccache'==$handle) return self::q($key,$data,$exp);
		static $instance = NULL;
		if (is_null($instance)) $instance = new qccache();
		if (''!=$data){
			if (is_null($data)) return $instance->delete($key);
			return $instance->set($key,$data,$exp);
		}else{
			return $instance->get($key);
		}
	}
	static public function add($key, $data='', $exp=0){
		$_this = self::factory();
		return $_this->add($key, $data, FALSE, $exp);
	}
	static public function del($key, $timeout=0){
		if (is_array($key)){
			foreach ($key as $val){
				self::del($val);
			}
		}else{
			$_this = self::factory();
			$_this->delete($key, $timeout);
			self::bind($key, NULL);
		}
		return TRUE;
	}
	static public function clear(){
		$_this = self::factory();
		$_this->flush();
	}
}
/*********************
Cookie class
*********************/
class cookie {
    static public function set($name, $value='', $expire=0,$domain='',$path='',$prefix=''){
        if (is_array($name)){
            if (NULL == $value){
                foreach ($name as $key=>$val){
                    self::set($key, $val);
                }
                return ;
            }else{
                foreach (array('value', 'expire', 'domain', 'path', 'prefix', 'name') as $item) {
                    if (isset($name[$item])) $$item = $name[$item];
                }
            }
        }
		$conf = gc('cookie');
		if ('#'=== $name{0}){
			$name = substr($name, 1);
			$func = $conf['encrypt_func'];
			if ($func AND function_exists($func)){
				$value = $func($value, 'ENCODE');
			}else{
				$value = base64_encode(serialize($value));
			}/**/
		}
		if (!$prefix) $prefix = $conf['prefix'];
        if (!$domain AND $conf['domain']) $domain = $conf['domain'];
        if (!$path AND $conf['path']) $path = $conf['path'];
        if (! is_numeric($expire)){
            $expire = time() - 86500;
            return setcookie($prefix.$name, '', $expire, $path, $domain, 0);
        }else{
            if ($expire > 0){
                $expire = time() + $expire;
            }else{
                $expire = 0;
            }
        }
        setcookie($prefix.$name, $value, $expire, $path, $domain, 0);
    }
    static public function get($name){
		$encrypt = ('#'===$name{0});
		if ($encrypt)
			$name = substr($name, 1);
        if (!self::is_set($name)) return NULL;
        $value = $_COOKIE[gc('cookie.prefix'). $name];
		if ($encrypt){
			$func = gc('cookie.encrypt_func');
			if ($func != NULL AND function_exists($func)){
				$value = $func($value);
			}else{
				$value = unserialize(base64_decode($value));
			}
		}
        return $value;
    }
    static public function del($name,$domain='',$path=''){
		$name = preg_replace('/^\#/is','',$name);
        if (is_string($name) AND FALSE !== strpos($name, ',')){
            $name = explode(',', $name);
        }
        if (is_array($name)){
            foreach ($name as $val){
                self::del($val);
            }
            return ;
        }
        if (!$path) $path = gc('cookie.path');
        self::set($name, '',0,$domain,$path);
    }
    static private function is_set($name){
        return isset($_COOKIE[gc('cookie.prefix'). $name]);
    }
}
/******************
IO Class
******************/
class io {
    // 创建目录
	static public function mkdir($dir, $mode = 0777){
        if (empty($dir)) return true;
		if (is_dir($dir) || @mkdir($dir,$mode)) return true;
		if (!self::mkdir(dirname($dir),$mode)) return false;
		return @mkdir($dir,$mode);
	}
    // 读取一个文件到字符串
	static public function read($path, $offset=0, $maxlen=NULL){
		if (empty($path) OR is_array($path) OR !is_file($path) OR !file_exists($path)) return FALSE;
		if (!is_null($maxlen)) return file_get_contents($path, FALSE, NULL, $offset, $maxlen);
		return file_get_contents($path, FALSE, NULL, $offset);
	}
    // 将字符串写入文件
	static public function qw($path, $data, $flag=0){
		self::mkdir(dirname($path));
		return file_put_contents($path, $data);
	}
    // Read File
    static public function read_file($path){
        ob_start();
        if (!($len = @readfile($path))) system_error('Read file ['.$path.'] error.');
        $buffer = ob_get_contents();
        @ob_end_clean();
        ob_end_flush();
        return $buffer;
    }
    // 将字符串附加到文件末尾
    static public function append($path, $data){
		return self::qw($path, $data, FILE_APPEND);
        return self::write($path, $data);
    }
    // 把字符串插入到文件头
    static public function insert($path, $data){
        return self::write($path, $data, 'r');
    }
    static public function write($path, $data, $mode='a'){
        self::mkdir(dirname($path));
        if (is_array($data)) $data = join("\r\n", $data);
        $data = trim($data). "\r\n";
        if (!is_writeable(dirname($path))) return FALSE;
		clearstatcache();
        if (FALSE!==($handle = fopen($path, $mode))){
            if (FALSE===fwrite($handle, $data)) return FALSE;
			fclose($handle);
			return TRUE;
        }
        return FALSE;
    }
	//获取文件总行数
	static public function sizeline($path){
		$line = 0;
		$fp = fopen($path , 'r');
		if($fp){
			while(stream_get_line($fp,8192,"\n")) $line++;
			fclose($fp);//关闭文件
		}
		return $line;
	}
	//获取文件行内容
	static public function getline($path, $start=0, $offset=1){
		$fp = fopen($path , 'r');
		$data = array();
		if($fp){
			$line = 0;
			while($rs = stream_get_line($fp,8192,"\n")){
				$line++;
				if ($line <= $start) continue;
				if ($line > $start+$offset) break;
				$data[] = $rs;
			}
			fclose($fp);//关闭文件
		}
		return $data;
	}
	static public function move($file, $new){
		self::mkdir(dirname($new));
		if (TRUE === ($rs = rename($file, $new))) self::delFile($file);
		return $rs;
	}
    /* Delete File */
    static public function delFile($file, $expath=''){
        if (empty($file) || !file_exists($file) || !is_file($file)) return false;
        $info = pathinfo($file);
		$size = filesize($file);
        if (in_array(strtolower($info['extension']), array('jpg','jpeg','bmp','gif','png','wbmp'))){
			$format = dirname($file).DS.basename($file, '.'.$info['extension']).'_*.*';
			foreach (glob($format) as $one){
				@unlink($one);
			}
		}
        @unlink($file);
        return $size;
    }
}
/*****************
Date Class
*****************/
class qcdate {
	var $time_offset; // 时区
	var $format = 'Y-m-d H:i:s'; // 时间格式
	var $usetime; // GMT(用于操作的时间戳)
	var $cookiename = 'time';
    var $time_zone = 'UTC';
	var $curtime;
    var $instance = Null;

	function factory(){
		Base::getInstance()->load->_assign_params($this, $GLOBALS['config']['date']);
        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set($this->time_zone);
			$this->time_offset = 0;
        }
        $this->curtime = $this->usetime = time();
		return $this;
	}
    static public function get($key){
        $_this = getInstance(__CLASS__, 'factory');
        return property_exists($_this, $key) ? $_this->$key : $key;
    }
    static public function set($key, $value){
        $_this = getInstance(__CLASS__, 'factory');
        $_this->$key = $value;
		return $value;
    }
	static public function gmdate($timestamp='', $format=''){
		return self::bind($timestamp, $format, 'gmdate');
	}
    static public function cdate($timestamp='', $format=''){
        return self::bind($timestamp, $format);
    }
	static public function string($timestamp='',$format='\s Second ago'){
        $_this = &getInstance('icdate');
		$format = explode('|',$format);
		$curtime = self::get('curtime');
		$diff = $curtime-$timestamp;
		if ($diff<60) return str_replace('\s',$diff,$format[0]);
		if (date('Ymd',$timestamp)==date('Ymd',$curtime)) return self::bind($timestamp,isset($format[1])?$format[1]:'H:i');
		if (date('Y',$timestamp)==date('Y',$curtime)) return self::bind($timestamp,isset($format[2])?$format[2]:'m-d H:i');
		return self::bind($timestamp,isset($format[3])?$format[3]:'Y-m-d H:i');
	}
    static private function bind($timestamp='', $format='', $fn='date'){
		if (!preg_match('/^\d+$/is', $timestamp)){
			$format = $timestamp;
			$timestamp = self::get('usetime');
		}
		empty($timestamp) && $timestamp = self::get('usetime');
		empty($format) && $format = self::get('format');
		if (FALSE!==strpos($format,'|')) return self::string($timestamp,$format);
        return $fn($format, $timestamp);
	}
	static public function timestamp($str=''){
		if (empty($str)) return self::get('curtime');
        if (is_float($str)) return $str;
		return strtotime($str)/*-self::get('time_offset')*3600*/;
	}
	static public function add($interval, $number=0, $z=FALSE){
		$data = getdate(self::get('usetime'));
		$offset = 0-self::get('time_offset');
		switch ($interval){
			case 'y': // 年
				$data['year'] += $number;
				if ($z) return mktime($offset,0,0,1,1, $data['year']);
			break;
			case 'q': // 季(3个月)
				$data['mon'] += ($number * 3);
				if ($z) return mktime($offset,0,0,$data['mon'], 1, $data['year']);
			break;
			case 'm': // 月
				$data['mon'] += $number;
				if ($z) return mktime($offset,0,0,$data['mon'], 1, $data['year']);
			break;
			case 'd': // 天
				$data['mday'] += $number;
				if ($z) return mktime($offset,0,0, $data['mon'], $data['mday'], $data['year']);
			break;
			case 'w': // 周(7天)
				$data['mday'] += ($number * 7);
				if ($z){
					return mktime($offset,0,0, $data['mon'], $data['mday']-($data['wday']==0?6:($data['wday']-1)), $data['year']);
				}
			break;
			case 'h': // 小时
				$data['hours'] += $number;
				if ($z) return mktime($data['hours'], 0,0, $data['mon'], $data['mday'], $data['year']);
			break;
			case 'n': // 分钟
				$data['minutes'] += $number;
				if ($z) return mktime($data['hours'], $data['minutes'], 0, $data['mon'], $data['mday'], $data['year']);
			break;
			case 's': $data['seconds'] += $number; // 秒
			break;
		}
		return mktime($data['hours'], $data['minutes'], $data['seconds'], $data['mon'], $data['mday'], $data['year']);
	}
	static public function diff($interval, $date){
		$diff = self::get('usetime') - self::totime($date);
		switch ($interval) {
			case 'w': return bcdiv($diff, 604800);
			case 'd': return bcdiv($diff, 86400);
			case 'h': return bcdiv($diff, 3600);
			case 'n': return bcdiv($diff, 60);
			case 's': return $diff;
		}
		return 0;
	}
}
class D extends qcdate { }
/******************
Input Class
******************/
class input{
	private $use_xss_clean		= FALSE;
	private $allow_get_array	= FALSE;

	public function factory(){
		$this->use_xss_clean	= (gc('input.global_xss_filtering') === TRUE) ? TRUE : FALSE;
		$this->allow_get_array	= (gc('input.enable_query_strings') === TRUE) ? TRUE : FALSE;
		$this->_sanitize_globals();
		return $this;
	}
	private function _sanitize_globals()
	{
		$protected = array('_SERVER', '_GET', '_POST', '_FILES', '_REQUEST', '_SESSION', '_ENV', 'GLOBALS', 'HTTP_RAW_POST_DATA');
		foreach (array($_GET, $_POST, $_COOKIE, $_SERVER, $_FILES, $_ENV, (isset($_SESSION) && is_array($_SESSION)) ? $_SESSION : array()) as $global){
			if ( ! is_array($global)){
				if ( ! in_array($global, $protected)) unset($GLOBALS[$global]);
			}
			else{
				foreach ($global as $key => $val){
					if ( ! in_array($key, $protected)) unset($GLOBALS[$key]);
					if (is_array($val)){
						foreach($val as $k => $v){
							if ( ! in_array($k, $protected)) unset($GLOBALS[$k]);
						}
					}
				}
			}
		}
		if ($this->allow_get_array == FALSE)
		{
			$_GET = array();
		}
		else
		{
			$_GET = $this->_clean_input_data($_GET);
		}
		$_POST = $this->_clean_input_data($_POST);
		unset($_COOKIE['$Version']);
		unset($_COOKIE['$Path']);
		unset($_COOKIE['$Domain']);
		$_COOKIE = $this->_clean_input_data($_COOKIE);
	}
	private function _clean_input_data($str)
	{
		if (is_array($str))
		{
			$new_array = array();
			foreach ($str as $key => $val)
			{
				$new_array[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
			}
			return $new_array;
		}

		// We strip slashes if magic quotes is on to keep things consistent
		if (get_magic_quotes_gpc())
		{
			$str = stripslashes($str);
		}

		// Should we filter the input data?
		if ($this->use_xss_clean === TRUE)
		{
			$str = $this->xss_clean($str);
		}

		// Standardize newlines
		if (strpos($str, "\r") !== FALSE)
		{
			$str = str_replace(array("\r\n", "\r"), "\n", $str);
		}

		return $str;
	}
	private function _clean_input_keys($str)
	{
		if ( ! preg_match("/^[a-z0-9:_\/-]+$/i", $str))
		{
			exit('Disallowed Key Characters.');
		}

		return $str;
	}
    public function xss_clean($str,$is_image=FALSE){
		$xss = &Base::getInstance()->load->libs('xss');
		if (!$xss) return ;
        return $xss->xss_clean($str, $is_image);
    }
}
class request{
	static private function _fetch_from_array(&$array, $index = '', $xss_clean = FALSE){
		$_this = getInstance('input','factory');
		if (empty($index)) return $xss_clean === TRUE ? array_walk($array, array($_this, 'xss_clean')) : $array;
		if (is_string($index)){
			if (FALSE === strpos($index, ',')){
				if ( ! isset($array[$index])) return NULL;
				return $xss_clean === TRUE ? $_this->xss_clean($array[$index]) : $array[$index];
			}
			$index = explode(',', $index);
		}
		if (is_array($index)){
			$value = array_intersect_key($array, array_flip($index));
			if (count($value) > 0) return $xss_clean === TRUE ? array_walk($value, array($_this, 'xss_clean')) : $value;
		}
		return NULL;
	}
	static public function req($index, $xss_clean = FALSE, $d=NULL) {
		if (!is_bool($xss_clean)){
			$d = $xss_clean;
			$xss_clean = FALSE;
		}
		if (is_string($index) AND FALSE!==strpos($index,',')){
			$value = array();
			$index = explode(',', $index);
			foreach ($index as $key){
				$value[$key] = self::req($key, $xss_clean);
				if (is_null($value[$key])) unset($value[$key]);
			}
			return $value;
		}
		if (is_null($value = self::post($index, $xss_clean))){
			if (is_null($value = self::get($index, $xss_clean))) return $d;
		}
		return $value;
	}
	static public function get($index = '', $xss_clean = FALSE){
		return self::_fetch_from_array($_GET, $index, $xss_clean);
	}
	static public function post($index = '', $xss_clean = FALSE){
		return self::_fetch_from_array($_POST, $index, $xss_clean);
	}
	static public function server($index = '', $xss_clean = FALSE){
		return self::_fetch_from_array($_SERVER, strtoupper($index), $xss_clean);
	}
}
class response{
	static private $ip_address = FALSE;
	static private $user_agent = FALSE;
	static public function ip(){
		if (self::$ip_address) return self::$ip_address;
		$ip = request::server('REMOTE_ADDR');
		if (preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', request::server('HTTP_CLIENT_IP'))) {
			$ip = request::server('HTTP_CLIENT_IP');
		} elseif(preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', request::server('HTTP_X_FORWARDED_FOR'), $matches)) {
			foreach ($matches[0] AS $xip) {
				if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
					$ip = $xip;
					break;
				}
			}
		}
		return self::$ip_address = $ip;
	}
	static public function user_agent(){
		if (self::$user_agent) return self::$user_agent;
		self::$user_agent = request::server('HTTP_USER_AGENT');
		return self::$user_agent;
	}
	static public function dump($var, $echo=true,$label=null, $strict=true){
		$charset = gc('base.charset','utf-8');
		$label = ($label===null) ? '' : rtrim($label) . ' ';
		if(!$strict) {
			if (ini_get('html_errors')) {
				$output = print_r($var, true);
				$output = "<pre>".$label.htmlspecialchars($output, ENT_QUOTES, $charset)."</pre>";
			} else {
				$output = $label . " : " . print_r($var, true);
			}
		}else {
			ob_start();
			var_dump($var);
			$output = ob_get_clean();
			if(!extension_loaded('xdebug')) {
				$output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
				$output = '<pre>'
						. $label
						. htmlspecialchars($output, ENT_QUOTES, $charset)
						. '</pre>';
			}
		}
		if ($echo) {
			header("Content-Type:text/html; charset=". $charset);
			echo($output);
			return null;
		}else {
			return $output;
		}
	}
	static public function cprint($code=NULL, $message='', $data=array()){
		if (is_array($code)){
			$data = $code;
			$code = 1;
		}elseif (is_numeric($code)){
		}elseif (is_string($code)){
			$message = $code;
			$code = NULL;
		}
		$exit = !empty($data);
		//if (!$exit) $data = Base::getInstance()->vars;
		$data = array(
			'code' => $code,
			'message' => $message,
			'body' => $data,
		);
		if ('json' === $_ENV['ajaxreq']){
			echo json_encode($data);
			exit;
		} elseif ('xml' == $_ENV['ajaxreq']) {
			echo Base::getInstance()->load->libs('xml')->build($data);
		}else{
			$file = Base::getInstance()->tpl->view('show_message');
			if (!$file) die($message);
			return require $file;
		}
		if ($exit) exit();
	}
	static public function header($code = 200, $text = '')
	{
		$stati = array(
			200	=> 'OK',
			201	=> 'Created',
			202	=> 'Accepted',
			203	=> 'Non-Authoritative Information',
			204	=> 'No Content',
			205	=> 'Reset Content',
			206	=> 'Partial Content',

			300	=> 'Multiple Choices',
			301	=> 'Moved Permanently',
			302	=> 'Found',
			304	=> 'Not Modified',
			305	=> 'Use Proxy',
			307	=> 'Temporary Redirect',

			400	=> 'Bad Request',
			401	=> 'Unauthorized',
			403	=> 'Forbidden',
			404	=> 'Not Found',
			405	=> 'Method Not Allowed',
			406	=> 'Not Acceptable',
			407	=> 'Proxy Authentication Required',
			408	=> 'Request Timeout',
			409	=> 'Conflict',
			410	=> 'Gone',
			411	=> 'Length Required',
			412	=> 'Precondition Failed',
			413	=> 'Request Entity Too Large',
			414	=> 'Request-URI Too Long',
			415	=> 'Unsupported Media Type',
			416	=> 'Requested Range Not Satisfiable',
			417	=> 'Expectation Failed',

			500	=> 'Internal Server Error',
			501	=> 'Not Implemented',
			502	=> 'Bad Gateway',
			503	=> 'Service Unavailable',
			504	=> 'Gateway Timeout',
			505	=> 'HTTP Version Not Supported'
		);

		if ($code == '' OR ! is_numeric($code))
		{
			show_error('Status codes must be numeric', 500);
		}

		if (isset($stati[$code]) AND $text == '')
		{
			$text = $stati[$code];
		}

		if ($text == '')
		{
			show_error('No status text available.  Please check your status code number or supply your own message text.', 500);
		}

		$server_protocol = (isset($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : FALSE;

		if (substr(php_sapi_name(), 0, 3) == 'cgi')
		{
			header("Status: {$code} {$text}", TRUE);
		}
		elseif ($server_protocol == 'HTTP/1.1' OR $server_protocol == 'HTTP/1.0')
		{
			header($server_protocol." {$code} {$text}", TRUE, $code);
		}
		else
		{
			header("HTTP/1.1 {$code} {$text}", TRUE, $code);
		}
	}
	static public function get_header_info($header='',$echo=true)
    {
        ob_start();
        $headers   = getallheaders();
        if(!empty($header)) {
            $info = $headers[$header];
            echo($header.':'.$info."\n"); ;
        }else {
            foreach($headers as $key=>$val) {
                echo("$key:$val\n");
            }
        }
        $output = ob_get_clean();
        if ($echo) {
            echo (nl2br($output));
        }else {
            return $output;
        }

    }
}
class Debug{
	static $debug = array();
	static $points = array();

	static public function start(){
        self::$debug = array(
            'use_memory' => memory_get_usage(),
        );
	}
	static public function end($print=TRUE){
		if ('debug'!=gc('base.run_mode')) return ;
		self::$debug['use_memory'] = memory_get_usage() - self::$debug['use_memory'];
		if (!self::$debug['points']){
			self::$debug += self::prbm();
			if ($_ENV['db'] AND class_exists('DB', FALSE)){
				$db = Db::getInstance();
				self::$debug['query_count'] = $db->dbinfo['query_count'];
				self::$debug['sql'] = $db->dbinfo['sql'];
			}
		}
		if (!$print) return self::$debug;
		echo '<!--';
		print_r(self::$debug);
		echo '//-->';
	}
	static public function setbm($point){
		self::$points[$point] = microtime(TRUE);
	}
	static public function prbm(){
		$pr = array(
			'points'=> array(),
			'usetime' => 0,
		);
		$last = 0;
		foreach (self::$points as $key=>$val){
			if (!$last) $last = $val;
			$pr['points'][$key] = $val. ','. ($val-$last);
			$pr['usetime'] += ($val-$last);
			$last = $val;
		}
		return $pr;
	}
}
class QcExceptions extends Exception {
	private $ob_level;
	private $levels = array(
						E_ERROR				=>	'Error',
						E_WARNING			=>	'Warning',
						E_PARSE				=>	'Parsing Error',
						E_NOTICE			=>	'Notice',
						E_CORE_ERROR		=>	'Core Error',
						E_CORE_WARNING		=>	'Core Warning',
						E_COMPILE_ERROR		=>	'Compile Error',
						E_COMPILE_WARNING	=>	'Compile Warning',
						E_USER_ERROR		=>	'User Error',
						E_USER_WARNING		=>	'User Warning',
						E_USER_NOTICE		=>	'User Notice',
						E_STRICT			=>	'Runtime Notice'
					);
	public function log_exception($severity, $message, $filepath, $line)
	{
		$severity = ( ! isset($this->levels[$severity])) ? $severity : $this->levels[$severity];
		logs('Severity: '.$severity.'  --> '.$message. ' '.$filepath.' '.$line, TRUE);
	}
	/**
	 * 404 Page Not Found Handler
	 *
	 * @access	private
	 * @param	string	the page
	 * @param 	bool	log error yes/no
	 * @return	string
	 */
	public function show_404($page = '', $log_error = TRUE){
		$heading = "404 Page Not Found";
		$message = "The page you requested was not found.";
		// By default we log this, but allow a dev to skip it
		if ($log_error)
		{
			logs('404 Page Not Found --> '.$page);
		}
		self::show_error($heading, $message, 404);
		exit;
	}

	// --------------------------------------------------------------------

	/**
	 * General Error Page
	 */
	public function show_error($heading, $message, $status_code = 500)
	{
		response::header($status_code);
		if ($_ENV['ajaxreq']){
			response::cprint(0, $message);
			exit;
		}
		$message = '<p>'.implode('</p><p>', ( ! is_array($message)) ? array($message) : $message).'</p>';
		if (!is_file($file = gc('tpl.viewpath'). 'public/sys_error.php')){
			if (!is_file($file = SYS_PATH. 'public/sys_error.php')){
				debug_print_backtrace();
				die($message);
			}
		}
		if (ob_get_level() > $this->ob_level + 1)
		{
			ob_end_flush();
		}
		$traces = debug_backtrace();
		include($file);
		unset($file);
	}

	public function show_php_error($severity, $message, $filepath, $line)
	{
		$severity = ( ! isset($this->levels[$severity])) ? $severity : $this->levels[$severity];

		$filepath = str_replace("\\", "/", $filepath);

		// For safety reasons we do not show the full file path
		if (FALSE !== strpos($filepath, '/'))
		{
			$x = explode('/', $filepath);
			$filepath = $x[count($x)-2].'/'.end($x);
		}
		if (!is_file($file = gc('tpl.viewpath'). 'public/error_php.php')) die();

		if (ob_get_level() > $this->ob_level + 1)
		{
			ob_end_flush();
		}
		ob_start();
		include($file);
		$buffer = ob_get_contents();
		ob_end_clean();
		unset($file);
		echo $buffer;
	}
}
// Include session
require dirname(__FILE__). DS. 'session.php';
