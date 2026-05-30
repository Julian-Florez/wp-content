<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$collections_page   = get_page_by_path( 'colecciones' );
$collections_title  = $collections_page instanceof WP_Post ? get_the_title( $collections_page ) : __( 'Colecciones', 'weirdlings-modern' );
$collections_content = $collections_page instanceof WP_Post ? apply_filters( 'the_content', $collections_page->post_content ) : '';
$selected_category  = isset( $_GET['categoria'] ) ? sanitize_text_field( wp_unslash( $_GET['categoria'] ) ) : '';
$selected_term      = $selected_category ? get_term_by( 'slug', $selected_category, 'product_cat' ) : false;
$selected_image_url = ( $selected_term && ! is_wp_error( $selected_term ) ) ? weirdlings_collection_term_image_url( $selected_term ) : '';

$collections_map = array(
	'raro'               => 'raro',
	'espeluznante'       => 'espeluznante',
	'criatura-del-bosque' => 'criatura-del-bosque',
	'criatura del bosque' => 'criatura-del-bosque',
	'criaturadelbosque'  => 'criatura-del-bosque',
	'alienigenas'        => 'alienigenas',
	'alienígenas'        => 'alienigenas',
);

$filtered_products = null;

if ( $selected_category ) {
	$normalized_category = strtolower( remove_accents( $selected_category ) );
	$mapped_slug         = $collections_map[ $normalized_category ] ?? sanitize_title( $selected_category );
	$filtered_term       = get_term_by( 'slug', $mapped_slug, 'product_cat' );

	if ( $filtered_term && ! is_wp_error( $filtered_term ) ) {
		$filtered_products = new WP_Query(
			array(
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'posts_per_page'      => -1,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'tax_query'           => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => (int) $filtered_term->term_id,
					),
				),
			)
		);
	}
}
?>

<section class="wl-page-wrap wl-collections-wrap">
	<div class="wl-container">
		<?php if ( $selected_category && $selected_image_url ) : ?>
			<section class="wl-collections-hero" style="background-image: url('<?php echo esc_url( $selected_image_url ); ?>');">
				<div class="wl-collections-hero__overlay">
					<h1 class="wl-collections-hero__title"><?php echo esc_html( $selected_term->name ); ?></h1>
                    
					<div class="wl-collections-hero__actions">
						<a class="wl-button wl-button--ghost" href="<?php echo esc_url( weirdlings_collections_page_url() ); ?>"><?php esc_html_e( 'Ver todas las colecciones', 'weirdlings-modern' ); ?></a>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<header class="wl-page-header wl-collections-header<?php echo $selected_category && $selected_image_url ? ' wl-collections-header--filtered' : ''; ?>">
			<h1><?php echo esc_html( $collections_title ); ?></h1>
            

			<?php if ( $selected_category && ! $selected_image_url ) : ?>
				<div class="wl-collections-header__actions">
					<a class="wl-button wl-button--ghost" href="<?php echo esc_url( weirdlings_collections_page_url() ); ?>"><?php esc_html_e( 'Ver todas las colecciones', 'weirdlings-modern' ); ?></a>
				</div>
			<?php endif; ?>
		</header>

		<?php if ( $collections_content && ! $selected_category ) : ?>
			<div class="wl-collections-intro">
				<?php echo wp_kses_post( $collections_content ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $selected_category ) : ?>
			<div class="wl-collections-products">
				<?php if ( $filtered_products instanceof WP_Query && $filtered_products->have_posts() ) : ?>
					<?php woocommerce_product_loop_start(); ?>
						<?php while ( $filtered_products->have_posts() ) : $filtered_products->the_post(); ?>
							<?php wc_get_template_part( 'content', 'product' ); ?>
						<?php endwhile; ?>
					<?php woocommerce_product_loop_end(); ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<div class="wl-product-empty"><?php esc_html_e( 'No encontramos productos para esa categoría.', 'weirdlings-modern' ); ?></div>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<?php $terms = weirdlings_collection_terms(); ?>
			<?php if ( ! empty( $terms ) ) : ?>
				<div class="wl-collections-grid">
					<?php foreach ( $terms as $term ) : ?>
						<?php
						$thumbnail_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
						$image_url    = '';

						// Primero: buscar archivo por slug en assets/images/collections/{slug}.{ext}
						$slug_base = 'assets/images/collections/' . $term->slug;
						$found_file = '';
						foreach ( array( 'jpg', 'jpeg', 'png', 'webp' ) as $ext ) {
							$candidate = $slug_base . '.' . $ext;
							$candidate_path = get_theme_file_path( $candidate );
							if ( file_exists( $candidate_path ) ) {
								$found_file = get_theme_file_uri( $candidate );
								$found_file .= ( strpos( $found_file, '?' ) === false ? '?' : '&' ) . 'v=' . (int) filemtime( $candidate_path );
								break;
							}
						}

						if ( $found_file ) {
							$image_url = $found_file;
						} else {
							// Si no hay archivo en assets, usar thumbnail de la categoría si existe
							if ( $thumbnail_id > 0 ) {
								$image_url = wp_get_attachment_image_url( $thumbnail_id, 'large' );
							}

							// Si aún no hay imagen, intentar tomar la imagen destacada del primer producto
							if ( ! $image_url ) {
								$products = new WP_Query(
									array(
										'post_type'           => 'product',
										'post_status'         => 'publish',
										'posts_per_page'      => 1,
										'no_found_rows'       => true,
										'ignore_sticky_posts' => true,
										'fields'              => 'ids',
										'tax_query'           => array(
											array(
												'taxonomy' => 'product_cat',
												'field'    => 'term_id',
												'terms'    => (int) $term->term_id,
											),
										),
									)
								);

								if ( ! empty( $products->posts[0] ) ) {
									$product_id    = (int) $products->posts[0];
									$attachment_id = (int) get_post_thumbnail_id( $product_id );
									if ( $attachment_id > 0 ) {
										$image_url = wp_get_attachment_image_url( $attachment_id, 'large' );
									} else {
										$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
										if ( $product ) {
											$image_url = get_the_post_thumbnail_url( $product_id, 'large' );
										}
									}
								}

								wp_reset_postdata();
							}

							if ( ! $image_url ) {
								$image_url = get_theme_file_uri( 'assets/images/collections/default-collection.jpg' );
							}
						}
						?>

						<article class="wl-collection-card" style="background-image: url('<?php echo esc_url( $image_url ); ?>');" data-bg="<?php echo esc_attr( $image_url ); ?>">
							<a class="wl-collection-card__link" href="<?php echo esc_url( weirdlings_collection_term_link( $term ) ); ?>" aria-label="<?php echo esc_attr( $term->name ); ?>">
								<img class="wl-collection-card__bg" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" aria-hidden="true" loading="lazy" />
								<div class="wl-collection-card__overlay">
									<div class="wl-collection-card__left">
										<h3 class="wl-collection-card__title"><?php echo esc_html( $term->name ); ?></h3>
										<div class="wl-collection-card__meta"><?php echo esc_html( sprintf( _n( '%d producto', '%d productos', (int) $term->count, 'weirdlings-modern' ), (int) $term->count ) ); ?></div>
									</div>
									<div class="wl-collection-card__right">
										<span class="wl-button wl-button--primary wl-collection-card__button"><?php esc_html_e( 'Entrar', 'weirdlings-modern' ); ?></span>
									</div>
								</div>
							</a>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="wl-product-empty"><?php esc_html_e( 'Todavía no hay categorías con productos publicados.', 'weirdlings-modern' ); ?></div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();