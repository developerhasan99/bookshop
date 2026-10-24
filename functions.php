<?php
/**
 * Bookshop child theme.
 */

function bookshop_fonts_url() {
	return 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&family=Tiro+Bangla:ital@0;1&family=Noto+Serif+Bengali:wght@500;600;700&display=swap';
}

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'bookshop-fonts', bookshop_fonts_url(), array(), null );
		wp_enqueue_style( 'bookshop-style', get_stylesheet_uri(), array(), filemtime( get_theme_file_path( 'style.css' ) ) );
		wp_enqueue_script( 'bookshop-live-search', get_theme_file_uri( 'assets/live-search.js' ), array(), filemtime( get_theme_file_path( 'assets/live-search.js' ) ), array( 'strategy' => 'defer' ) );
		wp_enqueue_script( 'bookshop-add-to-cart-loading', get_theme_file_uri( 'assets/add-to-cart-loading.js' ), array(), filemtime( get_theme_file_path( 'assets/add-to-cart-loading.js' ) ), array( 'strategy' => 'defer' ) );
		wp_add_inline_script( 'bookshop-live-search', 'const bookshopSearch = ' . wp_json_encode( array( 'api' => rest_url( 'bookshop/v1/search' ) ) ) . ';', 'before' );
	}
);

// Product card block: blocks/product-card (layout in card.html).
add_action(
	'init',
	function () {
		register_block_type( get_theme_file_path( 'blocks/product-card' ) );
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

// Product search also matches tag and author (pa_writer) names (live search + results page).
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
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy IN ( 'product_tag', 'pa_writer' )
			INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			WHERE t.name LIKE %s",
			'%' . $wpdb->esc_like( $query->get( 's' ) ) . '%'
		);
		return preg_replace( '/^\s*AND\s*/', ' AND (', $main, 1 ) . " OR {$wpdb->posts}.ID IN ( $ids ))" . $tail;
	},
	10,
	2
);

// Percent off for a simple product on sale, or 0.
function bookshop_discount( $product ) {
	if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_on_sale() ) {
		return 0;
	}
	$regular = (float) $product->get_regular_price();
	return $regular > 0 ? (int) round( 100 * ( $regular - (float) $product->get_sale_price() ) / $regular ) : 0;
}

// Sale badges show the discount, e.g. "-33%", instead of "Sale": cards (block) and the product page gallery (classic flash).
add_filter(
	'render_block_woocommerce/product-sale-badge',
	function ( $html, $block, $instance ) {
		$off = bookshop_discount( wc_get_product( $instance->context['postId'] ?? 0 ) );
		return $html && $off ? str_replace( '>Sale<', '>-' . $off . '%<', $html ) : $html;
	},
	10,
	3
);
add_filter(
	'woocommerce_sale_flash',
	function ( $html, $post, $product ) {
		$off = bookshop_discount( $product );
		return $off ? '<span class="onsale">-' . $off . '%</span>' : $html;
	},
	10,
	3
);

// Product page: "৳১,১৬১ সাশ্রয় (৫৪%)" under the price.
add_filter(
	'render_block_woocommerce/product-price',
	function ( $html, $block ) {
		$product = wc_get_product( get_the_ID() );
		$off     = bookshop_discount( $product );
		if ( empty( $block['attrs']['isDescendentOfSingleProductTemplate'] ) || ! $off ) {
			return $html;
		}
		$saved = (float) $product->get_regular_price() - (float) $product->get_sale_price();
		return $html . sprintf( '<p class="bookshop-save">৳%s সাশ্রয় (%s%%)</p>', esc_html( bookshop_bn_digits( number_format( $saved ) ) ), esc_html( bookshop_bn_digits( $off ) ) );
	},
	10,
	2
);

// Product gallery: no hover zoom or lightbox (thumbnail slider stays). WooCommerce adds both on template_redirect
// (priority 10) and only loads their scripts if supported, so drop the support right after. The gallery block also
// forces the JS flags on at priority 10; turn those off later.
add_action(
	'template_redirect',
	function () {
		remove_theme_support( 'wc-product-gallery-zoom' );
		remove_theme_support( 'wc-product-gallery-lightbox' );
	},
	20
);
add_filter( 'woocommerce_single_product_zoom_enabled', '__return_false', 20 );
add_filter( 'woocommerce_single_product_photoswipe_enabled', '__return_false', 20 );
// Without the lightbox the image link would just open the raw file, so drop it.
add_filter(
	'woocommerce_single_product_image_thumbnail_html',
	fn( $html ) => preg_replace( '#<a [^>]*>(.*?)</a>#s', '$1', $html )
);

