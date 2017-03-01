var User = Backbone.Model.extend({

  defaults: function(){
    return {
      user_id: '',
      display_name : '',
      user_email: ''
    };
  },

  sync: function(){
  }

});
