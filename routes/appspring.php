<?php

$r = Util::getInstance(); // Utils class utils.php
$app = \Slim\Slim::getInstance(); // slim (Framework) instance: http://www.slimframework.com/
use RedBean_Facade as R; // redbean (database ORM) instance : http://www.redbeanphp.com/ zulfa

// Built-in middlewares
// API : Required for json response, otherwise do not use $r->respond
// CHECKTOKEN : User required to have the valid auth token header
// RATELIMITER : Rate Limits are enforced

$app->get('/appspring/testnewroute', 'API', 'CHECKTOKEN', 'RATELIMITER', function() use ($r) {
    $r->respond(200, array('response' => 'route is alive!'));
});