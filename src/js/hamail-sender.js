/*!
 * Hametuha user selector
 *
 * @deps hamail-incsearch, wp-api-fetch, wp-i18n, wp-hooks
 */

const $ = jQuery;
const { ItemsController } = wp.hamail;
const { apiFetch } = wp;
const { addFilter } = wp.hooks;
const { __ } = wp.i18n;

$( document ).ready( function() {
	// User selector.
	$( '.hamail-search-wrapper' ).each( function( index, el ) {
		new ItemsController( {
			id: $( el ).attr( 'id' ),
		} );
	} );
	// Email input.
	const setRawRecipientsCount = () => {
		const length = $( '#hamail_raw_address' ).val().split( ',' ).map( ( email ) => email.trim() ).filter( ( email ) => {
			return /.*@.*/.test( email );
		} ).length;
		$( '#hamail-address-counter' ).text( length );
	};
	setRawRecipientsCount();
	$( '#hamail_raw_address' )
		.on( 'keyup', setRawRecipientsCount )
		.on( 'blur', setRawRecipientsCount );
	// Count user filter.
	const setUserFilterCount = ( count ) => {
		$( '#hamail-user-filter-count' ).text( count );
	};
	let timer = null;
	const updateUserFilterCount = function() {
		if ( timer ) {
			clearTimeout( timer );
		}
		const request = {
			roles: [],
			filters: [],
		};
		// Grab checked roles.
		$( 'input[name="hamail_roles[]"]:checked' ).each( function( i, input ) {
			request.roles.push( $( input ).val() );
		} );
		// Grab checked filters.
		$( '.hamail-user-filter' ).each( function( i, div ) {
			const filter = {
				id: $( div ).attr( 'data-filter-id' ),
				values: [],
			};
			$( div ).find( 'input[type="checkbox"]:checked, input[type="radio"]:checked' ).each( function( j, input ) {
				filter.values.push( $( input ).val() );
			} );
			$( div ).find( 'input' ).each( function( j, input ) {
				if ( -1 < [ 'url', 'text', 'date', 'email', 'password' ].indexOf( $( input ).attr( 'type' ) ) ) {
					// Add filter not empty.
					if ( $( input ).val() ) {
						filter.values.push( $( input ).val() );
					}
				}
			} );
			if ( 0 < filter.values.length ) {
				request.filters.push( filter );
			}
		} );
		if ( ! request.roles.length && ! request.filters.length ) {
			setUserFilterCount( 0 );
			return true;
		}
		const data = {
			roles: request.roles,
		};
		request.filters.forEach( ( filter ) => {
			data[ filter.id ] = filter.values;
		} );
		// Filter exists.
		timer = setTimeout( function() {
			apiFetch( {
				method: 'post',
				path: '/hamail/v1/users/filter',
				data,
			} ).then( ( res ) => {
				setUserFilterCount( res.total );
			} ).catch( () => {
				setUserFilterCount( 0 );
			} );
		}, 1000 );
	};
	updateUserFilterCount();
	$( 'input[name="hamail_roles[]"]' ).on( 'click', updateUserFilterCount );
	$( 'input[name^="hamail_user_filters"]' ).on( 'click keyup blur change', updateUserFilterCount );
} );

// Add filter to override text.
// https://developer.wordpress.org/block-editor/reference-guides/filters/i18n-filters/
addFilter( 'i18n.gettext_default', 'hamail/override-editor-label', ( translation, text ) => {
	switch ( text ) {
		case 'Publish':
			return __( 'Send', 'hamail' );
		case 'Schedule':
			return __( 'Schedule', 'hamail' );
		case 'Excerpt':
			return __( 'Pre-header Text', 'hamail' );
		case 'Add an excerpt…':
			return __( 'Add pre-header text', 'hamail' );
		case 'Edit excerpt':
			return __( 'Edit pre-header text', 'hamail' );
		case 'Learn more about manual excerpts':
			return __( 'Excerpt is used for pre-header text in email.', 'hamail' );
		default:
			return translation;
	}
} );
