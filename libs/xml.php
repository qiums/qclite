<?php if ( ! defined('ROOT')) exit('No direct script access allowed');

defined('CHARSET') AND define('CHARSET','utf-8');
class Lib_xml{
	private $headers=array();
	private $listkey='item';
	function Lib_xml(){
		if (ini_get('zend.ze1_compatibility_mode') == 1)
			ini_set ('zend.ze1_compatibility_mode', 0);
	}
	public function set($headers, $listkey='item'){
		$this->headers = $headers;
		$this->listkey = $listkey;
		return $this;
	}
	public function build($data, $root='channel', $xml=NULL){
		if (NULL==$xml){
			$xml = simplexml_load_string("<?xml version='1.0' encoding='".CHARSET."'?><$root />");
			foreach ($this->headers as $key=>$value)
				$xml->addChild($key, htmlspecialchars($value));
		}
		$data = (array)$data;
		foreach($data as $key => $value){
			if (is_numeric($key))
				$key = $this->listkey;
			$key = preg_replace('/[^a-z]/i', '', $key);
			if (is_array($value)){
				$node = $xml->addChild($key);
				$this->build($value, $key, $node);
			}
			else{
				//$value = htmlentities($value,ENT_COMPAT,CHARSET);
				//if (FALSE!==strpos($value,'&')) $value = "<![CDATA[{$value}]]>";
				$value = str_replace('&','&amp;',$value);
				$xml->addChild($key,$value);
			}
		}
		return $xml->asXML();
	}
	public function save($file, $data){
	}
}