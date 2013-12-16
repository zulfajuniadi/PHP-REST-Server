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
require_once('config.php');

if(file_exists('config.php.default'))
	die('Please remove the config.php.default.');

require_once('utils.php');

/* make sure admin pass has changed */

if($config['password'] === 'admin')
	die('Change the password in config.php');

/* setup database */

use RedBean_Facade as R;
R::setup($config['DBConn'], $config['DBUser'], $config['DBPass']);

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

$app->get('/:package/:name', 'API', function ($package, $name) use ($r) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageExists($package) && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	$beans = R::findAll($tableName);
	$data = R::exportAll($beans);
	return $r->respond(200, $data);
});

$app->get('/:package/:name/query', 'API', function ($package, $name) use ($r) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageExists($package) && $tableName !== 'managepackages') {
		return $r->respond(400, 'BAD REQUEST', true);
	}
	die(print_r($_GET));
	$beans = R::findAll($tableName);
	$data = R::exportAll($beans);
	return $r->respond(200, $data);
});

$app->get('/:package/:name/:id', 'API', function ($package, $name, $id) use ($r) {
	$tableName = $r->genTableName($package, $name);
	if(!$r->packageExists($package) && $tableName !== 'managepackages') {
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
	if(!$r->packageExists($package) && $tableName !== 'managepackages') {
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
	if(!$r->packageExists($package) && $tableName !== 'managepackages') {
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
	if(!$r->packageExists($package) && $tableName !== 'managepackages') {
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
