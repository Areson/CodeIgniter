<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * There is a limited number of semaphores that can be open, as defined
 * here: http://php.net/manual/en/intro.sem.php
 * Usually this value is 6. If you go over this number,
 * the library will block indefinitely.
 */

class CI_Mutex_semaphore extends CI_Mutex_driver
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
				@sem_release($val["mutex"]);
				@sem_remove($val["mutex"]);
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

			//while (TRUE)
			for($i = 0; $i < 5; $i++)
			{
				// Attempt to open the file
				$mutex = $this->_connect($name);
				
				if ($mutex !== FALSE)
				{
					$res = @sem_acquire($mutex);
					$res = TRUE;
					echo "lock ".$name."\n";
				}
				else
				{
					$res = FALSE;
				}
				
				echo "|".$i."|";
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
				echo "unlock ".$name."\n";					
				@sem_release($this->mutexes[$name]["mutex"]);
				
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
		$id = $this->_ftok($this->mutex_path.$name.".mutex", "m");
		echo "*".$name."*".$id."*\n";

		if ($id === -1)
		{
			return FALSE;
		}
		else 
		{
			return sem_get($id, 1, 0666, 0);	
		}
	}
	
	protected function _ftok($name, $proj)
	{
		$filename = $name . (string) $proj;
        for ($key = array(); sizeof($key) < strlen($filename); $key[] = ord(substr($filename, sizeof($key), 1)));
	    
	    return array_sum($key);
	}
}



?>