/*!
 * Hametuha user selector
 *
 * @deps hamail-incsearch, wp-api-fetch
 */

const $ = jQuery;
const { ItemsController } = wp.hamail;
const { apiFetch } = wp;

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
	$( 'input[name^="hamail_user_filters"]' ).on( 'click', updateUserFilterCount );
} );
