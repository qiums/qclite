<?php if ( ! defined('ROOT')) exit('No direct script access allowed');

class model{
	public $_parent_name;
	public $config;
	public $pagedata=array();
	private $usetab=array();
    private $maintab='';
	private $config_cache = array();
	private $attr_cache = array();

	//function model(){$this->__construct();}
	public function __construct(){
		$this->_parent_name = strtolower(str_replace(gc('base.model_suffix'), '', get_class($this)));
		$this->gc(":{$this->_parent_name}");
		Base::getInstance()->load->database();
	}
	public function __call($method, $args=''){
        if (0===strpos($method, 'top')){
            $limit = intval(substr($method, 3));
            return $this->limit($limit)->select($args);
        }
		return ;
	}
	public function __set($name, $value){
		$base = &Base::getInstance();
		if (!property_exists($base, $name)){
			if (! ($o=$base->load->model($name))){
				if (! ($o=$base->load->libs($name))) return $base;
			}
		}
		if (is_array($o)){
			$o->config = $value;
			return ;
		}
		$o->$name = $value;
	}
	public function __get($name){
		$base = &Base::getInstance();
		if (property_exists($base, $name)) return $base->$name;
		if (! ($o=$base->load->model($name))){
			if (! ($o=$base->load->libs($name))) return $this;
		}
		return $o;
	}
	public function property($name, $value=NULL){
		if (is_array($name)){
			foreach ($name as $key=>$value){
				$this->property($key, $value);
			}
		}elseif (property_exists($this, $name)){
			$this->$name = $value;
		}
		return $this;
	}
	public function gc($key, $default=NULL){
		if (is_string($key) AND ':'===$key{0}){
			list($key, $name) = explode('/', "{$key}/");
			$key = ltrim($key, ':');
			if (!$name) $name = $key;
			if (FALSE === ($config = import("group.{$key}.common.config_{$name}"))){
				if (FALSE === ($config = import("data.config.{$name}"))) return ;
			}
			$this->config = $config;
			unset($config);
			return TRUE;
		}
		return gc($key, $default, $this->config);
	}
	public function db($ac=''){
		return Db::getInstance($ac);
	}
	public function callback($fn='build'){
		if (method_exists($this, $fn)) $this->db()->callback = array($this, $fn);
		return $this;
	}
	public function limit($limit){
		$this->attr_cache['limit'] = $limit;
		$this->db()->limit($limit);
		return $this;
	}
	public function page($page=1, $limit=20){
		$page = max((int)$page, 1);
		if (!$limit) $limit = $this->gc('page_size', 20);
		$this->pagedata = array('cur'=>$page, 'size'=>$limit);
		$limit = ($page-1)*$limit. ", {$limit}";
		return $this->limit($limit);
	}
	public function where($key, $value=''){
		$this->db()->where($key, $value);
		return $this;
	}
	public function count(){
		$this->before();
		return $this->db()->count($this->maintab);
	}
	public function find($cond=array()){
		$this->before();
		if ($cond) $this->db()->where($cond);
		$data = $this->db()->find($this->maintab);
		$this->attr_cache = array();
		return $data;
	}
	public function findAll($cond=array()){
		return $this->select($cond);
	}
	public function select($cond=array()){
		if (is_numeric($cond)){
			$this->attr('datatype', $cond);
			$cond = array();
		}
		$this->before();
		if ($cond) $this->db()->where($cond);
		$data = $this->db()
			->attr('pk', $this->gc('data_pk', 'id'))
			->findAll($this->maintab);
		$this->pagedata['rows'] = $this->db()->count();
		$this->attr_cache = array();
		return $data;
	}
	// this->join('arcdata.aid', '', array('mid'=>3));
	public function update($data, $cond=NULL){
		if (!($data = $this->before($data))) return FALSE;
		$join = $this->attr_cache['join'];
		$on = $this->attr_cache['on'];
		$this->attr('join', '')->attr('on', '');//dump($join);dump($on);die;
		if (!$this->db()->where($cond)->update($this->maintab, $data['data'])) return FALSE;
		if ($data['sub'] AND $join){
			foreach ($join as $tab){
				$tab = qstrstr($tab, '.', TRUE);
				if (!$on[$tab]) continue;
				if (isset($data['sub'][$tab])){
					$sdata = $data['sub'][$tab];
					unset($data['sub'][$tab]);
				}else{
					$sdata = $data['sub'];
				}
				if (!$sdata) continue;
                if ($on[$tab]['_unset']){
					$unset = explode(',', $on[$tab]['_unset']);
					foreach ($unset as $key){
						unset($sdata[$key]);
					}
                    unset($on[$tab]['_unset']);
                }
				$this->db()->insert($tab, $sdata + $on[$tab], $sdata, 1);
			}
		}
		$this->attr_cache = array();
		return $this->db()->count();
	}
	// this->join('arcdata.aid', '')
	public function insert($data, $duplicat='', $noid=0){
		if (!($data = $this->before($data))) return FALSE;
		$join = $this->attr_cache['join'];
		$this->attr('join', '');
		$id = $this->db()->insert($this->maintab, $data['data'], $duplicat, $noid);
		if ($data['data']['id']) $id = $data['data']['id'];
		if ($id AND $join){
			$on = $this->attr_cache['on'];
			$this->attr('on', '');
			foreach ($join as $tab){
				$pk = qstrstr($tab, '.');
				$tab = qstrstr($tab, '.', TRUE);
				if (isset($data['sub'][$tab])){
					$sdata = $data['sub'][$tab];
					unset($data['sub'][$tab]);
				}else{
					$sdata = $data['sub'];
				}
				if (!$pk AND !$sdata) continue;
				if ($on[$tab]['_unset']){
					$unset = explode(',', $on[$tab]['_unset']);
					foreach ($unset as $key){
						unset($sdata[$key]);
					}
                    unset($on[$tab]['_unset']);
                }
				$in = array_merge($sdata, (array)$on[$tab], $pk ? array($pk => $id) : array());
				$this->db()->insert($tab, $in);
			}
			unset($sdata, $in);
		}
		$this->attr_cache = array();
		return $id;
	}
	public function setInc($key, $num=1, $table=''){
		if ($table) $this->maintab = $table;
		return $this->update(array($key=>"[+]{$num}"));
	}
	public function delete($cond=''){
		$this->before();
		if ($cond) $this->where($cond);
		return $this->db()->delete($this->maintab);
	}
	public function join($tab, $fields='*', $on=''){
		$this->usetab[$tab] = array($fields, $on);
		return $this;
	}
	public function order($order=NULL, $way='ASC'){
		$order = $this->db()->order($order, $way)->dbattr['order'];
		$sort = $this->gc('sort_fields');
		if (!$order){
			if (!$sort) return $this;
			$order = array();
		}
		if (!is_array($sort)) $sort = str2array($sort, '', ',');
		$fields = array();
		foreach ($sort as $key=>$val){
			$skey = explode('-', $key);
			$tk = isset($skey[1])?$skey[1]:$skey[0];
			$fields[$tk] = (isset($order[$tk]) ? $order[$tk] :$val);
			$order[$tk] = "{$skey[0]},{$fields[$tk]}";
		}
		foreach ($order as $key=>$val){
			if (FALSE===strpos($val, ',')) $order[$key] = "{$key},{$val}";
		}
		$this->config['order_fields'] = $fields;
		$order = join(',', $order);
		$order = str2array($order, '', ',');
		unset($sort, $skey);
		return $this->attr('order', $order);
	}
	public function attr($key, $value=NULL, $append=FALSE){
		if (is_null($value) AND isset($this->attr_cache[$key])) return $this->attr_cache[$key];
		if ($append AND isset($this->attr_cache[$key])){
			$value = is_array($value) ? array_merge($this->attr_cache[$key], $value) : ($value. (is_bool($append)?'': $append). $this->attr_cache[$key]);
		}
		$this->attr_cache[$key] = $value;
		Db::getInstance()->attr($key, $value);
		return $this;
	}
	private function before($data=NULL){
		$this->maintab = $this->gc('data_table');
        $on = array();
		if (!$this->attr_cache['fields']) $fields = array('*');
        foreach ($this->usetab as $tab=>$one){
            if (!$one) continue;
			$tab = qstrstr($tab, '.', TRUE);//substr($tab, 0, strpos($tab, '.'));
			if (!is_null($one[0])) $fields[] = "{$tab}.". str_replace(',', ",{$tab}.", $one[0]);
			if (isset($one[1])){
				$on[$tab] = $one[1];
			}
        }
        $this->attr('on', $on);
		$this->attr('join', array_keys($this->usetab));
        $this->attr('table', $this->maintab);
		if ($fields) $this->attr('fields', join(',', $fields), ',');
        $this->usetab = array();
        unset($tab, $on);
		if (!is_null($data)) return $this->parse_data($data);
	}
	private function parse_data($data){
		if (!$data OR !is_array($data)) return ;
		$subdata = array();
		foreach ($data as $key=>$val){
			if ('sd_' != substr($key, 0, 3)) continue;
			$subdata[substr($key, 3)] = $val;
			unset($data[$key]);
		}
		return array('data'=>$data, 'sub'=>$subdata);
	}
	private function parse_value($value){
		if (!is_scalar($value)) return $value;
		if (!$value) return $value;
		if ('!'==substr($value,0,1)) return "<> ".substr($value,1);
		if ('notnull'==$value) return "> ";
		if ('null'==$value) return '';
		return $value;
	}
	public function block($ac='', $a='', $fields=array()){
		if (!$a) return array();
		if (!is_array($a)) $a = str2array($a);
		$a = array_map(array($this, 'parse_value'), $a);
		$ac = 'list'===$ac ? 'findAll' : 'find';
		$do = $a['do'];
		$cachetime = (int)$a['cachetime'];
		if (isset($a['cachename'])){
			$cachename = $a['cachename'];
			if (FALSE!==($cache=cache::q("block.{$cachename}",'',$cachetime))) return $cache;
		}
		unset($a['do'], $a['cachetime'], $a['cachename']);
		foreach ($a as $method=>$val){
			if (method_exists($this, $method)){
				$this->$method($val);
				unset($a[$method]);
			}
		}
		if (!$fields) $fields = $a;
		return isset($do) ? call_user_func_array(array($this, $do), $fields) : $this->where($fields)->$ac();
	}
	public function apply_cond($fields=array(), $post=array()){
		$base = Base::getInstance();
		$cond = $mode = array();
		if (!$post) $post = $base->qdata;
		if (!$post OR !$fields) return array();
		foreach ($fields as $key=>$one){
			$name = isset($one['alias']) ? $one['alias'] : basename(str_replace('.', '/', $key));
			$val = isset($post[$name]) ? $post[$name] : NULL;
			if (!is_null($val) AND isset($one['pattern'])) $val = str_replace(':', $val, $one['pattern']);
			if (is_numeric($one['search'])){
				if (!is_null($val)) $cond[$key] = $val;
			}elseif ('%%'===$one['search']){
				if (!is_null($val)) $cond[$key] = 'LIKE %'. $val. '%';
			}elseif ('%'===$one['search']){
				if (!is_null($val)) $cond[$key] = 'LIKE ,'.$val.',%';
			}elseif ('m'===$one['search']){
				if (!is_null($val)) $cond[$key] = array('match', $val);
			}else{
				$mode[$one['search']][] = $key;
			}
		}
		unset($name, $val);
		if ($mode){
			foreach ($mode as $search=>$key){
				$logic = FALSE!==strpos($search, '|') ? '|' : (FALSE!==strpos($search, '&') ? '&' : NULL);
				$search = explode($logic, $search);
				$range = $post["f{$search[0]}"];
				unset($cond["f{$search[0]}"]);
				if (!$range OR $range!= $search[1]) continue;
				if (!isset($post[$search[0]])) continue;
				$val = $post[$search[0]];
				$flag = false;
				$arr = explode(' ', $val);
				foreach ($arr as $str){
					if (strlen(trim($str)) >= 4){
						$flag = true;
						break;
					}
				}
				if ($flag AND $search[2] AND $search[2]==='m' AND strlen($val)>=4 AND preg_match('/[\w\s\-]/i', $val)){
					$cond[join(',', $key)] = array('match', $val.(isset($search[3])?$search[3]:''));
				}else{
					$cond[join($logic, $key)] = "LIKE %{$val}%";
				}
			}
		}
		return $cond;
	}
	public function error($message='', $key=''){
		if (!$message AND !$key){
			return $this->form->error();
		}
		$err = lang("tips.{$message}");
		if (!$err) $err = $message;
		if ($key){
			return $this->form->error($key, $err);
		}
		return FALSE;
	}
}
