<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="wl-shop-wrap">
  <div class="wl-container">
    <?php
    if ( function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'weirdlings_render_account_page' ) ) {
				echo weirdlings_render_account_page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			woocommerce_content();
		}
    ?>
  </div>
</section>

<?php
get_footer();