// Product page: show the short description as written (core's excerpt block flattens its line breaks).
add_filter(
	'render_block_core/post-excerpt',
	function ( $html ) {
		$post = get_post();
		if ( ! is_product() || ! $post || ! $post->post_excerpt || get_queried_object_id() !== $post->ID ) {
			return $html;
		}
		return '<div class="wp-block-post-excerpt bookshop-product-excerpt">' . apply_filters( 'woocommerce_short_description', $post->post_excerpt ) . '</div>';
	}
);

// Product page: "Buy now" next to add to cart. Posting the same form to the checkout adds the book and opens checkout.
add_action(
	'woocommerce_after_add_to_cart_button',
	function () {
		global $product;
		if ( is_product() && $product && $product->is_type( 'simple' ) ) {
			printf(
				'<button type="submit" name="add-to-cart" value="%d" formaction="%s" class="bookshop-buy-now button wp-element-button">এখনই কিনুন</button>',
				absint( $product->get_id() ),
				esc_url( wc_get_checkout_url() )
			);
		}
	}
);

// Stars always shown, with the review count, e.g. ★★★★☆ (12): on cards and the product page. WooCommerce hides them without reviews.
add_filter(
	'render_block_woocommerce/product-rating',
	function ( $html, $block, $instance ) {
		$single  = ! empty( $block['attrs']['isDescendentOfSingleProductTemplate'] );
		$product = wc_get_product( $single ? get_the_ID() : ( $instance->context['postId'] ?? 0 ) );
		if ( ! $product || ( ! $single && empty( $block['attrs']['isDescendentOfQueryLoop'] ) ) ) {
			return $html;
		}
		$rating = (float) $product->get_average_rating();
		$count  = bookshop_bn_digits( $product->get_review_count() );
		return sprintf(
			'<p class="bookshop-rating"><span class="bookshop-stars" style="--rating:%s%%" role="img" aria-label="%s"></span> %s</p>',
			esc_attr( $rating * 20 ),
			esc_attr( sprintf( '৫ এর মধ্যে %s', bookshop_bn_digits( $rating ) ) ),
			$single ? '<a href="#tab-reviews">' . esc_html( $count ) . 'টি রিভিউ</a>' : '(' . esc_html( $count ) . ')'
		);
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
			// Product page.
			'Description'                                             => 'বিবরণ',
			'Reviews'                                                 => 'রিভিউ',
			'Reviews (%d)'                                            => 'রিভিউ (%d)',
			'There are no reviews yet.'                               => 'এখনো কোনো রিভিউ নেই।',
			'Be the first to review &ldquo;%s&rdquo;'                 => 'প্রথম রিভিউটি আপনিই লিখুন',
			'Add a review'                                            => 'রিভিউ লিখুন',
			'Your rating'                                             => 'আপনার রেটিং',
			'Your review'                                             => 'আপনার রিভিউ',
			'Submit'                                                  => 'জমা দিন',
			'Name'                                                    => 'নাম',
			'Email'                                                   => 'ইমেইল',
			'Product quantity'                                        => 'পরিমাণ',
			// Mini-cart drawer.
			'Your cart'                                               => 'আপনার কার্ট',
			'(items: %d)'                                             => '· %d',
			'Save %s'                                                 => '%s সাশ্রয়',
			'Remove item'                                             => 'মুছে ফেলুন',
			'View cart'                                               => 'কার্ট দেখুন',
			'Go to checkout'                                          => 'চেকআউট করুন',
			// Checkout.
			'Billing &amp; Shipping'                                  => 'ডেলিভারি তথ্য',
			'Billing details'                                         => 'ডেলিভারি তথ্য',
			'Additional information'                                  => 'অতিরিক্ত তথ্য',
			'Order notes'                                             => 'অর্ডার নোট',
			'Notes about your order, e.g. special notes for delivery.' => 'ডেলিভারি নিয়ে কোনো নির্দেশনা থাকলে লিখুন',
			'optional'                                                => 'ঐচ্ছিক',
			'Your order'                                              => 'অর্ডার সারাংশ',
			'Subtotal'                                                => 'সাবটোটাল',
			'Total'                                                   => 'মোট',
			'Place order'                                             => 'অর্ডার কনফার্ম করুন',
			'Have a coupon?'                                          => 'কুপন কোড আছে?',
			'Click here to enter your code'                           => 'এখানে লিখুন',
			'If you have a coupon code, please apply it below.'       => 'আপনার কুপন কোডটি নিচে লিখুন।',
			'Coupon code'                                             => 'কুপন কোড',
			'Apply coupon'                                            => 'প্রয়োগ করুন',
			'I have read and agree to the website %s'                 => 'আমি ওয়েবসাইটের %s পড়েছি ও সম্মত আছি',
			'terms and conditions'                                    => 'শর্তাবলী',
		);
		return $labels[ $original ] ?? $text;
	},
	10,
	2
);

