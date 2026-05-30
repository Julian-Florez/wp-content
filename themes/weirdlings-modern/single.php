<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$weirdlings_split_post_content = static function ( string $content ): array {
	$intro = '';
	$rest  = $content;

	if ( preg_match( '/<p[^>]*>.*?<\/p>/is', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
		$intro = $matches[0][0];
		$rest  = substr( $content, $matches[0][1] + strlen( $matches[0][0] ) );
		$rest  = ltrim( $rest );
	}

	return array( $intro, $rest );
};
?>

<section class="wl-page-wrap wl-blog-single-wrap">
	<div class="wl-container wl-blog-single-container">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php
			$rendered_content = apply_filters( 'the_content', get_the_content() );
			list( $intro_content, $body_content ) = $weirdlings_split_post_content( $rendered_content );
			?>
			<article <?php post_class( 'wl-blog-single' ); ?>>
				<header class="wl-blog-single__header">
					<div class="wl-blog-single__meta">
						<span><?php echo esc_html( get_the_date() ); ?></span>
						<span><?php echo esc_html( get_the_author() ); ?></span>
					</div>
					<h1><?php the_title(); ?></h1>
				</header>

				<?php if ( has_post_thumbnail() || '' !== trim( wp_strip_all_tags( $intro_content ) ) ) : ?>
					<div class="wl-blog-single__intro<?php echo has_post_thumbnail() ? ' wl-blog-single__intro--with-thumb' : ''; ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<figure class="wl-blog-single__thumb">
								<?php the_post_thumbnail( 'large' ); ?>
							</figure>
						<?php endif; ?>

						<?php if ( '' !== trim( wp_strip_all_tags( $intro_content ) ) ) : ?>
							<div class="wl-blog-single__intro-copy">
								<?php echo wp_kses_post( $intro_content ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="wl-blog-single__content">
					<?php echo wp_kses_post( $body_content ); ?>
				</div>

				<footer class="wl-blog-single__footer">
					<div class="wl-blog-single__tags">
						<?php the_tags( '<span>', '</span><span>', '</span>' ); ?>
					</div>
					<div class="wl-blog-single__nav">
						<div><?php previous_post_link( '%link', __( 'Entrada anterior', 'weirdlings-modern' ) ); ?></div>
						<div><?php next_post_link( '%link', __( 'Entrada siguiente', 'weirdlings-modern' ) ); ?></div>
					</div>
				</footer>
			</article>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		<?php endwhile; ?>
	</div>
</section>

<?php
get_footer();
