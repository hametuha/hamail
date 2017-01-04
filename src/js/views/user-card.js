var UserCard = Backbone.View.extend({

  tagName: 'li',

  template: _.template(jQuery('#hamail-user-card').html()),

  events: {
    'click .remove': 'removeUser'
  },

  initialize: function(){
    this.listenTo(this.model, 'destroy', this.remove);
  },

  removeUser: function(e){
    e.preventDefault();
    this.model.destroy();
  },

  render: function(){
    this.$el.html(this.template(this.model.toJSON()));
    return this;
  }

});
