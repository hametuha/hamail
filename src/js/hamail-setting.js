'use strict';

/*!
 * Admin setting helper.
 *
 * @deps jquery
 */

const $ = jQuery;

const timer = null;

const updateCsv = function () {
	if ( timer ) {
		clearTimeout( timer );
	}
	setTimeout( function () {
		const str = $( '#hamail_fields_to_sync' ).val().split( "\n" );

		$( '.hamail-csv-preview tr' ).each( function ( index, tr ) {
			const $tr = $( tr );
			// Clear cells.
			$tr.find( 'td' ).remove();
			if ( str[ index ] ) {
				$.each( str[ index ].split( ',' ), function ( i, cell ) {
					cell = $.trim( cell );
					$tr.append( '<td>' + cell + '</td>' );
				} );
			}
		} );
	}, 500 );
};

$( document ).ready( updateCsv );

$( '#hamail_fields_to_sync' ).on( 'keyup', updateCsv );
