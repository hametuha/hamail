/*!
 * Incremental search default.
 *
 * @deps wp-api-fetch, backbone, jquery-ui-autocomplete, wp-i18n;
 */

/* global _:false */
/* global Backbone: false */

const $ = jQuery;
const { __, sprintf } = wp.i18n;

const Item = Backbone.Model.extend( {

	defaults() {
		return {
			id: '',
			label: '',
			value: ''
		};
	},

	sync() {}
} );

const ItemList = Backbone.Collection.extend( {

	model: Item,

	url() {
		return false;
	},

	sync() {}
} );

const ItemCard = Backbone.View.extend( {

	tagName: 'li',

	template: _.template( `
		<span class="hamail-address-user-name"><%- label %></span>
		<a href="#" class="remove"><i class="dashicons dashicons-no"></i></a>
	` ),

	events: {
		'click .remove': 'removeUser',
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

const ItemsController = Backbone.View.extend( {

	collection: null,

	$complete: null,

	endpoint: '',

	initialize: function ( options ) {
		this.options = $.extend( {
			id: '',
		}, options );
		// Initialize with given value.
		this.$el      = $( '#' + this.options.id );
		this.$input   = this.$el.find( '.hamail-search-value' );
		this.$list    = this.$el.find( '.hamail-search-list' );
		const itemIds = this.$input.val().split( ',' ).map( ( val ) => $.trim( val ) ).filter( ( val ) => val.length );
		this.endpoint = this.$el.attr( 'data-endpoint' );
		// Initialize model.
		const collection = this.collection = new ItemList( null );
		_.bindAll( this, 'findUsers', 'addUser', 'changeList' );
		// Register auto complete.
		this.$complete = this.$el.find( '.hamail-search-field' );
		// Bind events.
		this.listenTo( this.collection, 'add', this.addUser );
		this.listenTo( this.collection, 'update', this.changeList );
		// If search completed, update UI.
		this.$complete.on( 'searched', ( e, data ) => {
			this.findUsers( [ data.id ] );
		} );
		// Initialize collection.
		if ( itemIds.length ) {
			this.findUsers( itemIds );
		}
		// Incremental search.
		this.$complete.autocomplete( {
			curPage: 1,
			hasNext: false,
			minLength: 1,
			delay: 500,
			source: ( request, callback ) => {
				wp.apiFetch( {
					path: this.endpoint + '?term=' + request.term + '&paged=' + this.$complete.autocomplete( 'instance' ).options.curPage,
					parse: false,
				} ).then( ( response ) => {
					this.$complete.autocomplete( 'option', {
						hasNext: 'more' === response.headers.get( 'X-WP-Next' ),
					} );
					return response.json();
				} ).then( ( response ) => {
					callback( response );
				} ).catch( ( response ) => {
					callback( response );
				} );
			},
			change: function() {
				$( this ).autocomplete( 'option', {
					curPage: 1,
				} );
			},
			focus() {
				return false;
			},
			select: function ( event, ui ) {
				const instance = $( this ).autocomplete( 'instance' );
				const curPage = instance.options.curPage;
				switch ( ui.item.id ) {
					case '__next__':
						$( this ).autocomplete( 'option', {
							curPage: curPage + 1,
						} );
						$( this ).autocomplete( 'search' );
						return false;
					case '__prev__':
						$( this ).autocomplete( 'option', {
							curPage: curPage - 1,
						} );
						$( this ).autocomplete( 'search' );
						return false;
					default:
						const model = new Item( ui.item );
						collection.add( model );
						$( this ).val( '' );
						return false;
				}
			},
		} );
		// Render item.
		this.$complete.autocomplete( 'instance' )._renderItem = function( ul, item ) {
			switch ( item.id ) {
				case '__prev__':
					return $( sprintf( '<li class="hamail-search-nav prev"><div><span class="dashicons dashicons-arrow-left"></span> %s</div></li>', item.label ) ).appendTo( ul );
				case '__next__':
					return $( sprintf( '<li class="hamail-search-nav next"><div>%s <span class="dashicons dashicons-arrow-right"></span></div></li>', item.label ) ).appendTo( ul );
				default:
					return $( sprintf( '<li><div>%s</div></li>', item.label ) ).appendTo( ul );
			}
		};
		// Render Menu.
		this.$complete.autocomplete( 'instance' )._renderMenu = ( ul, items ) => {
			let nextPrevious = false;
			const instance = this.$complete.autocomplete( 'instance' );
			if ( 1 < instance.options.curPage ) {
				nextPrevious = true;
				instance._renderItemData( ul, {
					id: '__prev__',
					label: __( 'Previous', 'hamail' ),
				} );
			}
			$.each( items, ( index, item ) => {
				instance._renderItemData( ul, item );
			});
			if ( instance.options.hasNext ) {
				nextPrevious = true;
				instance._renderItemData( ul, {
					id: '__next__',
					label: __( 'Next', 'hamail' ),
				} );
			}
			if ( nextPrevious ) {
				$( ul ).addClass( 'has-control' );
			}
		};
	},

	addUser: function ( item ) {
		const view = new ItemCard( { model: item } );
		this.$list.append( view.render().el );
	},

	changeList: function () {
		const ids = [];
		this.collection.forEach( function ( item ) {
			const id = item.get( 'id' );
			if ( 0 > ids.indexOf( id ) ) {
				ids.push( id );
			}
		} );
		this.$input.val( ids.join( ',' ) );
	},

	/**
	 * Get item token from id list.
	 *
	 * @param {number[]} itemIds
	 */
	findUsers: function( itemIds ) {
		this.$list.addClass( 'loading' );
		wp.apiFetch( {
			path: this.endpoint + '?ids=' + itemIds.join( ',' ),
		} ).then( ( res ) => {
			_.each( res, function ( item ) {
				const model = new Item( item );
				this.collection.add( model );
			}, this );
		} ).catch( () => {} ).finally( () => {
			this.$list.removeClass( 'loading' );
		} );
	},
} );

wp.hamail = wp.hamail || {};
wp.hamail.ItemsController = ItemsController;
