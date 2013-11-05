<?php if ( ! defined('ROOT')) exit('No direct script access allowed');
/*
* create by EditPlus v3.01
* author by Sam <wuyou192@163.com>.
*/
class Lib_ubb {
	public $edit = false;
	public $custom_tags = 'tmp';
	public $clear_tags = 'script|frame|iframe|object|embed';
	function callback($matches){
		return call_user_func(array('UbbTags',"parse"),$matches[1],$str,$matches[$key-1],$this->edit);
	}
	function replace($str){
		$str = str_replace('&amp;#061;','&#061;',$str);
		if ($this->clear_tags) $str = preg_replace('/\[('.$this->clear_tags.'[^\]]*?)(=(.*?))*\](.*?)\[\/\1\]/is', '', $str);
		$no = empty($this->custom_tags)?'':'(?!('.$this->custom_tags.'))';
		$re='/\[('.$no.'.[^\]]*?)(=(.*?))*\](.*?)\[\/\1\]/is';
		if(preg_match($re,$str)){
			return $this->replace(preg_replace_callback($re,array($this,'callback'),$str));
		}
		return str_replace(array('[br]','[hr]'),array('<br />','<hr />'),rtrim($str))."\n";
	}
	function clear($str){
		$str = str_replace(array('[hr]','[br]'),' ',$str);
		$re = '/\[(.[^\]]*?)(=(.*?))*\](.*?)\[\/\1\]/is';
		if (!preg_match($re,$str)) return $str;
		return $this->clear(preg_replace($re,"\\4",$str));
	}
	function get_remote_image(&$data){
		$text = $data;
		$data = preg_replace('/\[(img[^\]]*?)(=(.*?))*\](.*?)\[\/\1\]/ies',"ubb::preg_remote_image('\\3','\\4')",$text);
		return $this->preg_remote_image('get');
	}
	function preg_remote_image($url,$text=''){
		static $files=array();
		if ('get'==$url) return $files;
		$url = explode('|',$url);
		$url[0] = Base::getInstance()->upload->get_remote($url[0]);
		if (empty($url[0])) return '';
		$files[] = $url[0];
		return '[localimg='.join('|',$url).']'.$text.'[/localimg]';
	}
}
class UbbTags{
	var $short_tags = array(
		'b'=>'strong',
		'i'=>'em',
		'u'=>'u',
		'p'=>'p',
		'sup'=>'sup',
		'sub'=>'sub',
		's'=>'strike',
		'ol'=>'ol',
		'ul'=>'ul',
		'li'=>'li',
	);
	static public function parse($tag,$str,$args,$edit){
		$_this = &getInstance('UbbTags');
		if (array_key_exists($tag,$_this->short_tags)) return '<'.$_this->short_tags[$tag].'>'.nl2br($str).'</'.$_this->short_tags[$tag].'>';
		if (method_exists($_this,"parse_{$tag}")) return call_user_func(array($_this,"parse_{$tag}"),$str,$args,$edit);
	}
	function parse_bgcolor($str,$args){
		return '<span style="background-color:'.$args.'">'.$str.'</span>';
	}
	function parse_color($str,$args){
		return '<span style="color:'.$args.'">'.$str.'</span>';
	}
	function parse_size($str,$args){
		return '<span style="font-size:'.$args.'">'.$str.'</span>';
	}
	function parse_align($str,$args){
		return '<p style="text-align:'.$args.'">'.$str.'</p>';
	}
	function parse_url($str,$args){
		return '<a href="'.(empty($args)?$str:$args).'" target="_blank">'.(empty($str)?$args:$str).'</a>';
	}
	function parse_header($str,$args){
		return "<h{$args}>{$str}</h{$args}>";
	}
}
?>