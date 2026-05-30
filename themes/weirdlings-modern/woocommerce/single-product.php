<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="wl-single-wrap">
  <div class="wl-container">
    <?php while ( have_posts() ) : the_post(); ?>
      <header class="wl-single-header wl-archive-header">
        <h1><?php the_title(); ?></h1>
        <p>Una criatura Weirdlings con el mismo lenguaje visual de la portada y el catálogo.</p>
      </header>
      <?php wc_get_template_part( 'content', 'single-product' ); ?>
    <?php endwhile; ?>
  </div>
</section>

<?php
get_footer();
