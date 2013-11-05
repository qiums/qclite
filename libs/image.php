<?php if ( ! defined('ROOT')) exit('No direct script access allowed');
/*
* create by EditPlus v3.11
* author by Max
*/
!defined('CHARSET') AND define('CHARSET','utf-8');
class Lib_image {
	var $make_water = false;
	var $water_picture = null; // 水印图片
	var $water_text = null; // 水印文字
	var $font_path = null; // 字体文件 以上3个设置，在config.php配置
	var $ttfsize = 12; // TrueType字体字号
	var $fsize = 5; // 系统默认字体的字号(imagestring)
	var $src = null; // 源图片
	var $dst = ''; // 目标图片路径，为空则直接输出到浏览器
	var $im = null; // 源图像文件句柄
	var $obj = null; // 新建图像文件句柄
	var $src_w = null; // 源图片宽度
	var $src_h = null; // 源图片高度
	var $src_type = null; // 源图片类型
	var $src_ext = null; // 源图片后缀名
	var $src_mime = null; // 源图片MIME
	var $dst_w = null; // 目标图片宽度
	var $dst_h = null; // 目标图片高度
	var $dst_x = 0; // 目标图片X座标
	var $dst_y = 0; // 目标图片Y座标
	var $pos_x = 0; // 源图片剪切X座标
	var $pos_y = 0; // 源图片剪切Y座标
	var $pos_w = 0;
	var $pos_h = 0;
	var $bgalpha = 60; // 水印文字背景透明度
	var $mask_w = 0; // 水印文字/水印图片宽度
	var $mask_h = 0; // 水印文字/水印图片高度
	var $mask_position = 9; // 水印文字/图片位置 1 左上 2 上中 3 右上 4 左中 5 正中 6 右中 7 左下 8 下中 9 右下
	var $resize_zoom = 1; // 默认为1，不缩放

