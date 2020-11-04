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
			value: '',
			type: '',
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

	type: '',

	initialize: function ( options ) {
		this.options = $.extend( {
			id: '',
		}, options );
		// Initialize with given value.
		this.$el      = $( '#' + this.options.id );
		this.$input   = this.$el.find( '.hamail-search-value' );
		this.$list    = this.$el.find( '.hamail-search-list' );
		const itemIds = this.$input.val().split( ',' ).map( ( val ) => $.trim( val ) ).filter( ( val ) => val.length );
		// Set endpoint from radio button.
		this.endpoint = this.$el.find( 'input[name="hamail_search_action"]:checked' ).val();
		this.$el.find( 'input[name="hamail_search_action"]' ).click( ( e ) => {
			if ( e.target.checked ) {
				if ( this.endpoint !== e.target.value ) {
					this.flush();
				}
				this.endpoint = e.target.value;
			}
		} );
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
			total: 0,
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
						total: parseInt( response.headers.get( 'X-WP-Total' ), 10 ),
					} );
					return response.json();
				} ).then( ( response ) => {
					callback( response );
				} ).catch( ( response ) => {
					callback( response );
				} );
			},
			change: () => {
				this.flush();
			},
			focus() {
				return true;
			},
			select: function( event, ui ) {
				const instance = $( this ).autocomplete( 'instance' );
				const curPage = instance.options.curPage;
				switch ( ui.item.id ) {
					case '__total__':
						return false;
					case '__prev__':
						if ( 1 < curPage ) {
							$( this ).autocomplete( 'option', {
								curPage: curPage - 1,
							} );
							$( this ).autocomplete( 'search' );
							$( this ).trigger( 'keydown' );
						}
						return false;
					case '__next__':
						if ( instance.options.hasNext ) {
							$( this ).autocomplete( 'option', {
								curPage: curPage + 1,
							} );
							$( this ).autocomplete( 'search' );
							$( this ).trigger( 'keydown' );
						}
						return false;
					default:
						if ( 'user' === ui.item.type ) {
							const model = new Item( ui.item );
							collection.add( model );
						} else {
							// Need fetch.
							console.log( 'Fetch: ' );
						}
						$( this ).val( '' );
						return false;
				}
			},
		} );
		// Render item.
		this.$complete.autocomplete( 'instance' )._renderItem = function( ul, item ) {
			switch ( item.id ) {
				case '__total__':
				case '__next__':
				case '__prev__':
					return $( sprintf( '<li class="hamail-search-nav %s"><div>%2$s</div></li>', item.id.replace( /_/g, '' ), item.label ) ).appendTo( ul );
				default:
					return $( sprintf( '<li><div>%s</div></li>', item.label ) ).appendTo( ul );
			}
		};
		// Render Menu.
		this.$complete.autocomplete( 'instance' )._renderMenu = ( ul, items ) => {
			let hasNext = false;
			let hasPrev = false;
			const instance = this.$complete.autocomplete( 'instance' );
			items.forEach( ( item ) => {
				instance._renderItemData( ul, item );
			} );
			if ( 1 < instance.options.curPage ) {
				hasPrev = true;
			}
			if ( instance.options.hasNext ) {
				hasNext = true;
			}
			if ( hasPrev || hasNext ) {
				const parts = [
					{
						id: '__prev__',
						label: '',
						type: 'nav',
					},
					{
						id: '__total__',
						label: sprintf(
							'<span class="hamail-search-result"> %1$d/%1$d </span>',
							instance.options.curPage,
							Math.ceil( instance.options.total / 10 )
						),
						type: 'nav',
					},
					{
						id: '__next__',
						label: '',
						type: 'nav',
					},
				];
				if ( hasPrev ) {
					parts[0].label = sprintf( '<span class="dashicons dashicons-arrow-left"></span> %s', __( 'Previous', 'hamail' ) );
				}
				if ( hasNext ) {
					parts[2].label = sprintf( '%s <span class="dashicons dashicons-arrow-right"></span>', __( 'Next', 'hamail' ) );
				}
				parts.forEach( ( item ) => {
					instance._renderItemData( ul, item );
				} );
				$( ul ).addClass( 'hamail-search-nav-wrapper' );
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

	flush() {
		this.$complete.autocomplete( 'option', {
			curPage: 1,
			hasNext: false,
			total: 0,
		} );
	}
} );

wp.hamail = wp.hamail || {};
wp.hamail.ItemsController = ItemsController;
