<?php if ( ! defined('ROOT')) exit('No direct script access allowed');

class Libs_html{
	function __call($name, $arguments) {
		$name = array_merge(array($name), $arguments);
		return call_user_func_array(array($this, 'tag'), $name);
	}
	function a($link, $title, $append=''){
		$a = $title;
		if ('<' == $title{0}){
			$title = strip_tags($title);
		}
		if (is_array($append)){
			$append['title'] = $title;
			$append['href'] = $link;
		}elseif (is_string($append)){
			$append .= " href=\"{$link}\"";
			if ($title) $append .= " title=\"{$title}\"";
		}
		return $this->tag('a', $a, $append);
	}
	function img($src, $title=''){
		return $this->tag('img', array('src'=>$src, 'title'=>$title, 'alt'=>$title));
	}
	function tag($tag, $wrap='', $attr=array()){
		if (is_array($wrap)){
			$attr = $wrap;
			$wrap = '';
		}
		$str = '';
		if (is_array($attr)){
			foreach ($attr as $key => $value) {
				if (!$value) continue;
				$str .= " {$key}=\"{$value}\"";
			}
			$attr = $str;
		}
		$attr = trim($attr);
		$arr = array('img', 'hr', 'br');
		$str = "<{$tag}{$attr}";
		if (in_array($tag, $arr)) {
			$str .= " />";
		}else{
			$str .= "></{$tag}>";
		}
		return $str;
	}
}
?>
