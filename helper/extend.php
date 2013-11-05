<?php if ( ! defined('ROOT')) exit('No direct script access allowed');

function AFE($array1, $array2){
	return array_flip_extend($array1, $array2);
}
function AIKE($array1, $array2){
	return array_intersect_key_extend($array1, $array2);
}
function AIV($array1, $array2, $pre=', '){
	return array_intersect_value($array1, $array2, $pre);
}
function QCS($data, $id_name='id', $parent_name='pid'){
	return qcsort($data, $id_name, $parent_name);
}
function QCAS($data, $new=array()){
	return qcarray_sort($data, $new);
}
function GFS($string, $suffix, $start=0){
	return get_from_string($string, $suffix, $start);
}
function array_flip_extend($array1, $array2){
	foreach ($array1 as $key=>$value){
		if (empty($value) AND !empty($array2[$key])) $array1[$key] = $array2[$key];
	}
	return $array1;
}
function array_intersect_key_extend($array1, $array2){
	if (is_numeric(join('',array_keys($array1)))) $array1 = array_flip($array1);//print_r($array1);exit;
	foreach ($array1 as $key=>$value){
		unset($array1[$key]);
		if (!empty($array2[$key])) $array1[$key] = $array2[$key];
	}
	return $array1;
}
function array_intersect_value($input, $array, $pre=', '){
	if (''===$input) return '';
	if (!is_array($input)) $input = explode(',', $input);
	$input = array_flip($input);
	return join($pre, array_intersect_key($array, $input));
}
function qcsort($data, $id_name='id', $parent_name='pid'){
	//定义目标数组
	$d = array();
	//定义索引数组，用于记录节点在目标数组的位置
	$ind = array();
	$nextar = array(); // 保存末定义的父节点的节点.
	//$tmp = current($data);
	foreach($data as $v) {
		$v['child'] = array(); //给每个节点附加一个child项
		$pid = $v[$parent_name];
		if($pid == 0) {
			$i = count($d);
			$d[$i] = $v;
			$ind[$v[$id_name]] =&$d[$i]; //关键! 指向目标数组
		}else {
			if(!is_array($ind[$pid])) {
				$nextar[] = $v;
				continue;
			}
			$i = count($ind[$pid]['child']);
			$ind[$pid]['child'][$i] = $v;
			$ind[$v[$id_name]] =& $ind[$pid]['child'][$i];
		}
	}
	return $d;
}
function qcarray_sort($data, $new=array()){
	if (!is_array($data)) return $data;
	foreach ($data as $row){
		$child = $row['child'];
		unset($row['child']);
		$new[] = $row;//die;
		if ($child){
			$new = array_merge($new, qcsort($child));
		}
	}
	return $new;
}
function formatSize($size){
	$size = floatval($size);
	if ($size>0) {
		$j = 0;
		$ext = array(" bytes"," Kb"," Mb"," Gb"," Tb");
		while ($size >= pow(1024,$j)) ++$j;
		return round($size / pow(1024,$j-1) * 100) / 100 . $ext[$j-1];
	} else return "0 Kb";
}
function get_from_string($string, $suffix=',', $start=0){
	if (!is_array($string)) $string = explode ($suffix, $string);
	if ('end'===$start) $start = count($string)-1;
	return isset($string[$start]) ? $string[$start] : NULL;
}
//在$haystack中查找$needle元素
function cstrpos($haystack, $needle){
	if (!is_scalar($haystack) OR !is_scalar($needle)) return FALSE;
	$haystack = ",{$haystack},";
	$needle = ",{$needle},";
	return strpos($haystack, $needle);
}
// 在$string中查找数组$arr中的元素
function qstrpos($string, &$arr, $returnvalue = FALSE) {
	if(empty($string)) return FALSE;
	foreach((array)$arr as $v) {
		if(strpos($string, $v) !== FALSE) {
			$return = $returnvalue ? $v : TRUE;
			return $return;
		}
	}
	return FALSE;
}
//
function qstrstr($haystack, $needle=',', $before_needle=FALSE){
	$index = strpos($haystack, $needle);
	if ($index===FALSE) return $haystack;
	if ('5.3.0' <= gc('env.phpver')) return ltrim(strstr($haystack, $needle, $before_needle), $needle);
	if (!$before_needle) return ltrim(strstr($haystack, $needle), $needle);
	return substr($haystack, 0, $index);
}
function auto_charset($cont, $from='utf-8', $to=''){
	if(empty($to)) $to	= gc('base.charset');
	if(strtoupper($from) === strtoupper($to) || empty($cont) || (is_scalar($cont) && !is_string($cont))){
		return $cont;
	}
	$from = strtoupper($from)=='UTF8' ? 'utf-8' : $from;
	$to = strtoupper($to)=='UTF8' ? 'utf-8' : $to;
	if(is_string($cont)) {
		if(function_exists('mb_convert_encoding')){
			return mb_convert_encoding ($cont, $to, $from);
		}elseif(function_exists('iconv')){
			return iconv($from,$to,$cont);
		}else{
			return $cont;
		}
	}elseif(is_array($cont)){
		foreach	($cont as $key => $val) {
			$_key =	auto_charset($key,$from,$to);
			$cont[$_key] = auto_charset($val,$from,$to);
			if($key	!= $_key ) {
				unset($cont[$key]);
			}
		}
		return $cont;
	}elseif(is_object($cont)) {
		$vars =	get_object_vars($cont);
		foreach($vars as $key=>$val) {
			$cont->$key = auto_charset($val,$from,$to);
		}
		return $cont;
	}else{
		return $cont;
	}
}
function stripslashes_deep($value) {
	$value = is_array($value) ? array_map(array('string','stripslashes_deep'), $value) : stripslashes($value);
	return $value;
}
function csubstr($str, $len, $start=0, $charset='', $suffix='...'){
	if (empty($charset)) $charset = gc('base.charset');
	if (is_null($charset)) $charset = 'utf-8';
	$str = trim($str);
	if(function_exists("mb_substr")){
		if (mb_strlen($str, $charset) <= $len) return $str;
		$slice = mb_substr($str, $start, $len, $charset);
	}else{
		$re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/";
		$re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
		$re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
		$re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
		preg_match_all($re[$charset], $str, $match);
		if(count($match[0]) <= $len) return $str;
		$slice = join("",array_slice($match[0], $start, $len));
	}
	return $slice.$suffix;
}

if(!function_exists('parse_ini_string')) {
function parse_ini_string($string, $process_sections=FALSE){
	if(!$string) return false;
	$parse_array = array();
	$parse_key   = '';
	$temp_array  = explode("\n",$string);
	$count = count($temp_array);
	for($i=0;$i<=$count;$i++){
		unset($key,$val);
		$temp_array[$i] = str_replace(array("\r\n","\n","\r"," "),'',$temp_array[$i]);
		if($temp_array[$i]) {
			if($process_sections&&preg_match('/\[(.+)\]/',$temp_array[$i],$m)) {
				$parse_key = trim($m[1]);
			}else {
				list($key,$val) = explode('=',$temp_array[$i]);
			}
			if($key && $val) {
				if(preg_match('/^\d+$/is', $val)){
					$val = (int)$val;
				}elseif (in_array(strtolower($val), array('true','false'))){
					$val = (bool)$val;
				}
				if ($process_sections&&$parse_key){
					$parse_array[$parse_key][$key]=$val;
				}else{
					$parse_array[$key]=$val;
				}
			}
		} else {
			unset($temp_array[$i]);
		}
	}
	return $parse_array;
}
}
