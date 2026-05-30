<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="wl-page-wrap">
  <div class="wl-container">
    <div class="wl-page-header">
      <h1><?php bloginfo( 'name' ); ?></h1>
      <p><?php bloginfo( 'description' ); ?></p>
    </div>

    <?php if ( have_posts() ) : ?>
      <div class="wl-product-grid">
        <?php while ( have_posts() ) : the_post(); ?>
          <article class="wl-product-card">
            <div class="wl-product-card__media">
              <?php if ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail( 'large' ); ?>
              <?php else : ?>
                <?php echo weirdlings_render_placeholder( get_the_title(), 'square', 800, 900 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              <?php endif; ?>
            </div>
            <div class="wl-product-card__content">
              <div class="wl-product-card__type"><?php echo esc_html( get_post_type() ); ?></div>
              <h2 class="wl-product-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
              <div class="wl-product-card__price"><?php echo esc_html( get_the_date() ); ?></div>
            </div>
          </article>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php
get_footer();
