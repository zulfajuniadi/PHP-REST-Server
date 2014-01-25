<?php

/* Start Sessions */
session_start();

/* define root uri */

$ROOT_URI = explode('/', $_SERVER['PHP_SELF']);
array_pop($ROOT_URI);
$ROOT_URI = implode('/', $ROOT_URI);
define('ROOT_URI', $ROOT_URI);

/* define DS */

define('DS', DIRECTORY_SEPARATOR);

/* require external files */

if(!file_exists('vendor' . DS . 'autoload.php'))
	die('Run composer update first. View https://github.com/zulfajuniadi/PHP-REST-Server for more info');
require_once('vendor' . DS . 'autoload.php');

if(!file_exists('config.php'))
	die('config.php not found. Have you renamed the config.php.default to config.php?');

/* Require Config File */

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
R::$writer->setUseCache(false);
RedBean_OODBBean::setFlagBeautifulColumnNames(false);

/* app start */

if($config['debug'] === false) {
	ini_set('display_errors', '0');
}

$app = new \Slim\Slim(array(
	'debug' => $config['debug'],
	'templates.path' => 'templates'
));

/* Middlewares */

$r = new Util($app);

/* load hooks */

$r->loadHooks();


function API(){
	$app = \Slim\Slim::getInstance();
	$app->view(new \JsonApiView());
	$app->add(new \JsonApiMiddleware());
	$app->response->headers->set('Content-Type', 'application/json');
	$app->response->headers->set('Access-Control-Allow-Methods', 'GET,HEAD,POST,PUT,DELETE,OPTIONS');
	$app->response->headers->set('Access-Control-Allow-Headers', 'Auth-Token,Content-Type');
	$app->response->headers->set('Access-Control-Allow-Credentials', 'true');
	$uri = array_values(array_filter(explode('/', $app->request->getResourceUri())));
	$package = R::findOne('managepackages', ' name = ?', array($uri[0]));
	if($package) {
		$origin = $package->origin;

		if(!$origin) {
			$origin = 'http://localhost';
		}
	} else {
		$origin = 'http://localhost';
	}
	$app->response->headers->set("Access-Control-Allow-Origin", $origin);
}

function RATELIMITER() {
	$app = \Slim\Slim::getInstance();
	$uri = array_values(array_filter(explode('/', $app->request->getResourceUri())));
	$r = new Util($app);

	if($uri[0] === 'manage' && $uri[1] === 'packages') {
		return true;
	}
	if($r->checkLimit($uri[0]) === false) {
		return $r->respond(503, 'LIMIT EXCEEDED', false);
	}
}

function CHECKTOKEN() {
	$app = \Slim\Slim::getInstance();
	$uri = array_values(array_filter(explode('/', $app->request->getResourceUri())));
	$r = new Util($app);
	$token = $app->request->headers->get('Auth-Token');
	if($uri[0] === 'manage' && $uri[1] === 'packages') {
		return true;
	}

	if($r->checkTokenHeader($uri[0], $token) === false) {
		return $r->respond(403, 'UNAUTHORIZED', true);
	}

}

function AUTH() {
	$app = \Slim\Slim::getInstance();
	if(!isset($_SESSION['authenticated']) || !isset($_SESSION['expires'])  || $_SESSION['expires'] < (time())) {
		return $app->redirect( ROOT_URI . '/login');
	}
}

/* Test Route */

$app->get('/','API', function() use ($r){
	$r->respond(200, 'OK');
});

/* utilities */

