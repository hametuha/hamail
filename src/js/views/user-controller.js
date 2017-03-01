//=require ../models/user.js
//=require ../models/user-list.js
//=require ./user-card.js

/*global HamailRecipients:false*/

var UserController = Backbone.View.extend({

  el: jQuery('#hamail-users'),

  collection: null,

  $complete: null,

  events: {
    // 'searched #hamail-address-search': this.userFound
  },

  initialize: function () {
    _.bindAll(this, 'userFound', 'addUser', 'changeList');
    // Initialize model
    var collection = this.collection = new UserList(null);
    var $complete   = this.$complete = jQuery('#hamail-address-search');
    // Bind events
    this.listenTo(this.collection, 'add', this.addUser);
    this.listenTo(this.collection, 'update', this.changeList);
    $complete.on( 'searched', this.userFound );
    // Initialize collection
    _.each(HamailRecipients.users, function(user){
      var model = new User(user);
      this.collection.add(model);
    }, this);
    // Incremental search
    $complete.autocomplete({
      minLength: 1,
      source: HamailRecipients.search_endpoint,
      focus: function( event, ui ) {
        return false;
      },
      select: function( event, ui ) {
        switch ( ui.item.type ) {
          case 'user':
          case 'post':
            var model = new User({
              user_id: ui.item.id,
              display_name: ui.item.display_name,
              user_email: ui.item.data
            });
            collection.add(model);
            break;
          case 'term':
          default:
            $complete.trigger( 'searched', [ui.item] );
            break;
        }
        jQuery(this).val('');
        return false;
      }
    })
      .autocomplete( "instance" )._renderItem = function( ul, item ) {
      return jQuery( "<li>" )
        .append( "<div>" + item.label + "</div>" )
        .appendTo( ul );
    };
  },

  addUser: function (user) {
    var view = new UserCard({model: user});
    this.$el.find('#hamail-address-list').append(view.render().el);
  },

  changeList: function(){
    var ids = [];
    this.collection.forEach(function(user){
      var id = user.get('user_id');
      if ( 0 > ids.indexOf(id) ) {
        ids.push(id);
      }
    });
    this.$('#hamail-address-users-id').val(ids.join(','));
  },

  userFound: function(e, data){
    var $collection = this.collection;
    var $area = this.$el.find('#hamail-address-list');
    var query = '';
    switch ( data.type ) {
      case 'term':
        query = '&term_id=' + data.id;
        break;
      default:
        query = '&type=' + data.type + '&term_id=' + data.id;
        break;
    }
    $area.addClass('loading');
    jQuery.get(HamailRecipients.term_endpoint + query).done(function (users) {
      _.each(users, function (user) {
        var model = new User(user);
        $collection.add(model);
      });
    }).fail(function (err) {
      if (window.console) {
        console.log(err);
      }
    }).always(function () {
      $area.removeClass('loading');
    });
  }
});
