<?php

$r = Util::getInstance();

/* called after getting the data from the database and just before sending it to the user */
$r->registerHook('example', 'test', 'afterGet', function($data) {
	// do something with the data
	return $data;
	// send it to the user
});

$r->registerHook('example', 'test', 'beforeInsert', function($data) {
	// do something with the data
	return $data;
	// send it to the user
});

$r->registerHook('example', 'test', 'afterInsert', function($data) {
	// do something with the data
	// data already inserted, nothing to return
});

$r->registerHook('example', 'test', 'beforeUpdate', function($data) {
	// do something with the data
	return $data;
	// send it to the user
});

$r->registerHook('example', 'test', 'afterUpdate', function($data) {
	// do something with the data
	// data already updated, nothing to return
});

$r->registerHook('example', 'test', 'beforeDelete', function($data) {
	// do something with the data
	return true;
	// return true to allow the system to delete or false to return a 403:forbidden error;
});

$r->registerHook('example', 'test', 'afterDelete', function($data) {
	// do something with the data
	// data already deleted, nothin to return
});