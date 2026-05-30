<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

$badge = $product->is_on_sale() ? 'NUEVO' : 'TOP';
?>

<li <?php wc_product_class( 'wl-product-card wl-product-card--wc', $product ); ?>>
  <span class="wl-product-card__badge"><?php echo esc_html( $badge ); ?></span>
  <?php echo weirdlings_render_rarity_badge( $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  <a href="<?php the_permalink(); ?>" class="wl-product-card__media">
    <?php
    if ( has_post_thumbnail() ) {
			the_post_thumbnail( 'woocommerce_thumbnail' );
		} else {
			echo weirdlings_render_placeholder( get_the_title(), 'square', 800, 900 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
    ?>
  </a>
  <div class="wl-product-card__content">
    <div class="wl-product-card__type"><?php echo esc_html( weirdlings_product_label( $product->get_id() ) ); ?></div>
    <h2 class="wl-product-card__title"><?php the_title(); ?></h2>
    <div class="wl-product-card__price"><?php echo esc_html( weirdlings_product_price_text( $product ) ); ?></div>
  </div>
  <button type="button" class="wl-product-card__action" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" aria-label="Añadir <?php echo esc_attr( get_the_title() ); ?> al carrito">+</button>
</li>
