<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class CI_Mutex_flock extends CI_Mutex_driver
{
	protected $CI;
	
	protected $mutexes = array();
	
	protected $mutex_path = '';
	
	protected $mutex_max_lock = 60;
	
	function initialize()
	{
		$this->CI =& get_instance();
		
		$params = array(
			"mutex_path",
			"mutex_max_lock"
		);
		
		foreach($params AS $key)
		{
			$this->$key = $this->CI->config->item($key);
		}
		
		// Register our shutdown function so locks get closed properly
		register_shutdown_function(array($this, '__destruct'));
	}
	
	function __destruct()
	{
		foreach($this->mutexes as $key => $val)
		{
			if ($val["locked"] === TRUE)
			{
				flock($val["mutex"], LOCK_UN);
				fclose($val["mutex"]);
				unlink($val["filepath"]);
			}
		}
	} 
	
	public function lock($name, $block = TRUE)
	{
		if (!isset($this->mutexes[$name]))
		{
			$path = realpath($this->mutex_path)."/".$name.".mutex";
			$this->mutexes[$name] = array("mutex" => NULL, "locked" => FALSE, "filepath" => $path);			
		}
		
		if ($this->mutexes[$name]["locked"] !== TRUE)
		{
			$data = &$this->mutexes[$name];
			$mutex = &$data["mutex"];
		
			// Reset the backoff timers
			$data["backoff"] = 1000;
			$data["total_backoff"] = 0;
			
			if ($block === TRUE)
			{
				$block = $this->mutex_max_lock;
			}
			else if ($block === FALSE)
			{
				$block = 0;
			}
			else if (!is_numeric($block))
			{
				$block = $this->mutex_max_lock;
			}
			
			while (TRUE)
			{
				// Attempt to open the file
				$mutex = $this->_connect($name);
				
				if ($mutex !== FALSE)
				{
					$res = flock($mutex, LOCK_EX);
				}
				else
				{
					$res = FALSE;
				}
				
				
				if ($res !== FALSE)
				{
					$data["locked"] = TRUE;
					break;
				}
				else
				{
					if ($data["total_backoff"] >= $block * 1000000)
					{
						break;
					}
					else
					{
						$this->_backoff($data);
					}
				}
			}
		}

		return($data["locked"]);
	}
	
	public function isLocked($name)
	{
		if (isset($this->mutexes[$name]))
		{
			return($this->mutexes[$name]["locked"]);
		}
		else
		{
			return FALSE;
		}
	}
	
	public function unlock($name)
	{
		if($name == 'count')
			return;
		if (isset($this->mutexes[$name]))
		{
			if ($this->mutexes[$name]["locked"] === TRUE)
			{					
				flock($this->mutexes[$name]["mutex"], LOCK_UN);
				fclose($this->mutexes[$name]["mutex"]);
				
				// Destroy the lock file
				unlink($this->mutexes[$name]["filepath"]);
				
				$this->mutexes[$name]["locked"] = FALSE;
			}
		}
	}	
	
	public function unlock_all()
	{
		foreach($this->mutexes as $key)
		{
			$this->unlock($key);
		}
	}
	
	protected function _backoff(&$mutex)
	{		
		$duration = min($mutex["backoff"], 50000);
		$mutex["total_backoff"] += $duration;
		$mutex["backoff"] = $duration * 2;
		
		usleep($duration);
	}
	
	protected function _connect($name)
	{
		$path = $this->mutex_path.$name.".mutex";
		
		return @fopen($path, "x+");
	}
}



?>