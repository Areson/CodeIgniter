<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
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
 * bundled with this package in the files license.txt / license.rst.  It is
 * also available through the world wide web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to obtain it
 * through the world wide web, please send an email to
 * licensing@ellislab.com so we can send you a copy immediately.
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2006 - 2012 EllisLab, Inc.
 * @license		http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @link		http://codeigniter.com
 * @since		Version 2.0
 * @filesource
 */

/**
 * CodeIgniter Session Class
 *
 * The user interface defined by EllisLabs, now with puggable drivers to manage different storage mechanisms.
 * By default, the cookie session driver will load, but the 'sess_driver' config/param item (see above) can be
 * used to specify the 'native' driver, or any other you might create.
 * Once loaded, this driver setup is a drop-in replacement for the former CI_Session library, taking its place as the
 * 'session' member of the global controller framework (e.g.: $CI->session or $this->session).
 * In keeping with the CI_Driver methodology, multiple drivers may be loaded, although this might be a bit confusing.
 * The CI_Session library class keeps track of the most recently loaded driver as "current" to call for driver methods.
 * Ideally, one driver is loaded and all calls go directly through the main library interface. However, any methods
 * called through the specific driver will switch the "current" driver to itself before invoking the library method
 * (which will then call back into the driver for low-level operations). So, alternation between two drivers can be
 * achieved by specifying which driver to use for each call (e.g.: $this->session->native->set_userdata('foo', 'bar');
 * $this->session->cookie->userdata('foo'); $this->session->native->unset_userdata('foo');). Notice in the previous
 * example that the _native_ userdata value 'foo' would be set to 'bar', which would NOT be returned by the call for
 * the _cookie_ userdata 'foo', nor would the _cookie_ value be unset by the call to unset the _native_ 'foo' value.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Sessions
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/sessions.html
 */
class CI_Mutex extends CI_Driver_Library {

	public $params = array();
	protected $current = NULL;

	/**
	 * CI_Session constructor
	 *
	 * The constructor loads the configured driver ('sess_driver' in config.php or as a parameter), running
	 * routines in its constructor, and manages flashdata aging.
	 *
	 * @param	array	Configuration parameters
	 * @return	void
	 */
	public function __construct(array $params = array())
	{
		$CI =& get_instance();

		log_message('debug', 'CI_Mutex Class Initialized');

		// Get valid drivers list
		$this->valid_drivers = array(
			'Mutex_flock',
		   	'Mutex_semaphore'
		);
		$key = 'mutex_valid_drivers';
		$drivers = isset($params[$key]) ? $params[$key] : $CI->config->item($key);
		if ($drivers)
		{
			is_array($drivers) OR $drivers = array($drivers);

			// Add driver names to valid list
			foreach ($drivers as $driver)
			{
				if ( ! in_array(strtolower($driver), array_map('strtolower', $this->valid_drivers)))
				{
					$this->valid_drivers[] = $driver;
				}
			}
		}

		// Get driver to load
		$key = 'mutex_driver';
		$driver = isset($params[$key]) ? $params[$key] : $CI->config->item($key);
		if ( ! $driver)
		{
			$driver = 'flock';
		}

		if ( ! in_array('mutex'.strtolower($driver), array_map('strtolower', $this->valid_drivers)))
		{
			$this->valid_drivers[] = 'Mutex_'.$driver;
		}

		// Save a copy of parameters in case drivers need access
		$this->params = $params;

		// Load driver and get array reference
		$this->load_driver($driver);

		log_message('debug', 'CI_Mutex routines successfully run');
	}

	// ------------------------------------------------------------------------

	/**
	 * Loads session storage driver
	 *
	 * @param	string	Driver classname
	 * @return	object	Loaded driver object
	 */
	public function load_driver($driver)
	{
		// Save reference to most recently loaded driver as library default and sync userdata
		$this->current = parent::load_driver($driver);
		return $this->current;
	}

	// ------------------------------------------------------------------------

	/**
	 * Select default session storage driver
	 *
	 * @param	string	Driver classname
	 * @return	void
	 */
	public function select_driver($driver)
	{
		// Validate driver name
		$lowername = strtolower(str_replace('CI_', '', $driver));
		if (in_array($lowername, array_map('strtolower', $this->valid_drivers)))
		{
			// See if driver is loaded
			$child = str_replace($this->lib_name.'_', '', $driver);
			if (isset($this->$child))
			{
				// See if driver is already current
				if ($this->$child !== $this->current)
				{
					// Make driver current and sync userdata
					$this->current = $this->$child;
					$this->userdata =& $this->current->get_userdata();
				}
			}
			else
			{
				// Load new driver
				$this->load_driver($child);
			}
		}
	}

