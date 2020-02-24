/**
 * Hametuha user selector
 */

/* global _:false */
/* global Backbone:false */
/* global HamailRecipients:false */

const User = Backbone.Model.extend( {

	defaults() {
		return {
			user_id: '',
			display_name: '',
			user_email: ''
		};
	},

	sync() {
	}

} );

const UserList = Backbone.Collection.extend( {

	model: User,

	url() {
		return false;
	},

	sync() {
	}


} );

const UserCard = Backbone.View.extend( {

	tagName: 'li',

	template: _.template( jQuery( '#hamail-user-card' ).html() ),

	events: {
		'click .remove': 'removeUser'
	},

	initialize: function () {
		this.listenTo( this.model, 'destroy', this.remove );
	},

	removeUser: function ( e ) {
		e.preventDefault();
		this.model.destroy();
	},

	render: function () {
		this.$el.html( this.template( this.model.toJSON() ) );
		return this;
	}

} );

const UserController = Backbone.View.extend( {

	el: jQuery( '#hamail-users' ),

	collection: null,

	$complete: null,

	events: {
		// 'searched #hamail-address-search': this.userFound
	},

	initialize: function () {
		_.bindAll( this, 'userFound', 'addUser', 'changeList' );
		// Initialize model
		const collection = this.collection = new UserList( null );
		const $complete = this.$complete = jQuery( '#hamail-address-search' );
		// Bind events
		this.listenTo( this.collection, 'add', this.addUser );
		this.listenTo( this.collection, 'update', this.changeList );
		$complete.on( 'searched', this.userFound );
		// Initialize collection
		_.each( HamailRecipients.users, function ( user ) {
			const model = new User( user );
			this.collection.add( model );
		}, this );
		// Incremental search
		$complete.autocomplete( {
			minLength: 1,
			source: HamailRecipients.search_endpoint,
			focus() {
				return false;
			},
			select: function ( event, ui ) {
				switch ( ui.item.type ) {
					case 'user':
					case 'post':
						const model = new User( {
							user_id: ui.item.id,
							display_name: ui.item.display_name,
							user_email: ui.item.data
						} );
						collection.add( model );
						break;
					case 'term':
					default:
						$complete.trigger( 'searched', [ ui.item ] );
						break;
				}
				jQuery( this ).val( '' );
				return false;
			}
		} )
			.autocomplete( "instance" )._renderItem = function ( ul, item ) {
				return jQuery( "<li>" )
					.append( "<div>" + item.label + "</div>" )
					.appendTo( ul );
			};
	},

	addUser: function ( user ) {
		const view = new UserCard( { model: user } );
		this.$el.find( '#hamail-address-list' ).append( view.render().el );
	},

	changeList: function () {
		const ids = [];
		this.collection.forEach( function ( user ) {
			const id = user.get( 'user_id' );
			if ( 0 > ids.indexOf( id ) ) {
				ids.push( id );
			}
		} );
		this.$( '#hamail-address-users-id' ).val( ids.join( ',' ) );
	},

	userFound: function ( e, data ) {
		const $collection = this.collection;
		const $area = this.$el.find( '#hamail-address-list' );
		let query = '';
		switch ( data.type ) {
			case 'term':
				query = '&term_id=' + data.id;
				break;
			default:
				query = '&type=' + data.type + '&term_id=' + data.id;
				break;
		}
		$area.addClass( 'loading' );
		jQuery.get( HamailRecipients.term_endpoint + query ).done( function ( users ) {
			_.each( users, function ( user ) {
				const model = new User( user );
				$collection.add( model );
			} );
		} ).fail( function () {
			// This will be the error.
		} ).always( function () {
			$area.removeClass( 'loading' );
		} );
	}
} );

jQuery( document ).ready( function () {
	new UserController();
} );
