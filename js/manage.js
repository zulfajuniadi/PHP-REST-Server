(function(w) {

	Handlebars.registerHelper('trueFalse', function(val, options) {
		if (val === 'true') {
			return options.fn();
		}
		return options.inverse();
	});

	var editor;
    editor = ace.edit("hooks");
    editor.setTheme("ace/theme/github");
    editor.getSession().setMode("ace/mode/php");
	var updateEditor = function(packageName) {
		var template = "<?php\n\n$r = Util::getInstance(); // Utils class utils.php\n$app = \\Slim\\Slim::getInstance(); // slim (Framework) instance: http://www.slimframework.com/\nuse RedBean_Facade as R; // redbean (database ORM) instance : http://www.redbeanphp.com/\n\n/* called after getting the data from the database and just before sending it to the user */\n$r->registerHook('"+ packageName +"', 'resourceName', 'afterGet', function($data) {\n\t// do something with the data\n\treturn $data;\n\t// send it to the user\n});\n\n/* called before the data is inserted into the database. return false to deny insertion */\n$r->registerHook('"+ packageName +"', 'resourceName', 'beforeInsert', function($data) {\n\t// do something with the data\n\treturn $data;\n\t// send it to the user\n});\n\n/* called after the data has been inserted */\n$r->registerHook('"+ packageName +"', 'resourceName', 'afterInsert', function($data) {\n\t// do something with the data\n\t// data already inserted, nothing to return\n});\n\n/* called before the data is updated inside database. return false to deny update */\n$r->registerHook('"+ packageName +"', 'resourceName', 'beforeUpdate', function($newData, $oldData) {\n\t// do something with the data\n\treturn $newData;\n\t// send it to the user\n});\n\n/* called after the data has been updated */\n$r->registerHook('"+ packageName +"', 'resourceName', 'afterUpdate', function($newData, $oldData) {\n\t// do something with the data\n\t// data already updated, nothing to return\n});\n\n/* called before the data removed from the database. return true to allow deletion, return false to deny */\n$r->registerHook('"+ packageName +"', 'resourceName', 'beforeDelete', function($data) {\n\t// do something with the data\n\treturn true;\n\t// return true to allow the system to delete or false to return a 403:forbidden error;\n});\n\n/* called after the data has been removed from the database */\n$r->registerHook('"+ packageName +"', 'resourceName', 'afterDelete', function($data) {\n\t// do something with the data\n\t// data already deleted, nothin to return\n});"
		editor.setValue(template);
	}

	var packageModel = Backbone.Model.extend({
		parse: function(response) {
			if (response.data && response.status)
				return response.data;
			return response;
		},
	});

	var packageCollection = Backbone.Collection.extend({
		parse: function(response) {
			return response.data;
		},
		url: function() {
			return 'manage/packages'
		},
		model: packageModel
	});

	var packages = w.packages = new packageCollection();

	var editId = false;

	var tableView = Backbone.View.extend({
		collection: packages,
		template: Handlebars.compile($('#tbodyTemplate').html()),
		events: {
			"click .delete": function(e) {
				var id = $(e.currentTarget).data('id');
				if (id) {
					var model = this.collection.get(id);
					if (confirm('Are you sure you want to remove ' + model.attributes.name + '?')) {
						model.destroy();
						setTimeout(function(){
							$('#packageForm')[0].reset();
						}, 500);
					}
				}
			},
			"click .edit": function(e) {
				var id = $(e.currentTarget).data('id');
				if (id) {
					editId = id;
					var model = this.collection.get(id);
					$('#name').val(model.attributes.name);
					editor.setValue(model.attributes.hook);
					$('#api').val(model.attributes.api);
					$('#rate').val(model.attributes.rate);
					if (model.attributes.enabled === 'true') {
						$("#enabled").prop("checked", true);
					} else {
						$("#enabled").prop("checked", false);
					}
					if (model.attributes.list === 'true') {
						$("#list").prop("checked", true);
					} else {
						$("#list").prop("checked", false);
					}
					if (model.attributes.insert === 'true') {
						$("#insert").prop("checked", true);
					} else {
						$("#insert").prop("checked", false);
					}
					if (model.attributes.update === 'true') {
						$("#update").prop("checked", true);
					} else {
						$("#update").prop("checked", false);
					}
					if (model.attributes.remove === 'true') {
						$("#remove").prop("checked", true);
					} else {
						$("#remove").prop("checked", false);
					}
				}
			}
		},
		initialize: function() {
			var self = this;
			this.collection.on('add', function() {
				self.render();
			});
			this.collection.on('change', function() {
				self.render();
			});
			this.collection.on('remove', function() {
				self.render();
			});
			this.collection.fetch();
		},
		render: function() {
			this.$el.html(this.template({
				data: _.pluck(this.collection.toArray(), 'attributes') || []
			}));
		}
	});

	var table = w.table = new tableView({
		el: $('#tbody')[0]
	});

	$('#resetForm').click(function() {
		editId = null;
	});

	$('#genHooks').click(function(){
		var val = $('#name').val().replace(/[^0-9A-Za-z]/g, '').toLowerCase();
		if(val.length === 0) {
			alert('Input a package name first');
			$('#name').focus();
		} else {
			if(editor.getValue().length > 0) {
				if(confirm('Are you sure you want to generate the hooks? It will override the existing one.')) {
					updateEditor(val);
				}
			} else {
				updateEditor(val);
			}
		}
	});

	$('#genApiKey').click(function() {
		var text = "";
		var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		for (var i = 0; i < 24; i++)
			text += possible.charAt(Math.floor(Math.random() * possible.length));
		if ($('#api').val() !== '' && editId) {
			if (confirm('Are you sure you want to change the API Key?'))
				return $('#api').val(text);
			else
				return;
		}
		return $('#api').val(text);
	});

	$('#packageForm').on('submit', function() {
		var enabled = 'false';
		var list = 'false';
		var insert = 'false';
		var update = 'false';
		var remove = 'false';
		var hook = editor.getValue() || null;
		var name = $('#name').val().replace(/[^0-9A-Za-z]/g, '').toLowerCase();
		if ($('#enabled').is(':checked'))
			enabled = 'true';
		if ($('#list').is(':checked'))
			list = 'true';
		if ($('#insert').is(':checked'))
			insert = 'true';
		if ($('#update').is(':checked'))
			update = 'true';
		if ($('#remove').is(':checked'))
			remove = 'true';
		var data = {
			name: name,
			enabled: enabled,
			list: list,
			insert: insert,
			update: update,
			remove: remove,
			hook: hook,
			api: $('#api').val() || null,
			rate: parseInt($('#rate').val(), 10) || 0,
		};
		if (editId) {
			packages.get(editId).save(data);
		} else {
			packages.create(data);
		}
		editId = false;
		setTimeout(function(){
			$('#packageForm')[0].reset();
		}, 500);
		return false;
	});

	$('#packageForm').on('reset', function(){
		editor.setValue('');
	});
})(this);