// ---------- Book authors: the "লেখক" attribute (pa_writer) ----------

function bookshop_bn_digits( $n ) {
	return strtr( (string) $n, array_combine( range( 0, 9 ), array( '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' ) ) );
}

// Author photo, or a circle with the first letter when none is set.
function bookshop_writer_image( $term, $size = 'thumbnail' ) {
	$id = (int) get_term_meta( $term->term_id, 'writer_image', true );
	if ( $id ) {
		return wp_get_attachment_image( $id, $size, false, array( 'class' => 'bookshop-writer-img', 'alt' => $term->name ) );
	}
	return '<span class="bookshop-writer-img is-empty" aria-hidden="true">' . esc_html( mb_substr( $term->name, 0, 1 ) ) . '</span>';
}

// Front page author strip: [bookshop_writers]. Arrows scroll the row; it also swipes on touch.
add_shortcode(
	'bookshop_writers',
	function () {
		$terms = get_terms( array( 'taxonomy' => 'pa_writer', 'orderby' => 'count', 'order' => 'DESC', 'number' => 20 ) );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$items = '';
		foreach ( $terms as $term ) {
			$items .= sprintf( '<li><a href="%s">%s<span>%s</span></a></li>', esc_url( get_term_link( $term ) ), bookshop_writer_image( $term ), esc_html( $term->name ) );
		}
		$arrow = '<button type="button" class="bookshop-writers__%1$s" aria-label="%2$s" onclick="this.parentNode.querySelector(\'ul\').scrollBy({left:%3$s*this.parentNode.offsetWidth/2,behavior:\'smooth\'})"></button>';
		return '<div class="bookshop-writers">' . sprintf( $arrow, 'prev', 'আগের লেখক', -1 ) . '<ul>' . $items . '</ul>' . sprintf( $arrow, 'next', 'পরের লেখক', 1 ) . '</div>';
	}
);

// Front page combo offer: [bookshop_combos]. The three combo packs with the biggest discount, prices read live.
add_shortcode(
	'bookshop_combos',
	function () {
		$combos = array();
		foreach ( wc_get_products( array( 'category' => array( 'special-combo-pack' ), 'status' => 'publish', 'limit' => -1 ) ) as $product ) {
			$off = bookshop_discount( $product );
			if ( $off ) {
				$combos[] = array( $product, $off );
			}
		}
		usort( $combos, fn( $a, $b ) => $b[1] <=> $a[1] );
		$items = '';
		foreach ( array_slice( $combos, 0, 3 ) as list( $product, $off ) ) {
			$items .= sprintf(
				'<li><a href="%s">%s<span><strong>%s</strong><span class="price">%s</span></span><em>%s%% ছাড়</em></a></li>',
				esc_url( $product->get_permalink() ),
				$product->get_image( 'thumbnail' ),
				esc_html( $product->get_name() ),
				$product->get_price_html(),
				esc_html( bookshop_bn_digits( $off ) )
			);
		}
		return $items ? '<ul class="bookshop-combos">' . $items . '</ul>' : '';
	}
);