	// ------------------------------------------------------------------------
	public function lock($name, $block = TRUE)
	{
		return $this->current->lock($name, $block);
	}
	
	public function unlock($name)
	{
		$this->current->unlock($name);
	}

	public function isLocked($name)
	{
		return $this->current->isLocked($name);
	}
	
	public function unlock_all()
	{
		$this->current->unlock_all();
	}
}

// ------------------------------------------------------------------------

/**
 * CI_Session_driver Class
 *
 * Extend this class to make a new CI_Session driver.
 * A CI_Session driver basically manages an array of name/value pairs with some sort of storage mechanism.
 * To make a new driver, derive from (extend) CI_Session_driver. Overload the initialize method and read or create
 * session data. Then implement a save handler to write changed data to storage (sess_save), a destroy handler
 * to remove deleted data (sess_destroy), and an access handler to expose the data (get_userdata).
 * Put your driver in the libraries/Session/drivers folder anywhere in the loader paths. This includes the
 * application directory, the system directory, or any path you add with $CI->load->add_package_path().
 * Your driver must be named CI_Session_<name>, and your filename must be Session_<name>.php,
 * preferably also capitalized. (e.g.: CI_Session_foo in libraries/Session/drivers/Session_foo.php)
 * Then specify the driver by setting 'sess_driver' in your config file or as a parameter when loading the CI_Session
 * object. (e.g.: $config['sess_driver'] = 'foo'; OR $CI->load->driver('session', array('sess_driver' => 'foo')); )
 * Already provided are the Native driver, which manages the native PHP $_SESSION array, and
 * the Cookie driver, which manages the data in a browser cookie, with optional extra storage in a database table.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Sessions
 * @author		EllisLab Dev Team
 */
abstract class CI_Mutex_driver extends CI_Driver {

	protected $CI;

	/**
	 * Constructor
	 *
	 * Gets the CI singleton, so that individual drivers
	 * don't have to do it separately.
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->CI =& get_instance();
	}

	// ------------------------------------------------------------------------

	/**
	 * Decorate
	 *
	 * Decorates the child with the parent driver lib's methods and properties
	 *
	 * @param	object	Parent library object
	 * @return	void
	 */
	public function decorate($parent)
	{
		// Call base class decorate first
		parent::decorate($parent);

		// Call initialize method now that driver has access to $this->_parent
		$this->initialize();
	}

	// ------------------------------------------------------------------------

	/**
	 * __call magic method
	 *
	 * Handles access to the parent driver library's methods
	 *
	 * @param	string	Library method name
	 * @param	array	Method arguments (default: none)
	 * @return	mixed
	 */
	public function __call($method, $args = array())
	{
		// Make sure the parent library uses this driver
		$this->_parent->select_driver(get_class($this));
		return parent::__call($method, $args);
	}

	// ------------------------------------------------------------------------

	/**
	 * Initialize driver
	 *
	 * @return	void
	 */
	protected function initialize()
	{
		// Overload this method to implement initialization
	}

	// ------------------------------------------------------------------------

	abstract public function lock($name, $block = TRUE);

	// ------------------------------------------------------------------------

	/**
	 * Destroy the current session
	 *
	 * Clean up storage for this session - it has been terminated.
	 * The child class MUST implement this abstract method!
	 *
	 * @return	void
	 */
	abstract public function unlock($name);

	// ------------------------------------------------------------------------

	/**
	 * Regenerate the current session
	 *
	 * Regenerate the session ID.
	 * The child class MUST implement this abstract method!
	 *
	 * @param	bool	Destroy session data flag (default: false)
	 * @return	void
	 */
	abstract public function isLocked($name);

	// ------------------------------------------------------------------------

	/**
	 * Get a reference to user data array
	 *
	 * Give array access to the main CI_Session object.
	 * The child class MUST implement this abstract method!
	 *
	 * @return	array	Reference to userdata
	 */
	abstract public function unlock_all();

}

/* End of file Mutex.php */
/* Location: ./system/libraries/Mutex/Mutex.php */