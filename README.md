PHP REST Server
===============

##Installation

1. Install [http://getcomposer.org/doc/00-intro.md#installation-nix](Composer).
1. Download and extract / clone this repo into your htdocs directory.
1. In the directory run ``composer install --prefer-dist --no-dev``.
1. Change the configs in config.php.default and rename it to config.php.


##Usage

1. Create new package by going to http://[installationpath]/manage.
1. Once created, the routes under http://[installationpath]/[packagename]/[resourcename] will be activated
1. [resourcename] will be automatically generated for you.

##Routes

1. **List All** GET:http://[installationpath]/[packagename]/[tableName]
1. **Get One**  GET:http://[installationpath]/[packagename]/[tableName]/[id]
1. **Create New**  POST:http://[installationpath]/[packagename]/[tableName]
1. **Update**  PUT:http://[installationpath]/[packagename]/[tableName]/[id]
1. **Delete**  DELETE:http://[installationpath]/[packagename]/[tableName]/[id]

##Examples

View the ``templates/_manage.html`` and ``js/manage.js`` to view API usage with ``Backbone.js``