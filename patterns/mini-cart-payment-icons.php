<?php
/**
 * Title: Mini-cart payment icons
 * Slug: bookshop/mini-cart-payment-icons
 * Categories: woo-commerce
 * Inserter: no
 * Description: Payment logos under the mini-cart checkout button. Edit them in Site Editor → Patterns → Mini-Cart.
 */

$bookshop_pay = get_theme_file_uri( 'assets/images/payments/' );
?>
<!-- wp:group {"className":"bookshop-pay-icons","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
<div class="wp-block-group bookshop-pay-icons"><!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="<?php echo esc_url( $bookshop_pay . 'cod.svg' ); ?>" alt="ক্যাশ অন ডেলিভারি"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="<?php echo esc_url( $bookshop_pay . 'bkash.png' ); ?>" alt="বিকাশ"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="<?php echo esc_url( $bookshop_pay . 'nagad.png' ); ?>" alt="নগদ"/></figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="<?php echo esc_url( $bookshop_pay . 'rocket.png' ); ?>" alt="রকেট"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->
