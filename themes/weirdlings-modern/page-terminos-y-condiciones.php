<?php
/*
Template Name: Términos y condiciones
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="wl-page-wrap wl-legal-page">
	<div class="wl-container">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php echo weirdlings_render_terms_page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endwhile; ?>
	</div>
</section>

<?php
get_footer();