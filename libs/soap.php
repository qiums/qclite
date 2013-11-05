<?php if ( ! defined('ROOT')) exit('No direct script access allowed');

define('NONE_PHP_SOAP', !extension_loaded('soap'));
if (!NONE_PHP_SOAP) require dirname(__FILE__). DS. 'nusoap.php';

class Lib_soap{
	public $server = NULL;
	public $client = NULL;
	public $allowed = TRUE;
	function server($wsdl=NULL, $args=array()){
		if ($this->server) return $this->server;
		if (is_array($wsdl)){
			$args = $wsdl;
			$wsdl = NULL;
		}
		if (NONE_PHP_SOAP){
			$this->server = new soap_server();
			if ($wsdl) $server->configureWSDL($wsdl);
			$this->server->soap_defencoding = 'UTF-8';
			$this->server->decode_utf8 = FALSE;
			$this->server->xml_encoding = 'UTF-8';
		}else{
			$this->server = new SoapServer($wsdl, $args);
		}
		return $this;
	}
	function client($wsdl=NULL, $args=array()){
		if ($this->client) return $client;
		if (is_array($wsdl)){
			$args = $wsdl;
			$wsdl = NULL;
		}
		if (NONE_PHP_SOAP){
			$this->client = new soap_client($wsdl, !empty($wsdl),
				isset($args['proxy_host']) ? $args['proxy_host']: FALSE,
				isset($args['proxy_port']) ? $args['proxy_port']: FALSE,
				isset($args['proxy_login']) ? $args['proxy_login']: FALSE,
				isset($args['proxy_password']) ? $args['proxy_password']: FALSE,
				isset($args['proxy_host']) ? (int)$args['proxy_host']: 0);
			if ($wsdl) $this->client->configureWSDL($wsdl);
		}else{
			$this->client = new SoapClient($wsdl, $args);
		}
		return $this;
	}
	function headers($namespace='', $name='', $data='', $mustunderstand=FALSE, $actor=SOAP_ACTOR_NEXT){
		if (!$this->client) return $this;
		if (NONE_PHP_SOAP) return $this;
		$headers = new SoapHeader($namespace, $name, $data, $mustunderstand, $actor);
		$this->client->__setSoapHeaders(array($headers));
		return $this;
	}
	function call($function_name, $args=array()){
		if (!$this->client) return NULL;
		$method = NONE_PHP_SOAP ? 'call' : '__soapCall';
		return $this->client->$method($function_name, $args);
	}
	function auth($id, $key){
		global $config;
		$auth = $config['soap']['auth_key'];
		$this->allowed = !$auth OR ($id AND $key AND isset($auth[$id]) AND $auth[$id] == $key);
	}
	function register($name,$in=array(),$out=array(),$namespace=false,$soapaction=false,$style=false,$use=false,$documentation='',$encodingStyle=''){
		if (!$this->server) return $this;
		if (is_string($name)){
			$count = substr_count($name, '.');
			if (!$count){
				$method = !NONE_PHP_SOAP ? 'addFunction' : 'register';
			}elseif (1===$count){
				$method = !NONE_PHP_SOAP ? 'setClass' : 'register';
				$name = substr($name, 0, strpos($name, '.'));
			}
		}elseif (is_object($name)){
			$method = !NONE_PHP_SOAP ? 'setObject' : NULL;
		}
		if (is_null($method)) return $this;
		if (NONE_PHP_SOAP){
			$this->server->$method($name,$in,$out,$namespace,$soapaction,$style,$use,$documentation,$encodingStyle);
		}else{
			$this->server->$method($name);
			//if ('addFunction'==$method) $this->server->$method(SOAP_FUNCTIONS_ALL);
		}
		return $this;
	}
	function service(){
		if (!$this->server) return $this;
		if (NONE_PHP_SOAP){
			$HTTP_RAW_POST_DATA = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
			$this->server->service($HTTP_RAW_POST_DATA);
		}else{
			$this->server->handle();
		}
	}
	function get_error($key='client'){
		if ('client'==$key AND $this->client) return $this->client->getError();
		if ('server'==$key AND $this->server) return ;//
		return '';
	}
}
