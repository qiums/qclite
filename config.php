<?php  if ( ! defined('ROOT')) exit('No direct script access allowed');
/*
* create by EditPlus v3.01
* author by Max
*/
$config = array(
	// Base
	'base' => array(
		'run_mode' => 'debug',//debug,standard,safe
		'run_logs' => 1, //3-All 2-All(No require infomation) 1-Only System/DB error 0-None
        'write_log' => TRUE,
		'gzip_output' => TRUE,
		'model_suffix' => '_model',
		'controller_suffix'=>'_controller',
		'libclass_prefix' => 'Lib_',
		'widgetclass_prefix' => 'Widget_',
		'subclass_prefix' => 'QC_',
		'language' => 'zh-cn',
        'charset' => 'UTF-8',
        'encryption_key' => 'e981bd37',
		'enable_hooks' => TRUE,
	),
	// Cache
	'cache' => array(
        'cache_run' => TRUE,
		'handle' => 'qccache', //memcache,qccache,eaccelerator
		'qccache' => array('gzip'=>FALSE),
	),
	// Date
	'date' => array(
		'time_offset' => '+8',
        'time_zone' => 'Asia/Shanghai',
		'time_format' => 'Y-m-d H:i:s',
	),
	// Router
	'dispatch' => array(
		'url_suffix' => '.html',
		'pathinfo_trigger' => 'cmd',
		'function_trigger' => 'ac',
		'controller_trigger'=>'c',
		'directory_trigger' => 'dir',
		'group_trigger' => 'g',
		'language_trigger' => 'lang',
		'application_trigger' => 'app',
		'uri_protocol' => 'AUTO', // PATH_INFO, REQUEST_URI
		'default_group' => '',
		'default_directory'=>'',
		'default_controller'=>'home',
		'default_function' => 'index',
		'404_override' => '',
	),
	// Session
	'session' => array(
		//'autostart' => TRUE,
        'savepath' => 'app', // php, app, db
        'dbtable' => 'session',
        'expiration' => 3600, // 's'
        'match_ip' => FALSE,
        'match_useragent' => FALSE,
        'sess_cookie_name' => 'sess',
	),
    // Cookie
    'cookie' => array(
        'prefix' => 'q9Ae_',
        'encrypt_func' => 'authcode', //加密函数
        'domain' => '',
        'path' => '',
    ),
	// String
	'input' => array(
		'enable_query_strings' => TRUE,
		'global_xss_filtering' => FALSE,
		'permitted_uri_chars'=>'',
	),
	'tpl' => array(
		'engine' => 'qctpl',
		'extension' => '.html',
        'theme' => '',
        'cachepath' => CACHE_PATH. 'views'.DS,
	),
);
?>