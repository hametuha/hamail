/*!
 * Hametuha user selector
 *
 * @deps hamail-incsearch
 */

const $ = jQuery;
const { ItemsController } = wp.hamail;

$( document ).ready( function() {
	// User selector.
	$( '.hamail-search-wrapper' ).each( function( index, el ) {
		new ItemsController( {
			id: $( el ).attr( 'id' ),
		} );
	} );
	// Email input.
	const setRecipientsCount = () => {
		const length = $( '#hamail_raw_address' ).val().split( ',' ).map( ( email ) => email.trim() ).filter( ( email ) => {
			return /.*@.*/.test( email );
		} ).length;
		$( '#hamail-address-counter' ).text( length );
	};
	setRecipientsCount();
	$( '#hamail_raw_address' )
		.on( 'keyup', setRecipientsCount )
		.on( 'blur', setRecipientsCount );
} );
