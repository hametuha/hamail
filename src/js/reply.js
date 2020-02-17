/**
 * Description
 */

/*global hoge: true*/

( function ( $ ) {

	'use strict';

	var loading = false;


	$( document ).on( 'click', 'a.hamail-reply-link', function ( e ) {
		e.preventDefault();
		if ( loading ) {
			return;
		}
		var id = $( this ).attr( 'data-post-id' );
		loading = true;
		wp.apiRequest( {
			path: 'hamail/v1/reply/' + id,
			method: 'POST'
		} ).done( function ( response ) {
			if ( window.confirm( response.message ) ) {
				window.location.href = response.url;
			}
		} ).fail( function ( response ) {
			var message = 'ERROR';
			if ( response.responseJSON && response.responseJSON.message ) {
				message = response.responseJSON.message;
				alert( message );
			}
		} ).always( function () {
			loading = false;
		} );
	} );

} )( jQuery );
