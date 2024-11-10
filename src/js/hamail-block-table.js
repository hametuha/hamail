/*!
 * Table block for layout.
 *
 * @handle hamail-block-table
 */
const { registerBlockType } = wp.blocks;
const { InnerBlocks, useBlockProps } = wp.blockEditor;
const { __ } = wp.i18n;

registerBlockType( 'hamail/table', {

	title: __( 'Layout Table', 'hamail' ),

	icon: 'grid-view',

	category: 'layout',

	description: __( 'Table tag for email layout.', 'hamail' ),

	keywords: [ 'table' ],

	edit() {
		const blockProps = useBlockProps();

		const allowedBlocks = [ 'hamail/row' ];
		return (
			<div { ...blockProps }>
				<InnerBlocks allowedBlocks={ allowedBlocks } templateLock={ false }/>
			</div>
		);
	},

	save() {
		const blockProps = useBlockProps.save();

		return (
			<table { ...blockProps }>
				<InnerBlocks.Content />
			</table>
		);
	},

} );

registerBlockType( 'hamail/row', {

	title: __( 'Layout Table Row', 'hamail' ),

	icon: 'grid-view',

	category: 'layout',

	description: __( 'tr tag for email layout.', 'hamail' ),

	keywords: [ 'table' ],

	parent: [ 'hamail/table' ],

	edit() {
		const blockProps = useBlockProps();

		const allowedBlocks = [ 'hamail/col' ];
		return (
			<div { ...blockProps }>
				<InnerBlocks allowedBlocks={ allowedBlocks } templateLock={ false } />
			</div>
		);
	},

	save() {
		const blockProps = useBlockProps.save();

		return (
			<tr { ...blockProps }>
				<InnerBlocks.Content />
			</tr>
		);
	},

} );

registerBlockType( 'hamail/col', {

	title: __( 'Layout Table Column', 'hamail' ),

	icon: 'grid-view',

	category: 'layout',

	description: __( 'td tag for email layout.', 'hamail' ),

	keywords: [ 'table' ],

	parent: [ 'hamail/row' ],

	edit() {
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<InnerBlocks />
			</div>
		);
	},

	save() {
		const blockProps = useBlockProps.save();

		return (
			<td { ...blockProps }>
				<InnerBlocks.Content />
			</td>
		);
	},

} );
