<?php  if ( ! defined('ROOT')) exit('No direct script access allowed');

class session {
	var $savepath	= 'php';
	var $dbtable	= '';
	var $expiration	= 7200;
	var $match_ip				= FALSE;
	var $match_useragent		= TRUE;
	var $sess_cookie_name			= 'sess';
	var $sess_time_to_update		= 1200;
	var $encryption_key				= '!@w4$lc&';
	var $flashdata_key 				= 'flash';
	var $time_reference				= 'time';
	var $gc_probability				= 5;
	var $userdata					= array();
	var $now;
	var $db;

	function factory($params = array()){
		//log_message('debug', "Session Class Initialized");
        ini_set('session.auto_start', 0);
		Base::getInstance()->load->_assign_params($this, gc('session'));
		$this->encryption_key = gc('base.encryption_key');
        $this->savepath = strtolower($this->savepath);
		if ('app' == $this->savepath){
            $path = TMP_PATH. 'session'. DS;
            io::mkdir($path);
            session_save_path($path);
        }elseif ($this->savepath=='db'){
			if ($this->dbtable=='' OR !class_exists('Db')) $this->savepath='php';
		}
		$this->now = time();
		if ($this->expiration == 0) $this->expiration = (60*60*24*365*2);
		if ($this->savepath!='db') session_start();
		// Set the cookie name
		if ( ! $this->sess_read()){
			$this->sess_create();
		}else{
			$this->sess_update();
		}

		// Delete 'old' flashdata (from last request)
	   	$this->_flashdata_sweep();
		// Mark all new flashdata as old (data will be deleted before next request)
	   	$this->_flashdata_mark();
		// Delete expired sessions if necessary
		$this->_sess_gc();
		//log_message('debug', "Session routines successfully run");
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch the current session data if it exists
	 *
	 * @access	public
	 * @return	bool
	 */
	function sess_read(){
		// Fetch the cookie
		$session = cookie::get($this->sess_cookie_name);
		// No cookie?  Goodbye cruel world!...
		if ($session === NULL) return FALSE;
		$hash	 = substr($session, strlen($session)-32); // get last 32 chars
		$session = substr($session, 0, strlen($session)-32);
		// Does the md5 hash match?  This is to prevent manipulation of session data in userspace
		if ($hash !==  md5($session.$this->encryption_key)){
			//log_message('error', 'The session cookie data did not match what was expected. This could be a possible hacking attempt.');
			$this->sess_destroy();
			return FALSE;
		}
		// Unserialize the session array
		$session = $this->_unserialize($session);//
		//dump($session);exit;
		// Is the session data we unserialized an array with the correct format?
		if (!is_array($session) OR !isset($session['session_id']) OR !isset($session['ip_address']) OR !isset($session['user_agent']) OR !isset($session['last_activity'])){
			$this->sess_destroy();
			return FALSE;
		}

		// Is the session current?
		if (($session['last_activity'] + $this->expiration) < $this->now){
			$this->sess_destroy();
			return FALSE;
		}
        //dump($this);dump($session);exit;
		// Does the IP Match?
		if ($this->match_ip == TRUE AND $session['ip_address'] != ip2long(response::ip()))
		{
			$this->sess_destroy();
			return FALSE;
		}

		// Does the User Agent Match?
		if ($this->match_useragent == TRUE AND trim($session['user_agent']) != trim(substr(user_agent(), 0, 50)))
		{
			$this->sess_destroy();
			return FALSE;
		}

		// Is there a corresponding session in the DB?
		if ($this->savepath == 'db'){
			$cond = array('session_id'=>$session['session_id']);
			if ($this->match_ip == TRUE) $cond['ip_address']=$session['ip_address'];
			if ($this->match_useragent == TRUE) $cond['user_agent']=$session['user_agent'];

			$data = Db::getInstance()->find($this->dbtable, $cond);
			// No result?  Kill it!
			if (!$data){
				$this->sess_destroy();
				return FALSE;
			}
			if (isset($data['user_data']) AND $data['user_data'] != ''){
				$custom_data = $this->_unserialize($data['user_data']);
				if (is_array($custom_data)){
					foreach ($custom_data as $key => $val){
						$session[$key] = $val;
					}
				}
			}
		}
		// Session is valid!
		$this->userdata = $session;
		unset($session);

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Write the session data
	 *
	 * @access	public
	 * @return	void
	 */
	function sess_write()
	{
		// Are we saving custom data to the DB?  If not, all we do is update the cookie
		if ($this->savepath != 'db') return $this->_set_cookie();
		// set the custom userdata, the session data we will set in a second
		$custom_userdata = $this->userdata;
		$cookie_userdata = array();

		// Before continuing, we need to determine if there is any custom data to deal with.
		// Let's determine this by removing the default indexes to see if there's anything left in the array
		// and set the session data while we're at it
		foreach (array('session_id','ip_address','user_agent','last_activity') as $val)
		{
			unset($custom_userdata[$val]);
			$cookie_userdata[$val] = $this->userdata[$val];
		}
		// Did we find any custom data?  If not, we turn the empty array into a string
		// since there's no reason to serialize and store an empty array in the DB
		$custom_userdata = (count($custom_userdata)>0)?$this->_serialize($custom_userdata):'';
		// Run the update query
		Db::getInstance()->update($this->dbtable,array('session_id'=>$this->userdata['session_id']),
			array('last_activity' => $this->userdata['last_activity'], 'user_data' => $custom_userdata));
		$this->_set_cookie($cookie_userdata);
	}

	// --------------------------------------------------------------------
	/**
	 * Create a new session
	 *
	 * @access	public
	 * @return	void
	 */
	function sess_create()
	{
		$sessid = '';
		$ip = ip2long(response::ip());
		while (strlen($sessid) < 32){
			$sessid .= mt_rand(0, mt_getrandmax());
		}
		$sessid .= $ip;

		$this->userdata = array(
			'session_id' 	=> md5(uniqid($sessid, TRUE)),
			'ip_address' 	=> $ip,
			'user_agent' 	=> substr(response::user_agent(), 0, 50),
			'last_activity'	=> $this->now
		);
		// Save the data to the DB if needed
		if ($this->savepath == 'db') Db::getInstance()->insert($this->dbtable,$this->userdata);
		// Write the cookie
		$this->_set_cookie();
	}

	// --------------------------------------------------------------------

	/**
	 * Update an existing session
	 *
	 * @access	public
	 * @return	void
	 */
	function sess_update()
	{
		// We only update the session every five minutes by default
		if (($this->userdata['last_activity'] + $this->sess_time_to_update) >= $this->now) return ;
		// Save the old session id so we know which record to
		// update in the database if we need it
		$old_sessid = $this->userdata['session_id'];
		$new_sessid = '';
		while (strlen($new_sessid) < 32)
		{
			$new_sessid .= mt_rand(0, mt_getrandmax());
		}

		// To make the session ID even more secure we'll combine it with the user's IP
		$new_sessid .= ip2long(response::ip());

		// Turn it into a hash
		$new_sessid = md5(uniqid($new_sessid, TRUE));

		// Update the session data in the session data array
		$this->userdata['session_id'] = $new_sessid;
		$this->userdata['last_activity'] = $this->now;

		// _set_cookie() will handle this for us if we aren't using database sessions
		// by pushing all userdata to the cookie.
		$cookie_data = NULL;

		// Update the session ID and last_activity field in the DB if needed
		if ($this->savepath == 'db'){
			// set cookie explicitly to only have our session data
			$cookie_data = array();
			foreach (array('session_id','ip_address','user_agent','last_activity') as $val){
				$cookie_data[$val] = $this->userdata[$val];
			}
			Db::getInstance()->update($this->dbtable,array('session_id' => $old_sessid),
				array('last_activity' => $this->now, 'session_id' => $new_sessid));
		}
		// Write the cookie
		$this->_set_cookie($cookie_data);
	}

	// --------------------------------------------------------------------

	/**
	 * Destroy the current session
	 *
	 * @access	public
	 * @return	void
	 */
	function sess_destroy(){
		// Kill the session DB row
		if ($this->savepath == 'db' AND isset($this->userdata['session_id'])){
			Db::getInstance()->delete($this->dbtable,array('session_id'=>$this->userdata['session_id']));
		}
		// Kill the cookie
        cookie::del($this->sess_cookie_name);
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch a specific item from the session array
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function userdata($item=NULL, $val=NULL){
		if (is_null($item) && is_null($val)) return $this->all_userdata();
		if (is_array($item)) return $this->set_userdata($item);
		if (!is_null($val)) return $this->set_userdata($item, $val);
		return ( ! isset($this->userdata[$item])) ? NULL : $this->userdata[$item];
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch all session data
	 *
	 * @access	public
	 * @return	mixed
	 */
	function all_userdata()
	{
		return ( ! isset($this->userdata)) ? FALSE : $this->userdata;
	}

	// --------------------------------------------------------------------

	/**
	 * Add or change data in the "userdata" array
	 *
	 * @access	public
	 * @param	mixed
	 * @param	string
	 * @return	void
	 */
	function set_userdata($newdata = array(), $newval = '')
	{
		if (is_string($newdata))
		{
			$newdata = array($newdata => $newval);
		}

		if (count($newdata) > 0)
		{
			foreach ($newdata as $key => $val)
			{
				$this->userdata[$key] = $val;
			}
		}

		$this->sess_write();
	}

	// --------------------------------------------------------------------

	/**
	 * Delete a session variable from the "userdata" array
	 *
	 * @access	array
	 * @return	void
	 */
	function unset_userdata($newdata = array())
	{
		if (is_string($newdata))
		{
			$newdata = explode(',', $newdata);//array($newdata => '');
		}

		if (count($newdata) > 0)
		{
			foreach ($newdata as $key => $val)
			{
				unset($this->userdata[$val]);
			}
		}

		$this->sess_write();
	}

	// ------------------------------------------------------------------------

	/**
	 * Add or change flashdata, only available
	 * until the next request
	 *
	 * @access	public
	 * @param	mixed
	 * @param	string
	 * @return	void
	 */
	function set_flashdata($newdata = array(), $newval = '')
	{
		if (is_string($newdata))
		{
			$newdata = array($newdata => $newval);
		}

		if (count($newdata) > 0)
		{
			foreach ($newdata as $key => $val)
			{
				$flashdata_key = $this->flashdata_key.':new:'.$key;
				$this->set_userdata($flashdata_key, $val);
			}
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Keeps existing flashdata available to next request.
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function keep_flashdata($key)
	{
		// 'old' flashdata gets removed.  Here we mark all
		// flashdata as 'new' to preserve it from _flashdata_sweep()
		// Note the function will return FALSE if the $key
		// provided cannot be found
		$old_flashdata_key = $this->flashdata_key.':old:'.$key;
		$value = $this->userdata($old_flashdata_key);

		$new_flashdata_key = $this->flashdata_key.':new:'.$key;
		$this->set_userdata($new_flashdata_key, $value);
	}

	// ------------------------------------------------------------------------

	/**
	 * Fetch a specific flashdata item from the session array
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function flashdata($key)
	{
		$flashdata_key = $this->flashdata_key.':old:'.$key;
		return $this->userdata($flashdata_key);
	}

	// ------------------------------------------------------------------------

	/**
	 * Identifies flashdata as 'old' for removal
	 * when _flashdata_sweep() runs.
	 *
	 * @access	private
	 * @return	void
	 */
	function _flashdata_mark()
	{
		$userdata = $this->all_userdata();
		foreach ($userdata as $name => $value)
		{
			$parts = explode(':new:', $name);
			if (is_array($parts) && count($parts) === 2)
			{
				$new_name = $this->flashdata_key.':old:'.$parts[1];
				$this->set_userdata($new_name, $value);
				$this->unset_userdata($name);
			}
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Removes all flashdata marked as 'old'
	 *
	 * @access	private
	 * @return	void
	 */

	function _flashdata_sweep()
	{
		$userdata = $this->all_userdata();
		foreach ($userdata as $key => $value)
		{
			if (strpos($key, ':old:'))
			{
				$this->unset_userdata($key);
			}
		}

	}
	// --------------------------------------------------------------------

	/**
	 * Write the session cookie
	 *
	 * @access	public
	 * @return	void
	 */
	function _set_cookie($cookie_data = NULL)
	{
		if (is_null($cookie_data)) $cookie_data = $this->userdata;
		// Serialize the userdata for the cookie
		$cookie_data = $this->_serialize($cookie_data);
		$cookie_data = $cookie_data.md5($cookie_data.$this->encryption_key);
        cookie::set($this->sess_cookie_name, $cookie_data, $this->expiration);
        return ;
	}

	// --------------------------------------------------------------------

	/**
	 * Serialize an array
	 *
	 * This function first converts any slashes found in the array to a temporary
	 * marker, so when it gets unserialized the slashes will be preserved
	 *
	 * @access	private
	 * @param	array
	 * @return	string
	 */
	function _serialize($data)
	{
		if (is_array($data))
		{
			foreach ($data as $key => $val)
			{
				$data[$key] = str_replace('\\', '{{slash}}', $val);
			}
		}
		else
		{
			$data = str_replace('\\', '{{slash}}', $data);
		}

		return serialize($data);
	}

	// --------------------------------------------------------------------

	/**
	 * Unserialize
	 *
	 * This function unserializes a data string, then converts any
	 * temporary slash markers back to actual slashes
	 *
	 * @access	private
	 * @param	array
	 * @return	string
	 */
	function _unserialize($data)
	{
		$data = unserialize(stripslashes_deep($data));

		if (is_array($data))
		{
			foreach ($data as $key => $val)
			{
				$data[$key] = str_replace('{{slash}}', '\\', $val);
			}

			return $data;
		}

		return str_replace('{{slash}}', '\\', $data);
	}

	// --------------------------------------------------------------------

	/**
	 * Garbage collection
	 *
	 * This deletes expired session rows from database
	 * if the probability percentage is met
	 *
	 * @access	public
	 * @return	void
	 */
	function _sess_gc(){
		if ($this->savepath != 'db') return;
		srand(time());
		if ((rand() % 100) < $this->gc_probability){
			$expire = $this->now - $this->sess_expiration;
			Db::getInstance()->delete($this->dbtable,"last_activity < {$expire}");
			//log_message('debug', 'Session garbage collection performed.');
		}
	}
	static public function get($sess_name=''){
		$_this = getInstance('session','factory');
		return $_this->userdata($sess_name);
	}
	static public function set($sess_name,$value=''){
		$_this = getInstance('session','factory');
		if (is_null($value)) return $_this->unset_userdata($sess_name);
		return $_this->userdata($sess_name,$value);
	}
	static public function del($sess_name){
		return self::set($sess_name, NULL);
	}
}
// END Session Class

/* End of file Session.php */
/* Location: ./system/libraries/Session.php */