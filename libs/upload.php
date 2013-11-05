<?php if ( ! defined('ROOT')) exit('No direct script access allowed');

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
@set_time_limit(5 * 60);

class Lib_upload{
	private $cleanup_tmpdir = true; // Remove old files
	private $maxFileAge = 3600; // Temp file age in seconds
	private $tmpfile;
	private $filetype;
	private $chunk;
	public $oversize=0;
	public $savepath;
	public $tmpdir;
	public $filename;
	public $custom_path;

	function factory(){
		if(!$this->tmpdir) $this->tmpdir = ini_get("upload_tmp_dir") . DS . "plupload". DS;
		if(!is_dir($this->tmpdir)) io::mkdir($this->tmpdir);
		if(!$this->savepath OR !is_dir($this->savepath)) $this->savepath = UPLOAD_PATH;
		if(!is_dir($this->savepath)) io::mkdir($this->savepath);
		return $this;
	}
	function check_unique($chunks, $fileName){
		$fileName = substr(to_guid_string(time()),-6).mt_rand(1000,9999).'_'.preg_replace('/[^\w\._]+/', '_', $fileName);
		if ($chunks < 2 && file_exists($this->tmpdir . $fileName)) {
			$ext = strrpos($fileName, '.');
			$fileName_a = substr($fileName, 0, $ext);
			$fileName_b = substr($fileName, $ext);
			$count = 1;
			while (file_exists($this->tmpdir . $fileName_a . '_' . $count . $fileName_b))
				$count++;
			return $fileName_a . '_' . $count . $fileName_b;
		}
		return $fileName;
	}
	function clean_oldpart(){
		if (!$this->cleanup_tmpdir) return TRUE;
		if (!is_dir($this->tmpdir) OR !($dir = opendir($this->tmpdir))) return $this->output(100);
		while (($file = readdir($dir)) !== false) {
			$tmpfilePath = $this->tmpdir .DS . $file;
			// Remove temp file if it is older than the max age and is not the current file
			if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $this->maxFileAge)
				&& ($tmpfilePath != "{$this->tmpfile}.part")) {
				@unlink($tmpfilePath);
			}
		}
		closedir($dir);
		return TRUE;
	}
	function file_upload(){
		if (!isset($_FILES['file']['tmp_name']) OR !is_uploaded_file($_FILES['file']['tmp_name']))
			return $this->output(103);
		$out = fopen("{$this->tmpfile}.part", $this->chunk == 0 ? "wb" : "ab");
		if (!$out) return $this->output(102);
		// Read binary input stream and append it to temp file
		$in = fopen($_FILES['file']['tmp_name'], "rb");
		if ($in) {
			while ($buff = fread($in, 4096))
				fwrite($out, $buff);
		} else
			return $this->output(101);
		fclose($in);
		fclose($out);
		@unlink($_FILES['file']['tmp_name']);
		return TRUE;
	}
	function http_upload(){
		$part = "{$this->tmpfile}.part";
		$out = fopen($part, $this->chunk == 0 ? "wb" : "ab");
		if (!$out) return $this->output(102);
		// Read binary input stream and append it to temp file
		$in = fopen("php://input", "rb");
		if (!$in) return $this->output(101);
		while ($buff = fread($in, 4096))
			fwrite($out, $buff);
		fclose($in);
		fclose($out);
		if (!filesize($part)){
			@unlink($part);
			return $this->output(105);
		}
		return TRUE;
	}
	function start(){
		if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
			$contentType = $_SERVER["HTTP_CONTENT_TYPE"];
		if (isset($_SERVER["CONTENT_TYPE"]))
			$contentType = $_SERVER["CONTENT_TYPE"];
		if (strpos($contentType, "multipart") !== false){
			return $this->file_upload();
		}else{
			return $this->http_upload();
		}
	}
	function run($chunk, $chunks, $fileName){
		$fileName = $this->check_unique($chunks, $fileName);
		$this->chunk = $chunk;
		$this->tmpfile = $this->tmpdir. $fileName;
		if(TRUE !== ($result=$this->clean_oldpart())) return $result;
		if(TRUE !== ($result=$this->start())) return $result;
		if($this->oversize>0 AND filesize("{$this->tmpfile}.part")>$this->oversize){
			@unlink($this->tmpfile.'.part');
			return $this->output(104);
		}
		if (!$chunks || $chunk==$chunks - 1) {
			// Strip the temp .part suffix off 
			$file = $this->getfile($fileName);
			rename("{$this->tmpfile}.part", $file);
			return $this->output(array(
				'fileurl'=>$this->geturl($file),
				'filepath'=>$this->filename,
				'filesize'=>filesize($file),
				'filetype'=>$this->filetype,
				));
		}
		return $this->output(array('part'=>"{$this->tmpfile}.part"));
	}
	function geturl($file){
		$this->filename = trim(str_replace(array($this->savepath, DS), array('', '/'), realpath($file)), '/');
		if($this->domain) return trim($this->domain, '/'). "/{$this->filename}";
		$path = str_replace(DS, '/', str_replace(THIS_PATH, '', $this->savepath));
		return gc('env.webroot'). trim($path, '/'). '/'. $this->filename;
	}
	function getfile($file){
		$this->filetype = substr($file, strrpos($file,'.')+1);
		if ($this->custom_path){
			$path = $this->custom_path;
			$filename = $file;
		}else{
			$filename = preg_replace("/{$this->filetype}$/", '', basename($file)). strtolower($this->filetype);
			$folders = array(
				'picture' => array('jpg','jpeg','gif','png'),
				'media' => array('flv','mp3','mp4','swf'),
				'archive' => array('zip','rar','pdf','doc'),
			);
			foreach ($folders as $folder=>$types){
				if (in_array($this->filetype, $types)) break;
			}
			$path = $this->savepath;
		}
		if ($this->subpath){
			$path .= str_replace('[ext]', $folder, $this->subpath);
			if (FALSE !== strpos($path,'[Y]')){
				$path = preg_replace('/\[(.[^\}\]]*?)\]/ies', 'date(\'$1\')', $path);
			}
			$path = preg_replace('/\[.[^\}\]]*?\]/i', '', $path);
		}
		io::mkdir($path);
		return $path. $filename;
	}
	function get_remote($url){
		if (!preg_match('/^http:\/\/(.*)\.(gif|jpg|jpeg|bmp|png)$/is',$url)) return $url;
		$new = $this->getfile($url);
		Base::getInstance()->load->helper('http');
		$size = file_put_contents($new, http::send($url));
		if (!$size) return $url;
		return $this->output(array(
				'fileurl'=>$this->geturl($new),
				'filepath'=>$new,
				'filesize'=> $size,
				'filetype'=> $this->filetype,
				));
	}
	function output($code){
		$result = array('jsonrpc'=>'2.0','id'=>'id');
		if(is_numeric($code)){
			$err = array(
				100 => 'Failed to open temp directory.',
				101 => 'Failed to open input stream.',
				102 => 'Failed to open output stream.',
				103 => 'Failed to move uploaded file.',
				104 => 'Not enough free space.',
				105 => 'Not input data.',
			);
			$result['code'] = $code;
			$result['message'] = $err[$code];
		}elseif(is_array($code)){
			$result = array_merge($result, $code);
		}
		return $result;
	}
}
