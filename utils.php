<?php

use RedBean_Facade as R;
class Util
{
	/**
	 * The \Slim\Slim instance
	 * @var \Slim\Slim
	 */
	private $app;

	/**
	 * Cleanup names to be compatible to be used with Redbean
	 * @param  string $string The string to be stripped
	 * @return string         The string after stripped
	 */
	private function cleanup($string) {
		return strtolower((preg_replace('/[^0-9a-zA-Z]+/', '', $string)));
	}

	/**
	 * Checks the database for the existance of the table inside the database, and whether modifications can be made on
	 * the table based on the $state variable. if called without the $state variable, this method will check whether the
	 * whole resource is disabled.
	 * @param  string $package The package name
	 * @param  string $state   The state of the action: list, insert, update, remove
	 * @return boolean         Whether the system can go ahead with next logic
	 */
	public function packageOK($package, $state = null) {
		$stateStr = ($state) ?  'and `' . $state . '` = "true"' : '';
		$packages = R::find('managepackages', ' name = ? and enabled = "true" ' . $stateStr, array($this->cleanup($package)));
		if(count($packages) > 0) {
			return true;
		}
		return false;
	}

	public function serialize($array) {
		foreach ($array as $row => $columns) {
			foreach ($columns as $key => $value) {
				if(is_array($value) || is_object($value)) {
					$array[$row][$key] = json_encode($value);
				} else {
					$array[$row][$key] = $value;
				}
			}
		}
		return $array;
	}

	public function unserialize($array) {
		foreach ($array as $row => $columns) {
			foreach ($columns as $key => $value) {
				if($key === 'id') {
					$array[$row][$key] = (int) $value;
				} else {
					try {
						if(preg_match('/\{|\[/',$value) === 1) {
							$decodedValue = json_decode($value);
							if($decodedValue === null) {
								$array[$row][$key] = $value;
							} else {
								$array[$row][$key] = json_decode($value);
							}
						}
					} catch (Exception $e) {
						$array[$row][$key] = $value;
					}
				}
			}
		}
		return $array;
	}

	public function genTableName($package, $name) {
		$package = $this->cleanup($package);
		$name = $this->cleanup($name);
		return $package . $name;
	}

	public function checkTokenHeader($package, $token) {
		if(is_null($token))
			$package = R::findOne('managepackages', ' name = ? and api is null', array($this->cleanup($package)));
		else
			$package = R::findOne('managepackages', ' name = ? and api = ?', array($this->cleanup($package), $token));
		if($package) {
			return true;
		}
		return false;
	}

	public function checkLimit($package) {
		$thisHour = floor(time() / 60 / 60);
		if(isset($_SESSION['requestHour']) && $_SESSION['requestHour'] == $thisHour) {
			$_SESSION['requestCount'] ++;
		} else {
			$_SESSION['requestHour'] = $thisHour;
			$_SESSION['requestCount'] = 1;
		}
		$package = R::findOne('managepackages', ' name = ?', array($this->cleanup($package)));
		$this->app->response()->header('Api-Utilization', $_SESSION['requestCount']);
		$this->app->response()->header('Api-Limit', $package->rate);
		if($package->rate >= $_SESSION['requestCount']) {
			return true;
		}
		return false;
	}

	public function respond($code, $msg = '', $error = FALSE) {
		$this->app->render($code ,array(
			'error' => $error,
			'data' => $msg
		));
	}

	private $hooks = array();

	public function loadHooks() {
		try {
			foreach (glob("hooks/*.php") as $filename) {
		    	include $filename;
			}
		} catch (Exception $e) {

		}
	}

	public function issetHook($package, $resource, $hook) {
		if(isset($this->hooks[$package][$resource][$hook]))
			return is_callable($this->hooks[$package][$resource][$hook]);
		return false;
	}

	public function fireHook($package, $resource, $hook) {
		$arguments = func_get_args();
		$arguments = array_slice($arguments, 3);
		return call_user_func_array($this->hooks[$package][$resource][$hook], $arguments);
	}

	public function fireHookIfExists($package, $resource, $hook) {
		$arguments = func_get_args();
		if($this->issetHook($package, $resource, $hook)) {
			return call_user_func_array(array($this,'fireHook'), $arguments);
		}
		return array_slice($arguments, 3)[0];
	}

	public function registerHook($package, $resource, $hook, $callback) {
		if(is_callable($callback)) {
			if(!isset($this->hooks[$package])) {
				$this->hooks[$package] = array();
			}
			if(!isset($this->hooks[$package][$resource])) {
				$this->hooks[$package][$resource] = array();
			}
			$this->hooks[$package][$resource][$hook] = $callback;
		}
	}

	private static $instance;

	public static function getInstance() {
		return self::$instance;
	}

	public function __construct(\Slim\Slim $app) {
		$this->app = $app;
		static::$instance = $this;
	}
}
