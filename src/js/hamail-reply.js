'use strict';

/*!
 * Contact Reply helper.
 *
 * Jetpack, flamingo
 *
 * @deps wp-api-fetch, jquery
 */

const $ = jQuery;

let loading = false;

$( document ).on( 'click', 'a.hamail-reply-link', function ( e ) {
	e.preventDefault();
	if ( loading ) {
		return;
	}
	const id = $( this ).attr( 'data-post-id' );
	loading = true;
	wp.apiFetch( {
		path: 'hamail/v1/reply/' + id,
		method: 'POST',
	} ).then( ( response ) => {
		if ( window.confirm( response.message ) ) {
			window.location.href = response.url;
		}
	} ).catch( ( response ) => {
		let message = 'ERROR';
		if ( response.message ) {
			message = response.message;
		}
		alert( message );
	} ).finally( () => {
		loading = false;
	} );
} );