// Front page reader reviews: [bookshop_reviews]. Latest approved book reviews; the whole section is hidden until there is one.
add_shortcode(
	'bookshop_reviews',
	function () {
		$reviews = get_comments( array( 'post_type' => 'product', 'status' => 'approve', 'type' => 'review', 'number' => 3 ) );
		if ( ! $reviews ) {
			return '';
		}
		$items = '';
		foreach ( $reviews as $review ) {
			$rating = (int) get_comment_meta( $review->comment_ID, 'rating', true );
			$items .= sprintf(
				'<li>%s<p>%s</p><footer><strong>%s</strong><a href="%s">%s</a></footer></li>',
				$rating ? sprintf( '<span class="bookshop-stars" style="--rating:%d%%" role="img" aria-label="৫ এর মধ্যে %s"></span>', $rating * 20, esc_attr( bookshop_bn_digits( $rating ) ) ) : '',
				esc_html( wp_trim_words( $review->comment_content, 40 ) ),
				esc_html( $review->comment_author ),
				esc_url( get_permalink( $review->comment_post_ID ) ),
				esc_html( get_the_title( $review->comment_post_ID ) )
			);
		}
		return '<div class="bookshop-reviews alignwide"><div class="bookshop-section-head"><h2 class="wp-block-heading">পাঠকদের মতামত</h2></div><ul>' . $items . '</ul></div>';
	}
);

// Admin: photo picker on the author edit screen (Products → Attributes → লেখক → Configure terms → Edit).
add_action(
	'pa_writer_edit_form_fields',
	function ( $term ) {
		$id = (int) get_term_meta( $term->term_id, 'writer_image', true );
		?>
		<tr class="form-field">
			<th scope="row"><label>লেখকের ছবি</label></th>
			<td class="bookshop-writer-field">
				<input type="hidden" name="writer_image" value="<?php echo esc_attr( $id ?: '' ); ?>">
				<div class="bookshop-writer-preview"><?php echo $id ? wp_get_attachment_image( $id, 'thumbnail' ) : ''; ?></div>
				<button type="button" class="button bookshop-writer-pick">ছবি বাছাই করুন</button>
				<button type="button" class="button-link-delete bookshop-writer-remove">মুছুন</button>
				<p class="description">জীবনী লিখুন উপরের "Description" ঘরে।</p>
			</td>
		</tr>
		<?php
	}
);

add_action(
	'admin_enqueue_scripts',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'term' !== $screen->base || 'pa_writer' !== $screen->taxonomy ) {
			return;
		}
		wp_enqueue_media();
		wp_add_inline_script(
			'media-editor',
			"jQuery( function ( $ ) {
				$( document ).on( 'click', '.bookshop-writer-pick', function () {
					const field = $( this ).closest( '.bookshop-writer-field' );
					const frame = wp.media( { title: 'লেখকের ছবি', library: { type: 'image' }, multiple: false } );
					frame.on( 'select', function () {
						const img = frame.state().get( 'selection' ).first().toJSON();
						field.find( 'input' ).val( img.id );
						field.find( '.bookshop-writer-preview' ).html( $( '<img width=\"96\">' ).attr( 'src', ( img.sizes.thumbnail || img ).url ) );
					} );
					frame.open();
				} );
				$( document ).on( 'click', '.bookshop-writer-remove', function () {
					const field = $( this ).closest( '.bookshop-writer-field' );
					field.find( 'input' ).val( '' );
					field.find( '.bookshop-writer-preview' ).empty();
				} );
			} );"
		);
	}
);

