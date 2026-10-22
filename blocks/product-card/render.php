<?php
/**
 * Product card: renders card.html for the current product, passing the loop's context down
 * so WooCommerce's image, badge, price and cart button blocks know which product they're on.
 *
 * In the editor preview (REST block renderer) there is no loop context, so editor.js sends
 * the product ID as ?bookshop_product=.
 */

$bookshop_context = $block->context;
if ( empty( $bookshop_context['postId'] ) && isset( $_GET['bookshop_product'] ) && current_user_can( 'edit_posts' ) ) {
	$bookshop_context['postId']   = absint( $_GET['bookshop_product'] );
	$bookshop_context['postType'] = 'product';
}
if ( empty( $bookshop_context['postId'] ) ) {
	return;
}

static $bookshop_card_blocks = null;
if ( null === $bookshop_card_blocks ) {
	$bookshop_card_blocks = parse_blocks( file_get_contents( __DIR__ . '/card.html' ) );
}

$bookshop_html = '';
foreach ( $bookshop_card_blocks as $bookshop_parsed ) {
	if ( $bookshop_parsed['blockName'] ) {
		$bookshop_html .= ( new WP_Block( $bookshop_parsed, $bookshop_context ) )->render();
	}
}
?>
<div <?php echo get_block_wrapper_attributes(); ?>><?php echo $bookshop_html; // Rendered block output. ?></div>
