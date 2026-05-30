<?php
/**
 * Template Name: Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$query = new WP_Query(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => (int) get_option( 'posts_per_page', 10 ),
		'paged'          => $paged,
	)
);
?>

<section class="wl-page-wrap wl-blog-wrap">
	<div class="wl-container">
		<header class="wl-page-header wl-blog-header">
			<h1><?php the_title(); ?></h1>
			<p><?php esc_html_e( 'Historias, guias y novedades del universo Weirdlings.', 'weirdlings-modern' ); ?></p>
		</header>

		<?php if ( $query->have_posts() ) : ?>
			<div class="wl-blog-grid">
				<?php while ( $query->have_posts() ) : $query->the_post(); ?>
					<article class="wl-blog-card">
						<a class="wl-blog-card__media" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'large' ); ?>
							<?php else : ?>
								<?php echo weirdlings_render_placeholder( get_the_title(), 'square', 900, 620 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</a>
						<div class="wl-blog-card__body">
							<div class="wl-blog-card__meta">
								<span><?php echo esc_html( get_the_date() ); ?></span>
								<span><?php echo esc_html( get_the_author() ); ?></span>
							</div>
							<h2 class="wl-blog-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p class="wl-blog-card__excerpt"><?php echo esc_html( has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 30 ) ); ?></p>
							<a class="wl-button wl-button--ghost" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Leer entrada', 'weirdlings-modern' ); ?></a>
						</div>
					</article>
				<?php endwhile; ?>
			</div>

			<?php
			$pagination = paginate_links(
				array(
					'base'      => str_replace( 999999, '%#%', esc_url( get_pagenum_link( 999999 ) ) ),
					'format'    => '?paged=%#%',
					'current'   => $paged,
					'total'     => (int) $query->max_num_pages,
					'prev_text' => __( 'Anterior', 'weirdlings-modern' ),
					'next_text' => __( 'Siguiente', 'weirdlings-modern' ),
					'type'      => 'list',
				)
			);
			if ( $pagination ) :
				?>
				<nav class="wl-blog-pagination" aria-label="<?php esc_attr_e( 'Paginacion del blog', 'weirdlings-modern' ); ?>">
					<?php echo wp_kses_post( $pagination ); ?>
				</nav>
			<?php endif; ?>

		<?php else : ?>
			<div class="wl-account-empty">
				<h2><?php esc_html_e( 'Aun no hay entradas publicadas', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Cuando publiques tu primer post aparecera aqui automaticamente.', 'weirdlings-modern' ); ?></p>
			</div>
		<?php endif; ?>

		<?php wp_reset_postdata(); ?>
	</div>
</section>

<?php
get_footer();
