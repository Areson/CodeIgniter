<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class CI_Mutex_sqlite extends CI_Mutex_driver
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
	}
	
	function __destruct()
	{
		foreach($this->mutexes as $key => $val)
		{
			if ($val["locked"] === TRUE)
			{
				$val["mutex"]->exec("END TRANSACTION");
			}
			
			$val["mutex"] = NULL;
			
			// Attempt to delete the SQLite database
			$path = $val["filepath"];
			
			if (file_exists($path))
			{
				unlink($path);
			}
		}
	} 
	
	public function lock($name, $block = TRUE)
	{
		if (!isset($this->mutexes[$name]))
		{
			$db = $this->_connect($name);
			$path = $this->mutex_path.$name.".mutex";
			$this->mutexes[$name] = array("mutex" => $db, "locked" => FALSE, "filepath" => realpath($path));			
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
			
			$mutex->setAttribute(PDO::ATTR_TIMEOUT, 0);
			$err = NULL;
			
			while (TRUE)
			{
				// Did the database get removed between attempts to lock?
				if($err[1] === 10)
				{
					$mutex = $this->_connect($name);
				}

				$res = $mutex->exec("BEGIN EXCLUSIVE TRANSACTION");
				
				if ($res !== FALSE)
				{
					$data["locked"] = TRUE;
					break;
				}
				else
				{
					$err = $mutex->errorInfo();
					if ($data["total_backoff"] >= $block * 1000000)
					{
						$mutex->exec("END TRANSACTION");
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
				$this->mutexes[$name]["mutex"]->exec("END TRANSACTION");
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
		return (new PDO('sqlite:'.$path, NULL, NULL, array(PDO::ATTR_PERSISTENT => FALSE)));
	}
}



?>