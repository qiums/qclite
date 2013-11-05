<?php if ( ! defined('ROOT')) exit('No direct script access allowed');
/*
* create by EditPlus v3.01
* author by Sam <wuyou192@163.com>.
*/
class http{
	public function download ($filename, $showname='',$content='',$expire=600){
		if ('' != $content){
			$size = strlen($content);
		}elseif(is_file($filename)){
			$size = sprintf("%u", filesize($filename));
		}
		if (!$size) exit($filename.' Not found.');
        if(empty($showname)) $showname = $filename;
        $showname = basename($showname);
		if (!empty($filename)) $type = mime_content_type($filename);
		if (!$type) $type = "application/octet-stream";
		if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
			$showname = str_replace('+','%20',urlencode($showname));
		}
		header("Cache-Control:");
		header("Cache-Control: public");
		header("Content-Type: $type");
		header("Content-Disposition: attachment; filename=" . $showname);
		header('Content-Length: '.$size);
		if ('' != $content) exit($content);
		header('Accept-Ranges: bytes');
		if(isset($_SERVER['HTTP_RANGE'])){
			list ($a, $range) = explode("=", $_SERVER['HTTP_RANGE']);
			$length = $size-1-$range;
			header('HTTP/1.1 206 Partial Content');
			header('Content-Length: '.($length>0 ?$length:0));
			header('Content-Range: bytes '.$range.($size-1).'/'.$size);
		}else{
			header("Content-Range: bytes 0-".($size-1)."/$size");
			header('Content-Length: '.$size);
		}
		if (FALSE === ($fp = fopen($filename, 'rb'))) return $fp;
		ini_set('memory_limit', '256M');
		fseek($fp, $range);
		set_time_limit(0);
		ob_start();
		while (!feof($fp)){
			echo (fread($fp, 1024*8));
			flush();
			ob_flush();
		}
		ob_end_flush();
		fclose($fp);
		exit;
    }
	public function send($url, $data=array(), $timeout=2) {
		if ( !function_exists('curl_init') ) { return empty($data) ? $this->get($url, $timeout) : $this->post($url, $data, $timeout); }
		$ch = curl_init();
		if (is_array($data) AND $data) {
			$formdata = http_build_query($data);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $formdata);
		}
		$useragent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)";
		$header = array('Accept-Language: zh-cn','Connection: Keep-Alive','Cache-Control: no-cache'); 
		curl_setopt($ch, CURLOPT_HTTPHEADER,$header);  //模拟浏览器的头信息
		curl_setopt($ch, CURLOPT_USERAGENT, $useragent);  //模拟浏览器的信息
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		$return = curl_exec($ch);
		curl_close($ch);
		return $return;
	}
	public function get($url, $fsock_timeout=2){
		$url2 = parse_url($url);
		$url2["path"] = ($url2["path"] == "" ? "/" : $url2["path"]);
		$url2["port"] = (!isset($url2['port']) || empty($url2["port"])) ? 80 : $url2["port"];
		$host_ip = @gethostbyname($url2["host"]);
		if(($fsock = fsockopen($host_ip, $url2['port'], $errno, $errstr, $fsock_timeout)) < 0){
			return false;
		}
		$request =  $url2["path"] .($url2["query"] ? "?".$url2["query"] : "");
		$in  = "GET " . $request . " HTTP/1.0\r\n";
		$in .= "Accept: */*\r\n";
		$in .= "User-Agent: Payb-Agent\r\n";
		$in .= "Host: " . $url2["host"] . "\r\n";
		$in .= "Connection: Close\r\n\r\n";
		if(!@fwrite($fsock, $in, strlen($in))){
			fclose($fsock);
			return false;
		}
		return $this->_GetHttpContent($fsock);
	}
	public function post($url,$post_data=array(), $fsock_timeout=2){
		$url2 = parse_url($url);
		$url2["path"] = ($url2["path"] == "" ? "/" : $url2["path"]);
		$url2["port"] = (!isset($url2['port']) || empty($url2["port"])) ? 80 : $url2["port"];
		$host_ip = @gethostbyname($url2["host"]);
		if(($fsock = fsockopen($host_ip, $url2['port'], $errno, $errstr, $fsock_timeout)) < 0){
			return false;
		}
		$request =  $url2["path"].($url2["query"] ? "?" . $url2["query"] : "");
		$post_data2 = http_build_query($post_data);
		$in  = "POST " . $request . " HTTP/1.0\r\n";
		$in .= "Accept: */*\r\n";
		$in .= "Host: " . $url2["host"] . "\r\n";
		$in .= "User-Agent: Lowell-Agent\r\n";
		$in .= "Content-type: application/x-www-form-urlencoded\r\n";
		$in .= "Content-Length: " . strlen($post_data2) . "\r\n";
		$in .= "Connection: Close\r\n\r\n";
		$in .= $post_data2 . "\r\n\r\n";
		unset($post_data2);
		if(!@fwrite($fsock, $in, strlen($in))){
			fclose($fsock);
			return false;
		}
		return self::_GetHttpContent($fsock);
	}
	private function _GetHttpContent($fsock=null) {
		$out = null;
		while($buff = @fgets($fsock, 2048)){
			$out .= $buff;
		}
		fclose($fsock);
		$pos = strpos($out, "\r\n\r\n");
		$head = substr($out, 0, $pos);    //http head
		$status = substr($head, 0, strpos($head, "\r\n"));    //http status line
		$body = substr($out, $pos + 4, strlen($out) - ($pos + 4));//page body
		if(preg_match("/^HTTP\/\d\.\d\s([\d]+)\s.*$/", $status, $matches)){
			if(intval($matches[1]) / 100 == 2){
				return $body;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
}
