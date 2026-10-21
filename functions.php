<?php
/**
 * Bookshop child theme.
 */

function bookshop_fonts_url() {
	return 'https://fonts.googleapis.com/css2?family=Tiro+Bangla:ital@0;1&family=Noto+Serif+Bengali:wght@500;600;700&display=swap';
}

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'bookshop-fonts', bookshop_fonts_url(), array(), null );
		wp_enqueue_style( 'bookshop-style', get_stylesheet_uri(), array( 'twentytwentyfive-style' ), filemtime( get_theme_file_path( 'style.css' ) ) );
		wp_enqueue_script( 'bookshop-live-search', get_theme_file_uri( 'assets/live-search.js' ), array(), filemtime( get_theme_file_path( 'assets/live-search.js' ) ), array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'bookshop-live-search', 'const bookshopSearch = ' . wp_json_encode( array( 'api' => rest_url( 'wc/store/v1/products' ) ) ) . ';', 'before' );
	}
);

add_action(
	'after_setup_theme',
	function () {
		add_editor_style( array( bookshop_fonts_url(), 'style.css' ) );
	}
);

// Account menu: Bangla labels, no Downloads tab (physical books only).
add_filter(
	'woocommerce_account_menu_items',
	function ( $items ) {
		unset( $items['downloads'] );
		$labels = array(
			'dashboard'       => 'ড্যাশবোর্ড',
			'orders'          => 'আমার অর্ডার',
			'edit-address'    => 'ঠিকানা',
			'edit-account'    => 'অ্যাকাউন্ট তথ্য',
			'customer-logout' => 'লগ আউট',
		);
		foreach ( $labels as $key => $label ) {
			if ( isset( $items[ $key ] ) ) {
				$items[ $key ] = $label;
			}
		}
		return $items;
	}
);

// Quick-link cards on the account dashboard.
add_action(
	'woocommerce_account_dashboard',
	function () {
		$cards = array(
			'orders'       => array( 'আমার অর্ডার', 'অর্ডারের অবস্থা ও ইতিহাস দেখুন' ),
			'edit-address' => array( 'ঠিকানা', 'ডেলিভারির ঠিকানা যোগ বা পরিবর্তন করুন' ),
			'edit-account' => array( 'অ্যাকাউন্ট তথ্য', 'নাম, ইমেইল ও পাসওয়ার্ড পরিবর্তন করুন' ),
		);
		echo '<div class="bookshop-account-cards">';
		foreach ( $cards as $endpoint => $card ) {
			printf(
				'<a href="%s"><strong>%s</strong><span>%s</span></a>',
				esc_url( wc_get_account_endpoint_url( $endpoint ) ),
				esc_html( $card[0] ),
				esc_html( $card[1] )
			);
		}
		echo '</div>';
	}
);

// Mark the nav link for the current page (custom links don't get this from core).
add_filter(
	'render_block_core/navigation-link',
	function ( $html, $block ) {
		$url = $block['attrs']['url'] ?? '';
		if ( ! $url ) {
			return $html;
		}
		$here = trailingslashit( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) );
		$link = trailingslashit( wp_parse_url( $url, PHP_URL_PATH ) );
		if ( strtolower( $here ) !== strtolower( $link ) ) {
			return $html;
		}
		$p = new WP_HTML_Tag_Processor( $html );
		if ( $p->next_tag( 'a' ) ) {
			$p->set_attribute( 'aria-current', 'page' );
		}
		return $p->get_updated_html();
	},
	10,
	2
);

// Product search also matches product tag names (live search + results page).
add_filter(
	'posts_search',
	function ( $search, $query ) {
		global $wpdb;
		if ( ! $search || ! in_array( 'product', (array) $query->get( 'post_type' ), true ) ) {
			return $search;
		}
		// Core may append a password clause; keep it outside our OR.
		$pw   = " AND ({$wpdb->posts}.post_password = '')";
		$tail = str_ends_with( $search, $pw ) ? $pw : '';
		$main = $tail ? substr( $search, 0, -strlen( $pw ) ) : $search;
		$ids  = $wpdb->prepare(
			"SELECT tr.object_id FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_tag'
			INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			WHERE t.name LIKE %s",
			'%' . $wpdb->esc_like( $query->get( 's' ) ) . '%'
		);
		return preg_replace( '/^\s*AND\s*/', ' AND (', $main, 1 ) . " OR {$wpdb->posts}.ID IN ( $ids ))" . $tail;
	},
	10,
	2
);

// Sale badge shows the discount, e.g. "-33%", instead of "Sale".
add_filter(
	'render_block_woocommerce/product-sale-badge',
	function ( $html, $block, $instance ) {
		$product = wc_get_product( $instance->context['postId'] ?? 0 );
		if ( ! $html || ! $product || ! $product->is_type( 'simple' ) || ! $product->is_on_sale() ) {
			return $html;
		}
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		if ( $regular <= 0 ) {
			return $html;
		}
		return str_replace( '>Sale<', '>-' . round( 100 * ( $regular - $sale ) / $regular ) . '%<', $html );
	},
	10,
	3
);

// Bangla labels for product card buttons.
add_filter(
	'gettext_woocommerce',
	function ( $text, $original ) {
		$labels = array(
			'Add to cart'    => 'কার্টে যোগ করুন',
			'%s in cart'     => 'কার্টে আছে (%s)',
			'Read more'      => 'বিস্তারিত দেখুন',
			'Select options' => 'অপশন বাছাই করুন',
		);
		return $labels[ $original ] ?? $text;
	},
	10,
	2
);
