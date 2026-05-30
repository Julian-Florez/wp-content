<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="wl-page-wrap">
  <div class="wl-container">
    <?php while ( have_posts() ) : the_post(); ?>
      <header class="wl-page-header">
        <h1><?php the_title(); ?></h1>
      </header>
      <article class="wl-page-header">
        <?php the_content(); ?>
      </article>
    <?php endwhile; ?>
  </div>
</section>

<?php
get_footer();
