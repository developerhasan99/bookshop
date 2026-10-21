<?php
/**
 * Bookshop child theme.
 */

function bookshop_fonts_url() {
	return 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600&family=Noto+Serif+Bengali:wght@500;600;700&display=swap';
}

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'bookshop-fonts', bookshop_fonts_url(), array(), null );
		wp_enqueue_style( 'bookshop-style', get_stylesheet_uri(), array( 'twentytwentyfive-style' ), wp_get_theme()->get( 'Version' ) );
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