$app->get('/:package/_sync','API','RATELIMITER', function($package) use($r, $app) {
	$tables = $app->request()->params('resources');
	$last_sync = $app->request()->params('last_sync');
	$new_last_sync = date('Y-m-d H:i:s');

	if(!$r->packageOK($package) || !$tables || !is_array($tables) || !$last_sync) {
		return $r->respond(400, 'BAD REQUEST', true);
	}

	$response = [];

	$tables = array_map(function($table) use ($package) {
		return $package . $table;
	}, $tables);

	foreach ($tables as $table) {
		$resource = substr($table, strlen($package));
		$response[$resource]['insert'] = array();
		$response[$resource]['update'] = array();
		$response[$resource]['remove'] = array();
		try {
			$queryData = R::$f->begin()
				->select('*')
				->from('syncmeta')
				->left_join($table . ' on ' . $table . '.id = syncmeta.row_id')
				->where('syncmeta.tableName = ? and syncmeta.timestamp > ?')
				->order_by('syncmeta.type')
				->put($table)
				->put($last_sync)
				->get();
		} catch (Exception $e) {
			$queryData = array();
		}

		foreach ($queryData as $row) {
			$data = array();
			$meta = array();
			foreach ($row as $column => $value) {
				if($column !== 'id' || $column !== 'type') {
					if ($column === 'row_id') {
						$data['id'] = (int) $value;
					} else if (in_array($column, array('timestamp','tableName', 'type'))) {
						$meta[$column] = $value;
					} else {
						$data[$column] = $value;
					}
				}
			}
			$oper = $row['type'];
			$response[$resource][$oper][] = array(
				'meta' => $meta,
				'data' => $data
			);
		}
	}
	return $r->respond(200, array('response' => $response, 'last_sync' => $new_last_sync));
});

/* Management Routes */

$app->get('/manage', 'AUTH', function() use ($app) {
	return $app->render("_manage.html");
});

$app->get('/login', function() use ($app) {
	return $app->render("_login.html");
});

$app->get('/logout', function() use ($app) {
	session_destroy();
	return $app->redirect( ROOT_URI . "/login");
});

$app->post('/login', function() use ($app, $config) {
	$username = $app->request->params('username');
	$password = $app->request->params('password');
	if($username === $config['username'] && $password === $config['password']) {
		$_SESSION['authenticated'] = true;
		$_SESSION['expires'] = time() + $config['session_expiry'];
		return $app->redirect( ROOT_URI . '/manage');
	}
	$app->redirect( ROOT_URI . '/login');
});

/* include predefined routes */

$path = array_filter(explode('/', $app->request->getPath()));
$path = array_shift($path);
$routesFilePath = 'routes' . DS . $path . '.php';

if(file_exists($routesFilePath))
	require_once($routesFilePath);

/* REST API Routes */

$app->get('/:package','API','CHECKTOKEN','RATELIMITER', function($package) use ($r){
	if(!$r->packageOK($package)) {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	$listOfTables = R::inspect();
	$data = array_filter($listOfTables, function($table) use ($package){
		return substr($table,0,strlen($package)) == $package;
	});
	$data = array_map(function($table) use ($package){
		return substr($table,strlen($package));
	}, $data);
	return $r->respond(200, array_values($data));
});

$app->get('/:package/:name','API','CHECKTOKEN','RATELIMITER', function ($package, $name) use ($r, $app, $config) {
	$tableName = $r->genTableName($package, $name);
	try {
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
	} catch (Exception $e) {
		$data = R::exportAll(R::findAll($tableName));
	}
	$data = $r->unserialize($data);
	$data = $r->fireHookIfExists($package, $name, 'afterGet', $data);
	if($data === false)
		return $r->respond(403, 'FORBIDDEN:HOOK', true);
	return $r->respond(200, $data);
});

$app->get('/:package/:name/:id','API','CHECKTOKEN', 'RATELIMITER', function ($package, $name, $id) use ($r) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageOK($package, 'list') && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	$data = R::findOne($tableName, 'id = ?', array($id));
	if($data) {
		$data = $r->unserialize(array($data->export()))[0];
		$data = $r->fireHookIfExists($package, $name, 'afterGet', array($data))[0];
		if($data === false)
			return $r->respond(403, 'FORBIDDEN:HOOK', true);
		return $r->respond(200, $data);
	}
	return $r->respond(404, 'NOT FOUND', true);
});

