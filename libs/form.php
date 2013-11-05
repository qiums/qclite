<?php if ( ! defined('ROOT')) exit('No direct script access allowed');
/*
* create by EditPlus v3.11
* author by Max
*/
class Lib_form{
	public $idpre = 'qc';
	public $htmlflag = array();
	public $form_data = array();
	private $err = array();
	private $for_id = '';
	private $append_html = array();
	private $cache_data = array();
	private $mode = 0;
	private $groupid = NULL;

	public function render($form_data=array(), $db_data=array(), $mode=0){
		if ($form_data) $this->form_data = $form_data;
		if (!$this->form_data){
			$this->data(gc('env.controller'), gc('env.action'));
		}
		if (!$this->form_data OR !is_array($this->form_data)) return array();
		if (is_numeric($db_data)){
			$mode = $db_data;
			$db_data = array();
		}
		$this->mode = $mode;
		$form = array();
		foreach ($this->form_data as $name=>$args){
			if (!$args['type'] OR (!is_null($this->groupid) AND $args['group'] != $this->groupid))	continue;
			$true_name = FALSE!==($index = strpos($name, '.')) ? substr($name, $index+1) : $name;
			if (!$args['label']) $args['label'] = lang("form_label.{$true_name}");
			$alias = isset($args['alias']) ? $args['alias'] : $true_name;
			$value = isset($db_data[$alias]) ? $db_data[$alias] : (1===$this->mode ? '' : $args['value']);
			$element = $this->element(array('true_name'=>$true_name, 'name'=>isset($args['alias'])?$args['alias']:$name)
				, $args['type'], $args['attr'], $value, $args['option'], $args['label']);
			$tips = lang("form_tip.{$true_name}");
			preg_match_all('/(minlength|maxlength)(\=\")*(\d+)/is', $args['attr'], $m);
			if ($m[1]){
				if (!$tips) $tips = lang('form_label.'. join('_', $m[1]));
				$tips = str_replace($m[1], $m[3], $tips);
			}
			$form[$alias] = array(
				'label'		=>	empty($args['label']) ? '' : ('<label for="'.$this->for_id.'" class="control-label">'.
						(in_array('minlength', $m[1]) ? '<cite>*</cite>':''). $args['label'].'</label>'),
				'ele'		=>	$element,
				'tips'		=>	empty($tips) ? '' : '<span class="help-block">'.$tips.'</span>',
			);
			$this->for_id = '';
		}
		unset($m, $element, $true_name);
		$this->idpre = 'qc';
		$this->form_data = array();
		$this->mode = 0;
		$this->groupid = NULL;
		return $form;
	}
	public function data($ctrl, $name){
		$dir = gc('env.group');
		if (!$dir) $dir = gc('env.directory');
		$cache_key = trim("{$dir}_{$ctrl}", '_');
		$form_data = $this->cache_data[$cache_key];
		if (!$form_data){
			if (!is_file($file = import("data.form_{$cache_key}", TRUE))){
				if (!is_file($file = import("data.form.{$cache_key}", TRUE))){
					if (!is_file($file = import("data.form.{$ctrl}", TRUE))) return $this;
				}
			}
			$form_data = $this->cache_data[$cache_key] = include $file;
		}
		if (is_array($form_data)){
			if (isset($form_data[$cache_key][$name])){
				$this->form_data = $form_data[$name];
			}else{
				foreach ($form_data as $key=>$one){
					if (FALSE !== strpos($key, $name)){
						$this->form_data = $one;
						break;
					}
				}
			}
		}
		return $this;
	}
	public function group($k=0){
		$this->groupid = $k;
		return $this;
	}
	public function element($name, $type, &$attr='', $value='', $option='',$label=''){
		if (is_array($name)){
			extract($name);
		}else{
			$true_name = $name;
		}
		if (FALSE!==($index = strpos($name, '.'))){
			$true_name = substr($name, $index+1);
			$name = (1!==$this->mode) ? "sd_{$true_name}" : $true_name;
		}
		list($tag, $type) = explode(':', "{$type}:ele");
		$prefix = (FALSE!==strpos('select,textarea', $tag) ? $tag : str_replace(':', '-', $type));
		$this->for_id = "{$this->idpre}-". $prefix. "-{$name}";
		if (FALSE === strpos('checkbox,radio', $type)){
			$this->add_type($attr, 'class', 'elelayout form-'. $prefix, TRUE);
			$this->add_type($attr, 'placeholder', lang("placeholder.{$true_name}"));
		}
		$this->add_type($attr, 'data-alt', lang("form_alt.{$true_name}"));
		if ('[now]' === $value) $value = D::cdate();
		if (FALSE !== strpos($attr, 'req-date')){
			if (preg_match('/^\d{10}$/s',$value)){
				$value = D::cdate($value, FALSE !== strpos($attr, 'req-datetime') ? 'Y-m-d H:i:s' : 'Y-m-d');
			}else{
				$value = '';
			}
		}
		if (is_string($option) AND 'u.'===substr($option,0,2)){
			$this->htmlflag['mselect'] = TRUE;
			$this->add_type($attr, 'data-url', url(substr($option, 2)));
			$this->add_type($attr, 'data-label', $label);
			$option = '';
			$type = 'hidden';
		}
		$option = $this->get_option($option ? $option : $true_name);
		$str = '';
		if ('textarea' === $tag){
			$this->add_type($attr, 'name', $name);
			$this->add_type($attr, 'id', $this->for_id);
			return '<textarea '. $attr. '>'. $value. '</textarea>';
		}elseif ('input' === $tag){
			if ('checkbox' === $type OR 'radio' === $type){
				if (!$option) return '';
				if (!is_array($value)) $value = explode(',', $value);
				foreach ($option as $id=>$val){
					$tmp = $attr;
					$for = "{$this->idpre}-{$type}-{$name}-{$id}";
					if ($value == $id) $this->add_type($tmp, 'checked', 'checked');
					$this->add_type($tmp, 'name', (count($option)===1 OR 'radio'===$type) ? $name : "{$name}[]");
					$this->add_type($tmp, 'id', $for);
					$str .= '<label for="'.$for. '" class="'.$type. '"><input type="'. $type. '" '. $tmp. ' /> '. $val. '</label> ';
				}
				return $str;
			}elseif ($option){
				foreach ($option as $id=>$val){
					$tmp = $attr;
					$this->add_type($tmp, 'name', "{$name}[]");
					$this->add_type($tmp, 'id', "{$this->idpre}-{$type}-{$name}-{$id}");
					$this->add_type($tmp, 'value', $val);
					$str .= '<input type="'. $type. '" '. $tmp. ' />';
				}
				return $str;
			}else{
				if ('file'===$type){
					$this->htmlflag['file'] = TRUE;
					$type = 'text';
					$this->add_type($attr, 'class', 'qcfile');
				}
				$this->add_type($attr, 'name', "{$name}");
				$this->add_type($attr, 'id', $this->for_id);
				$this->add_type($attr, 'value', $value);
				return '<input type="'. $type. '" '. $attr. ' />';
			}
		}elseif ('select' === $tag){
			$this->add_type($attr, 'name', $name);
			$this->add_type($attr, 'id', $this->for_id);
			$str = '<select '. $attr. '>';
			if ($type AND 'ele' !== $type){
				if ('[label]' === $type) $type = lang("form_label.{$true_name}");
				$str .= '<option value="">'. $type .'</option>';
			}
			if (is_numeric($value)) $value = (int)$value;
			foreach ($option as $id=>$val){
				if (is_array($val)) $val = join('/', $val);
				if (is_numeric($id)) $id = (int)$id;
				$str .= '<option value="'. $id. '"';
				if ($id === $value) $str .= ' selected="selected"';
				$str .= '>'. $val. '</option>';
			}
			$str .= '</select>';
			return $str;
		}elseif ('html' === $tag){
			$this->htmlflag['html-editor'] = TRUE;
			$this->add_type($attr, 'name', $name);
			$this->add_type($attr, 'class', 'html-editor', TRUE);
			return $this->element($name, 'textarea', $attr, $value);
		}
		return '';
	}
	public function htmleditor($name, $attr='', $value=''){
		$this->add_type($attr, 'class', 'html-editor', TRUE);
		return $this->element($name, 'textarea', $attr, $value);
	}
	public function text($name, $attr='', $value='', $option=''){
		return $this->element($name, 'input:text', $attr, $value, $option);
	}
	public function hidden($name, $attr='', $value='', $option=''){
		return $this->element($name, 'input:hidden', $attr, $value, $option);
	}
	public function checkbox($name, $attr='', $value='', $option=''){
		return $this->element($name, 'input:checkbox', $attr, $value, $option);
	}
	public function radio($name, $attr='', $value='', $option=''){
		return $this->element($name, 'input:radio', $attr, $value, $option);
	}
	public function textarea($name, $attr='', $value=''){
		return $this->element($name, 'textarea', $attr, $value);
	}
	public function select($name, $attr='', $value='', $option='', $text='[label]'){
		return $this->element($name, "select:{$text}", $attr, $value, $option);
	}
	public function unionsel($name, $attr='', $value='', $option=''){
		if (!$this->append_html['union_sel']) $this->append_html['union_sel'] = '';
		$this->add_type($attr, 'class', 'mselect', TRUE);
		return $this->hidden($name, $attr, $value, $option);
	}
	public function error($id=NULL, $error=''){
		if (is_null($id) AND !$error){
			if ($this->err){
				response::cprint(0, 'form-element-error', $this->err);
				return TRUE;
			}
		}elseif (is_array($id)){
			$this->err[] = $id;
		}elseif (!is_null($id)){
			if ('#' !== $id{0}){
				if (isset($this->cache_data['rules'][$id])){
					$id = "#{$this->idpre}-". str_replace(':', '-', $this->cache_data['rules'][$id]['type']). "-{$id}";
				}
			}
			$this->err[] = array('id'=>$id, 'error'=>$error);
		}
		return FALSE;
	}
	public function validate($rules='', &$data=''){
		if (!$rules){
			$this->data(gc('env.controller'), gc('env.action'));
			$rules = $this->form_data;
		}
		if (!$rules) return TRUE;
		$this->err = '';
		if (!$data) $data = &Base::getInstance()->post;
		foreach ($rules as $key=>$value){
			if (FALSE!==($index=strpos($key,'.'))){
				unset($rules[$key]);
				$rules['sd_'.substr($key, $index+1)] = $value;
			}
		}
		$data = array_intersect_key($data, $rules);
		$add = array();//dump($rules);die;
		foreach ($data as $key=>$value){
			$rule = $rules[$key];
			if (''===$value AND $rule['value']){
				$value = $rule['value'];
				if ('[now]'==$value) $value = D::cdate();//D::get('curtime');
			}
			if ($rule['map']){
				if (FALSE!==($index=strpos($rule['map'],'.'))){
					$rule['map'] = 'sd_'.substr($rule['map'], $index+1);
				}
				if (isset($rules[$rule['map']])){
					$map = $rules[$rule['map']];
					if ($map['pattern']){
						$tmp = explode(',', $value);
						foreach ($tmp as &$val){
							$val = str_replace(':', $val, $map['pattern']);
						}
						$add[$rule['map']] = join(' ', $tmp);
					}else{
						$add[$rule['map']] = $value;
					}
				}
			}
			if ($rule['alias'])
				$add[$rule['alias']] = $value;
			if (TRUE !== ($res = $this->vali_rule($key, $value, $rule['attr']))){
				list($tag, $type) = explode(':', "{$rule['type']}:ele");
				$prefix = (FALSE!==strpos('select,textarea', $tag) ? $tag : str_replace(':', '-', $type));
				$this->error("#{$this->idpre}-{$prefix}-{$key}", $res);
				continue;
			}
			if (is_scalar($value)){
				if ('html'===$rule['type']) $value = trim($value);
				if (preg_match('/^1\d{10,12}$/',$value))
					$value = intval($value);
				if (!empty($value) AND !is_numeric($value)
					AND preg_match('/req-(date|datetime)/is', $rule['attr'])) $value = D::timestamp($value);
			}
			if (!empty($value) AND $rule['function'] AND function_exists($rule['function'])){
				$data[$key] = call_user_func($rule['function'], $value);
			}else{
				$data[$key] = $value;
			}
		}
		if (!empty($this->err)) return FALSE;
		$data += $add;
		return $data;
	}
	private function vali_rule($key, &$value, $rule){
		preg_match('/data-depr="(.+?)"/i', $rule, $depr);
		if ($depr AND $depr[1] AND $value AND FALSE!==strpos($value, $depr[1])){
			$value = explode($depr[1], $value);
		}
		if (is_array($value)){
			$depr = ($depr AND $depr[1]) ? $depr[1] : ',';
			foreach ($value as $val){
				if(TRUE !== $this->vali_rule($key,$val,$rule)) return FALSE;
			}
			$value = join($depr, $value);
			return TRUE;
		}
		if (empty($value) AND (!preg_match('/(req\-|min\d+|\/maxlength\/)/is', $rule)
			OR strpos($rule,'cannull'))) return TRUE;
		preg_match_all('/(placehoder|data\-alt)="(.+?)"/is', $rule, $tip);
		if ($tip[2][1]){
			$tip = $tip[2][1];
		}elseif ($tip[2][0]){
			$tip = $tip[2][0];
		}else{
			$tip = lang("form_alt.{$key}");
		}
		$rules = array(
			'email'=>"^[0-9a-zA-Z\\-\\_\\.]+@[0-9a-zA-Z\\-\\_]+[\\.]{1}[0-9a-zA-Z]+[\\.]?[0-9a-zA-Z]+$",
			'url'=>"^(http|https|ftp):\\/\\/([\\w\-]+\\.)+[\\w\\-]+(\\/([\\w\-\\.\\/\\\\\?%&=])*)?",
			'datetime'=>"^(19|20)[0-9]{2}[\\-\\/](0?[1-9]|1[0-2])[\\-\\/](0?[1-9]|[1|2][0-9]|3[0|1])\\s([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
			'date'=>"^(19|20)[0-9]{2}[\\-\\/](0?[1-9]|1[0-2])[\\-\\/](0?[1-9]|[1|2][0-9]|3[0|1])$",
			'time'=>"^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
			'money'=>"^(?!0\\d)(?!\\.)[0-9]+(\\.[0-9]{2})?$",
			'cnidcard'=>"(^\d{15}|\d{18}|(\d{17}X))$",
			'cnphone'=>"(^(800|400)[\\-]*[0-9]{7,8}$)|(^0[1-9]{1}[0-9]{1,2}[\\-]*[2-8]{1}[0-9]{6,7}(\\-\\d+)*$)|(^[2-8]{1}[0-9]{6,7}(\\-\\d+)*$)|(^[0]?1[3458]{1}[0-9]{9}$)|(^[19]{1}[0-9]{4,5}$)",
			'enword'=>"^[a-z]+$",
			'string'=>"^[a-z0-9\\-_]+$",
			'num'=>"^[0-9]+(\.?[0-9]+)?$",
			'any'=>"^[\\s\\S]+$"
		);
		preg_match_all('/req\-(\w+)|minlength(\d+)|maxlength="(\d+)"/i', $rule, $matches);
		preg_match('/req\-(\w+)/i', $rule, $req);
		preg_match('/minlength(\d+)/i', $rule, $min);
		preg_match('/maxlength="(\d+)"/i', $rule, $max);
		$result = is_scalar($value)?array($value):$value;
		foreach ($result as $val){
			$len = strlen($val);
			if (!$min[1] AND !$len) continue;
			if (($min[1] AND $len<$min[1])
				OR ($max[1] AND $len>$max[1])
				OR ($req[1] AND isset($rules[$req[1]])
					AND !preg_match('/'.$rules[$req[1]].'/is', $val))
				) return $tip;
		}
		unset($req, $min, $max);
		return TRUE;
	}
	private function add_type(&$attr, $key, $val='', $append=FALSE){
		if (!is_scalar($val) OR ''===$val) return '';
		if (FALSE === strpos(" {$attr}", " {$key}=")){
			$attr .= " {$key}=\"{$val}\"";
		}elseif ($append){
			$attr = preg_replace("/{$key}=\"(.[^\"]*)\"/is", $key .'="\\1'. ' '.$val. '"', $attr);
		}
		$attr = trim($attr);
		return $attr;
	}
	private function get_option($option){
		if (!$option) return array();
		if (is_array($option)) return $option;
		$pre = substr($option, 0, 2);
		$key = substr($option, 2);
		if ('g.' === $pre){
			return isset($GLOBALS[$key]) ? $GLOBALS[$key] : array();
		}elseif ('l.'===$pre){
			return lang($key);
		}elseif ('c.' === $pre OR 'm.'===$pre){
			$key = str2array($key);
			$class = 'c.'===$pre ? Base::getInstance()->cp->load(key($key)) : Base::getInstance()->load->model(key($key));
			if ($class){
				$ac = array_shift($key);
				if (method_exists($class, $ac)) return $class->$ac($key);
			}
			return array();
		}elseif (FALSE===strpos($option, '=')){
			$option = lang('form_source.'. $option);
		}
		if (is_array($option)) return $option;
		parse_str(preg_replace("/[\r\n\,]+/is", '&', $option), $option);
		return (array)$option;
	}
}
?>
