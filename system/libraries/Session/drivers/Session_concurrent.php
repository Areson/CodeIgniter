<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.2.4 or newer
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Open Software License version 3.0
 *
 * This source file is subject to the Open Software License (OSL 3.0) that is
 * bundled with this package in the files license.txt / license.rst. It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@ellislab.com so we can send you a copy immediately.
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2012, EllisLab, Inc. (http://ellislab.com/)
 * @license		http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

/**
 * Concurrent request session management driver
 *
 * This is a driver that allows for CodeIgniter to provide fine
 * and course locking mechanisms for use during sessions. This
 * allows for safe reading and writing of session data, as well
 * as the creation of session and application specfic critical
 * sections.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Sessions
 * @author		Areson
 * @link		http://codeigniter.com/user_guide/libraries/sessions.html
 */
class CI_Session_concurrent extends CI_Session_driver {

	/**
	 * Whether to encrypt the session cookie
	 *
	 * @var bool
	 */
	public $sess_encrypt_cookie		= FALSE;

	/**
	 * Name of the database table in which to store sessions
	 *
	 * @var string
	 */
	public $sess_table_name			= '';

	/**
	 * Name of the database table in which to store static session data
	 * 
	 * @var string
	 */
	 public $sess_static_table_name	= '';
	 
	/**
	 * Name of the database table in which to store user data
	 * 
	 * @var string
	 */
	 public $sess_userdata_table_name = '';
	 
	/**
	 * Length of time (in seconds) for sessions to expire
	 *
	 * @var int
	 */
	public $sess_expiration			= 7200;
	
	/**
	 * Length of time (in seconds) for multisessions to expire
	 * 
	 * @var int
	 */
	public $sess_multi_expiration	= 15;

	/**
	 * Whether to kill session on close of browser window
	 *
	 * @var bool
	 */
	public $sess_expire_on_close	= FALSE;

	/**
	 * Whether to match session on ip address
	 *
	 * @var bool
	 */
	public $sess_match_ip			= FALSE;

	/**
	 * Whether to match session on user-agent
	 *
	 * @var bool
	 */
	public $sess_match_useragent	= TRUE;

	/**
	 * Name of session cookie
	 *
	 * @var string
	 */
	public $sess_cookie_name		= 'ci_session';

	/**
	 * Session cookie prefix
	 *
	 * @var string
	 */
	public $cookie_prefix			= '';

	/**
	 * Session cookie path
	 *
	 * @var string
	 */
	public $cookie_path				= '';

	/**
	 * Session cookie domain
	 *
	 * @var string
	 */
	public $cookie_domain			= '';

	/**
	 * Whether to set the cookie only on HTTPS connections
	 *
	 * @var bool
	 */
	public $cookie_secure			= FALSE;

	/**
	 * Whether cookie should be allowed only to be sent by the server
	 *
	 * @var bool
	 */
	public $cookie_httponly 		= FALSE;

	/**
	 * Interval at which to update session
	 *
	 * @var int
	 */
	public $sess_time_to_update		= 300;

	/**
	 * Key with which to encrypt the session cookie
	 *
	 * @var string
	 */
	public $encryption_key			= '';

	/**
	 * Timezone to use for the current time
	 *
	 * @var string
	 */
	public $time_reference			= 'local';

	/**
	 * Session data
	 *
	 * @var array
	 */
	public $userdata				= array();

	/**
	 * User data locks
	 */
	protected $userdata_locks		= array();
	 
	/**
	 * 
	 */
	protected $cached_userdata		= array();
	 
	/**
	 * 
	 */ 
	protected $unique_id			= NULL;	
	
	/**
	 * Current time
	 *
	 * @var int
	 */
	public $now;

	/**
	 * Default userdata keys
	 *
	 * @var	array
	 */
	protected $defaults = array(
		'session_id' => NULL,
		'ip_address' => NULL,
		'user_agent' => NULL,
		'last_activity' => NULL
	);

	/**
	 * Data needs DB update flag
	 *
	 * @var	bool
	 */
	protected $data_dirty = FALSE;

	/**
	 * Initialize session driver object
	 *
	 * @return	void
	 */
	protected function initialize()
	{
		// Set all the session preferences, which can either be set
		// manually via the $params array or via the config file
		$prefs = array(
			'sess_encrypt_cookie',
			'sess_use_database',
			'sess_table_name',
			'sess_static_table_name',
			'sess_userdata_table_name',
			'sess_expiration',
			'sess_multi_expiration',
			'sess_expire_on_close',
			'sess_match_ip',
			'sess_match_useragent',
			'sess_cookie_name',
			'cookie_path',
			'cookie_domain',
			'cookie_secure',
			'cookie_httponly',
			'sess_time_to_update',
			'time_reference',
			'cookie_prefix',
			'encryption_key'
		);

		foreach ($prefs as $key)
		{
			$this->$key = isset($this->_parent->params[$key])
				? $this->_parent->params[$key]
				: $this->CI->config->item($key);
		}

		if ($this->encryption_key === '')
		{
			show_error('In order to use the Ajax Session driver you are required to set an encryption key in your config file.');
		}

		// Load the string helper so we can use the strip_slashes() function
		$this->CI->load->helper('string');

		// Load the mutex class so we can aquire locks
		$this->CI->load->library("Mutex");

		// Do we need encryption? If so, load the encryption class
		if ($this->sess_encrypt_cookie === TRUE)
		{
			$this->CI->load->library('encrypt');
		}

		// Check for database
		if ($this->sess_table_name === '')
		{
			show_error('In order to use the Concurrent Session driver you are required to set the name of the session database table.');
		}

		if ($this->sess_static_table_name === '')
		{
			show_error('In order to use the Concurrent Session driver you are required to set the name of the static session data database table.');
		}
		
		if ($this->sess_userdata_table_name === '')
		{
			show_error('In order to use the Concurrent Session driver you are required to set the name of the session userdata database table.');
		}
		
		// Load database driver
		$this->CI->load->database();

		// Register shutdown function
		register_shutdown_function(array($this, '_update_db'));

		// Set the "now" time. Can either be GMT or server time, based on the config prefs.
		// We use this to set the "last activity" time
		$this->now = $this->_get_time();

		// Set the session length. If the session expiration is
		// set to zero we'll set the expiration two years from now.
		if ($this->sess_expiration === 0)
		{
			$this->sess_expiration = (60*60*24*365*2);
		}

		// Set the cookie name
		$this->sess_cookie_name = $this->cookie_prefix.$this->sess_cookie_name;

		// Run the Session routine. If a session doesn't exist we'll
		// create a new one. If it does, we'll update it.
		if ( ! $this->_sess_read())
		{
			$this->_sess_create();
		}
		else
		{
			$this->_sess_update();
		}

		// Delete expired sessions if necessary
		$this->_sess_gc();
	}

	// ------------------------------------------------------------------------

	/**
	 * Write the session data
	 *
	 * @return	void
	 */
	public function sess_save()
	{
		$this->data_dirty = TRUE;
		
		// Write the cookie
		//$this->_set_cookie();
	}

	// ------------------------------------------------------------------------

	/**
	 * Destroy the current session
	 *
	 * @return	void
	 */
	public function sess_destroy()
	{		
		// Kill the session DB row
		$this->_multisess_destroy();

		// Kill the cookie
		$this->_setcookie($this->sess_cookie_name, addslashes(serialize(array())), ($this->now - 31500000),
		$this->cookie_path, $this->cookie_domain, 0);

		// Kill session data
		$this->userdata = array();
	}

	// ------------------------------------------------------------------------

	public function lock_userdata($item, $block = TRUE)
	{
		if (!is_array($item))
		{
			$item = array($item);
		}
		
		// Remove default items
		$item = array_diff_key($item, $this->defaults);
		
		// Determine which items needs to be locked
		$lock_items = array();
		
		foreach($item as $key)
		{
			if ($this->CI->mutex->isLocked($this->unique_id.".".$key) === FALSE)
			{
				$lock_items[] = $key;
			}
		}
		
		if ($this->lock_session_mutex($lock_items, $block) === FALSE)
		{
			$this->unlock_session_mutex($lock_items);
			return FALSE;
		}

		// Get new userdata
		$this->fetch_userdata($lock_items);
		
		foreach ($item as $val)
		{
			$this->userdata_locks[$val] = $val;
		}
		
		return TRUE;
	}
	
	public function unlock_userdata($item, $block = TRUE)
	{
		if (!is_array($item))
		{
			$item = array($item);
		}
		
		// Strip the default items
		$userdata = array_diff_key($item, $this->defaults);
		
		// Unset the lock flags
		foreach ($userdata as $key)
		{
			if (isset($this->userdata_locks[$key]) === TRUE)
			{
				unset($this->userdata_locks[$key]);
			}
		}
		
		// Write values for what we are unlocking
		$this->commit_userdata($userdata);
		
		// Unset the values
		foreach ($userdata as $key)
		{
			if (isset($this->userdata[$key]) === TRUE)
			{
				unset($this->userdata[$key]);
			}
		}
		
		$this->unlock_session_mutex($userdata, $block);
	}
	
	// ------------------------------------------------------------------------
	
	public function lock_session_mutex($item, $block = TRUE)
	{
		if (!is_array($item))
		{
			$item = array($item);
		}
		
		foreach($item as $key)
		{
			if ($this->CI->mutex->lock($this->unique_id.".".$key) === FALSE)
			{
				log_message('error', "Session lock for '".$key."' failed.");
				$this->unlock_session_mutex($item);
				return FALSE;
			}
		}
		
		return TRUE;
	}
	
	public function unlock_session_mutex($item, $block = TRUE)
	{
		if (!is_array($item))
		{
			$item = array($item);
		}
		
		foreach($item as $key)
		{
			$this->CI->mutex->unlock($this->unique_id.".".$key);
		}
	}
	
	// ------------------------------------------------------------------------

	public function lock_application_mutex($item, $block = TRUE)
	{
		if (!is_array($item))
		{
			$item = array($item);
		}
		
		foreach($item as $key)
		{
			if ($this->CI->mutex->lock($key) === FALSE)
			{
				$this->unlock_application_mutex($item);
				return FALSE;
			}
		}
		
		return TRUE;
	}
	
	public function unlock_application_mutex($item, $block = TRUE)
	{
		if (!is_array($item))
		{
			$item = array($item);
		}
		
		foreach($item as $key)
		{
			$this->CI->mutex->unlock($key);
		}
	}
	
	// ------------------------------------------------------------------------
	
	public function lock($block = TRUE)
	{
		return($this->lock_application_mutex($this->unique_id, $block));
	}
	
	public function unlock($block = TRUE)
	{
		$this->unlock_application_mutex($this->unique_id, $block);
	}
	
	/**
	 * Regenerate the current session
	 *
	 * Regenerate the session id
	 *
	 * @param	bool	Destroy session data flag (default: false)
	 * @return	void
	 */
	public function sess_regenerate($destroy = FALSE)
	{
		// Check destroy flag
		if ($destroy)
		{
			// Destroy old session and create new one
			$this->sess_destroy();
			$this->_sess_create();
		}
		else
		{
			// Just force an update to recreate the id
			$this->_sess_update(TRUE);
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Get a reference to user data array
	 *
	 * @return	array	Reference to userdata
	 */
	public function &get_userdata()
	{
		return $this->userdata;
	}

	// ------------------------------------------------------------------------

	/**
	 * Fetch the current session data if it exists
	 *
	 * @return	bool
	 */
	protected function _sess_read()
	{
		// Fetch the cookie
		$session = $this->CI->input->cookie($this->sess_cookie_name);

		// No cookie? Goodbye cruel world!...
		if ($session === NULL)
		{
			log_message('error', 'A session cookie was not found.');
			return FALSE;
		}

		// Check for encryption
		if ($this->sess_encrypt_cookie === TRUE)
		{
			// Decrypt the cookie data
			$session = $this->CI->encrypt->decode($session);
		}
		else
		{
			// Encryption was not used, so we need to check the md5 hash in the last 32 chars
			$len	 = strlen($session)-32;
			$hash	 = substr($session, $len);
			$session = substr($session, 0, $len);

			// Does the md5 hash match? This is to prevent manipulation of session data in userspace
			if ($hash !== md5($session.$this->encryption_key))
			{
				log_message('error', 'The session cookie data did not match what was expected. This could be a possible hacking attempt.');
				$this->sess_destroy();
				return FALSE;
			}
		}

		// Unserialize the session array
		$session = $this->_unserialize($session);

		// Is the session data we unserialized an array with the correct format?
		if ( ! is_array($session) OR ! isset($session['session_id'], $session['ip_address'], $session['user_agent'], $session['last_activity']))
		{
			$this->sess_destroy();
			return FALSE;
		}

		// Is the session current?
		if (($session['last_activity'] + $this->sess_expiration) < $this->now)
		{
			$this->sess_destroy();
			return FALSE;
		}

		// Does the IP match?
		if ($this->sess_match_ip === TRUE && $session['ip_address'] !== $this->CI->input->ip_address())
		{
			$this->sess_destroy();
			return FALSE;
		}

		// Does the User Agent Match?
		if ($this->sess_match_useragent === TRUE &&
			trim($session['user_agent']) !== trim(substr($this->CI->input->user_agent(), 0, 120)))
		{
			$this->sess_destroy();
			return FALSE;
		}

		//Grab a lock on this session to perform mulisession logic
		IF ($this->lock_application_mutex($session['session_id']) === FALSE)
		{
			log_message("error", "The initial lock during session read could not be aquired.");
			$this->sess_destroy();
			return FALSE;
		}
	
		// Fetch the session data from the database
		$this->CI->db->where('S.session_id = ', $session['session_id']);

		if ($this->sess_match_ip === TRUE)
		{
			$this->CI->db->where('ip_address', $session['ip_address']);
		}

		if ($this->sess_match_useragent === TRUE)
		{
			$this->CI->db->where('user_agent', $session['user_agent']);
		}

		$this->CI->db->from($this->sess_table_name.' AS S');
		$this->CI->db->join($this->sess_static_table_name.' AS SD', 'S.unique_id = SD.unique_id');
		$this->CI->db->select('S.unique_id, session_id, last_activity, ip_address, user_agent, session_old');

		// Is caching in effect? Turn it off
		$db_cache = $this->CI->db->cache_on;
		$this->CI->db->cache_off();

		$query = $this->CI->db->limit(1)->get();
		
		// Was caching in effect?
		if ($db_cache)
		{
			// Turn it back on
			$this->CI->db->cache_on();
		}

		// No result? Kill it!
		if ($query->num_rows() === 0)
		{
			$this->sess_destroy();
			$this->unlock_application_mutex($session['session_id']);
			return FALSE;
		}
		
		// Load the data into our session
		$row = $query->row();
		
		foreach ($this->defaults as $key)
		{
			if (isset($row->$key))
			{
				$session[$key] = $row->$key;
			}
		}
		
		// Save a copy of the unique id
		$this->unique_id = $row->unique_id;
		
		// Aquire a lock on our unique identifier
		$this->lock();
		
		// Is this an old session that is being updated?
		if ($row->session_old === 1)
		{
			// Has the window for the old session id expired?
			if (($session['last_activity'] + $this->sess_multi_expiration) < $this->now)
			{
				$this->_multisess_destroy($row->old_session_id);
			
				// Release the lock on the old session id
				$this->unlock_application_mutex($session['session_id']);
				return FALSE;
			}
			else
			{
				// Check to see if the session has expired
				if (($session['last_activity'] + $this->sess_expiration) < $this->now)
				{
					$this->sess_destroy();
					// Release the lock for the old session_id
					$this->unlock_application_mutex($session['session_id']);
					return FALSE;
				}
			}
		}

		// Release the lock on the old session_id
		$this->unlock_application_mutex($session['session_id']);
		
		// Session is valid!
		$this->userdata = $session;
		return TRUE;
	}

	// ------------------------------------------------------------------------

	/**
	 * Create a new session
	 *
	 * @return	void
	 */
	protected function _sess_create()
	{
		// Create a unique id
		$this->unique_id = $this->_make_sess_id();
		
		$session_data = array(
			'ip_address'		=> $this->CI->input->ip_address(),
			'user_agent'		=> substr($this->CI->input->user_agent(), 0, 120),
		);
		
		// Add the static session data
		$this->CI->db->insert($this->sess_static_table_name, $session_data);
		
		// Set the new unique id
		$this->unique_id = $this->CI->db->insert_id();
		
		$session_map = array(
			'session_id'		=> $this->_make_sess_id(),
			'session_old'		=> 0,
			'last_activity'		=> $this->now,
			'unique_id'			=> $this->unique_id
		);
		
		// Add a session record
		$this->CI->db->insert($this->sess_table_name, $session_map);
				
		// Setup user data
		$this->userdata = array_intersect_key(array_merge($session_map, $session_data), $this->defaults);

		// Write the cookie
		$this->_set_cookie();
	}

	// ------------------------------------------------------------------------

	/**
	 * Update an existing session
	 *
	 * @param	bool	Force update flag (default: false)
	 * @return	void
	 */
	protected function _sess_update($force = FALSE)
	{
		// We only update the session every five minutes by default (unless forced)
		if ( ! $force && ($this->userdata['last_activity'] + $this->sess_time_to_update) >= $this->now)
		{
			$this->unlock();
			return;
		}

		// Update last activity to now
		$this->userdata['last_activity'] = $this->now;

		// Save the old session id so we know which DB record to update
		$old_sessid = $this->userdata['session_id'];

		// Grab a lock on the old session id
		if ($this->lock_application_mutex($old_sessid) === FALSE)
		{
			log_message('error', 'Failed to lock old session id during regeneration.');
			$this->unlock();
			return;
		}

		// Generate a new session id
		$new_sessid = $this->_make_sess_id();

		// Get the custom userdata, leaving out the defaults
		// (which get stored in the cookie)
		$userdata = array_diff_key($this->userdata, $this->defaults);
		
		// Did we find any custom data?
		$old_userdata = $this->_serialize($userdata);

		// Update the last_activity and prevent_update fields in the DB
		$this->CI->db->update($this->sess_table_name, array(
				 'last_activity' => $this->now,
				 'session_old' => 1
		), array('session_id' => $old_sessid));

		// Unlock the old session
		$this->unlock_application_mutex($old_sessid);

		// Set the new session id
		$this->userdata['session_id'] = $new_sessid;

		// Set up activity and data fields to be set
		// If we don't find custom data, user_data will remain an empty string
		$set = array(
			'last_activity' => $this->now,
			'session_id' => $this->userdata['session_id'],
			'unique_id' => $this->unique_id,
			'session_old' => 0
		);

		// Write the new session id to the database 
		$this->CI->db->insert($this->sess_table_name, $set);

		// Release the session lock so that old sessions can continue to process
		$this->unlock();

		// Write the cookie
		$this->_set_cookie();
	}

	// ------------------------------------------------------------------------

	/**
	 * Update database with current data
	 *
	 * This gets called from the shutdown function and also
	 * registered with PHP to run at the end of the request
	 * so it's guaranteed to update even when a fatal error
	 * occurs. The first call makes the update and clears the
	 * dirty flag so it won't happen twice.
	 *
	 * @return	void
	 */
	public function _update_db()
	{
		// Check for locked userdata
		$userdata = array_diff_key($this->userdata_locks, $this->defaults);

		if (count($userdata) > 0)
		{
			if ($this->unlock_userdata(array_keys($userdata)) === FALSE)
			{
				log_message('error', 'Session failed to automatically commit user data during session close.');
			}
			else
			{
				log_message('debug', 'CI_Session Data Saved To DB');
			}
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Generate a new session id
	 *
	 * @return	string	Hashed session id
	 */
	protected function _make_sess_id()
	{
		$new_sessid = '';
		do
		{
			$new_sessid .= mt_rand(0, mt_getrandmax());
		}
		while (strlen($new_sessid) < 32);

		// To make the session ID even more secure we'll combine it with the user's IP
		$new_sessid .= $this->CI->input->ip_address();

		// Turn it into a hash and return
		return md5(uniqid($new_sessid, TRUE));
	}

	// ------------------------------------------------------------------------

	/**
	 * Get the "now" time
	 *
	 * @return	int	 Time
	 */
	protected function _get_time()
	{
		if ($this->time_reference === 'local' OR $this->time_reference === date_default_timezone_get())
		{
			return time();
		}

		$datetime = new DateTime('now', new DateTimeZone($this->time_reference));
		sscanf($datetime->format('j-n-Y G:i:s'), '%d-%d-%d %d:%d:%d', $day, $month, $year, $hour, $minute, $second);

		return mktime($hour, $minute, $second, $month, $day, $year);
	}

	// ------------------------------------------------------------------------

	/**
	 * Write the session cookie
	 *
	 * @return	void
	 */
	protected function _set_cookie()
	{
		// Get userdata (only defaults if database)
		$cookie_data = array_intersect_key($this->userdata, $this->defaults);

		// Serialize the userdata for the cookie
		$cookie_data = $this->_serialize($cookie_data);

		$cookie_data = ($this->sess_encrypt_cookie === TRUE)
			? $this->CI->encrypt->encode($cookie_data)
			// if encryption is not used, we provide an md5 hash to prevent userside tampering
			: $cookie_data.md5($cookie_data.$this->encryption_key);

		$expire = ($this->sess_expire_on_close === TRUE) ? 0 : $this->sess_expiration + time();

		// Set the cookie
		$this->_setcookie($this->sess_cookie_name, $cookie_data, $expire, $this->cookie_path, $this->cookie_domain,
			$this->cookie_secure, $this->cookie_httponly);
	}

	// ------------------------------------------------------------------------

	/**
	 * Set a cookie with the system
	 *
	 * This abstraction of the setcookie call allows overriding for unit testing
	 *
	 * @param	string	Cookie name
	 * @param	string	Cookie value
	 * @param	int	Expiration time
	 * @param	string	Cookie path
	 * @param	string	Cookie domain
	 * @param	bool	Secure connection flag
	 * @param	bool	HTTP protocol only flag
	 * @return	void
	 */
	protected function _setcookie($name, $value = '', $expire = 0, $path = '', $domain = '', $secure = FALSE, $httponly = FALSE)
	{
		setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
	}

	// ------------------------------------------------------------------------

	/**
	 * Serialize an array
	 *
	 * This function first converts any slashes found in the array to a temporary
	 * marker, so when it gets unserialized the slashes will be preserved
	 *
	 * @param	mixed	Data to serialize
	 * @return	string	Serialized data
	 */
	protected function _serialize($data)
	{
		if (is_array($data))
		{
			array_walk_recursive($data, array(&$this, '_escape_slashes'));
		}
		elseif (is_string($data))
		{
			$data = str_replace('\\', '{{slash}}', $data);
		}

		return serialize($data);
	}

	// ------------------------------------------------------------------------

	/**
	 * Escape slashes
	 *
	 * This function converts any slashes found into a temporary marker
	 *
	 * @param	string	Value
	 * @param	string	Key
	 * @return	void
	 */
	protected function _escape_slashes(&$val, $key)
	{
		if (is_string($val))
		{
			$val = str_replace('\\', '{{slash}}', $val);
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Unserialize
	 *
	 * This function unserializes a data string, then converts any
	 * temporary slash markers back to actual slashes
	 *
	 * @param	mixed	Data to unserialize
	 * @return	mixed	Unserialized data
	 */
	protected function _unserialize($data)
	{
		$data = @unserialize(strip_slashes(trim($data)));

		if (is_array($data))
		{
			array_walk_recursive($data, array(&$this, '_unescape_slashes'));
			return $data;
		}

		return is_string($data) ? str_replace('{{slash}}', '\\', $data) : $data;
	}

	// ------------------------------------------------------------------------

	/**
	 * Unescape slashes
	 *
	 * This function converts any slash markers back into actual slashes
	 *
	 * @param	string	Value
	 * @param	string	Key
	 * @return	void
	 */
	protected function _unescape_slashes(&$val, $key)
	{
		if (is_string($val))
		{
	 		$val= str_replace('{{slash}}', '\\', $val);
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Garbage collection
	 *
	 * This deletes expired session rows from database
	 * if the probability percentage is met
	 *
	 * @return	void
	 */
	protected function _sess_gc()
	{
		$probability = ini_get('session.gc_probability');
		$divisor = ini_get('session.gc_divisor');

		srand(time());
		if ((mt_rand(0, $divisor) / $divisor) < $probability )
		{
			// No GC at the moment...active record class won't let
			// us use joins in deletes, so we need another method
			// to make this work in a cross-platform fashion.
			
			// Blank values associated with expired sessions
			//$this->CI->db->join($this->session)
			
			// Remove expired sessions
			$expire = $this->now - $this->sess_expiration;
			$this->CI->db->delete($this->sess_table_name, 'last_activity < '.$expire);
			/*
			// Remove the old static data
			$this->CI->db->join($this->sess_table_name.' AS SM', 'SM.unique_id = SD.unique_id', "left");
			$this->CI->db->delete($this->sess_static_table_name.' AS SD', 'SM.unique_id IS NULL');
			
			// Remove old user data
			$this->CI->db->join($this->sess_table_name.' AS SM', 'SM.unique_id = SV.unique_id', "left");
			$this->CI->db->delete($this->sess_static_table_name.' AS SV', 'SM.unique_id IS NULL');

			log_message('debug', 'Session garbage collection performed.');*/
		}
	}	
	 
	 // ------------------------------------------------------------------------

	/**
	 * Destroy the entry in the database for the multisession
	 * 
	 * @return void
	 */
	protected function _multisess_destroy()
	{
		$session_id = isset($this->userdata['session_id'])?$this->userdata['session_id']:NULL;
		
		// Kill the session DB row
		if (!is_null($session_id))
		{
			$this->CI->db->where('session_id', $session_id);
			$this->CI->db->delete($this->sess_table_name);
			$this->data_dirty = FALSE;
		}
	}
	
	// ------------------------------------------------------------------------
	
	public function fetch_userdata($items = array())
	{
		// Get the latest copy of the data
		$this->CI->db->select('name, value');
		$this->CI->db->from($this->sess_static_table_name.' AS SD');
		$this->CI->db->join($this->sess_userdata_table_name.' AS SV', 'SD.unique_id = SV.unique_id');
		$this->CI->db->where('SD.unique_id', $this->unique_id);

		$query = $this->CI->db->get();
		
		// Placed the data in a temporary holding array
		$tempdata = array();
		if (count($items) === 0)
			$tempdata = &$this->userdata;
		
		foreach ($query->result() as $row)
		{
			// Only write to userdata if we previously did not have a lock on the value
			if (isset($this->userdata_locks[$row->name]) === FALSE)
			{
				$tempdata[$row->name] = $row->value;
			}
			
			// Add to the cache if ot previously there
			if (isset($this->cached_userdata[$row->name]) === FALSE)
			{
				$this->cached_userdata[$row->name] = $row->value;
			}
		}
		
		// Move the requested items into userdata
		if (count($items) > 0)
		{
			foreach($items as $val)
			{
				if (isset($tempdata[$val]))
				{
					$this->userdata[$val] = $tempdata[$val];
				}
			}
		}
	}

	// ------------------------------------------------------------------------
	
	public function commit_userdata($items = array())
	{			
		// Determine which items to be committed have changed
		$commit_updates = array();
		$commit_inserts = array();
		
		// If no items were passed, update all values
		if (count($items) === 0)
		{
			// Prevent update on values that we stil have locks on, and any default values
			$userdata = array_diff_key($this->userdata, $this->defaults, $this->userdata_locks);
			
			foreach ($userdata as $key => $val)
			{
				$items[] = $key;
			}
		}

		foreach($items as $key)
		{
			if (isset($this->userdata[$key]) === TRUE)
			{
				if (isset($this->cached_userdata[$key]) === FALSE)
				{
					$commit_inserts[] = array('unique_id' => $this->unique_id, 'name' => $key, 'value' => $this->userdata[$key]);
				}
				else if ($this->cached_userdata[$key] !== $this->userdata[$key])
				{
					$commit_updates[] = array('name' => $key, 'value' => $this->userdata[$key]);
				}
			}
		}
	
		// Add new items
		if (count($commit_inserts) > 0)
		{
			$this->CI->db->insert_batch($this->sess_userdata_table_name, $commit_inserts);
		}
		
		// Update existing items
		if (count($commit_updates) > 0)
		{
			foreach ($commit_updates as $val)
			{
				$this->CI->db->where('unique_id', $this->unique_id);
				$this->CI->db->where('name', $val["name"]);
				$ret=$this->CI->db->update($this->sess_userdata_table_name, $val);
				
				if(!$ret)
					echo $this->CI->db->_error_message();
			}
		}
	}
}

/* End of file Session_cookie.php */
/* Location: ./system/libraries/Session/drivers/Session_cookie.php */