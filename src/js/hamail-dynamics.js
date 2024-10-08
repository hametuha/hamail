/*!
 * Dynamic Email helper
 *
 * @deps wp-api-fetch, jquery
 */

const $ = jQuery;
const { apiFetch } = wp;

$( function() {
	$( '.hamail-dynamics-toggle' ).click( function() {
		const $label = $( this ).next( 'label' );
		if ( $label.hasClass( 'loading' ) ) {
			return;
		}
		$label.addClass( 'loading' );
		const checked = this.checked;
		apiFetch( {
			path: '/hamail/v1/dynamics/' + $( this ).attr( 'id' ),
			method: checked ? 'post' : 'delete',
		} )
			.then( ( response ) => {
				$label.find( '.message' ).text( response.message );
				setTimeout( () => {
					$label.find( '.message' ).text( '' );
				}, 2000 );
			} )
			.catch( ( response ) => {
				this.checked = ! checked;
				alert( response.message );
			} )
			.finally( () => {
				$label.removeClass( 'loading' );
			} );
	} );
} );