	function init(){
		if (!function_exists('gd_info')) return logs('No image support in this PHP server',__FILE__,__LINE__);
		$this->water_picture = DATAPATH.'water'.DS.$this->water_picture;
		$this->font_path = DATAPATH.'water'.DS.$this->font_path;
		return $this;
	}
	function error($error){
		system_error($error,__FILE__,__LINE__);
		exit;
	}
	function _reset(){
		$this->pos_w = $this->pos_h = $this->pos_x = $this->pos_y = 0;
	}
	/*
	* 初始化源图像/目标图像信息
	*/
	function set_file($srcfile='', $dstfile=''){
		if (!is_file($srcfile)) $this->error('Not found source file.');
		$this->src = $srcfile;
		$this->dst = $dstfile;
		if ((int)ini_get('memory_limit') < 64){
			// 超过256K图片，将内存调至128M
			ini_set('memory_limit', '256M');
		}
		//$this->src_bname = basename($this->src);
		$this->getinfo($this->src);
		return $this;
	}
	/*
	* 创建一个画布
	*/
	function createobj(){
		if (function_exists("imagecreatetruecolor")){ // GD 2.0.1
			$this->obj = imagecreatetruecolor($this->dst_w, $this->dst_h);
		}else{
			$this->obj = imagecreate($this->dst_w, $this->dst_h);
		}
	}
	/*
	* 固定剪切，将源图片缩放到适合大小后进行居中剪切
	* 输出的图像大小一定等于$w/$h
	*/
	function fixed_cut($w, $h){
		if (empty($this->im)) return $this->src;
		if (($this->src_w<=$w OR $this->src_h<=$h) OR empty($w) OR empty($h)) return $this->src;
		$this->pos_w = $this->dst_w = $w;
		$this->pos_h = $this->dst_h = $h;
		if ($this->src_w/$this->src_h > $this->dst_w/$this->dst_h){
			if (empty($this->pos_x)) {
				$this->pos_x = ($this->src_w-$this->src_h*$this->dst_w/$this->dst_h)/2;
			}
			$this->src_w = $this->src_h*$this->dst_w/$this->dst_h;
		}else{
			if (empty($this->pos_y)) {
				$this->pos_y = ($this->src_h-$this->src_w*$this->dst_h/$this->dst_w)/2;
			}
			$this->src_h = $this->src_w*$this->dst_h/$this->dst_w;
		}
		return $this->create();
	}
	/*
	* 比例缩小图片到指定尺寸，不足的宽/高，以颜色填补
	* 输出的图片大小一定等于$w/$h
	*/
	function resize_cut($w, $h){
		if (empty($this->im) OR empty($w) OR empty($h)) return $this->src;
		if ($this->resize_zoom == 1 && $w > 0 && $h > 0){
			$this->resize_zoom = min($w/$this->src_w, $h/$this->src_h);
		}
		// 画布宽高
		$this->dst_w = $w;
		$this->dst_h = $h;
		// 实际裁切图片宽高
		$this->pos_w = $this->src_w * $this->resize_zoom;
		$this->pos_h = $this->src_h * $this->resize_zoom;
		// 画面座标
		$this->dst_x = ($this->dst_w-$this->pos_w)/2;
		$this->dst_y = ($this->dst_h-$this->pos_h)/2;
		return $this->create();
	}
	/*
	* 从源图片从$pos_x/$pos_y位置剪切出$w/$h尺寸的图片进行缩放
	* 输出图片的尺寸不一定等于$w/$h
	*/
	function cut($w, $h){
		if (empty($this->im)) return $this->src;
		if (($this->src_w<$w AND $this->src_h<$h) OR empty($w) OR empty($h)) return $this->src;
		$this->src_w = $this->dst_w = $w;
		$this->src_h = $this->dst_h = $h;
		$this->createobj();
		imagecopy($this->obj, $this->im, 0, 0, $this->pos_x, $this->pos_y, $w, $h);
		// 用于resize
		$this->im = $this->obj;
		$this->pos_x = $this->pos_y = 0;
	}
	/*
	* 重写尺寸
	*/
	function resize($w=0, $h=0){
		if (empty($this->im) OR empty($w) OR empty($h)) return $this->src;
		if ($this->resize_zoom == 1 && $w > 0 && $h > 0){
			$this->resize_zoom = min($w/$this->src_w, $h/$this->src_h);
		}
		if ($this->resize_zoom>=1) return true;
		$this->pos_w = $this->dst_w = $this->src_w * $this->resize_zoom;
		$this->pos_h = $this->dst_h = $this->src_h * $this->resize_zoom;
		//dump($this->im);die;
		$this->create();
	}
	/*
	* 水印文字
	*/
	function text(){
		if (empty($this->water_text)) return false;
		$this->water_text = auto_charset($this->water_text, CHARSET, 'utf-8');
		$bgcol = imagecolorallocate($this->obj, 255, 255, 255);
		$textcol = imagecolorallocate($this->obj, 0, 0, 0);
		$alpha = imagecolorallocatealpha($this->obj, 230, 230, 230, $this->bgalpha);
		if (is_file($this->font_path) && function_exists('imagettftext')){
			$tmp = imagettfbbox($this->ttfsize, 0, $this->font_path, $this->water_text);
			$this->mask_w = $tmp[2] - $tmp[6] + 3;
			$this->mask_h = $tmp[3] - $tmp[7] + 3;
			$pos = $this->position();
			//imagefilledrectangle($this->obj, $pos[0], $pos[1], $pos[0]+$this->mask_w, $pos[1]+$this->mask_h, $alpha);
			imagefilledrectangle($this->obj, 0, $pos[1], $this->dst_w, $pos[1]+$this->mask_h, $alpha);
			imagettftext($this->obj, $this->ttfsize, 0, $pos[0]+2, $pos[1]+$this->mask_h-6, $textcol, $this->font_path, $this->water_text);
		}else {
			$this->mask_w = strlen($this->water_text) * imagefontwidth($this->fsize)+5;
			$this->mask_h = imagefontheight($this->fsize)+2;
			$pos = $this->position();
			imagefilledrectangle($this->obj, 0, $pos[1], $this->dst_w, $pos[1]+$this->mask_h, $alpha);
			imagestring($this->obj, $this->fsize, $pos[0]+3, $pos[1], $this->water_text, $textcol);
		}
	}
	/*
	* 水印图片
	*/
	function picture(){
		@$data = getimagesize($this->water_picture);
		if (!$data) return false;
		if ($data[0] > $this->dst_w || $data[1] > $this->dst_h) return false;
		$this->mask_w = $data[0];
		$this->mask_h = $data[1];
		$pos = $this->position();
		switch ($data[2]) {
			case 1:
				@$water = imagecreatefromgif($this->water_picture);
				break;
			case 2:
				@$water = imagecreatefromjpeg($this->water_picture);
				break;
			case 3:
				@$water = imagecreatefrompng($this->water_picture);
				break;
			default:
				$water = false;
				break;
		}
		if (!$water) return false;
		imagealphablending($this->obj, true);
		imagecopy($this->obj, $water, $pos[0], $pos[1], 0, 0, $this->mask_w, $this->mask_h);
		imagedestroy($water);
	}
	/*
	* 自动水印判断
	*/
	function water(){
		if (!$this->make_water) return false;
		if (is_file($this->water_picture)){
			$this->picture();
		}else {
			$this->text();
		}
	}
	/*
	* 创建一个目标图像画布，并将源图像重采样复制到目标图像
	*/
	function create(){
		if (function_exists("imagecreatetruecolor")){ // GD 2.0.1
			$this->obj = imagecreatetruecolor($this->dst_w, $this->dst_h);
			//imagerectangle($this->obj,0,0,$this->dst_w,$this->dst_h,);
			$white = imagecolorallocate($this->obj, 255, 255, 255);
			imagefill($this->obj, 0, 0, $white);
			imagecopyresampled($this->obj, $this->im, $this->dst_x, $this->dst_y, $this->pos_x, $this->pos_y, $this->pos_w, $this->pos_h, $this->src_w, $this->src_h);
		}else{
			$this->obj = imagecreate($this->dst_w, $this->dst_h);
			$white = imagecolorallocate($this->obj, 255, 255, 255);
			imagefill($this->obj, 0, 0, $white);
			imagecopyresized($this->obj, $this->im, $this->dst_x, $this->dst_y, $this->pos_x, $this->pos_y, $this->pos_w, $this->pos_h, $this->src_w, $this->src_h);
		}
		$this->water();
		return TRUE;
	}
	/*
	* 水平翻转图片
	*/
	function horizontal(){
		$this->createobj();
		for ($i=0; $i < $this->src_w; $i++){
			imagecopy($this->obj, $this->im, $this->src_w-$i-1, 0, $i, 0, 1, $this->src_h);
		}
		//$this->output();
	}
	/*
	* 垂直翻转图像
	*/
	function vertical(){
		$this->createobj();
		for ($i=0; $i < $this->src_h; $i++){
			imagecopy($this->obj, $this->im, 0, $this->src_h-$i-1, 0, $i, $this->src_w, 1);
		}
		//$this->output();
	}
	/*
	* 任意角度旋转图像
	*/
	function rotate($degrees=0){
		$this->obj = imagerotate($this->im, $degrees, 0);
		//$this->output();
	}
	/*
	* 输出图像
	* $this->dst的值为空，输出到浏览器，否则保存为文件
	*/
	function output(){//die($this->src_ext);
		$this->_reset();
		if (!is_resource($this->obj)) return $this->src;
		if (!empty($this->dst) && file_exists($this->dst)) @unlink($this->dst);
		if (empty($this->dst)) {
			header("Pragma:no-cache\r\n");
			header("Cache-Control:no-cache\r\n");
			header("Expires:0\r\n");
			header('Content-type: '.$this->headers($this->src_ext));
		}

		switch ($this->src_ext){
			case 'gif':
				imagegif($this->obj, $this->dst);
			break;
			case 'jpg':
				imagejpeg($this->obj, $this->dst, 100);
			break;
			case 'png':
				imagepng($this->obj, $this->dst);
			break;
			case 'wbmp':
				imagewbmp($this->obj, $this->dst);
			break;
			default:
				imagejpeg($this->obj, $this->dst, 100);
		}
		imagedestroy($this->obj);
		if (is_resource($this->im)) imagedestroy($this->im);
	}

