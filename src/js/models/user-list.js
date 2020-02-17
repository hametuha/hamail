//=require ./user.js

var UserList = Backbone.Collection.extend( {

	model: User,

	url: function () {
		return false;
	},

	sync: function () {
	}


} );