$app->post('/:package/:name','API','CHECKTOKEN', 'RATELIMITER', function ($package, $name) use ($r, $app) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageOK($package, 'insert') && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	$request = json_decode($app->request()->getBody());
	if(!is_array($request) && !is_object($request)) {
		return $r->respond(400, 'MALFORMED DATA', true);
	}
	$request = (array) $request;
	$data = R::dispense($tableName);
	$request = $r->fireHookIfExists($package, $name, 'beforeInsert', $request);
	if($request === false) {
		return $r->respond(403, 'FORBIDDEN:HOOK', true);
	}
	$requestData = $r->serialize(array($request))[0];
	foreach ($requestData as $key => $value) {
		$data->{$key} = $value;
	}
	$id = R::store($data);
	$sm = R::dispense('syncmeta');
	$sm->timestamp = date('Y-m-d H:i:s');
	$sm->tableName = $tableName;
	$sm->type = 'insert';
	$sm->row_id = $id;
	R::store($sm);
	$data = $r->unserialize(array($data->export()))[0];
	if($data) {
		$r->fireHookIfExists($package, $name, 'afterInsert', $data);
		return $r->respond(201, $data);
	}
	return $r->respond(500, 'ERROR WHILE INSERTING DATA', true);
});

$app->put('/:package/:name/:id','API','CHECKTOKEN', 'RATELIMITER', function ($package, $name, $id) use ($r, $app) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageOK($package, 'update') && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	$request = json_decode($app->request()->getBody());
	if(!is_array($request) && !is_object($request)) {
		return $r->respond(400, 'MALFORMED DATA', true);
	}
	$request = (array) $request;
	$data = R::findOne($tableName, 'id = ?', array($id));
	if($data) {
		$request = $r->fireHookIfExists($package, $name, 'beforeUpdate', $request, $data);
		if($request === false) {
			return $r->respond(403, 'FORBIDDEN:HOOK', true);
		}
		$requestData = $r->serialize(array($request))[0];
		foreach ($requestData as $key => $value) {
			$data->{$key} = $value;
		}
		R::store($data);
		$existingSyncMeta = R::findOne('syncmeta', 'where row_id = ? and tableName = ?', array($id, $tableName));
		if($existingSyncMeta) {
			$existingSyncMeta->type = 'update';
			$existingSyncMeta->timestamp = date('Y-m-d H:i:s');
			R::store($existingSyncMeta);
		}
		$data = $r->unserialize(array($data->export()));
		$data = array_shift($data);
		if($data) {
			$r->fireHookIfExists($package, $name, 'afterUpdate', $data);
			return $r->respond(200, $data);
		}
		return $r->respond(500, 'ERROR WHILE INSERTING DATA', true);
	}
	return $r->respond(404, 'NOT FOUND', true);
});

$app->delete('/:package/:name/:id','API','CHECKTOKEN', 'RATELIMITER', function ($package, $name, $id) use ($r) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageOK($package ,'remove') && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	$data = R::findOne($tableName, 'id = ?', array($id));
	if($data) {
		$existingSyncMeta = R::findOne('syncmeta', 'where row_id = ? and tableName = ?', array($id, $tableName));
		if($existingSyncMeta) {
			$existingSyncMeta->type = 'remove';
			$existingSyncMeta->timestamp = date('Y-m-d H:i:s');
			R::store($existingSyncMeta);
		}
		if($r->fireHookIfExists($package, $name, 'beforeRemove', $r->unserialize(array($data->export()))[0])) {
			R::trash($data);
			$r->fireHookIfExists($package, $name, 'afterRemove', $r->unserialize(array($data->export()))[0]);
			return $r->respond(200, 'DELETED');
		}
		return $r->respond(403, 'FORBIDDEN:HOOK', true);
	}
	return $r->respond(404, 'NOT FOUND', true);
});

/* Handle Options Route */

$app->options('/:any+','API',function () use ($app, $r) {
    return $r->respond(200);
});

/* default 404 and Error Handler */

$app->error('API',function (\Exception $e) use ($app) {
    return $r->respond(500, $e, true);
});

$app->notFound('API',function () use ($r) {
    return $r->respond(404, 'NOT FOUND', true);
});

$app->run();
