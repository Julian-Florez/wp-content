<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="wl-shop-wrap">
  <div class="wl-container">
    <header class="wl-archive-header">
      <h1><?php woocommerce_page_title(); ?></h1>
      <p>El catálogo sigue la misma estética horror cute de la portada, con tarjetas oscuras y acentos coleccionables.</p>
    </header>

    <?php if ( woocommerce_product_loop() ) : ?>
      <?php woocommerce_product_loop_start(); ?>
        <?php while ( have_posts() ) : the_post(); ?>
          <?php wc_get_template_part( 'content', 'product' ); ?>
        <?php endwhile; ?>
      <?php woocommerce_product_loop_end(); ?>
    <?php else : ?>
      <div class="wl-product-empty">Todavía no hay productos publicados. La tienda mostrará placeholders hasta que se carguen las criaturas reales.</div>
    <?php endif; ?>
  </div>
</section>

<?php
get_footer();