// Core verifies the edit-term nonce before this runs; quick edit doesn't post the field, so it's left alone.
add_action(
	'edited_pa_writer',
	function ( $term_id ) {
		if ( ! isset( $_POST['writer_image'] ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		$id = absint( $_POST['writer_image'] );
		if ( $id ) {
			update_term_meta( $term_id, 'writer_image', $id );
		} else {
			delete_term_meta( $term_id, 'writer_image' );
		}
	}
);

// Author page: replace the plain title with photo, name, book count and bio.
add_filter(
	'render_block_core/query-title',
	function ( $html ) {
		if ( ! is_tax( 'pa_writer' ) ) {
			return $html;
		}
		$term = get_queried_object();
		$bio  = term_description( $term );
		return sprintf(
			'<div class="bookshop-writer-head alignwide">%s<div><h1 class="wp-block-query-title">%s</h1><p class="bookshop-writer-count">%s টি বই</p>%s</div></div>',
			bookshop_writer_image( $term, 'medium' ),
			esc_html( $term->name ),
			esc_html( bookshop_bn_digits( $term->count ) ),
			$bio ? '<div class="bookshop-writer-bio">' . $bio . '</div>' : ''
		);
	}
);
add_filter(
	'render_block_core/term-description',
	function ( $html ) {
		return is_tax( 'pa_writer' ) ? '' : $html; // Already shown in the author header.
	}
);

// Author name(s) under the product title, on cards and the single book page.
add_filter(
	'render_block_core/post-title',
	function ( $html, $block, $instance ) {
		$id = $instance->context['postId'] ?? 0;
		if ( ! $id || 'product' !== get_post_type( $id ) ) {
			return $html;
		}
		$terms = get_the_terms( $id, 'pa_writer' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return $html;
		}
		$links = array_map(
			fn( $t ) => sprintf( '<a href="%s">%s</a>', esc_url( get_term_link( $t ) ), esc_html( $t->name ) ),
			$terms
		);
		return $html . '<p class="bookshop-writer">' . implode( ', ', $links ) . '</p>';
	},
	10,
	3
);

// Live search endpoint. Uses the normal WordPress search (title, descriptions, tags, writers),
// unlike the Store API, which only matches titles and SKUs.
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'bookshop/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'search' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'callback'            => function ( $request ) {
					$query = new WP_Query(
						array(
							'post_type'      => 'product',
							'post_status'    => 'publish',
							's'              => $request['search'],
							'posts_per_page' => 6,
							'no_found_rows'  => true,
							'tax_query'      => array(
								array(
									'taxonomy' => 'product_visibility',
									'field'    => 'name',
									'terms'    => array( 'exclude-from-search' ),
									'operator' => 'NOT IN',
								),
							),
						)
					);
					return array_map(
						function ( $post ) {
							$product = wc_get_product( $post );
							return array(
								'name'       => $product->get_name(),
								'permalink'  => $product->get_permalink(),
								'image'      => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '',
								'price_html' => $product->get_price_html(),
							);
						},
						$query->posts
					);
				},
			)
		);
	}
);

// ---------- Checkout: Shopify-style two columns (classic checkout) ----------
// Left: delivery details + payment. Right: order summary. Layout is in style.css.
add_action(
	'wp',
	function () {
		if ( ! is_checkout() ) {
			return;
		}
		remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
		add_action(
			'woocommerce_checkout_before_customer_details',
			function () {
				echo '<div class="bookshop-co-main">';
			}
		);
		add_action(
			'woocommerce_checkout_after_customer_details',
			function () {
				echo '<h3 class="bookshop-co-heading">পেমেন্ট</h3>';
				woocommerce_checkout_payment(); // AJAX refreshes find it by class, wherever it sits.
				echo '</div>';
			}
		);
		add_action(
			'woocommerce_checkout_before_order_review_heading',
			function () {
				echo '<aside class="bookshop-co-summary"><div class="bookshop-co-summary__inner">';
			}
		);
		add_action(
			'woocommerce_checkout_after_order_review',
			function () {
				echo '</div></aside>';
			}
		);
	}
);

// Privacy text goes under the confirm button. On wp_loaded so it also applies to AJAX refreshes.
add_action(
	'wp_loaded',
	function () {
		remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
		add_action( 'woocommerce_review_order_after_submit', 'wc_checkout_privacy_policy_text' );
	}
);

// Order summary rows get a thumbnail with a quantity bubble.
add_filter(
	'woocommerce_cart_item_name',
	function ( $name, $item ) {
		if ( ! is_checkout() ) {
			return $name;
		}
		return sprintf(
			'<span class="bookshop-co-item"><span class="bookshop-co-thumb">%s<span class="bookshop-co-qty">%s</span></span><span class="bookshop-co-name">%s</span></span>',
			$item['data']->get_image( 'thumbnail' ),
			esc_html( bookshop_bn_digits( $item['quantity'] ) ),
			$name
		);
	},
	10,
	2
);

add_filter(
	'woocommerce_shipping_package_name',
	function () {
		return 'ডেলিভারি চার্জ';
	}
);
