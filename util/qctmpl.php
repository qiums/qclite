<?php if ( ! defined('ROOT')) exit('No direct script access allowed');
/**
 * Description of qctmpl
 *
 * @author Amul
 */
class qctmpl {
	static private $theme = '';

	static public function view($name, $cachepath='', $return=0){
		if (is_array($name)){
			foreach ($name as $tplfile){
				if (FALSE!==($cache=self::view($tplfile, $cachepath, 2))) break;
			}
			if (!$cache) show_error("Not found template file \"{$tplfile}\".");
			return $cache;
		}
		if ($_ENV['RUN_COMP']) $cachepath = CACHE_PATH. 'views'.DS. 'cp'. DS;
		$file = self::template($name, $cachepath);
		if (2===$return) return $file;
		if (!$file) show_error("Not found template file \"{$tplfile}[{$name}]\".");
		!defined('IN_TEMPLATE') AND define('IN_TEMPLATE', TRUE);
		if (class_exists('Debug')) Debug::setbm('parse_template');
		return $file;
	}
	static public function template($file, $cachepath=''){
		$theme = self::$theme = gc('tpl.theme', '');
		if (!gc('env.skin_path')){
			gc('env.skin_path', gc('env.webroot').'static/', TRUE);
		}
		if (!gc('env.theme_path')){
			$path = trim($_ENV['RUN_DIRECTORY'] ? $_ENV['RUN_DIRECTORY'] : "themes/{$theme}", '/');
			gc('env.theme_path', gc('env.skin_path'). "{$path}/", TRUE);
		}
		if (is_numeric($cachepath)) $cachepath = '';
		if (!$cachepath) $cachepath = gc('tpl.cachepath', CACHE_PATH. 'views'. DS);
		if ($_ENV['RUN_DIRECTORY']){
			$cachepath .= $_ENV['RUN_DIRECTORY']. DS;
		}else{
			if (FALSE===strpos($file, '/')){
				$cachepath .= ltrim($theme.'_');
			}else{
				$cachepath .= dirname($file). DS. ltrim($theme. '_');
			}
		}
		$cache = $cachepath .basename($file).'.php';
		$file = self::get_file($file);
		if (!$file) return FALSE;
		if (!is_file($cache) OR filemtime($file)>filemtime($cache)){
			$engine = gc('tpl.engine');
			if (!import("extend.template.{$engine}")) $engine = 'TplTags';
			$content = call_user_func_array(array($engine, 'build'), array(file_get_contents($file)));
			io::qw($cache, $content);
		}
		return $cache;
	}
	static public function get_file($file){
		$file = $file. gc('tpl.extension');
		if (is_file($file)) return $file;
		$rootview = $viewdir = gc('tpl.viewpath');
		if (!is_dir($rootview)) $rootview = $viewdir = THIS_PATH. 'views'. DS;
		if (0===strpos($file, 'public/')){
			if (is_file($rootview. $file)) return $rootview. $file;
			$file = str_replace('public/', '', $file);
		}
		if ($_ENV['CORE_PATH']) $viewdir = $_ENV['CORE_PATH']. 'views'. DS;
		$fullview = rtrim($rootview. self::$theme.DS, DS). DS;
		if ($_ENV['RUN_DIRECTORY']){
			$rootview .= $_ENV['RUN_DIRECTORY']. DS;
			$fullview .= $_ENV['RUN_DIRECTORY']. DS;
		}
		$ctrl = gc('env.controller');
		foreach (array_unique(array(
			$viewdir. $file,
			$fullview. $ctrl. DS. $file,
			$fullview. $ctrl. '_'. $file,
			$fullview. $file,
			$rootview. $file,
			$rootview. 'public'.DS.$file,
			dirname($fullview). DS. $file,
			dirname($rootview). DS. 'public'. DS. $file,
			dirname($rootview). DS. $file,
			)) as $tplfile){//echo $tplfile.'<br/>';
			if (file_exists($tplfile) AND is_file($tplfile)) break;
		}
		if (!is_file($tplfile)) return FALSE;
		return $tplfile;
	}
}

