/*!
 * Hametuha user selector
 *
 * @deps hamail-incsearch
 */

const $ = jQuery;
const { ItemsController } = wp.hamail;

$( document ).ready( function() {
	$( '.hamail-search-wrapper' ).each( function( index, el ) {
		new ItemsController( {
			id: $( el ).attr( 'id' ),
		} );
	} );
} );
