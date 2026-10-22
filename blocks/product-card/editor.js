// Editor preview: the card is rendered by render.php for the product the loop is showing.
( function ( blocks, element, blockEditor, ServerSideRender ) {
	const el = element.createElement;
	blocks.registerBlockType( 'bookshop/product-card', {
		edit: function ( props ) {
			return el(
				'div',
				blockEditor.useBlockProps(),
				el( ServerSideRender, {
					block: 'bookshop/product-card',
					urlQueryArgs: { bookshop_product: props.context.postId },
				} )
			);
		},
	} );
} )( wp.blocks, wp.element, wp.blockEditor, wp.serverSideRender );
