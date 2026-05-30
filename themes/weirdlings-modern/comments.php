<?php
/**
 * Custom comments template for Weirdlings blog posts.
 *
 * @package Weirdlings_Modern
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}

$comment_count = get_comments_number();
$comment_count_text = number_format_i18n( $comment_count );
$comment_form_args = array(
	'class_form'           => 'wl-comments-form',
	'class_submit'         => 'wl-button wl-button--primary wl-comments-form__submit',
	'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
	'title_reply_before'   => '<div class="wl-comments-form__eyebrow">',
	'title_reply_after'    => '</div>',
	'label_submit'         => __( 'Publicar comentario', 'weirdlings-modern' ),
	'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'Comentario', 'weirdlings-modern' ) . ' *</label><textarea id="comment" name="comment" rows="6" required placeholder="' . esc_attr__( 'Cuéntanos qué piensas...', 'weirdlings-modern' ) . '"></textarea></p>',
	'fields'               => array(
		'author' => '<p class="comment-form-author"><label for="author">' . esc_html__( 'Nombre', 'weirdlings-modern' ) . ' *</label><input id="author" name="author" type="text" value="" size="30" required placeholder="' . esc_attr__( 'Tu nombre', 'weirdlings-modern' ) . '" /></p>',
		'email'  => '<p class="comment-form-email"><label for="email">' . esc_html__( 'Correo electrónico', 'weirdlings-modern' ) . ' *</label><input id="email" name="email" type="email" value="" size="30" required placeholder="' . esc_attr__( 'tu@correo.com', 'weirdlings-modern' ) . '" /></p>',
		'url'    => '<p class="comment-form-url"><label for="url">' . esc_html__( 'Web', 'weirdlings-modern' ) . '</label><input id="url" name="url" type="url" value="" size="30" placeholder="https://" /></p>',
	),
);

$render_comment = function ( $comment, $args, $depth ) {
	$GLOBALS['comment'] = $comment;
	$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';
	?>
	<<?php echo esc_html( $tag ); ?> <?php comment_class( 'wl-comment-card' ); ?> id="comment-<?php comment_ID(); ?>">
		<article class="wl-comment-card__inner">
			<div class="wl-comment-card__avatar">
				<?php echo get_avatar( $comment, 72 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="wl-comment-card__content">
				<header class="wl-comment-card__header">
					<div>
						<div class="wl-comment-card__eyebrow"><?php echo esc_html__( 'Comentario', 'weirdlings-modern' ); ?></div>
						<h3 class="wl-comment-card__author"><?php echo get_comment_author_link( $comment ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h3>
					</div>
					<time class="wl-comment-card__date" datetime="<?php echo esc_attr( get_comment_date( DATE_W3C, $comment ) ); ?>">
						<?php echo esc_html( get_comment_date( '', $comment ) ); ?>
					</time>
				</header>

				<div class="wl-comment-card__body">
					<?php comment_text(); ?>
				</div>

				<footer class="wl-comment-card__footer">
					<?php
					comment_reply_link(
						array_merge(
							$args,
							array(
								'depth'     => $depth,
								'max_depth' => $args['max_depth'],
								'add_below'  => 'comment',
								'before'    => '<span class="wl-comment-card__reply">',
								'after'     => '</span>',
							)
						)
					);
					?>
				</footer>
			</div>
		</article>
	</<?php echo esc_html( $tag ); ?>>
	<?php
};
?>

<section id="comments" class="wl-comments-shell">
	<div class="wl-comments-shell__head">
		<div>
			<div class="wl-comments-shell__eyebrow"><?php esc_html_e( 'Conversacion', 'weirdlings-modern' ); ?></div>
			<h2><?php echo esc_html( sprintf( _n( '%s respuesta', '%s respuestas', $comment_count, 'weirdlings-modern' ), $comment_count_text ) ); ?></h2>
		</div>
		<p><?php esc_html_e( 'Deja un comentario con vibra tranquila, curiosa o extraña. Todo queda dentro del estilo Weirdlings.', 'weirdlings-modern' ); ?></p>
	</div>

	<?php if ( have_comments() ) : ?>
		<ol class="wl-comments-list">
			<?php
			wp_list_comments(
				array(
					'style'      => 'ol',
					'short_ping' => true,
					'avatar_size'=> 72,
					'callback'   => $render_comment,
				)
			);
			?>
		</ol>

		<?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : ?>
			<nav class="wl-comments-pagination" aria-label="<?php esc_attr_e( 'Paginacion de comentarios', 'weirdlings-modern' ); ?>">
				<?php paginate_comments_links(); ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>

	<div class="wl-comments-form-shell">
		<?php
		comment_form(
			array_merge(
				$comment_form_args,
				array(
					'title_reply' => have_comments() ? __( 'Escribe tu comentario', 'weirdlings-modern' ) : __( 'Deja un comentario', 'weirdlings-modern' ),
				)
			)
		);
		?>
	</div>
</section>