class TplTags {
	static public function build($cont){
		return self::parse($cont);
	}
	static protected function parse($cont){
        if (empty($cont)) return '';
        $var_regexp = "((\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(\[[a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+\])*)";
		$const_regexp = "([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)";
		$cont = preg_replace("/\\\$\{$const_regexp\}/s", '###\\1***', $cont);
		$cont = str_replace(array('{{', '}}'), array('@@@', '%%%'), $cont);
        $cont = preg_replace("/(src|href|action)=\"(.[^\"]*)\"/ise", "self::parse_path('\\1', '\\2')", $cont);
        $cont = preg_replace("/(background)=\"(.[^\{\"\<]*)\"/ise", "self::parse_path('background', '\\2')", $cont);
		$cont = preg_replace('/<form[^>]+?method=\"post\"(.*?)>/is', '\\0<input type="hidden" name="token" value="{$config[env][token]}" />', $cont);
        $cont = preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $cont);
		$cont = preg_replace("/[\n\r\t]*\{(".$var_regexp."\s*\=\s*)*include\s+\'(.+?)\'\}[\n\r\t]*/ies", "self::parse_include('\\5', '\\3')", $cont);
        $cont = str_replace("{LF}", "<?=\"\\n\"?>", $cont);
		$cont = preg_replace("/\{(\\\$[a-zA-Z0-9_\[\]\'\"\$\.\x7f-\xff]+)\}/s", "<?=\\1;?>", $cont);
		$cont = preg_replace("/$var_regexp/es", "self::parse_quote('\\1')", $cont);
		$cont = preg_replace("/\<\?\=\<\?\=$var_regexp;\?\>;\?\>/es", "self::parse_quote('\\1')", $cont);
		$cont = preg_replace("/([\n\r\t]*)\{elseif\s+(.+?)\}([\n\r\t]*)/ies", "self::stripv_tags('\\1<?php elseif(\\2): ?>\\3','')", $cont);
		$cont = preg_replace("/([\n\r\t]*)\{else\}([\n\r\t]*)/is", "\\1<?php else: ?>\\2", $cont);
        /* Loop Parse */
        for($i = 0; $i < 5; $i++) {
			$cont = preg_replace("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\}[\n\r]*(.+?)[\n\r]*\{\/loop\}[\n\r\t]*/ies",
					"self::stripv_tags('<?php \\2_index=0; if(isset(\\1) && is_array(\\1)): foreach(\\1 as \\2): \\2_index++; ?>','\\3<?php endforeach; endif; ?>')", $cont);
			$cont = preg_replace("/[\n\r\t]*\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}[\n\r\t]*(.+?)[\n\r\t]*\{\/loop\}[\n\r\t]*/ies",
					"self::stripv_tags('<?php \\3_index=0; if(isset(\\1) && is_array(\\1)): foreach(\\1 as \\2 => \\3): \\3_index++; ?>','\\4<?php endforeach; endif; ?>')", $cont);
			$cont = preg_replace("/([\n\r\t]*)\{if\s+(.+?)\}([\n\r]*)(.+?)([\n\r]*)\{\/if\}([\n\r\t]*)/ies",
					"self::stripv_tags('\\1<?php if(\\2): ?>\\3','\\4\\5<?php endif; ?>\\6')", $cont);
		}
        /* Template Tag Parse */
		$cont = preg_replace("/[\n\r\t]*\{template\s+([a-z0-9_]+)\}[\n\r\t]*/ies", "self::parse_template('\\1')", $cont);
		$cont = preg_replace("/[\n\r\t]*\{template\s+(.+?)\}[\n\r\t]*/ies", "self::parse_template('\\1')", $cont);
		$cont = preg_replace("/[\n\r\t]*\{eval\s+(.+?)\}[\n\r\t]*/ies", "self::stripv_tags('<?php \\1 ?>','')", $cont);
		$cont = preg_replace("/[\n\r\t]*\{echo\s+(.+?)\}[\n\r\t]*/ies", "self::stripv_tags('<?=\\1; ?>','')", $cont);
        $cont = preg_replace("/\"(http)?[\w\.\/:]+\?[^\"]+?&[^\"]+?\"/e", "self::parse_transamp('\\0')", $cont);
        $cont = preg_replace("/\{$const_regexp\}/s", "<?php echo \\1;?>", $cont);
		$cont = str_replace('<?=', '<?php echo ', $cont);
        $cont = preg_replace("/\{(\w+)\s+(.[^=]+?)\}/ies", "self::parse_func('\\1','\\2', TRUE)", $cont);
		$cont = preg_replace("/[\n\r\t]*\{:(\w+)\s*(.[^\}]+?)\}/ies", "self::parse_func('\\1','\\2')", $cont);
		$cont = str_replace('&amp;', '&', $cont);
		$cont = preg_replace("/(\s*)\?\>[\n\r\s]*\<\?php(\s*)/s", " ", $cont);
		$cont = str_replace(array('###','***','@@@','%%%'), array('${','}','{{','}}'), $cont);
        return $cont;
    }
	static protected function parse_func($fn, $args, $bool=FALSE){
		if (!function_exists($fn)) return '';
		$args = self::stripv_tags(self::parse_quote($args, 2), $bool);
		if (FALSE === strpos($args, '(')) $args = "(\"{$args}\")";
		return '<?php echo '.$fn. $args . '; ?>';
	}
    static protected function parse_path($attr, $path){
		if ('{'== $path{0} OR '[' == $path{0}
			OR FALSE!==strpos($path,'\'')
			OR 'javascript'==substr($path,0,10)
			OR preg_match('/^(#|http\:\/\/|mailto\:|ftp\:\/\/|file\:|\{echo|\{url)/i', $path)
			) return $attr.'="'.$path.'"';
		$re = preg_match('/\.(gif|jpg|jpeg|png|bmp|css|swf|ico|js)$/i',$path);
		if ('/'===$path{0}){
			$path = '{echo '. ($re ? '$config[env][skin_path]' : '$config[env][webroot]'). '}'. ltrim($path, '/');
		}elseif (is_numeric($path{0})){
			$path = preg_replace('/^(\d+)\//is', '{echo $config[site][skin_path][\\1]}', $path);
		}elseif ($path == '' || $path == '\\' || '.'===$path{0}){
			$path = '{echo $config[env][webpath]}'. ltrim(ltrim($path, '.'), '/');
		}else{
			if ($re){
				$path = '{$config[env][theme_path]}'. ltrim($path, '/');
			}else{
                $path = '{echo url("'. $path. '")}';
            }
		}
		if (empty($attr)) return $path;
        if ('background' == $attr) return 'background="'. $path. '"';
		return $attr.'="'.$path.'"';
    }
	static protected function parse_quote($var, $all=1){
		$var = str_replace("\\\"", "\"", preg_replace("/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s", "['\\1']", $var));
		if (2===$all) return $var;
		if (!$all) return '{'.$var.'}';
		return '<?='.$var.';?>';
	}
    static protected function parse_template($name){
		if (FALSE===qctmpl::get_file($name)) return '';
		if (FALSE===strpos($name, '$')) $name = "'{$name}'";
		return '{eval include qctmpl::template('.$name.');}';
	}
    static protected function stripv_tags($expr, $statement='') {
		$expr = str_replace("\\\"", "\"", preg_replace("/\<\?(php)?\s?(echo|\=)\s?(\\\$.+?);\?\>/s", TRUE===$statement?"{\\3}" : "\\3", $expr));
		if (TRUE===$statement) $statement = '';
		$statement = str_replace("\\\"", "\"", $statement);
		return $expr.$statement;
	}
	static protected function parse_transamp($str) {
		$str = str_replace('&', '&amp;', $str);
		$str = str_replace('&amp;amp;', '&amp;', $str);
		$str = str_replace('\"', '"', $str);
		return $str;
	}
	static protected function replace_vars($str){
		$str = preg_replace('/#(.[^#]*?)#/ies',"self::parse_quote('$\\1', 0)",$str);
		$str = str_replace("\\\"", "\"", preg_replace("/\<\?(php)?\s?(echo|\=)\s?(\\\$.+?);\?\>/s", '{\\3}', $str));
		return preg_replace('/%(.[^%]*?)%/is','".\\1."', $str);
	}
}