	function headers($ext='jpg'){
		$header = array(
			'jpg' => 'image/jpeg',
			'png' => 'image/png',
			'wbmp' => 'image/vnd.wap.wbmp',
			'gif' => 'image/gif'
		);
		if (!isset($header[$ext])) return 'image/jpeg';
		return $header[$ext];
	}
	/*
	* 获取图片信息，并根据图像类型创建源图像句柄
	*/
	function getinfo($source){
		if (empty($source)) return false;
		@$data = getimagesize($source);
		if ($data){
			$this->dst_w = $this->src_w = $data[0];
			$this->dst_h = $this->src_h = $data[1];
			if ($this->src_w*$this->src_h==0) return false;
			$this->src_type = $data[2];
			$this->src_mime = $data['mime'];
			$this->src_ext = strtolower(substr(strrchr($this->src,"."),1));
			switch ($this->src_type){
				case "1": // gif
					$this->im = @imagecreatefromgif($source);
					//$this->src_ext = 'gif';
				break;
				case "2":
					$this->im = @imagecreatefromjpeg($source);
					//$this->src_ext = 'jpg';
				break;
				case "3":
					$this->im = @imagecreatefrompng($source);
					//$this->src_ext = 'png';
				break;
				default:
					//return $source;
			}
		}
	}
	/*
	* 根据mask_position获取水印图片/文字位置座标
	*/
	function position(){
		switch ($this->mask_position){
			case 1:
				return array(0, 0);
			case 2:
				return array(($this->dst_w - $this->mask_w)/2, 0);
			case 3:
				return array($this->dst_w - $this->mask_w, 0);
			case 4:
				return array(0, ($this->dst_h - $this->mask_h)/2);
			case 5:
				return array(($this->dst_w - $this->mask_w)/2, ($this->dst_h - $this->mask_h)/2);
			case 6:
				return array($this->dst_w - $this->mask_w, ($this->dst_h - $this->mask_h)/2);
			case 7:
				return array(0, $this->dst_h-$this->mask_h);
			case 8:
				return array(($this->dst_w - $this->mask_w)/2, $this->dst_h-$this->mask_h);
			case 9:
				return array($this->dst_w - $this->mask_w, $this->dst_h-$this->mask_h);
		}
	}
}
