<?php

/* Start Sessions */

session_cache_limiter(false);
session_start();
session_write_close();

/* define root uri */

$ROOT_URI = explode('/', $_SERVER['PHP_SELF']);
array_pop($ROOT_URI);
$ROOT_URI = implode('/', $ROOT_URI);
define('ROOT_URI', $ROOT_URI);

/* require external files */

if(!file_exists('vendor' . DIRECTORY_SEPARATOR . 'autoload.php'))
	die('Run composer update first. View https://github.com/zulfajuniadi/PHP-REST-Server for more info');
require_once('vendor' . DIRECTORY_SEPARATOR . 'autoload.php');



if(!file_exists('config.php'))
	die('config.php not found. Have you renamed the config.php.default to config.php?');

$config = array();
require_once('config.php');

$default_config = array();
$default_config['debug'] = true;
$default_config['session_expiry'] = 60 * 60 * 4;
$default_config['username'] = 'admin';
$default_config['password'] = 'admin';
$default_config['timezone'] = 'UTC';
$default_config['db_conn'] = 'sqlite:db/database.sqlite3';
$default_config['db_user'] = null;
$default_config['db_pass'] = null;
$default_config['default_sql_limit'] = array(0, 25);
$default_config['rate_limit'] = 100;
$config = array_merge($default_config, $config);

if(file_exists('config.php.default') && $config['debug'] === false)
	die('Please remove the config.php.default.');

require_once('utils.php');

/* make sure admin pass has changed */

if($config['password'] === 'admin')
	die('Change the password in config.php');

/* setup database */

use RedBean_Facade as R;
R::setup($config['db_conn'], $config['db_user'], $config['db_pass']);

/* app start */

if($config['debug'] === false) {
	ini_set('display_errors', '0');
}

$app = new \Slim\Slim(array(
	'debug' => $config['debug'],
	'templates.path' => 'templates'
));

/* Middlewares */

function API(){
	$app = \Slim\Slim::getInstance();
	$app->view(new \JsonApiView());
	$app->add(new \JsonApiMiddleware());
}

function AUTH() {
	$app = \Slim\Slim::getInstance();
	if(!isset($_SESSION['authenticated']) || !isset($_SESSION['expires'])  || $_SESSION['expires'] < (time())) {
		return $app->redirect( ROOT_URI . '/login');
	}
}

$r = new Util($app);

/* Management Routes */

$app->get('/manage', 'AUTH', function() use ($app) {
	return $app->render("_manage.html");
});

$app->get('/login', function() use ($app) {
	return $app->render("_login.html");
});

$app->get('/logout', function() use ($app) {
	session_start();
	session_destroy();
	session_write_close();
	return $app->redirect( ROOT_URI . "/login");
});

$app->post('/login', function() use ($app, $config) {
	$username = $app->request->params('username');
	$password = $app->request->params('password');
	if($username === $config['username'] && $password === $config['password']) {
		session_start();
		$_SESSION['authenticated'] = true;
		$_SESSION['expires'] = time() + $config['session_expiry'];
		session_write_close();
		return $app->redirect( ROOT_URI . '/manage');
	}
	$app->redirect( ROOT_URI . '/login');
});

$app->get('/', 'API', function() use ($r){
	$r->respond(200, 'OK');
});


/* REST API Routes */

$app->get('/:package/:name', 'API', function ($package, $name) use ($r, $app, $config) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageOK($package, 'list') && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}

	$query = R::$f->begin()->select('*')->from($tableName);

	$wheres = json_decode($app->request()->params('where'));

	if(is_array($wheres)) {
		$count = 0;
		foreach ($wheres as $where) {
			if($count === 0)
				$query->where($where[0] . ' ' . $where[1] . ' ?');
			else
				$query->and($where[0] . ' ' . $where[1] . ' ?');
			$query->put($where[2]);
			$count++;
		}
	}
	$orders = json_decode($app->request()->params('order'));
	if(is_array($orders)) {
		$order_by = [];
		foreach ($orders as $order) {
			if(!isset($order[1]))
				$order[1] = 'asc';
			$order_by[] = $order[0] . ' ' . $order[1];
		}
		$order_by = implode(',', $order_by);
		$query->order_by($order_by);
	}

	$limits = json_decode($app->request()->params('limit'));
	if(is_array($limits)) {
		$query->limit(implode(', ', $limits));
	} else if (is_array($config['default_sql_limit']) && $tableName !== 'managepackages') {
		$query->limit(implode(', ', $config['default_sql_limit']));
	}
	$data = $query->get();
	return $r->respond(200, $data);
});

$app->get('/:package/:name/:id', 'API', function ($package, $name, $id) use ($r) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageOK($package, 'list') && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	$bean = R::load($tableName, $id);
	$data = $bean->export();
	if($data['id'] !== 0) {
		return $r->respond(200, $data[0]);
	}
	return $r->respond(404, 'NOT FOUND', true);
});

$app->post('/:package/:name', 'API', function ($package, $name) use ($r, $app) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageOK($package, 'insert') && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	$request = (array) json_decode($app->request()->getBody());
	$bean = R::dispense($tableName);
	$bean->import($request);
	R::store($bean);
	return $r->respond(201, $bean->export());
});

$app->put('/:package/:name/:id', 'API', function ($package, $name, $id) use ($r, $app) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageOK($package, 'update') && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	$request = (array) json_decode($app->request()->getBody());
	$bean = R::load($tableName, $id);
	$data = $bean->export();
	if($data['id'] === 0) {
		return $r->respond(404, 'NOT FOUND', true);
	}
	$bean->import($request);
	R::store($bean);
	return $r->respond(200, $bean->export());
});

$app->delete('/:package/:name/:id', 'API', function ($package, $name, $id) use ($r) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageOK($package ,'remove') && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	$bean = R::load($tableName, $id);
	$data = $bean->export();
	if($data['id'] === 0) {
		return $r->respond(404, 'NOT FOUND', true);
	}
	R::trash($bean);
	return $r->respond(200, 'DELETED');
});

$app->run();
