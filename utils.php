<?php

use RedBean_Facade as R;

class Util
{
	private $app;

	private function cleanup($string) {
		return strtolower((preg_replace('/[^0-9a-zA-Z]+/', '', $string)));
	}

	public function packageOK($package, $state) {
		$packages = R::find('managepackages', ' name = ? and enabled = "true" and `' . $state . '` = "true" ', array($this->cleanup($package)));
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
				try {
					if(json_decode($value) !== null) {
						$array[$row][$key] = json_decode($value);
					}
				} catch (Exception $e) {
					$array[$row][$key] = $value;
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
		session_start();
		if(isset($_SESSION['requestHour']) && $_SESSION['requestHour'] == $thisHour) {
			$_SESSION['requestCount'] ++;
		} else {
			$_SESSION['requestHour'] = $thisHour;
			$_SESSION['requestCount'] = 1;
		}
		session_write_close();
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

	public function __construct(\Slim\Slim $app) {
		$this->app = $app;
	}
}
