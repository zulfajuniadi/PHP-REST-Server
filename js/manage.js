(function(w){

	Handlebars.registerHelper('trueFalse', function(val, options){
		if(val === 'true') {
			return options.fn();
		}
		return options.inverse();
	})

	var packageModel = Backbone.Model.extend({
		parse : function(response) {
			if(response.data && response.status)
				return response.data;
			return response;
		},
	});

	var packageCollection = Backbone.Collection.extend({
		parse : function(response) {
			return response.data;
		},
		url : function() {
			return 'manage/packages'
		},
		model : packageModel
	});

	var packages = w.packages = new packageCollection();

	var editId = false;

	var tableView = Backbone.View.extend({
		collection : packages,
		template : Handlebars.compile($('#tbodyTemplate').html()),
		events : {
			"click .delete": function(e){
				var id = $(e.currentTarget).data('id');
				if(id) {
					this.collection.get(id).destroy();
				}
			},
			"click .edit": function(e){
				var id = $(e.currentTarget).data('id');
				if(id) {
					editId = id;
					var model = this.collection.get(id);
					$('#name').val(model.attributes.name);
					if(model.attributes.enabled === 'true') {
						$("#enabled").prop( "checked", true );
					} else {
						$("#enabled").prop( "checked", false );
					}
				}
			}
		},
		initialize : function() {
			var self = this;
			this.collection.on('add', function(){
				self.render();
			});
			this.collection.on('change', function(){
				self.render();
			});
			this.collection.on('remove', function(){
				self.render();
			});
			this.collection.fetch();
		},
		render : function() {
			this.$el.html(this.template({ data : _.pluck(this.collection.toArray(), 'attributes') || []}));
		}
	});

	var table = w.table = new tableView({
		el : $('#tbody')[0]
	});

	$('#form').on('submit', function(){
		var enabled = 'false';
		var name = $('#name').val().replace(/[^0-9A-Za-z]/g,'').toLowerCase();
		if($('#enabled').is(':checked'))
			enabled = 'true';
		if(editId) {
			packages.get(editId).save({
				name : name,
				enabled : enabled
			});
		} else {
			packages.create({
				name : name,
				enabled : enabled
			});
		}
		editId = false;
		this.reset();
		return false;
	});
})(this);