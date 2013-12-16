PHP REST Server
===============

##Features

1. Automatically generated resources (tables and table structure) based on incomming data.
1. Routes are automatically generated from the database. Apart from the initial config.php edit, you won't need to modify the code.
1. Supports multiple databases MySQL 5+ SQLite 3.6.19+ PostgreSQL 8+ CUBRUD.
1. Can be accessed in subdirectories (Does not _have_ to be located on a different vhost / webroot)
1. Simple ACL System
1. Supports where, limit, order queries
1. Pure JSON API Server. User does not need to use the ``emulate_http`` in Backbone.js or other similar front-end frameworks
1. Lightweight. Response around 10 - 20 ms per request under untweaked PHP.

##Installation

1. Install [Composer](http://getcomposer.org/doc/00-intro.md#installation-nix).
1. Download and extract / clone this repo into your htdocs directory.
1. In the directory run ``composer install --prefer-dist --no-dev``.
1. Change the configs in config.php.default and rename it to config.php.
1. Go To: http://yourapiserver/ to make sure everything is ok. The response should be something like this : ``{"error":false,"data":"OK","status":200}``.

##Usage

1. Go To: http://yourapiserver/manage to manage your Api server.
1. Create new package by going to http://[installationpath]/manage.
1. Once created, the routes under http://[installationpath]/[packagename]/[resourcename] will be activated
1. [resourcename] will be automatically generated for you.

##Routes

1. **List Records** >> (GET) http://[installationpath]/[packagename]/[tableName]
1. **Get One** >> (GET) http://[installationpath]/[packagename]/[tableName]/[id]
1. **Create New** >> (POST) http://[installationpath]/[packagename]/[tableName]
1. **Update** >> (PUT) http://[installationpath]/[packagename]/[tableName]/[id]
1. **Delete** >> (DELETE) http://[installationpath]/[packagename]/[tableName]/[id]

Example

Let's say you create an application (package) called 'uberpackage' and you want to retrieve all users. The request should be:
``http://api.uberapp.com/uberapp/users``

*List Records is subject to the default limit as in the Querying::order section below.*

##Querying

Queries are appended as query strings to the List Records (GET) request.

###Where

Key : ``where``

Example : http://api.uberapp.com/uberapp/users?where=[["id",">",23],["name","like","%adam"]]

Generates : ``select all from users where id > 23 and name like '%adam';``

*Right now only 'and' is supported*

###Order

Key : ``order``

Example : http://api.uberapp.com/uberapp/users?order=[["age","desc"],["name","asc"]]

Generates : ``select all from users order by age desc, name asc;``

*Note that if you did not pass the second parameter in an order array, it will default to asc. The following syntax is valid*

http://api.uberapp.com/uberapp/users?order=[["age"]]

Generates : ``select all from users order by age asc;``

###Limit

Key : ``limit``

Params : [$start,$length]

Example : http://api.uberapp.com/uberapp/users?limit=[0,25]

Generates : ``select all from users limit 0, 25;``

*By default, all queries will return only 25 rows, you can change the default limit in config.php $config['default_sql_limit'] variable*

###Chained

Queries can be chained as per example below:

Example : http://api.uberapp.com/uberapp/users?where=[["age",">",18]]&order=[["age","desc"],["name"]]&limit=[0,50]

Generates : ``select * from users where age > 18 order by age desc, name asc limit 0, 50;``

##Access Control List (ACL)

This REST service offers very simple ACL system where you can set ACL per package level. You can define whether any user are able to list (and query), create, update or remove a record in any combinations. You can also disable the whole package by unchecking the 'Enabled' checkbox in /manage.

##List of HTTP Responses

* 200 : (OK) Request was successfully performed
* 201 : (Created) A new entry was created (During the POST method)
* 400 : (Bad Request) Either the package has not yet been created, or the package is marked disabled or the method is disabled. (View ACL Section).
* 403 : (Forbidden) The client end did not send the authentication token (Auth-Token) inside the header or the Auth-Token is invalid.
* 404 : (Not Found) The record does not exist. May happen during the Get (GET:/packageName/resourceName/id) One request or Update (PUT:/packageName/resourceName/id) request
* 500 : (Server Error) PHP Farted. Shouldn't Happen. Check Server Logs.
* 503 : (Service Unavailable) For rate limiting.

##Data Structure

The server responds in the following data format:

```json
{
	"error" : false,
	"data" : [],
	"status" : 200
}

```

##Rate Limiting

You can set a limit on the number of request a user can make. The requests are set per package, per user, per hour. Upon hitting the limit, the REST Service will return a 503 Service Unavailable HTTP Error. Refer to the response headers ``Api-Utilization`` and ``Api-Limit`` To view the utilized requests in the current hour and the limit set on the package respectivly.

##Authentication Token

A token can be generated via the management page. Once generated and tagged to a resource. All request with invalid or missing Auth-Token header, will directly return a 403 Forbidden HTTP Error.

###Sample Backbone Auth Token Implementation

The below example demonstrates usage of the 'Auth-Token' header.

```javascript
var _sync = Backbone.sync;
Backbone.sync = function(method, model, options) {
	options.beforeSend = function(xhr) {
		xhr.setRequestHeader('Auth-Token' , [generated_token]);
	}
	_sync.call(this,method,model,options);
}

```

##Example Usage

View the ``templates/_manage.html`` and ``js/manage.js`` to view API usage with ``Backbone.js``

##Todos

1. Auto sync (Client data to be persistant on client side, can sync automatically from the server when there are new data)
