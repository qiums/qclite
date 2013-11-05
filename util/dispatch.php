<?php if ( ! defined('ROOT')) exit('No direct script access allowed');

class dispatch{
	static function run(){
		$config = gc('dispatch');
		foreach ($config as $key=>$val){
			if (FALSE===strpos($key, '_trigger')) continue;
			$get[$key] = isset($_GET[$val]) ? $_GET[$val] : NULL;
		}
		if ($get['pathinfo_trigger']){
			$_SERVER['PATH_INFO'] = $get['pathinfo_trigger'];
		}
		if (php_sapi_name() == 'cli' or defined('STDIN')){
			$_SERVER['PATH_INFO'] = array_slice($_SERVER['argv'], 1);
		}
		if ('compat' === $config['uri_protocol']){
			$phpfile = gc('env.webroot'). SELF. '?'. $get['pathinfo_trigger']. '=';
		}elseif ('rewrite' === $config['uri_protocol']){
			$phpfile = gc('env.webroot');
		}else{
			$phpfile = gc('env.webroot'). SELF;
		}
		$_ENV['phpfile'] = $phpfile;
		if ($config['sub_domain']){
			$sub = $config['sub_domain'];
			if (isset($sub[$_SERVER['HTTP_HOST']])){
				$rule = $sub[$_SERVER['HTTP_HOST']];
			}else{
				$subdomain = strtolower(substr($_SERVER['HTTP_HOST'],0,strpos($_SERVER['HTTP_HOST'],'.')));
				if (isset($sub[$subdomain])){
					$rule = $sub[$subdomain];
				}elseif ('*'===$sub){
					$rule = array($subdomain);
				}
			}
			if ($rule){
				$get['group_trigger'] = $rule[0];
				if(isset($rule[1])) { // 传入参数
                    parse_str($rule[1], $parms);
                    $_GET = array_merge($_GET, $parms);
                }
			}
		}
		unset($get);
		if (!isset($_SERVER['PATH_INFO'])){
			$types = array('ORIG_PATH_INFO','REDIRECT_PATH_INFO','REDIRECT_URL');
			foreach ($types as $type){
                if(0===strpos($type,':')) {// 支持函数判断
                    $_SERVER['PATH_INFO'] =   call_user_func(substr($type,1));
                    break;
                }elseif(!empty($_SERVER[$type])) {
                    $_SERVER['PATH_INFO'] = (0 === strpos($_SERVER[$type],$_SERVER['SCRIPT_NAME']))?
                        substr($_SERVER[$type], strlen($_SERVER['SCRIPT_NAME']))   :  $_SERVER[$type];
                    break;
                }
            }
		}
		$depr = gc('dispatch.url_depr', '/');
		$_SERVER['PATH_INFO'] = trim($_SERVER['PATH_INFO'], '/');
		if(!empty($_SERVER['PATH_INFO'])) {
            $part =  pathinfo($_SERVER['PATH_INFO']);
            $_ENV['extension'] = isset($part['extension'])?strtolower($part['extension']):'';
            if($config['url_suffix']) {
                $_SERVER['PATH_INFO'] = preg_replace('/\.('.trim($config['url_suffix'],'.').')$/i', '', $_SERVER['PATH_INFO']);
            }elseif($_ENV['extension']) {
                $_SERVER['PATH_INFO'] = preg_replace('/.'.$_ENV['extension'].'$/i','',$_SERVER['PATH_INFO']);
            }
			self::parse_routes();
			if (!$_SERVER['PATH_INFO']) return ;
			$_ENV['PATH_INFO'] = $_SERVER['PATH_INFO'];
			$paths = explode($depr, trim($_SERVER['PATH_INFO'],'/'));
			if ($paths[0]){
				if ($config['rename_group']){
					if (array_search($paths[0], $config['rename_group'])) show_error('The group is enable alias.');
					if (isset($config['rename_group'][$paths[0]]))
						$paths[0] = $config['rename_group'][$paths[0]];
				}
				if ($paths[0]==='cp'){
					array_shift($paths);
					$_ENV['RUN_COMP'] = $paths[0];
					$_ENV['CORE_PATH'] = COMP_PATH. $paths[0];
				}elseif (is_dir($dir = GROUP_PATH. $paths[0])){
					$_ENV['RUN_DIRECTORY'] = $paths[0];
					$_ENV['CORE_PATH'] = $dir;
					self::set('group', array_shift($paths));
				}elseif (is_dir($dir = CORE_PATH. 'controllers'. DS. $paths[0])){
					$_ENV['RUN_DIRECTORY'] = $paths[0];
					$_ENV['CORE_PATH'] = $dir;
					self::set('directory', array_shift($paths));
				}
				if (!$_ENV['CORE_PATH']){
					if (is_dir($dir = COMP_PATH. $paths[0])){
						$_ENV['RUN_COMP'] = $paths[0];
						$_ENV['CORE_PATH'] = $dir;
					}
				}
				if ($_ENV['CORE_PATH']){
					$_ENV['CORE_PATH'] .= DS;
				}
				self::set('controller', array_shift($paths));
				self::set('function', array_shift($paths));
			}
			if ($paths) $_GET = array_merge(str2array($paths), $_GET);
		}else{
			self::set('controller');
			self::set('function');
		}
	}
	static private function set($trigger, $value=NULL){
		if (!$value) $value = gc("dispatch.default_{$trigger}");
		$key = gc("dispatch.{$trigger}_trigger");
		if (!isset($_GET[$key])){
			$_GET[$key] = $value;
		}
		gc("env.{$trigger}", (string)$value, TRUE);
	}
	static private function parse_routes()
	{
		if (!is_file($route_file = import('this.routes', TRUE))){
			if (!is_file($route_file = import('data.config.routes', TRUE))) return ;
		}
		require $route_file;
		unset($route_file);
		if (!isset($routes)) return ;
		$uri = $_SERVER['PATH_INFO'];
		if (isset($routes[$uri])){
			return $_SERVER['PATH_INFO'] = $routes[$uri];
		}
		foreach ($routes as $key => $val)
		{
			$key = str_replace(':any', '.+', str_replace(':num', '[0-9]+', $key));

			if (preg_match('#^'.$key.'$#', $uri))
			{
				if (strpos($val, '$') !== FALSE AND strpos($key, '(') !== FALSE)
				{
					$val = preg_replace('#^'.$key.'$#', $val, $uri);
				}
				if (FALSE !== ($index = strpos($val, '?'))){
					parse_str(substr($val, $index+1), $params);
					$_GET = array_merge($_GET, $params);
					$val = substr($val, 0, $index);
				}
				return $_SERVER['PATH_INFO'] = $val;
			}
		}
		return false;
	}
	static public function url($paths, $args=array()){
		if(preg_match('/^(http|ftp|https)\:\/\//', $paths)) return $paths;
		$dc = gc('dispatch');
		$use_pathinfo = '#pathinfo#' === $paths;
		if ($use_pathinfo){
			$paths = $_ENV['PATH_INFO'];
		}elseif ('#current#' === $paths){
			$paths = str_replace('//', '/', $_ENV['RUN_DIRECTORY'].'/'.gc('env.controller').'/'.gc('env.function'));
			if ($_GET) $paths .= '?'. http_build_query($_GET);
		}elseif ('#back#' === $paths){
			$paths = request::req('qcurl');
			if (!$paths) $paths = @$_SERVER['HTTP_REFERER'];
			if (!$paths AND is_string($args)) $paths = url($args);
			if (!$paths) $paths = url('/');
			return $paths;
		}elseif ('/' === $paths) return gc('env.webroot');
		$url = parse_url($paths);
		$paths = $url['path'];
		$tmp = explode('/', $paths);
		if (!$use_pathinfo AND $tmp[0]){
			if (is_dir(GROUP_PATH. $tmp[0])
				OR is_dir(CORE_PATH. 'controllers'. DS. $tmp[0])){
				if ($dc['rename_group'] && FALSE!==($key = array_search($tmp[0], $dc['rename_group'])))
					$tmp[0] = $key;
				if (!$tmp[1]) $tmp[1] = $dc['default_controller'];
				if (!$tmp[2]) $tmp[2] = $dc['default_function'];
			}else{
				if (!$tmp[1]) $tmp[1] = $dc['default_function'];
			}
		}
		$depr = gc('dispatch.url_depr', '/');
		$paths = join($depr, $tmp);
		if (is_string($args)){
			if (FALSE!==strpos($args, '=')){
				parse_str($args, $args);
			}else{
				$args = str2array($args, '', $depr);
			}
		}
		if ($url['query']){
			parse_str($url['query'], $query);
			$args = array_merge($query, $args);
			unset($query);
		}
		foreach ($args as $key=>$val){
			if (FALSE!==strpos($val, $depr) OR FALSE!==strpos($val, '.')) continue;
			if (!is_null($val) AND ''!==$val){
				if (strpos($paths, "{$depr}{$key}{$depr}")){
					$paths = preg_replace("/{$key}\\{$depr}(\w[^\\{$depr}]+)/is", "{$key}{$depr}{$val}", $paths);
				}else{
					$paths .= "{$depr}{$key}{$depr}{$val}";
				}
			}
			unset($args[$key]);
		}
		$ex = "({$dc['default_controller']})?\\{$depr}{$dc['default_action']}$";
		$paths = trim(preg_replace('/'.$ex.'/is', '', $paths), $depr);
		$phpfile = $_ENV['phpfile'];
		if (!$paths){
			if (FALSE !== ($index = strpos($phpfile, '?'))) $phpfile = substr($phpfile, 0, $index);
		}
		if ($args){
			$paths .= (FALSE!==strpos($phpfile, '?') ? '&' : '?'). http_build_query($args);
		}
		if ($url['fragment']) $paths .= "#{$url['fragment']}";
		unset($dc, $tmp, $args, $url);
		return rtrim($phpfile, '/'). $depr. $paths;
	}
}
?>
