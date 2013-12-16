<?php

use RedBean_Facade as R;

class Util
{
	private $app;

	private function cleanup($string) {
		return strtolower((preg_replace('/[^0-9a-zA-Z]+/', '', $string)));
	}

	public function packageOK($package, $state) {
		$packages = R::find('managepackages', ' name = ? and enabled = "true" and ' . $state . ' = "true" ', array($this->cleanup($package)));

		if(count($packages) > 0) {
			return true;
		}
		return false;
	}

	public function genTableName($package, $name) {
		$package = $this->cleanup($package);
		$name = $this->cleanup($name);
		return $package . $name;
	}

	public function respond($code, $msg = array(), $error = FALSE) {
		$this->app->render($code ,array(
			'error' => $error,
			'data' => $msg
		));
	}

	public function __construct(\Slim\Slim $app) {
		$this->app = $app;
	}
}
