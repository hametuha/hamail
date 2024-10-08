/*!
 * Template Editor
 *
 * @deps code-editor, jquery
 */

const $ = jQuery;

/* global HamailCodeEditor:false */

$( () => {
	wp.codeEditor.initialize(
		document.getElementById( 'hamail-template-body' ),
		HamailCodeEditor
	);
} );
