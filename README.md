PHP REST Server
===============

##Features

1. Automatically generated resources (tables) based on incomming data.
1. Routes are automatically generated from the database. Apart from the initial setup, you won't need to modify the code.
1. Supports multiple databases MySQL 5+ SQLite 3.6.19+ PostgreSQL 8+ CUBRUD.

##Installation

1. Install [Composer](http://getcomposer.org/doc/00-intro.md#installation-nix).
1. Download and extract / clone this repo into your htdocs directory.
1. In the directory run ``composer install --prefer-dist --no-dev``.
1. Change the configs in config.php.default and rename it to config.php.


##Usage

1. Create new package by going to http://[installationpath]/manage.
1. Once created, the routes under http://[installationpath]/[packagename]/[resourcename] will be activated
1. [resourcename] will be automatically generated for you.

##Routes

1. **List All** >> GET:http://[installationpath]/[packagename]/[tableName]
1. **Get One** >> GET:http://[installationpath]/[packagename]/[tableName]/[id]
1. **Create New** >> POST:http://[installationpath]/[packagename]/[tableName]
1. **Update** >> PUT:http://[installationpath]/[packagename]/[tableName]/[id]
1. **Delete** >> DELETE:http://[installationpath]/[packagename]/[tableName]/[id]

##Examples

View the ``templates/_manage.html`` and ``js/manage.js`` to view API usage with ``Backbone.js``

##Todos

1. Query route (wheres, limits, orders)
1. Auto sync (Client data to be persistant on client side, can sync automatically from the server when there are new data)
