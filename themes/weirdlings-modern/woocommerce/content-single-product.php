<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * @package WooCommerce\Templates
 */

defined( 'ABSPATH' ) || exit;

global $product;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'wl-single-product', $product ); ?>>
	<div class="wl-single-product__top">
		<div class="wl-single-product__gallery">
			<?php
			/**
			 * Hook: woocommerce_before_single_product_summary.
			 *
			 * @hooked woocommerce_show_product_sale_flash - 10
			 * @hooked woocommerce_show_product_images - 20
			 */
			do_action( 'woocommerce_before_single_product_summary' );
			?>
		</div>

		<div class="wl-single-product__sidebar">
			<?php echo weirdlings_render_rarity_badge( $product->get_id(), 'single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="summary entry-summary">
				<?php
				/**
				 * Hook: woocommerce_single_product_summary.
				 *
				 * @hooked woocommerce_template_single_title - 5
				 * @hooked woocommerce_template_single_rating - 10
				 * @hooked woocommerce_template_single_price - 10
				 * @hooked woocommerce_template_single_excerpt - 20
				 * @hooked woocommerce_template_single_add_to_cart - 30
				 * @hooked woocommerce_template_single_meta - 40
				 * @hooked woocommerce_template_single_sharing - 50
				 * @hooked WC_Structured_Data::generate_product_data() - 60
				 */
				do_action( 'woocommerce_single_product_summary' );
				?>
			</div>

			<div class="wl-single-product__details">
				<section class="wl-single-product__section wl-single-product__section--description">
					<?php wc_get_template( 'single-product/tabs/description.php' ); ?>
				</section>
			</div>
		</div>
	</div>

	<div class="wl-single-product__related">
		<?php
		$related_args = apply_filters(
			'woocommerce_output_related_products_args',
			array(
				'posts_per_page' => 8,
				'columns'        => 4,
			)
		);

		$related_ids = wc_get_related_products(
			$product->get_id(),
			$related_args['posts_per_page'],
			array_merge( array( $product->get_id() ), $product->get_upsell_ids() )
		);

		if ( $related_ids ) :
			?>
			<div class="wl-related-carousel" data-related-carousel>
				<div class="wl-related-carousel__head">
					<h2><?php esc_html_e( 'Productos relacionados', 'weirdlings-modern' ); ?></h2>
					<div class="wl-related-carousel__controls" aria-label="Productos relacionados">
						<button type="button" class="wl-related-carousel__button wl-related-carousel__button--prev" data-related-scroll="prev" aria-label="Ver producto anterior">
							<span aria-hidden="true">‹</span>
						</button>
						<button type="button" class="wl-related-carousel__button wl-related-carousel__button--next" data-related-scroll="next" aria-label="Ver producto siguiente">
							<span aria-hidden="true">›</span>
						</button>
					</div>
				</div>
				<div class="wl-related-carousel__viewport">
					<ul class="products columns-<?php echo esc_attr( max( 1, (int) $related_args['columns'] ) ); ?> wl-related-carousel__track">
						<?php
						global $post;
						$original_post = $post;

						foreach ( $related_ids as $related_id ) {
							$post_object = get_post( $related_id );

							if ( ! $post_object ) {
								continue;
							}

							setup_postdata( $GLOBALS['post'] = $post_object );
							wc_get_template_part( 'content', 'product' );
						}

						wp_reset_postdata();
						$GLOBALS['post'] = $original_post;
						?>
					</ul>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<section class="wl-single-product__section wl-single-product__section--reviews wl-single-product__reviews">
		<?php comments_template(); ?>
	</section>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>