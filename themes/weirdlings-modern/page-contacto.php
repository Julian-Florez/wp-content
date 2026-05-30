<?php
/*
Template Name: Contacto
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<section class="wl-page-wrap wl-contact-page">
	<div class="wl-container">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php echo weirdlings_render_contact_page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endwhile; ?>
	</div>
</section>

<?php
get_footer();