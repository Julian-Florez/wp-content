<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEIRDLINGS_THEME_VERSION', '1.0.0' );

function weirdlings_setup_theme() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array( 'height' => 96, 'width' => 320, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'custom-spacing' );
	add_theme_support( 'automatic-feed-links' );

	register_nav_menus(
		array(
			'primary' => __( 'Menú principal', 'weirdlings-modern' ),
			'footer'  => __( 'Menú de pie', 'weirdlings-modern' ),
		)
	);
}
add_action( 'after_setup_theme', 'weirdlings_setup_theme' );

function weirdlings_enqueue_assets() {
	wp_enqueue_style(
		'weirdlings-modern-style',
		get_stylesheet_uri(),
		array(),
		filemtime( get_stylesheet_directory() . '/style.css' )
	);

	wp_enqueue_script( 'weirdlings-modern-script', get_theme_file_uri( 'assets/js/theme.js' ), array( 'jquery' ), filemtime( get_theme_file_path( 'assets/js/theme.js' ) ), true );
	wp_script_add_data( 'weirdlings-modern-script', 'defer', true );
	wp_localize_script(
		'weirdlings-modern-script',
		'WeirdlingsChatbot',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => 'weirdlings_chatbot_proxy',
			'nonce'   => wp_create_nonce( 'weirdlings_chatbot_proxy' ),
		)
	);

	// On checkout, ensure WooCommerce enhanced selects (Select2/SelectWoo) are available
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'select2' );
	}
}
add_action( 'wp_enqueue_scripts', 'weirdlings_enqueue_assets' );

function weirdlings_chatbot_proxy_request( string $message_text, string $payload_json, array $files = array() ) {
	if ( ! defined( 'WEIRDLINGS_CHATBOT_WEBHOOK' ) || ! WEIRDLINGS_CHATBOT_WEBHOOK ) {
		return new WP_Error( 'weirdlings_chatbot_webhook_missing', __( 'No se configuró el webhook del chatbot.', 'weirdlings-modern' ) );
	}

	$body = array(
		'payload' => $payload_json,
	);

	if ( ! empty( $files ) ) {
		$body['media[]'] = $files;
	}

	$response = wp_remote_post(
		WEIRDLINGS_CHATBOT_WEBHOOK,
		array(
			'timeout' => 45,
			'body'    => $body,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status_code = (int) wp_remote_retrieve_response_code( $response );
	$raw_body    = (string) wp_remote_retrieve_body( $response );
	$decoded     = json_decode( $raw_body, true );
	$reply       = '';
	$html        = '';
	$options     = array();

	if ( is_array( $decoded ) ) {
		$reply   = isset( $decoded['reply'] ) ? (string) $decoded['reply'] : '';
		$html    = isset( $decoded['html'] ) ? (string) $decoded['html'] : '';
		$options = isset( $decoded['options'] ) && is_array( $decoded['options'] ) ? $decoded['options'] : array();

		if ( '' === $reply ) {
			$reply = isset( $decoded['message'] ) ? (string) $decoded['message'] : '';
		}
		if ( '' === $reply ) {
			$reply = isset( $decoded['response'] ) ? (string) $decoded['response'] : '';
		}
		if ( '' === $reply ) {
			$reply = isset( $decoded['output'] ) ? (string) $decoded['output'] : '';
		}
		if ( '' === $reply ) {
			$reply = isset( $decoded['answer'] ) ? (string) $decoded['answer'] : '';
		}
		if ( '' === $reply ) {
			$reply = isset( $decoded['text'] ) ? (string) $decoded['text'] : '';
		}
	}

	if ( '' === $reply ) {
		$reply = wp_strip_all_tags( $raw_body );
	}

	if ( '' === $reply ) {
		$reply = __( 'Recibimos tu mensaje, pero n8n no devolvió texto de respuesta.', 'weirdlings-modern' );
	}

	return array(
		'status'  => $status_code,
		'reply'   => $reply,
		'html'    => $html,
		'options' => $options,
	);
}

function weirdlings_chatbot_proxy_ajax() {
	check_ajax_referer( 'weirdlings_chatbot_proxy', 'nonce' );

	$payload_json = isset( $_POST['payload'] ) ? wp_unslash( (string) $_POST['payload'] ) : '';
	$payload      = json_decode( $payload_json, true );
	$message_text = '';

	if ( is_array( $payload ) && isset( $payload['message'] ) ) {
		$message_text = sanitize_text_field( (string) $payload['message'] );
	}

	$forward_files = array();
	if ( class_exists( 'CURLFile' ) && ! empty( $_FILES['media'] ) && is_array( $_FILES['media'] ) && ! empty( $_FILES['media']['name'] ) ) {
		$file_count = is_array( $_FILES['media']['name'] ) ? count( $_FILES['media']['name'] ) : 0;
		for ( $index = 0; $index < $file_count; $index++ ) {
			if ( empty( $_FILES['media']['name'][ $index ] ) || empty( $_FILES['media']['tmp_name'][ $index ] ) ) {
				continue;
			}

			$forward_files[] = new CURLFile(
				$_FILES['media']['tmp_name'][ $index ],
				sanitize_mime_type( (string) $_FILES['media']['type'][ $index ] ),
				sanitize_file_name( (string) $_FILES['media']['name'][ $index ] )
			);
		}
	}

	$result = weirdlings_chatbot_proxy_request( $message_text, $payload_json, $forward_files );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			),
			500
		);
	}

	wp_send_json_success( $result, 200 );
}
add_action( 'wp_ajax_weirdlings_chatbot_proxy', 'weirdlings_chatbot_proxy_ajax' );
add_action( 'wp_ajax_nopriv_weirdlings_chatbot_proxy', 'weirdlings_chatbot_proxy_ajax' );

function weirdlings_enable_myaccount_registration( $enabled ) {
	return 'yes';
}
add_filter( 'pre_option_woocommerce_enable_myaccount_registration', 'weirdlings_enable_myaccount_registration' );

function weirdlings_render_registration_fields() {
	?>
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="reg_first_name"><?php esc_html_e( 'Nombre', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'weirdlings-modern' ); ?></span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="first_name" id="reg_first_name" autocomplete="given-name" value="<?php echo isset( $_POST['first_name'] ) ? esc_attr( wp_unslash( (string) $_POST['first_name'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="reg_last_name"><?php esc_html_e( 'Apellido', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'weirdlings-modern' ); ?></span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="last_name" id="reg_last_name" autocomplete="family-name" value="<?php echo isset( $_POST['last_name'] ) ? esc_attr( wp_unslash( (string) $_POST['last_name'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="reg_phone"><?php esc_html_e( 'Teléfono', 'weirdlings-modern' ); ?>&nbsp;<span class="screen-reader-text"><?php esc_html_e( 'Optional', 'weirdlings-modern' ); ?></span></label>
		<input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="phone" id="reg_phone" autocomplete="tel" value="<?php echo isset( $_POST['phone'] ) ? esc_attr( wp_unslash( (string) $_POST['phone'] ) ) : ''; ?>" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="reg_password"><?php esc_html_e( 'Contraseña', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'weirdlings-modern' ); ?></span></label>
		<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="reg_password_2"><?php esc_html_e( 'Confirmar contraseña', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'weirdlings-modern' ); ?></span></label>
		<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_2" id="reg_password_2" autocomplete="new-password" required aria-required="true" />
	</p>

	<p class="wl-account-register-note"><?php esc_html_e( 'Crear una cuenta te permite seguir pedidos, guardar datos de envío y acelerar futuras compras.', 'weirdlings-modern' ); ?></p>
	<?php
}
add_action( 'woocommerce_register_form', 'weirdlings_render_registration_fields' );

function weirdlings_validate_registration_fields( $errors, $username, $email ) {
	$first_name  = isset( $_POST['first_name'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['first_name'] ) ) ) : '';
	$last_name   = isset( $_POST['last_name'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['last_name'] ) ) ) : '';
	$phone       = isset( $_POST['phone'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['phone'] ) ) ) : '';
	$password_1  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
	$password_2  = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : '';

	if ( '' === $first_name ) {
		$errors->add( 'registration_first_name_missing', __( 'Por favor ingresa tu nombre.', 'weirdlings-modern' ) );
	}

	if ( '' === $last_name ) {
		$errors->add( 'registration_last_name_missing', __( 'Por favor ingresa tu apellido.', 'weirdlings-modern' ) );
	}

	if ( '' === $password_1 ) {
		$errors->add( 'registration_password_missing', __( 'Por favor ingresa una contraseña.', 'weirdlings-modern' ) );
	}

	if ( '' === $password_2 ) {
		$errors->add( 'registration_password_confirm_missing', __( 'Confirma tu contraseña.', 'weirdlings-modern' ) );
	}

	if ( '' !== $password_1 && '' !== $password_2 && $password_1 !== $password_2 ) {
		$errors->add( 'registration_password_mismatch', __( 'Las contraseñas no coinciden.', 'weirdlings-modern' ) );
	}

	return $errors;
}
add_filter( 'woocommerce_registration_errors', 'weirdlings_validate_registration_fields', 10, 3 );

function weirdlings_force_manual_registration_password( $value ) {
	return is_admin() ? $value : 'no';
}
add_filter( 'pre_option_woocommerce_registration_generate_password', 'weirdlings_force_manual_registration_password' );

function weirdlings_save_registration_fields( $customer_id ) {
	$first_name = isset( $_POST['first_name'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['first_name'] ) ) ) : '';
	$last_name  = isset( $_POST['last_name'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['last_name'] ) ) ) : '';
	$phone      = isset( $_POST['phone'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['phone'] ) ) ) : '';

	if ( '' !== $first_name ) {
		update_user_meta( $customer_id, 'first_name', $first_name );
		update_user_meta( $customer_id, 'billing_first_name', $first_name );
	}

	if ( '' !== $last_name ) {
		update_user_meta( $customer_id, 'last_name', $last_name );
		update_user_meta( $customer_id, 'billing_last_name', $last_name );
	}

	if ( '' !== $phone ) {
		update_user_meta( $customer_id, 'billing_phone', $phone );
	} else {
		delete_user_meta( $customer_id, 'billing_phone' );
	}

	$display_name = trim( $first_name . ' ' . $last_name );
	if ( '' !== $display_name ) {
		wp_update_user(
			array(
				'ID'           => $customer_id,
				'display_name' => $display_name,
				'nickname'     => $display_name,
			)
		);
	}
}
add_action( 'woocommerce_created_customer', 'weirdlings_save_registration_fields' );

function weirdlings_open_cart_controls_wrapper() {
	echo '<div class="wl-cart-controls"><div class="wl-cart-controls__quantity"><button type="button" class="wl-qty-btn wl-qty-btn--minus" aria-label="Disminuir cantidad">−</button>';
}
add_action( 'woocommerce_before_add_to_cart_quantity', 'weirdlings_open_cart_controls_wrapper', 5 );

function weirdlings_close_cart_controls_quantity() {
	echo '<button type="button" class="wl-qty-btn wl-qty-btn--plus" aria-label="Aumentar cantidad">+</button>';
}
add_action( 'woocommerce_after_add_to_cart_quantity', 'weirdlings_close_cart_controls_quantity', 20 );

function weirdlings_close_cart_controls_wrapper() {
	echo '</div></div>';
}
add_action( 'woocommerce_after_add_to_cart_button', 'weirdlings_close_cart_controls_wrapper', 20 );

function weirdlings_force_product_reviews_open( $open, $post_id ) {
	if ( ! is_singular( 'product' ) ) {
		return $open;
	}

	$post = get_post( $post_id );
	if ( ! $post || 'product' !== $post->post_type ) {
		return $open;
	}

	return true;
}
add_filter( 'comments_open', 'weirdlings_force_product_reviews_open', 20, 2 );

function weirdlings_body_classes( array $classes ): array {
	$classes[] = 'weirdlings-theme';
	if ( is_front_page() ) {
		$classes[] = 'weirdlings-home';
	}
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		$classes[] = 'weirdlings-account';
	}

	return $classes;
}
add_filter( 'body_class', 'weirdlings_body_classes' );

function weirdlings_svg_placeholder( string $label, string $variant = 'product', int $width = 1200, int $height = 900 ): string {
	$label = esc_html( $label );
	$variant_colors = array(
		'hero'    => array( '#1b1324', '#7c59c7', '#d775b7' ),
		'product' => array( '#16111d', '#b9c33b', '#d775b7' ),
		'portrait' => array( '#16111d', '#7c59c7', '#f2f0ea' ),
		'square'  => array( '#16111d', '#d775b7', '#b9c33b' ),
	);
	$colors = $variant_colors[ $variant ] ?? $variant_colors['product'];
	$accent_label = esc_attr( $colors[1] );

	$svg = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d" role="img" aria-label="%4$s"><defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop offset="0%%" stop-color="%3$s"/><stop offset="55%%" stop-color="#0b0810"/><stop offset="100%%" stop-color="#1b1324"/></linearGradient><radialGradient id="r" cx="50%%" cy="35%%" r="65%%"><stop offset="0%%" stop-color="#ffffff" stop-opacity="0.22"/><stop offset="100%%" stop-color="#ffffff" stop-opacity="0"/></radialGradient></defs><rect width="100%%" height="100%%" rx="40" fill="url(#g)"/><rect x="20" y="20" width="%1$d" height="%2$d" rx="36" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="2"/><circle cx="%5$d" cy="%6$d" r="%7$d" fill="url(#r)"/><text x="50%%" y="47%%" text-anchor="middle" fill="#f2f0ea" font-size="%8$d" font-family="Georgia, serif" font-weight="700">%4$s</text><text x="50%%" y="58%%" text-anchor="middle" fill="%9$s" font-size="%10$d" font-family="Space Grotesk, sans-serif" letter-spacing="6">PLACEHOLDER</text></svg>',
		$width,
		$height,
		esc_attr( $colors[0] ),
		$label,
		(int) round( $width * 0.52 ),
		(int) round( $height * 0.37 ),
		(int) round( min( $width, $height ) * 0.22 ),
		(int) round( max( 36, min( $width, $height ) * 0.08 ) ),
		$accent_label,
		(int) round( max( 22, min( $width, $height ) * 0.03 ) )
	);

	return $svg;
}

function weirdlings_primary_menu_fallback() {
	$items = array(
		'/'              => 'Inicio',
		'/tienda/'       => 'Tienda',
		'/llaveros/'     => 'Llaveros',
		'/amigurumis/'   => 'Amigurumis',
		'/personalizados/' => 'Personalizados',
		'/blog/'         => 'Blog',
		'/sobre-mi/'     => 'Sobre mí',
	);

	echo '<ul>';
	foreach ( $items as $url => $label ) {
		echo '<li><a href="' . esc_url( home_url( $url ) ) . '">' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul>';
}

function weirdlings_collections_page_url(): string {
	$page = get_page_by_path( 'colecciones' );

	if ( $page instanceof WP_Post ) {
		return get_permalink( $page );
	}

	return home_url( '/colecciones/' );
}

function weirdlings_collections_normalize_token( string $value ): string {
	return mb_strtolower( remove_accents( trim( $value ) ) );
}

function weirdlings_collection_terms(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}

function weirdlings_collection_term_link( WP_Term $term ): string {
	return add_query_arg( 'categoria', $term->slug, weirdlings_collections_page_url() );
}

function weirdlings_collection_term_image_html( WP_Term $term ): string {
	$image_url = weirdlings_collection_term_image_url( $term );

	if ( $image_url ) {
		return sprintf(
			'<img src="%1$s" alt="%2$s" loading="lazy" />',
			esc_url( $image_url ),
			esc_attr( $term->name )
		);
	}

	return weirdlings_render_placeholder( $term->name, 'square', 1200, 900 );
}

function weirdlings_collection_term_image_url( WP_Term $term ): string {
	$thumbnail_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );

	if ( $thumbnail_id > 0 ) {
		$image_url = wp_get_attachment_image_url( $thumbnail_id, 'large' );
		if ( $image_url ) {
			return $image_url;
		}
	}

	$slug_base  = 'assets/images/collections/' . $term->slug;
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
		return $found_file;
	}

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
			if ( $image_url ) {
				wp_reset_postdata();
				return $image_url;
			}
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$image_url = wp_get_attachment_image_url( $product->get_image_id(), 'large' );
				if ( $image_url ) {
					wp_reset_postdata();
					return $image_url;
				}
			}
		}
	}

	wp_reset_postdata();

	return get_theme_file_uri( 'assets/images/collections/default-collection.jpg' );
}

/**
 * Filtrado de la página "Colecciones" por ?categoria=...
 * Soporta búsqueda por términos reales de product_cat y, si no existen,
 * cae a meta/atributos usados en la tienda.
 */
function weirdlings_collections_pre_get_posts( \WP_Query $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( ! is_page( 'colecciones' ) ) {
		return;
	}

	$cat = isset( $_GET['categoria'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['categoria'] ) ) ) : '';
	$query->set( 'post_type', 'product' );
	$query->set( 'post_status', 'publish' );

	if ( '' === $cat ) {
		return;
	}

	$cat_lookup = mb_strtolower( remove_accents( $cat ) );
	$explicit_map = array(
		'raro'               => 'raro',
		'espeluznante'       => 'espeluznante',
		'criatura del bosque' => 'criatura-del-bosque',
		'criaturadelbosque'  => 'criatura-del-bosque',
		'criadelbosque'      => 'criatura-del-bosque',
		'alienigenas'        => 'alienigenas',
		'alienígenas'        => 'alienigenas',
	);

	if ( isset( $explicit_map[ $cat_lookup ] ) ) {
		$mapped_slug = $explicit_map[ $cat_lookup ];
		$term        = get_term_by( 'slug', $mapped_slug, 'product_cat' );

		if ( $term && ! is_wp_error( $term ) ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => (int) $term->term_id,
					),
				)
			);
			return;
		}
	}

	$tax_query = array( 'relation' => 'OR' );

	foreach ( array( 'product_cat', 'product_tag', 'pa_coleccion' ) as $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			if ( weirdlings_collections_normalize_token( $term->slug ) === $cat_lookup || weirdlings_collections_normalize_token( $term->name ) === $cat_lookup ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => (int) $term->term_id,
				);
			}
		}
	}

	if ( count( $tax_query ) > 1 ) {
		$query->set( 'tax_query', $tax_query );
		return;
	}

	$query->set(
		'meta_query',
		array(
			'relation' => 'OR',
			array(
				'key'     => 'coleccion',
				'value'   => $cat,
				'compare' => 'LIKE',
			),
			array(
				'key'     => '_wl_product_rarity',
				'value'   => $cat_lookup,
				'compare' => 'LIKE',
			),
		)
	);
}
add_action( 'pre_get_posts', 'weirdlings_collections_pre_get_posts' );

function weirdlings_force_collections_template( $template ) {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = trim( (string) wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );

	if ( 'colecciones' === $path || 'colecciones/' === $path || false !== strpos( $path, 'colecciones' ) ) {
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->is_404 = false;
		}
		status_header( 200 );
		nocache_headers();

		$collections_template = get_theme_file_path( 'page-colecciones.php' );
		if ( file_exists( $collections_template ) ) {
			return $collections_template;
		}
	}

	return $template;
}
add_filter( 'template_include', 'weirdlings_force_collections_template', 99 );

function weirdlings_svg_icon_placeholder( string $label, int $size = 56 ): string {
	$label = mb_substr( wp_strip_all_tags( $label ), 0, 8 );
	$font_size = max( 8, (int) round( $size * 0.18 ) );

	return sprintf(
		'<svg viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" role="img" aria-label="%2$s" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop offset="0%%" stop-color="#d775b7"/><stop offset="100%%" stop-color="#7c59c7"/></linearGradient></defs><circle cx="%3$d" cy="%3$d" r="%4$d" fill="rgba(255,255,255,0.04)" stroke="rgba(255,255,255,0.15)" stroke-width="1.5"/><text x="50%%" y="46%%" text-anchor="middle" fill="#f2f0ea" font-size="%5$d" font-family="Fraunces, Georgia, serif" font-weight="700">%2$s</text><text x="50%%" y="68%%" text-anchor="middle" fill="url(#g)" font-size="%6$d" font-family="Space Grotesk, sans-serif" font-weight="700">SVG</text></svg>',
		$size,
		esc_attr( $label ),
		(int) round( $size / 2 ),
		(int) round( $size / 2 - 2 ),
		$font_size,
		max( 7, (int) round( $size * 0.14 ) )
	);
}

function weirdlings_render_header_icon( string $icon ): string {
	$icons = array(
		'instagram' => 'instagram.svg',
		'tiktok'    => 'tiktok.svg',
		'shop'      => 'tienda.svg',
		'account'   => 'cuenta.svg',
		'cart'      => 'carrito.svg',
		'menu'      => 'menu.svg',
	);

	if ( ! isset( $icons[ $icon ] ) ) {
		return '';
	}

	$src  = get_theme_file_uri( 'assets/images/SVG/' . $icons[ $icon ] );
	$file = get_theme_file_path( 'assets/images/SVG/' . $icons[ $icon ] );

	if ( file_exists( $file ) ) {
		$ver = (int) filemtime( $file );
		$src  = $src . ( strpos( $src, '?' ) === false ? '?' : '&' ) . 'v=' . $ver;
	}

	return sprintf(
		'<img class="wl-header-icon wl-header-icon--%1$s" src="%2$s" alt="" aria-hidden="true" decoding="async" />',
		esc_attr( $icon ),
		esc_url( $src )
	);
}

function weirdlings_render_menu_items( string $theme_location, bool $include_mobile = false ): string {
	$locations = get_nav_menu_locations();
	$menu_id   = isset( $locations[ $theme_location ] ) ? (int) $locations[ $theme_location ] : 0;
	$items     = $menu_id ? wp_get_nav_menu_items( $menu_id ) : array();
	$site_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/';
	$site_path = untrailingslashit( $site_path );
	$blog_url = weirdlings_blog_page_url();
	$blog_path = untrailingslashit( wp_parse_url( $blog_url, PHP_URL_PATH ) ?: '/blog/' );
	$blog_seen = false;
	$customization_url = weirdlings_customization_page_url();
	$customization_path = untrailingslashit( wp_parse_url( $customization_url, PHP_URL_PATH ) ?: '/personalizados/' );
	$customization_seen = false;

	if ( empty( $items ) ) {
		ob_start();
		weirdlings_primary_menu_fallback();
		return ob_get_clean();
	}

	$seen = array();
	$html = '<ul>';

	foreach ( $items as $item ) {
		$parsed_url = wp_parse_url( $item->url );
		$normalized_path = '';
		$relative_path = '/';

		if ( is_array( $parsed_url ) ) {
			$normalized_path = untrailingslashit( strtolower( trim( $parsed_url['path'] ?? '/' ) ) );
			$relative_path   = $normalized_path;

			if ( '' !== $site_path && 0 === strpos( $normalized_path, $site_path ) ) {
				$relative_path = substr( $normalized_path, strlen( $site_path ) );
			}

			$relative_path = '/' . ltrim( $relative_path, '/' );
		} else {
			$normalized_path = strtolower( trim( $item->url ) );
			$relative_path   = $item->url;
		}

		$key = $normalized_path;
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		if ( $key === $blog_path ) {
			$blog_seen = true;
		}
		if ( $key === $customization_path ) {
			$customization_seen = true;
		}
		$classes = array( 'menu-item' );
		if ( in_array( 'current-menu-item', (array) $item->classes, true ) || in_array( 'current_page_item', (array) $item->classes, true ) ) {
			$classes[] = 'current-menu-item';
		}
		$html .= sprintf(
			'<li class="%1$s"><a href="%2$s">%3$s</a></li>',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( is_array( $parsed_url ) ? home_url( $relative_path ) : $item->url ),
			esc_html( $item->title )
		);
	}

	if ( ! $blog_seen ) {
		$classes = array( 'menu-item' );
		if ( is_home() || is_singular( 'post' ) || is_category() || is_tag() ) {
			$classes[] = 'current-menu-item';
		}

		$html .= sprintf(
			'<li class="%1$s"><a href="%2$s">%3$s</a></li>',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $blog_url ),
			esc_html__( 'Blog', 'weirdlings-modern' )
		);
	}

	if ( ! $customization_seen ) {
		$classes = array( 'menu-item', 'menu-item--cta' );
		if ( is_page( 'personalizados' ) ) {
			$classes[] = 'current-menu-item';
		}
		$html .= sprintf(
			'<li class="%1$s"><a class="wl-nav__cta" href="%2$s">%3$s</a></li>',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $customization_url ),
			esc_html__( 'Pedir personalizado', 'weirdlings-modern' )
		);
	}

	$html .= '</ul>';

	return $html;
}

function weirdlings_render_placeholder( string $label, string $variant = 'product', int $width = 1200, int $height = 900 ): string {
	return sprintf(
		'<div class="wl-placeholder wl-placeholder--%1$s"><span class="wl-placeholder__art">%2$s</span><span class="wl-placeholder__symbol">✦</span><span class="wl-placeholder__label">%3$s</span></div>',
		esc_attr( $variant ),
		weirdlings_svg_placeholder( $label, $variant, $width, $height ),
		esc_html( $label )
	);
}

function weirdlings_has_woocommerce(): bool {
	return class_exists( 'WooCommerce' );
}

function weirdlings_currency_format( float $amount ): string {
	return '$ ' . number_format_i18n( $amount, 0 ) . ' COP';
}

function weirdlings_product_label( int $product_id ): string {
	$categories = wp_get_post_terms(
		$product_id,
		'product_cat',
		array(
			'fields' => 'names',
		)
	);

	if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
		return (string) $categories[0];
	}

	$tags = wp_get_post_terms(
		$product_id,
		'product_tag',
		array(
			'fields' => 'names',
		)
	);

	if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
		return (string) $tags[0];
	}

	return __( 'Amigurumi', 'weirdlings-modern' );
}

function weirdlings_product_rarity_options(): array {
	return array(
		'comun'      => array(
			'label' => __( 'Común', 'weirdlings-modern' ),
			'icon'  => '●',
		),
		'raro'       => array(
			'label' => __( 'Raro', 'weirdlings-modern' ),
			'icon'  => '◆',
		),
		'epico'      => array(
			'label' => __( 'Épico', 'weirdlings-modern' ),
			'icon'  => '✦',
		),
		'legendario' => array(
			'label' => __( 'Legendario', 'weirdlings-modern' ),
			'icon'  => '♛',
		),
		'mitico'     => array(
			'label' => __( 'Mítico', 'weirdlings-modern' ),
			'icon'  => '✶',
		),
	);
}

function weirdlings_product_rarity_key( int $product_id ): string {
	$rarity  = sanitize_key( (string) get_post_meta( $product_id, '_wl_product_rarity', true ) );
	$options = weirdlings_product_rarity_options();

	if ( isset( $options[ $rarity ] ) ) {
		return $rarity;
	}

	return 'comun';
}

function weirdlings_render_rarity_badge_by_key( string $rarity, string $context = 'card' ): string {
	$options = weirdlings_product_rarity_options();
	$rarity  = sanitize_key( $rarity );

	if ( ! isset( $options[ $rarity ] ) ) {
		$rarity = 'comun';
	}

	$classes = array(
		'wl-rarity-badge',
		'wl-rarity-badge--' . $rarity,
	);

	if ( 'single' === $context ) {
		$classes[] = 'wl-rarity-badge--single';
	}

	return sprintf(
		'<span class="%1$s" aria-label="%2$s"><span class="wl-rarity-badge__icon" aria-hidden="true">%3$s</span><span class="wl-rarity-badge__label">%4$s</span></span>',
		esc_attr( implode( ' ', $classes ) ),
		esc_attr( sprintf( __( 'Rareza: %s', 'weirdlings-modern' ), $options[ $rarity ]['label'] ) ),
		esc_html( $options[ $rarity ]['icon'] ),
		esc_html( $options[ $rarity ]['label'] )
	);
}

function weirdlings_render_rarity_badge( int $product_id, string $context = 'card' ): string {
	return weirdlings_render_rarity_badge_by_key( weirdlings_product_rarity_key( $product_id ), $context );
}

function weirdlings_product_rarity_admin_field(): void {
	global $post;

	if ( ! ( $post instanceof WP_Post ) || 'product' !== $post->post_type ) {
		return;
	}

	$options      = weirdlings_product_rarity_options();
	$select_items = array();

	foreach ( $options as $slug => $data ) {
		$select_items[ $slug ] = $data['label'];
	}

	woocommerce_wp_select(
		array(
			'id'          => '_wl_product_rarity',
			'label'       => __( 'Rareza', 'weirdlings-modern' ),
			'description' => __( 'Define la rareza visual del producto para Home, Tienda y ficha del producto.', 'weirdlings-modern' ),
			'desc_tip'    => true,
			'options'     => $select_items,
		)
	);
}
add_action( 'woocommerce_product_options_general_product_data', 'weirdlings_product_rarity_admin_field' );

function weirdlings_save_product_rarity_admin_field( WC_Product $product ): void {
	$raw_value = isset( $_POST['_wl_product_rarity'] ) ? sanitize_key( wp_unslash( $_POST['_wl_product_rarity'] ) ) : '';
	$options   = weirdlings_product_rarity_options();
	$value     = isset( $options[ $raw_value ] ) ? $raw_value : 'comun';

	$product->update_meta_data( '_wl_product_rarity', $value );
}
add_action( 'woocommerce_admin_process_product_object', 'weirdlings_save_product_rarity_admin_field' );

function weirdlings_product_price_text( WC_Product $product ): string {
	$price = $product->get_price();

	if ( '' === $price || null === $price ) {
		$price = $product->get_regular_price();
	}

	if ( '' === $price || null === $price ) {
		return '';
	}

	return wp_strip_all_tags( wc_price( (float) $price ) );
}

function weirdlings_product_price_details( WC_Product $product ): array {
	$regular_price = $product->get_regular_price();
	$sale_price    = $product->get_sale_price();
	$current_price = $product->get_price();
	$on_sale       = $product->is_on_sale() && '' !== $sale_price && '' !== $regular_price;

	if ( '' === $current_price || null === $current_price ) {
		$current_price = '' !== $regular_price ? $regular_price : $sale_price;
	}

	return array(
		'on_sale'      => $on_sale,
		'regular_html' => '' !== $regular_price ? wp_strip_all_tags( wc_price( (float) $regular_price ) ) : '',
		'sale_html'    => '' !== $current_price ? wp_strip_all_tags( wc_price( (float) $current_price ) ) : '',
		'current_html' => '' !== $current_price ? wp_strip_all_tags( wc_price( (float) $current_price ) ) : '',
	);
}

function weirdlings_home_featured_items(): array {
	if ( weirdlings_has_woocommerce() ) {
		$products = wc_get_products(
			array(
				'limit'   => 4,
				'orderby'  => 'date',
				'order'    => 'DESC',
				'status'   => 'publish',
				'featured' => true,
			)
		);

		if ( $products ) {
			return array_map(
				static function ( $product ) {
					$price_details = weirdlings_product_price_details( $product );
					$badge         = $price_details['on_sale'] ? 'SALE' : 'TOP';
					$price         = $price_details['on_sale']
						? array(
							'regular' => $price_details['regular_html'],
							'sale'    => $price_details['sale_html'],
						)
						: array(
							'current' => $price_details['current_html'],
						);

					return array(
						'id'          => $product->get_id(),
						'title'       => $product->get_name(),
						'rarity'      => weirdlings_product_rarity_key( $product->get_id() ),
						'type'        => weirdlings_product_label( $product->get_id() ),
						'price'       => $price,
						'link'        => get_permalink( $product->get_id() ),
						'image'       => $product->get_image( 'woocommerce_thumbnail' ),
						'badge'       => $badge,
						'on_sale'     => $price_details['on_sale'],
						'description' => $product->get_short_description(),
					);
				},
				$products
			);
		}
	}

	return array(
		array( 'title' => 'Baphy', 'rarity' => 'epico', 'type' => 'Amigurumi', 'price' => array( 'current' => weirdlings_currency_format( 75000 ) ), 'link' => '#', 'badge' => 'TOP', 'image' => weirdlings_render_placeholder( 'Baphy', 'square', 800, 900 ), 'description' => 'Criatura original', 'on_sale' => false ),
		array( 'title' => 'Stitchy', 'rarity' => 'raro', 'type' => 'Llavero', 'price' => array( 'current' => weirdlings_currency_format( 25000 ) ), 'link' => '#', 'badge' => 'TOP', 'image' => weirdlings_render_placeholder( 'Stitchy', 'square', 800, 900 ), 'description' => 'Edición temporal', 'on_sale' => false ),
		array( 'title' => 'Nocti', 'rarity' => 'legendario', 'type' => 'Amigurumi', 'price' => array( 'current' => weirdlings_currency_format( 65000 ) ), 'link' => '#', 'badge' => 'TOP', 'image' => weirdlings_render_placeholder( 'Nocti', 'square', 800, 900 ), 'description' => 'Placeholder', 'on_sale' => false ),
		array( 'title' => 'Cthuluita', 'rarity' => 'mitico', 'type' => 'Llavero', 'price' => array( 'current' => weirdlings_currency_format( 28000 ) ), 'link' => '#', 'badge' => 'TOP', 'image' => weirdlings_render_placeholder( 'Cthuluita', 'square', 800, 900 ), 'description' => 'Placeholder', 'on_sale' => false ),
	);
}

function weirdlings_footer_links(): array {
	$links = array();

	if ( has_nav_menu( 'footer' ) ) {
		$links['menu'] = wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => false,
				'echo'           => false,
				'fallback_cb'    => false,
				'items_wrap'     => '<ul>%3$s</ul>',
			)
		);
	}

	return $links;
}

function weirdlings_contact_page_url(): string {
	return home_url( '/contacto/' );
}

function weirdlings_customization_page_url(): string {
	$page = get_page_by_path( 'personalizados' );

	if ( $page instanceof WP_Post ) {
		return get_permalink( $page );
	}

	return home_url( '/personalizados/' );
}

function weirdlings_blog_page_url(): string {
	$posts_page_id = (int) get_option( 'page_for_posts' );
	if ( $posts_page_id > 0 ) {
		$posts_page_link = get_permalink( $posts_page_id );
		if ( $posts_page_link ) {
			return $posts_page_link;
		}
	}

	$page = get_page_by_path( 'blog' );

	if ( $page instanceof WP_Post ) {
		return get_permalink( $page );
	}

	return home_url( '/blog/' );
}

function weirdlings_contact_whatsapp_number(): string {
	return '573107750438';
}

function weirdlings_contact_whatsapp_url(): string {
	$message = __( 'Hola Weirdlings, necesito ayuda con mi pedido.', 'weirdlings-modern' );

	return add_query_arg( 'text', $message, 'https://wa.me/' . weirdlings_contact_whatsapp_number() );
}

function weirdlings_customization_whatsapp_url(): string {
	$message = __( 'Hola Weirdlings, quiero solicitar un producto personalizado.', 'weirdlings-modern' );

	return add_query_arg( 'text', $message, 'https://wa.me/' . weirdlings_contact_whatsapp_number() );
}

function weirdlings_contact_feedback_notice(): string {
	$status = isset( $_GET['wl_contact'] ) ? sanitize_key( wp_unslash( $_GET['wl_contact'] ) ) : '';

	if ( 'sent' === $status ) {
		return '<div class="wl-contact-notice wl-contact-notice--success">' . esc_html__( 'Tu mensaje fue enviado. Te responderemos pronto.', 'weirdlings-modern' ) . '</div>';
	}

	if ( 'error' === $status ) {
		return '<div class="wl-contact-notice wl-contact-notice--error">' . esc_html__( 'No se pudo enviar el mensaje. Revisa los campos e inténtalo otra vez.', 'weirdlings-modern' ) . '</div>';
	}

	return '';
}

function weirdlings_customization_feedback_notice(): string {
	$status = isset( $_GET['wl_custom'] ) ? sanitize_key( wp_unslash( $_GET['wl_custom'] ) ) : '';

	if ( 'sent' === $status ) {
		return '<div class="wl-contact-notice wl-contact-notice--success">' . esc_html__( 'Tu solicitud de personalizado fue enviada. Te responderemos pronto con disponibilidad y detalles.', 'weirdlings-modern' ) . '</div>';
	}

	if ( 'error' === $status ) {
		return '<div class="wl-contact-notice wl-contact-notice--error">' . esc_html__( 'No se pudo enviar la solicitud. Revisa los campos e inténtalo otra vez.', 'weirdlings-modern' ) . '</div>';
	}

	return '';
}

function weirdlings_render_contact_form(): string {
	ob_start();
?>
	<form class="wl-contact-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
		<?php echo weirdlings_contact_feedback_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input type="hidden" name="action" value="weirdlings_contact_form">
		<?php wp_nonce_field( 'weirdlings_contact_form', 'weirdlings_contact_nonce' ); ?>

		<div class="wl-contact-form__grid">
			<p class="wl-contact-field">
				<label for="wl-contact-name"><?php esc_html_e( 'Nombre', 'weirdlings-modern' ); ?></label>
				<input id="wl-contact-name" name="contact_name" type="text" placeholder="<?php esc_attr_e( 'Tu nombre', 'weirdlings-modern' ); ?>" required>
			</p>

			<p class="wl-contact-field">
				<label for="wl-contact-email"><?php esc_html_e( 'Correo electrónico', 'weirdlings-modern' ); ?></label>
				<input id="wl-contact-email" name="contact_email" type="email" placeholder="<?php esc_attr_e( 'tu@correo.com', 'weirdlings-modern' ); ?>" required>
			</p>

			<p class="wl-contact-field">
				<label for="wl-contact-phone"><?php esc_html_e( 'Teléfono', 'weirdlings-modern' ); ?></label>
				<input id="wl-contact-phone" name="contact_phone" type="tel" placeholder="<?php esc_attr_e( 'Opcional', 'weirdlings-modern' ); ?>">
			</p>

			<p class="wl-contact-field">
				<label for="wl-contact-subject"><?php esc_html_e( 'Asunto', 'weirdlings-modern' ); ?></label>
				<input id="wl-contact-subject" name="contact_subject" type="text" placeholder="<?php esc_attr_e( '¿En qué te ayudo?', 'weirdlings-modern' ); ?>" required>
			</p>

			<p class="wl-contact-field wl-contact-field--full">
				<label for="wl-contact-message"><?php esc_html_e( 'Mensaje', 'weirdlings-modern' ); ?></label>
				<textarea id="wl-contact-message" name="contact_message" rows="7" placeholder="<?php esc_attr_e( 'Escribe aquí tu consulta: número de pedido, seguimiento, cambio, envío o cualquier detalle.', 'weirdlings-modern' ); ?>" required></textarea>
			</p>

			<p class="wl-contact-field wl-contact-field--full">
				<label for="wl-contact-file"><?php esc_html_e( 'Adjuntar archivo o imagen', 'weirdlings-modern' ); ?></label>
				<input id="wl-contact-file" name="contact_file" type="file" accept="image/*,.pdf">
				<span class="wl-contact-field__hint"><?php esc_html_e( 'Puedes subir JPG, PNG, WEBP o PDF.', 'weirdlings-modern' ); ?></span>
			</p>

			<p class="wl-contact-field wl-contact-field--full wl-contact-field--consent">
				<label class="wl-contact-consent">
					<input name="contact_privacy" type="checkbox" value="1" required>
					<span><?php esc_html_e( 'Acepto que me contacten por este mensaje y que mis datos se usen solo para responder.', 'weirdlings-modern' ); ?></span>
				</label>
			</p>
		</div>

		<div class="wl-contact-form__actions">
			<button class="wl-button wl-button--primary" type="submit"><?php esc_html_e( 'Enviar solicitud', 'weirdlings-modern' ); ?></button>
			<p class="wl-contact-form__note"><?php esc_html_e( 'Respondemos desde el correo del sitio y, si lo necesitas, también por WhatsApp.', 'weirdlings-modern' ); ?></p>
		</div>
	</form>
	<?php

	return (string) ob_get_clean();
}

function weirdlings_render_customization_form(): string {
	ob_start();
?>
	<form class="wl-contact-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
		<?php echo weirdlings_customization_feedback_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input type="hidden" name="action" value="weirdlings_customization_form">
		<?php wp_nonce_field( 'weirdlings_customization_form', 'weirdlings_customization_nonce' ); ?>

		<div class="wl-contact-form__grid">
			<p class="wl-contact-field">
				<label for="wl-custom-name"><?php esc_html_e( 'Nombre', 'weirdlings-modern' ); ?></label>
				<input id="wl-custom-name" name="custom_name" type="text" placeholder="<?php esc_attr_e( 'Tu nombre', 'weirdlings-modern' ); ?>" required>
			</p>

			<p class="wl-contact-field">
				<label for="wl-custom-email"><?php esc_html_e( 'Correo electrónico', 'weirdlings-modern' ); ?></label>
				<input id="wl-custom-email" name="custom_email" type="email" placeholder="<?php esc_attr_e( 'tu@correo.com', 'weirdlings-modern' ); ?>" required>
			</p>

			<p class="wl-contact-field">
				<label for="wl-custom-phone"><?php esc_html_e( 'WhatsApp', 'weirdlings-modern' ); ?></label>
				<input id="wl-custom-phone" name="custom_phone" type="tel" placeholder="<?php esc_attr_e( 'Opcional', 'weirdlings-modern' ); ?>">
			</p>

			<p class="wl-contact-field">
				<label for="wl-custom-product"><?php esc_html_e( 'Tipo de pieza', 'weirdlings-modern' ); ?></label>
				<select id="wl-custom-product" name="custom_product" required>
					<option value=""><?php esc_html_e( 'Elige una opción', 'weirdlings-modern' ); ?></option>
					<option value="Amigurumi"><?php esc_html_e( 'Amigurumi', 'weirdlings-modern' ); ?></option>
					<option value="Llavero"><?php esc_html_e( 'Llavero', 'weirdlings-modern' ); ?></option>
					<option value="Edición especial"><?php esc_html_e( 'Edición especial', 'weirdlings-modern' ); ?></option>
					<option value="No estoy seguro/a"><?php esc_html_e( 'No estoy seguro/a', 'weirdlings-modern' ); ?></option>
				</select>
			</p>

			<p class="wl-contact-field">
				<label for="wl-custom-size"><?php esc_html_e( 'Tamaño aproximado', 'weirdlings-modern' ); ?></label>
				<input id="wl-custom-size" name="custom_size" type="text" placeholder="<?php esc_attr_e( 'Mini, pequeño, mediano...', 'weirdlings-modern' ); ?>">
			</p>

			<p class="wl-contact-field">
				<label for="wl-custom-colors"><?php esc_html_e( 'Colores preferidos', 'weirdlings-modern' ); ?></label>
				<input id="wl-custom-colors" name="custom_colors" type="text" placeholder="<?php esc_attr_e( 'Ej. morado, negro y rosa', 'weirdlings-modern' ); ?>">
			</p>

			<p class="wl-contact-field">
				<label for="wl-custom-accessories"><?php esc_html_e( 'Accesorios o detalles', 'weirdlings-modern' ); ?></label>
				<input id="wl-custom-accessories" name="custom_accessories" type="text" placeholder="<?php esc_attr_e( 'Gorro, lazo, llave, cadena...', 'weirdlings-modern' ); ?>">
			</p>

			<p class="wl-contact-field">
				<label for="wl-custom-expression"><?php esc_html_e( 'Expresión o rasgos', 'weirdlings-modern' ); ?></label>
				<input id="wl-custom-expression" name="custom_expression" type="text" placeholder="<?php esc_attr_e( 'Dulce, misterioso, sonriente...', 'weirdlings-modern' ); ?>">
			</p>

			<p class="wl-contact-field">
				<label for="wl-custom-text"><?php esc_html_e( 'Nombre o bordado', 'weirdlings-modern' ); ?></label>
				<input id="wl-custom-text" name="custom_text" type="text" placeholder="<?php esc_attr_e( 'Texto, nombre o palabra opcional', 'weirdlings-modern' ); ?>">
			</p>

			<p class="wl-contact-field wl-contact-field--full">
				<label for="wl-custom-idea"><?php esc_html_e( 'Descripción de la idea', 'weirdlings-modern' ); ?></label>
				<textarea id="wl-custom-idea" name="custom_idea" rows="7" placeholder="<?php esc_attr_e( 'Cuéntanos qué criatura quieres, de dónde sale la idea y qué debe conservar sí o sí.', 'weirdlings-modern' ); ?>" required></textarea>
			</p>

			<p class="wl-contact-field wl-contact-field--full">
				<label for="wl-custom-reference"><?php esc_html_e( 'Referencias visuales o enlaces', 'weirdlings-modern' ); ?></label>
				<textarea id="wl-custom-reference" name="custom_reference" rows="4" placeholder="<?php esc_attr_e( 'Sube imágenes o describe los links/referencias que quieres que revisemos.', 'weirdlings-modern' ); ?>"></textarea>
			</p>

			<p class="wl-contact-field wl-contact-field--full">
				<label for="wl-custom-deadline"><?php esc_html_e( 'Fecha ideal de entrega', 'weirdlings-modern' ); ?></label>
				<input id="wl-custom-deadline" name="custom_deadline" type="text" placeholder="<?php esc_attr_e( 'Opcional', 'weirdlings-modern' ); ?>">
			</p>

			<p class="wl-contact-field wl-contact-field--full">
				<label for="wl-custom-file"><?php esc_html_e( 'Adjuntar referencias', 'weirdlings-modern' ); ?></label>
				<input id="wl-custom-file" name="custom_file" type="file" accept="image/*,.pdf">
				<span class="wl-contact-field__hint"><?php esc_html_e( 'Puedes subir JPG, PNG, WEBP o PDF.', 'weirdlings-modern' ); ?></span>
			</p>

			<p class="wl-contact-field wl-contact-field--full wl-contact-field--consent">
				<label class="wl-contact-consent">
					<input name="custom_privacy" type="checkbox" value="1" required>
					<span><?php esc_html_e( 'Acepto que me contacten para revisar esta solicitud y confirmar viabilidad, tiempos y precio.', 'weirdlings-modern' ); ?></span>
				</label>
			</p>
		</div>

		<div class="wl-contact-form__actions">
			<button class="wl-button wl-button--primary" type="submit"><?php esc_html_e( 'Enviar solicitud', 'weirdlings-modern' ); ?></button>
			<p class="wl-contact-form__note"><?php esc_html_e( 'Te responderemos por correo y, si hace falta, seguiremos por WhatsApp.', 'weirdlings-modern' ); ?></p>
		</div>
	</form>
	<?php

	return (string) ob_get_clean();
}

function weirdlings_render_contact_page(): string {
	$whatsapp_url = weirdlings_contact_whatsapp_url();

	ob_start();
?>
	<section class="wl-contact-shell">
		<div class="wl-contact-hero">
			<div class="wl-contact-hero__copy">
				<h1><?php esc_html_e( 'Cuéntanos tu necesidad y te ayudamos.', 'weirdlings-modern' ); ?></h1>
				<p><?php esc_html_e( 'Este formulario está pensado para servicio al cliente, dudas de pedidos, seguimiento, cambios, envíos y cualquier consulta sobre tu compra.', 'weirdlings-modern' ); ?></p>
			</div>
		</div>

		<div class="wl-contact-layout">
			<div class="wl-contact-panel">
				<div class="wl-contact-panel__head">
					<div>
						<div class="wl-contact-panel__eyebrow"><?php esc_html_e( 'Formulario', 'weirdlings-modern' ); ?></div>
						<h2><?php esc_html_e( 'Envíanos el detalle completo', 'weirdlings-modern' ); ?></h2>
					</div>
				</div>

				<?php echo weirdlings_render_contact_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<aside class="wl-contact-aside">
				<div class="wl-contact-card">
					<h3><?php esc_html_e( 'Email del taller', 'weirdlings-modern' ); ?></h3>
					<p><?php echo esc_html( get_option( 'admin_email' ) ); ?></p>
				</div>

				<div class="wl-contact-card">
					<h3><?php esc_html_e( 'WhatsApp', 'weirdlings-modern' ); ?></h3>
					<a class="wl-button wl-button--ghost wl-contact-card__button" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Escribir por WhatsApp', 'weirdlings-modern' ); ?></a>
					<p><?php esc_html_e( 'Respuesta directa para dudas rápidas sobre tu pedido.', 'weirdlings-modern' ); ?></p>
				</div>

			</aside>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

function weirdlings_render_customization_page(): string {
	$whatsapp_url = weirdlings_customization_whatsapp_url();

	ob_start();
?>
	<section class="wl-contact-shell wl-custom-shell">
		<div class="wl-contact-hero">
			<div class="wl-contact-hero__copy">
				<h1><?php esc_html_e( 'Cuéntanos tu idea y la convertimos en una criatura Weirdlings.', 'weirdlings-modern' ); ?></h1>
				<p><?php esc_html_e( 'Este formulario está pensado para amigurumis y llaveros personalizados. Incluye colores, tamaño, accesorios, expresiones, textos y referencias visuales para que podamos revisar la viabilidad de tu pedido.', 'weirdlings-modern' ); ?></p>
			</div>
		</div>

		<div class="wl-contact-layout">
			<div class="wl-contact-panel">
				<div class="wl-contact-panel__head">
					<div>
						<div class="wl-contact-panel__eyebrow"><?php esc_html_e( 'Personalizado', 'weirdlings-modern' ); ?></div>
						<h2><?php esc_html_e( 'Cuéntanos lo más importante de tu encargo', 'weirdlings-modern' ); ?></h2>
					</div>
				</div>

				<?php echo weirdlings_render_customization_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<aside class="wl-contact-aside">
				<div class="wl-contact-card">
					<h3><?php esc_html_e( 'Puedes personalizar', 'weirdlings-modern' ); ?></h3>
					<p><?php esc_html_e( 'Colores, tamaño, accesorios, expresiones faciales, bordados, nombres, personajes originales y referencias visuales.', 'weirdlings-modern' ); ?></p>
				</div>

				<div class="wl-contact-card">
					<h3><?php esc_html_e( 'Qué conviene adjuntar', 'weirdlings-modern' ); ?></h3>
					<p><?php esc_html_e( 'Imágenes, bocetos, enlaces o cualquier referencia que ayude a entender mejor la criatura que tienes en mente.', 'weirdlings-modern' ); ?></p>
				</div>

				<div class="wl-contact-card">
					<h3><?php esc_html_e( 'Revisión del pedido', 'weirdlings-modern' ); ?></h3>
					<p><?php esc_html_e( 'Revisamos viabilidad, materiales y tiempos antes de confirmar cada pieza hecha a medida.', 'weirdlings-modern' ); ?></p>
					<a class="wl-button wl-button--ghost wl-contact-card__button" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Hablar por WhatsApp', 'weirdlings-modern' ); ?></a>
				</div>
			</aside>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

function weirdlings_render_about_page(): string {
	ob_start();
?>
	<section class="wl-about-shell">
		<div class="wl-about-hero">
			<div class="wl-about-hero__copy">
				<span class="wl-about-kicker"><?php esc_html_e( 'Sobre Weirdlings', 'weirdlings-modern' ); ?></span>
				<h1><?php esc_html_e( 'Diseño artesanal con una identidad propia.', 'weirdlings-modern' ); ?></h1>
				<p><?php esc_html_e( 'Weirdlings es una tienda online colombiana especializada en amigurumis y llaveros tejidos a mano. Su propuesta combina estética horror cute, diseño original y una experiencia de compra pensada para productos coleccionables y personalizados.', 'weirdlings-modern' ); ?></p>
			</div>
			<div class="wl-about-hero__panel">
				<div class="wl-about-stat">
					<span><?php esc_html_e( 'Enfoque', 'weirdlings-modern' ); ?></span>
					<strong><?php esc_html_e( 'Artesanía + comercio electrónico', 'weirdlings-modern' ); ?></strong>
				</div>
				<div class="wl-about-stat">
					<span><?php esc_html_e( 'Propuesta', 'weirdlings-modern' ); ?></span>
					<strong><?php esc_html_e( 'Piezas únicas, funcionales y coleccionables', 'weirdlings-modern' ); ?></strong>
				</div>
				<div class="wl-about-stat">
					<span><?php esc_html_e( 'Público', 'weirdlings-modern' ); ?></span>
					<strong><?php esc_html_e( 'Personas que valoran lo original y lo hecho a mano', 'weirdlings-modern' ); ?></strong>
				</div>
			</div>
		</div>

		<div class="wl-about-grid">
			<article class="wl-about-card">
				<div class="wl-about-card__eyebrow"><?php esc_html_e( 'La marca', 'weirdlings-modern' ); ?></div>
				<h2><?php esc_html_e( 'Una estética diferenciada, pensada para destacar.', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Weirdlings se ubica en el nicho de productos artesanales y coleccionables, con una identidad visual que mezcla lo extraño, lo tierno y lo alternativo. La tienda está dirigida a adultos jóvenes, coleccionistas y personas que buscan regalos originales con una personalidad clara.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-about-card">
				<div class="wl-about-card__eyebrow"><?php esc_html_e( 'Lo que hacemos', 'weirdlings-modern' ); ?></div>
				<h2><?php esc_html_e( 'Amigurumis, llaveros y diseños personalizados.', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'La oferta incluye amigurumis coleccionables, llaveros tejidos y ediciones especiales. También contempla personalización por colores, tamaño, accesorios, expresiones faciales, bordados y referencias visuales, siempre con un enfoque artesanal y cuidado.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-about-card">
				<div class="wl-about-card__eyebrow"><?php esc_html_e( 'Cómo trabajamos', 'weirdlings-modern' ); ?></div>
				<h2><?php esc_html_e( 'Proceso claro, inventario flexible y empaque cuidado.', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'El proyecto contempla un modelo híbrido de inventario, con piezas disponibles y piezas hechas bajo pedido. Cada compra puede incluir tarjeta de agradecimiento, tarjeta de adopción, cuidados básicos y material de protección para el transporte.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-about-card">
				<div class="wl-about-card__eyebrow"><?php esc_html_e( 'La plataforma', 'weirdlings-modern' ); ?></div>
				<h2><?php esc_html_e( 'Una tienda preparada para crecer.', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Weirdlings está desarrollada sobre WordPress y WooCommerce, con estructura para catálogo, pedidos, pagos, envío, analítica, automatización y despliegue en la nube. La prioridad es unir una experiencia visual sólida con una operación funcional y escalable.', 'weirdlings-modern' ); ?></p>
			</article>
		</div>

		<div class="wl-about-banner">
			<div class="wl-about-banner__copy">
				<div class="wl-about-banner__eyebrow"><?php esc_html_e( 'En resumen', 'weirdlings-modern' ); ?></div>
				<h2><?php esc_html_e( 'Una marca artesanal con lenguaje propio y una base comercial seria.', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Weirdlings une el valor de lo hecho a mano con una estructura de comercio electrónico pensada para vender, organizar y escalar sin perder identidad.', 'weirdlings-modern' ); ?></p>
			</div>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

function weirdlings_render_privacy_page(): string {
	ob_start();
?>
	<section class="wl-legal-shell">
		<div class="wl-legal-hero">
			<div class="wl-legal-hero__copy">
				<span class="wl-legal-kicker"><?php esc_html_e( 'Política de privacidad', 'weirdlings-modern' ); ?></span>
				<h1><?php esc_html_e( 'Tratamos tus datos con cuidado y solo para lo necesario.', 'weirdlings-modern' ); ?></h1>
				<p><?php esc_html_e( 'Esta política explica qué información recogemos en Weirdlings, para qué la usamos, con quién podemos compartirla y qué derechos tienes sobre tus datos.', 'weirdlings-modern' ); ?></p>
			</div>
		</div>

		<div class="wl-legal-grid">
			<article class="wl-legal-card">
				<h2><?php esc_html_e( '1. Información que recopilamos', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Recopilamos los datos que nos proporcionas en los formularios del sitio, como nombre, correo electrónico, teléfono, asunto, mensaje, archivos adjuntos y, cuando aplica, datos asociados a pedidos y pagos gestionados por WooCommerce.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '2. Para qué usamos tus datos', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Usamos esta información para responder consultas, gestionar pedidos, coordinar envíos, dar soporte al cliente, procesar pagos y mejorar la experiencia de compra y operación de la tienda.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '3. Con quién compartimos la información', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Podemos compartir información únicamente con proveedores necesarios para operar el negocio, como pasarelas de pago, servicios de correo, alojamiento web y transportadoras, siempre con el propósito de prestar el servicio solicitado.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '4. Conservación y seguridad', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Conservamos los datos solo durante el tiempo necesario para cumplir la finalidad para la que fueron recolectados, atender obligaciones legales y resolver incidencias. Aplicamos medidas básicas de seguridad para proteger la información frente a accesos no autorizados o uso indebido.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '5. Tus derechos', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Puedes solicitar acceso, actualización, corrección o eliminación de tus datos personales, así como revocar la autorización de uso cuando sea procedente. Para ejercer estos derechos, escríbenos desde la página de contacto.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '6. Uso de cookies y herramientas del sitio', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'El sitio puede utilizar cookies técnicas y herramientas de WordPress/WooCommerce necesarias para el funcionamiento del catálogo, el carrito y el proceso de compra. Si en el futuro incorporamos analítica u otras integraciones, esta política se actualizará para reflejarlo.', 'weirdlings-modern' ); ?></p>
			</article>
		</div>

		<div class="wl-legal-note">
			<p><?php esc_html_e( 'Última actualización: mayo de 2026. Esta política es una base operativa para el sitio y puede ajustarse cuando cambien los procesos, proveedores o requerimientos legales.', 'weirdlings-modern' ); ?></p>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

function weirdlings_render_terms_page(): string {
	ob_start();
?>
	<section class="wl-legal-shell">
		<div class="wl-legal-hero">
			<div class="wl-legal-hero__copy">
				<span class="wl-legal-kicker"><?php esc_html_e( 'Términos y condiciones', 'weirdlings-modern' ); ?></span>
				<h1><?php esc_html_e( 'Reglas claras para comprar y usar la tienda.', 'weirdlings-modern' ); ?></h1>
				<p><?php esc_html_e( 'Estos términos describen cómo funciona Weirdlings, qué puedes esperar del catálogo y qué responsabilidades aplican al navegar, comprar y solicitar productos personalizados.', 'weirdlings-modern' ); ?></p>
			</div>
		</div>

		<div class="wl-legal-grid">
			<article class="wl-legal-card">
				<h2><?php esc_html_e( '1. Alcance del servicio', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Weirdlings ofrece productos artesanales elaborados en crochet, incluyendo amigurumis, llaveros y piezas personalizadas. La información de productos, precios y disponibilidad puede cambiar sin previo aviso mientras se actualiza el catálogo.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '2. Compras y pedidos', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Al realizar un pedido, el cliente debe proporcionar datos veraces y completos. Los pedidos personalizados pueden requerir confirmación adicional antes de iniciar su elaboración, y el tiempo de producción puede variar según complejidad y volumen.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '3. Pagos', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Los pagos se gestionan mediante WooCommerce y las pasarelas compatibles que se configuren para el sitio. La compra solo se considera confirmada cuando el pago es aprobado o cuando exista confirmación expresa por parte de la tienda.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '4. Envíos y entrega', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Los tiempos de entrega dependen de la disponibilidad del producto, la dirección de destino y la transportadora seleccionada. Weirdlings no se responsabiliza por demoras causadas por terceros, aunque hará seguimiento razonable del pedido dentro de sus posibilidades.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '5. Personalización', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Cuando el cliente solicite personalización, deberá entregar instrucciones claras y referencias si las tiene. Weirdlings se reserva el derecho de validar la viabilidad técnica y estética de cada solicitud antes de aceptarla.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '6. Propiedad intelectual y uso del contenido', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Las imágenes, textos, diseños, marcas y piezas visuales publicadas en la tienda pertenecen a Weirdlings o a sus respectivos titulares. No está permitido copiar, reproducir o reutilizar ese contenido sin autorización previa y por escrito.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '7. Responsabilidad', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'La tienda se ofrece tal como está y se mantiene con un enfoque artesanal y de pequeña escala. Weirdlings no garantiza disponibilidad continua absoluta, pero procurará mantener la información actualizada y la operación del sitio en condiciones adecuadas.', 'weirdlings-modern' ); ?></p>
			</article>

			<article class="wl-legal-card">
				<h2><?php esc_html_e( '8. Cambios en los términos', 'weirdlings-modern' ); ?></h2>
				<p><?php esc_html_e( 'Weirdlings puede actualizar estos términos cuando cambie la operación de la tienda, los métodos de pago, los envíos o los requisitos legales aplicables. La versión vigente será la publicada en esta página.', 'weirdlings-modern' ); ?></p>
			</article>
		</div>

		<div class="wl-legal-note">
			<p><?php esc_html_e( 'Última actualización: mayo de 2026. Si necesitas revisar un caso particular de compra o personalización, contáctanos desde la página de contacto.', 'weirdlings-modern' ); ?></p>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

function weirdlings_render_faq_page(): string {
	$faqs = array(
		array(
			'question' => __( '¿Qué es Weirdlings?', 'weirdlings-modern' ),
			'answer'   => __( 'Weirdlings es una tienda online de amigurumis y llaveros tejidos a mano con estética horror cute. La marca combina diseño original, piezas coleccionables y una experiencia de compra pensada para productos artesanales con identidad propia.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Qué tipo de productos venden?', 'weirdlings-modern' ),
			'answer'   => __( 'Vendemos amigurumis coleccionables, llaveros tejidos, ediciones especiales y diseños personalizados. Los productos están pensados para personas que buscan piezas originales, hechas a mano y con una estética distinta a la oferta tradicional.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Hacen productos personalizados?', 'weirdlings-modern' ),
			'answer'   => __( 'Sí. Podemos trabajar personalizaciones en color, tamaño, accesorios, expresiones faciales, bordados y referencias visuales. Para solicitudes especiales, lo ideal es escribirnos con la mayor cantidad de detalles posible y, si tienes imágenes de referencia, adjuntarlas al formulario.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Qué información debo enviar para solicitar un personalizado?', 'weirdlings-modern' ),
			'answer'   => __( 'Es útil incluir el tipo de producto, la medida aproximada, colores, fecha deseada, ciudad de envío y cualquier referencia visual. Cuanto más claro sea el encargo, más fácil será validar si la pieza es viable y darte una respuesta precisa.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Cuáles son los medios de pago disponibles?', 'weirdlings-modern' ),
			'answer'   => __( 'La tienda está desarrollada sobre WooCommerce y puede trabajar con pasarelas compatibles con el mercado colombiano. En el proyecto se contemplan tarjetas débito y crédito, PSE, Nequi, Daviplata, transferencias bancarias y otros métodos que soporte la pasarela seleccionada.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Realizan envíos a todo el país?', 'weirdlings-modern' ),
			'answer'   => __( 'Sí, la tienda está pensada para envíos dentro de Colombia. El costo y el tiempo de entrega dependen del destino, la transportadora y la disponibilidad del producto. Cada pedido se empaca para proteger la pieza durante el transporte.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Cuánto tardan en despachar un pedido?', 'weirdlings-modern' ),
			'answer'   => __( 'El tiempo puede variar según si el producto está disponible o si debe elaborarse bajo pedido. Las piezas personalizadas y las ediciones especiales suelen requerir más tiempo de producción, por lo que recomendamos consultar el plazo antes de confirmar la compra.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Cómo se empacan los pedidos?', 'weirdlings-modern' ),
			'answer'   => __( 'Cada pedido se prepara con empaque individual y material de protección para evitar deformaciones. Además, puede incluir tarjeta de agradecimiento, tarjeta de adopción y recomendaciones básicas de cuidado del producto.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Qué cuidados necesita un amigurumi o llavero tejido?', 'weirdlings-modern' ),
			'answer'   => __( 'Recomendamos evitar humedad, fricción excesiva y exposición prolongada al sol. Si la pieza requiere limpieza, lo ideal es hacerlo de forma suave y localizada para conservar la forma y los detalles del tejido.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Puedo adjuntar una imagen de referencia?', 'weirdlings-modern' ),
			'answer'   => __( 'Sí. El formulario de contacto permite adjuntar imágenes o archivos PDF para compartir referencias, ideas o bocetos. Esto ayuda a entender mejor la solicitud y evaluar la viabilidad del diseño.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Qué pasa si el producto que quiero no está en stock?', 'weirdlings-modern' ),
			'answer'   => __( 'Como trabajamos con un modelo híbrido de inventario, algunas piezas están disponibles y otras se fabrican bajo pedido. Si el producto no está en stock, puedes escribirnos para revisar opciones de elaboración o una alternativa similar.', 'weirdlings-modern' ),
		),
		array(
			'question' => __( '¿Cómo puedo hacer una consulta directa?', 'weirdlings-modern' ),
			'answer'   => __( 'Puedes usar la página de contacto o el botón de WhatsApp para escribirnos. Respondemos por correo y, cuando hace falta, también por WhatsApp para resolver dudas de compra, envío, personalización o soporte.', 'weirdlings-modern' ),
		),
	);

	ob_start();
?>
	<section class="wl-legal-shell wl-faq-shell">
		<div class="wl-legal-hero">
			<div class="wl-legal-hero__copy">
				<span class="wl-legal-kicker"><?php esc_html_e( 'FAQ', 'weirdlings-modern' ); ?></span>
				<h1><?php esc_html_e( 'Respuestas claras para comprar, personalizar y cuidar tus piezas.', 'weirdlings-modern' ); ?></h1>
				<p><?php esc_html_e( 'Aquí reunimos las dudas más habituales sobre la tienda, los pedidos, los pagos, los envíos y el trabajo artesanal de Weirdlings.', 'weirdlings-modern' ); ?></p>
			</div>
		</div>

		<div class="wl-faq-list">
			<?php foreach ( $faqs as $faq ) : ?>
				<details class="wl-faq-item">
					<summary><?php echo esc_html( $faq['question'] ); ?></summary>
					<div class="wl-faq-item__answer">
						<p><?php echo esc_html( $faq['answer'] ); ?></p>
					</div>
				</details>
			<?php endforeach; ?>
		</div>

		<div class="wl-legal-note">
			<p><?php esc_html_e( 'Si tu duda no aparece aquí, escríbenos desde la página de contacto y te ayudamos directamente.', 'weirdlings-modern' ); ?></p>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

function weirdlings_contact_shortcode(): string {
	return weirdlings_render_contact_page();
}
add_shortcode( 'weirdlings_contact_form', 'weirdlings_contact_shortcode' );

function weirdlings_about_shortcode(): string {
	return weirdlings_render_about_page();
}
add_shortcode( 'weirdlings_about', 'weirdlings_about_shortcode' );

function weirdlings_privacy_shortcode(): string {
	return weirdlings_render_privacy_page();
}
add_shortcode( 'weirdlings_privacy_policy', 'weirdlings_privacy_shortcode' );

function weirdlings_terms_shortcode(): string {
	return weirdlings_render_terms_page();
}
add_shortcode( 'weirdlings_terms_conditions', 'weirdlings_terms_shortcode' );

function weirdlings_faq_shortcode(): string {
	return weirdlings_render_faq_page();
}
add_shortcode( 'weirdlings_faq', 'weirdlings_faq_shortcode' );

function weirdlings_account_tabs(): array {
	return array(
		'dashboard' => array(
			'label' => __( 'Escritorio', 'weirdlings-modern' ),
			'help'   => __( 'Resumen rápido de tu cuenta', 'weirdlings-modern' ),
		),
		'orders' => array(
			'label' => __( 'Pedidos', 'weirdlings-modern' ),
			'help'   => __( 'Historial y estado de compras', 'weirdlings-modern' ),
		),
		'downloads' => array(
			'label' => __( 'Descargas', 'weirdlings-modern' ),
			'help'   => __( 'Archivos disponibles', 'weirdlings-modern' ),
		),
		'addresses' => array(
			'label' => __( 'Direcciones', 'weirdlings-modern' ),
			'help'   => __( 'Facturación y envío', 'weirdlings-modern' ),
		),
		'account' => array(
			'label' => __( 'Cuenta', 'weirdlings-modern' ),
			'help'   => __( 'Nombre, correo y contraseña', 'weirdlings-modern' ),
		),
	);
}

function weirdlings_render_account_orders_list( int $user_id ): string {
	$orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'limit'       => 6,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);

	ob_start();

	if ( empty( $orders ) ) :
		?>
		<div class="wl-account-empty">
			<p><?php esc_html_e( 'Aún no tienes pedidos. Cuando compres algo, aparecerá aquí con su estado y detalles.', 'weirdlings-modern' ); ?></p>
			<a class="wl-button wl-button--primary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Ir a la tienda', 'weirdlings-modern' ); ?></a>
		</div>
		<?php
		return (string) ob_get_clean();
	endif;

	foreach ( $orders as $order ) :
		if ( ! $order instanceof WC_Order ) {
			continue;
		}

		$order_date = $order->get_date_created();
		$status     = wc_get_order_status_name( $order->get_status() );
		$item_count = $order->get_item_count() - $order->get_item_count_refunded();
		?>
		<article class="wl-account-order-card">
			<header class="wl-account-order-card__head">
				<div>
					<div class="wl-account-order-card__eyebrow"><?php echo esc_html( sprintf( '#%s', $order->get_order_number() ) ); ?></div>
					<h3><?php echo esc_html( $order_date ? wc_format_datetime( $order_date ) : '' ); ?></h3>
				</div>
				<span class="wl-account-chip wl-account-chip--status-<?php echo esc_attr( sanitize_title( $order->get_status() ) ); ?>"><?php echo esc_html( $status ); ?></span>
			</header>

			<div class="wl-account-order-card__grid">
				<div class="wl-account-metric">
					<span><?php esc_html_e( 'Productos', 'weirdlings-modern' ); ?></span>
					<strong><?php echo esc_html( (string) $item_count ); ?></strong>
				</div>
				<div class="wl-account-metric">
					<span><?php esc_html_e( 'Total', 'weirdlings-modern' ); ?></span>
					<strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
				</div>
			</div>

			<details class="wl-account-order-card__details">
				<summary><?php esc_html_e( 'Ver productos', 'weirdlings-modern' ); ?></summary>
				<ul class="wl-account-order-card__items">
					<?php foreach ( $order->get_items() as $item ) : ?>
						<li>
							<span><?php echo esc_html( $item->get_name() ); ?></span>
							<strong><?php echo esc_html( 'x' . $item->get_quantity() ); ?></strong>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		</article>
		<?php
	endforeach;

	return (string) ob_get_clean();
}

function weirdlings_render_account_downloads_list( int $user_id ): string {
	$downloads = function_exists( 'wc_get_customer_available_downloads' ) ? wc_get_customer_available_downloads( $user_id ) : array();

	ob_start();

	if ( empty( $downloads ) ) :
		?>
		<div class="wl-account-empty">
			<p><?php esc_html_e( 'Todavía no hay descargas disponibles para tu cuenta.', 'weirdlings-modern' ); ?></p>
			<a class="wl-button wl-button--primary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Explorar productos', 'weirdlings-modern' ); ?></a>
		</div>
		<?php
		return (string) ob_get_clean();
	endif;

	foreach ( $downloads as $download ) :
		$product_name = $download['product_name'] ?? '';
		$file_name    = $download['download_name'] ?? $product_name;
		$download_url = $download['download_url'] ?? '';
		$remaining    = $download['downloads_remaining'] ?? '';
		$expires      = $download['access_expires'] ?? '';
		?>
		<article class="wl-account-download-card">
			<div class="wl-account-download-card__copy">
				<div class="wl-account-download-card__eyebrow"><?php echo esc_html( $product_name ); ?></div>
				<h3><?php echo esc_html( $file_name ); ?></h3>
				<div class="wl-account-download-card__meta">
					<?php if ( '' !== $remaining ) : ?>
						<span><?php echo esc_html( sprintf( __( 'Descargas restantes: %s', 'weirdlings-modern' ), $remaining ) ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $expires ) : ?>
						<span><?php echo esc_html( sprintf( __( 'Expira: %s', 'weirdlings-modern' ), $expires ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $download_url ) : ?>
				<a class="wl-button wl-button--ghost" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Descargar', 'weirdlings-modern' ); ?></a>
			<?php endif; ?>
		</article>
		<?php
	endforeach;

	return (string) ob_get_clean();
}

function weirdlings_render_account_page(): string {
	if ( ! is_user_logged_in() ) {
		ob_start();
?>
		<section class="wl-account-shell wl-account-shell--guest">
			<div class="wl-account-hero">
				<div class="wl-account-hero__copy">
					<span class="wl-account-kicker"><?php esc_html_e( 'Mi cuenta', 'weirdlings-modern' ); ?></span>
					<h1><?php esc_html_e( 'Inicia sesión para ver tus pedidos y datos.', 'weirdlings-modern' ); ?></h1>
					<p><?php esc_html_e( 'Desde este panel podrás revisar pedidos, direcciones y descargas sin salir del mismo tablero.', 'weirdlings-modern' ); ?></p>
				</div>
			</div>

			<div class="wl-account-auth-card">
				<?php wc_get_template( 'myaccount/form-login.php' ); ?>
			</div>

			<?php echo weirdlings_render_test_registration_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}


function weirdlings_is_test_registration_enabled(): bool {
	return (bool) apply_filters( 'weirdlings_enable_test_registration_flow', true );
}

function weirdlings_generate_registration_username( string $email ): string {
	$base = sanitize_user( current( explode( '@', $email ) ), true );
	$base = $base ? $base : 'cliente';
	$username = $base;
	$index = 1;

	while ( username_exists( $username ) ) {
		$username = $base . $index;
		$index++;
	}

	return $username;
}

function weirdlings_render_test_registration_form(): string {
	if ( ! weirdlings_is_test_registration_enabled() ) {
		return '';
	}

	ob_start();
	?>
	<div class="wl-account-form-shell wl-account-test-register">
		<div class="wl-account-form-shell__block wl-account-form-shell__block--register">
			<div class="wl-account-form-shell__eyebrow"><?php esc_html_e( 'Registro de prueba', 'weirdlings-modern' ); ?></div>
			<h2><?php esc_html_e( 'Crear usuario al instante', 'weirdlings-modern' ); ?></h2>
			<p class="wl-account-form-shell__text"><?php esc_html_e( 'Este flujo crea la cuenta directamente y no envía verificación por correo. Úsalo para pruebas internas o para validar la experiencia de alta.', 'weirdlings-modern' ); ?></p>

			<form class="woocommerce-form woocommerce-form-register register" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="weirdlings_test_register" />
				<?php wp_nonce_field( 'weirdlings_test_register', 'weirdlings_test_register_nonce' ); ?>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="test_first_name"><?php esc_html_e( 'Nombre', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
					<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="first_name" id="test_first_name" autocomplete="given-name" value="<?php echo isset( $_POST['first_name'] ) ? esc_attr( wp_unslash( (string) $_POST['first_name'] ) ) : ''; ?>" required /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
				</p>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="test_last_name"><?php esc_html_e( 'Apellido', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
					<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="last_name" id="test_last_name" autocomplete="family-name" value="<?php echo isset( $_POST['last_name'] ) ? esc_attr( wp_unslash( (string) $_POST['last_name'] ) ) : ''; ?>" required /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
				</p>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="test_email"><?php esc_html_e( 'Correo electrónico', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
					<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="test_email" autocomplete="email" value="<?php echo isset( $_POST['email'] ) ? esc_attr( wp_unslash( (string) $_POST['email'] ) ) : ''; ?>" required /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
				</p>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="test_password"><?php esc_html_e( 'Contraseña', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
					<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="test_password" autocomplete="new-password" required />
				</p>

				<p class="wl-account-register-note"><?php esc_html_e( 'La cuenta se crea y se inicia sesión automáticamente en la misma prueba.', 'weirdlings-modern' ); ?></p>

				<p class="form-row wl-account-form-shell__actions">
					<button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit"><?php esc_html_e( 'Crear usuario de prueba', 'weirdlings-modern' ); ?></button>
				</p>
			</form>
		</div>
	</div>
	<?php

	return (string) ob_get_clean();
}

function weirdlings_handle_test_registration() {
	if ( ! weirdlings_is_test_registration_enabled() ) {
		wp_die( esc_html__( 'El registro de prueba no está disponible en este entorno.', 'weirdlings-modern' ), 403 );
	}

	if ( ! isset( $_POST['weirdlings_test_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['weirdlings_test_register_nonce'] ) ), 'weirdlings_test_register' ) ) {
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	$first_name = isset( $_POST['first_name'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['first_name'] ) ) ) : '';
	$last_name  = isset( $_POST['last_name'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['last_name'] ) ) ) : '';
	$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
	$password   = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

	if ( '' === $first_name || '' === $last_name || '' === $email || '' === $password ) {
		wc_add_notice( __( 'Completa nombre, apellido, correo y contraseña.', 'weirdlings-modern' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	if ( ! is_email( $email ) ) {
		wc_add_notice( __( 'Ingresa un correo válido.', 'weirdlings-modern' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	if ( email_exists( $email ) ) {
		wc_add_notice( __( 'Ese correo ya tiene una cuenta creada.', 'weirdlings-modern' ), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	$username = weirdlings_generate_registration_username( $email );
	$user_id  = wp_insert_user(
		array(
			'user_login'   => $username,
			'user_pass'    => $password,
			'user_email'   => $email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
			'nickname'     => trim( $first_name . ' ' . $last_name ),
			'role'         => 'customer',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		wc_add_notice( $user_id->get_error_message(), 'error' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_page_permalink( 'myaccount' ) );
		exit;
	}

	update_user_meta( $user_id, 'billing_first_name', $first_name );
	update_user_meta( $user_id, 'billing_last_name', $last_name );
	update_user_meta( $user_id, 'billing_email', $email );

	wp_set_current_user( (int) $user_id );
	wp_set_auth_cookie( (int) $user_id, true );
	do_action( 'wp_login', $username, get_user_by( 'id', $user_id ) );

	wc_add_notice( __( 'Usuario de prueba creado e iniciado sesión.', 'weirdlings-modern' ), 'success' );
	wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
	exit;
}
add_action( 'admin_post_nopriv_weirdlings_test_register', 'weirdlings_handle_test_registration' );
add_action( 'admin_post_weirdlings_test_register', 'weirdlings_handle_test_registration' );
	$current_user          = wp_get_current_user();
	$orders_count          = function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $current_user->ID ) : 0;
	$downloads_count       = function_exists( 'wc_get_customer_available_downloads' ) ? count( wc_get_customer_available_downloads( $current_user->ID ) ) : 0;
	$billing_address       = function_exists( 'wc_get_account_formatted_address' ) ? wc_get_account_formatted_address( 'billing', $current_user->ID ) : '';
	$shipping_address      = function_exists( 'wc_get_account_formatted_address' ) ? wc_get_account_formatted_address( 'shipping', $current_user->ID ) : '';
	$has_shipping          = function_exists( 'wc_shipping_enabled' ) && wc_shipping_enabled() && ( ! function_exists( 'wc_ship_to_billing_address_only' ) || ! wc_ship_to_billing_address_only() );

	ob_start();
?>
	<section class="wl-account-shell">
		<div class="wl-account-hero">
			<div class="wl-account-hero__copy">
				<span class="wl-account-kicker"><?php esc_html_e( 'Mi cuenta', 'weirdlings-modern' ); ?></span>
				<h1><?php echo esc_html( sprintf( __( 'Hola, %s.', 'weirdlings-modern' ), $current_user->display_name ?: $current_user->user_login ) ); ?></h1>
				<p><?php esc_html_e( 'Administra tus pedidos, descargas, direcciones y datos desde un tablero unificado con la estética de Weirdlings.', 'weirdlings-modern' ); ?></p>
			</div>
			<div class="wl-account-hero__actions">
				<button type="button" class="wl-button wl-button--ghost" data-account-logout-url="<?php echo esc_url( wc_logout_url() ); ?>"><?php esc_html_e( 'Cerrar sesión', 'weirdlings-modern' ); ?></button>
			</div>
		</div>

		<div class="wl-account-layout">
			<aside class="wl-account-sidebar">
				<div class="wl-account-nav" role="tablist" aria-label="<?php esc_attr_e( 'Secciones de mi cuenta', 'weirdlings-modern' ); ?>" data-account-tabs>
					<?php foreach ( weirdlings_account_tabs() as $tab_id => $tab ) : ?>
						<button class="wl-account-nav__button <?php echo 'dashboard' === $tab_id ? 'is-active' : ''; ?>" type="button" role="tab" aria-selected="<?php echo 'dashboard' === $tab_id ? 'true' : 'false'; ?>" aria-controls="wl-account-panel-<?php echo esc_attr( $tab_id ); ?>" data-account-tab="<?php echo esc_attr( $tab_id ); ?>">
							<span><?php echo esc_html( $tab['label'] ); ?></span>
							<small><?php echo esc_html( $tab['help'] ); ?></small>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="wl-account-sidebar__card">
					<div class="wl-account-sidebar__eyebrow"><?php esc_html_e( 'Resumen', 'weirdlings-modern' ); ?></div>
					<div class="wl-account-sidebar__stats">
						<div class="wl-account-stat">
							<span><?php esc_html_e( 'Pedidos', 'weirdlings-modern' ); ?></span>
							<strong><?php echo esc_html( (string) $orders_count ); ?></strong>
						</div>
						<div class="wl-account-stat">
							<span><?php esc_html_e( 'Descargas', 'weirdlings-modern' ); ?></span>
							<strong><?php echo esc_html( (string) $downloads_count ); ?></strong>
						</div>
					</div>
				</div>
			</aside>

			<div class="wl-account-panels">
				<section class="wl-account-panel is-active" id="wl-account-panel-dashboard" data-account-panel="dashboard" role="tabpanel">
					<div class="wl-account-panel__head">
						<h2><?php esc_html_e( 'Escritorio', 'weirdlings-modern' ); ?></h2>
						<p><?php esc_html_e( 'Una vista rápida de lo que importa sin saltar entre páginas.', 'weirdlings-modern' ); ?></p>
					</div>

					<div class="wl-account-cards">
						<div class="wl-account-card wl-account-card--stat">
							<span><?php esc_html_e( 'Pedidos totales', 'weirdlings-modern' ); ?></span>
							<strong><?php echo esc_html( (string) $orders_count ); ?></strong>
						</div>
						<div class="wl-account-card wl-account-card--stat">
							<span><?php esc_html_e( 'Descargas activas', 'weirdlings-modern' ); ?></span>
							<strong><?php echo esc_html( (string) $downloads_count ); ?></strong>
						</div>
						<div class="wl-account-card wl-account-card--stat">
							<span><?php esc_html_e( 'Dirección de facturación', 'weirdlings-modern' ); ?></span>
							<strong><?php echo $billing_address ? esc_html__( 'Completa', 'weirdlings-modern' ) : esc_html__( 'Pendiente', 'weirdlings-modern' ); ?></strong>
						</div>
						<div class="wl-account-card wl-account-card--stat">
							<span><?php esc_html_e( 'Dirección de envío', 'weirdlings-modern' ); ?></span>
							<strong><?php echo $has_shipping ? ( $shipping_address ? esc_html__( 'Completa', 'weirdlings-modern' ) : esc_html__( 'Pendiente', 'weirdlings-modern' ) ) : esc_html__( 'No aplica', 'weirdlings-modern' ); ?></strong>
						</div>
					</div>

					<div class="wl-account-card">
						<div class="wl-account-card__head">
							<div>
								<div class="wl-account-card__eyebrow"><?php esc_html_e( 'Acciones rápidas', 'weirdlings-modern' ); ?></div>
								<h3><?php esc_html_e( 'Salta directo a la sección que necesitas', 'weirdlings-modern' ); ?></h3>
							</div>
						</div>
						<div class="wl-account-actions">
							<button type="button" class="wl-button wl-button--ghost" data-account-tab-target="orders"><?php esc_html_e( 'Ver pedidos', 'weirdlings-modern' ); ?></button>
							<button type="button" class="wl-button wl-button--ghost" data-account-tab-target="addresses"><?php esc_html_e( 'Editar direcciones', 'weirdlings-modern' ); ?></button>
							<button type="button" class="wl-button wl-button--ghost" data-account-tab-target="account"><?php esc_html_e( 'Cambiar datos', 'weirdlings-modern' ); ?></button>
						</div>
					</div>
				</section>

				<section class="wl-account-panel" id="wl-account-panel-orders" data-account-panel="orders" role="tabpanel" hidden>
					<div class="wl-account-panel__head">
						<h2><?php esc_html_e( 'Pedidos', 'weirdlings-modern' ); ?></h2>
						<p><?php esc_html_e( 'Historial reciente y estado actual de tus compras.', 'weirdlings-modern' ); ?></p>
					</div>
					<div class="wl-account-stack">
						<?php echo weirdlings_render_account_orders_list( (int) $current_user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</section>

				<section class="wl-account-panel" id="wl-account-panel-downloads" data-account-panel="downloads" role="tabpanel" hidden>
					<div class="wl-account-panel__head">
						<h2><?php esc_html_e( 'Descargas', 'weirdlings-modern' ); ?></h2>
						<p><?php esc_html_e( 'Archivos y contenidos digitales asociados a tu cuenta.', 'weirdlings-modern' ); ?></p>
					</div>
					<div class="wl-account-stack">
						<?php echo weirdlings_render_account_downloads_list( (int) $current_user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</section>

				<section class="wl-account-panel" id="wl-account-panel-addresses" data-account-panel="addresses" role="tabpanel" hidden>
					<div class="wl-account-panel__head">
						<h2><?php esc_html_e( 'Direcciones', 'weirdlings-modern' ); ?></h2>
						<p><?php esc_html_e( 'Revisa y actualiza facturación y envío sin salir del tablero.', 'weirdlings-modern' ); ?></p>
					</div>

					<div class="wl-account-cards">
						<article class="wl-account-card">
							<div class="wl-account-card__head">
								<div>
									<div class="wl-account-card__eyebrow"><?php esc_html_e( 'Facturación', 'weirdlings-modern' ); ?></div>
									<h3><?php esc_html_e( 'Dirección de facturación', 'weirdlings-modern' ); ?></h3>
								</div>
							</div>
							<div class="wl-account-address-preview">
								<?php echo $billing_address ? wp_kses_post( $billing_address ) : esc_html__( 'Aún no has configurado tu dirección de facturación.', 'weirdlings-modern' ); ?>
							</div>
							<div class="wl-account-form-shell">
								<?php if ( class_exists( 'WC_Shortcode_My_Account' ) ) { WC_Shortcode_My_Account::edit_address( 'billing' ); } ?>
							</div>
						</article>

						<?php if ( $has_shipping ) : ?>
							<article class="wl-account-card">
								<div class="wl-account-card__head">
									<div>
										<div class="wl-account-card__eyebrow"><?php esc_html_e( 'Envío', 'weirdlings-modern' ); ?></div>
										<h3><?php esc_html_e( 'Dirección de envío', 'weirdlings-modern' ); ?></h3>
									</div>
								</div>
								<div class="wl-account-address-preview">
									<?php echo $shipping_address ? wp_kses_post( $shipping_address ) : esc_html__( 'Aún no has configurado tu dirección de envío.', 'weirdlings-modern' ); ?>
								</div>
								<div class="wl-account-form-shell">
									<?php if ( class_exists( 'WC_Shortcode_My_Account' ) ) { WC_Shortcode_My_Account::edit_address( 'shipping' ); } ?>
								</div>
							</article>
						<?php endif; ?>
					</div>
				</section>

				<section class="wl-account-panel" id="wl-account-panel-account" data-account-panel="account" role="tabpanel" hidden>
					<div class="wl-account-panel__head">
						<h2><?php esc_html_e( 'Cuenta', 'weirdlings-modern' ); ?></h2>
						<p><?php esc_html_e( 'Actualiza tu nombre, correo y contraseña desde un formulario limpio y directo.', 'weirdlings-modern' ); ?></p>
					</div>
					<div class="wl-account-card wl-account-form-shell">
						<?php if ( class_exists( 'WC_Shortcode_My_Account' ) ) { WC_Shortcode_My_Account::edit_account(); } ?>
					</div>
				</section>
			</div>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

function weirdlings_handle_contact_form_submission(): void {
	if ( ! isset( $_POST['weirdlings_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['weirdlings_contact_nonce'] ) ), 'weirdlings_contact_form' ) ) {
		wp_safe_redirect( add_query_arg( 'wl_contact', 'error', weirdlings_contact_page_url() ) );
		exit;
	}

	$name     = isset( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '';
	$email    = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
	$phone    = isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '';
	$subject  = isset( $_POST['contact_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_subject'] ) ) : '';
	$message  = isset( $_POST['contact_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['contact_message'] ) ) : '';
	$privacy  = ! empty( $_POST['contact_privacy'] );
	$redirect = weirdlings_contact_page_url();

	if ( '' === $name || '' === $email || '' === $subject || '' === $message || ! is_email( $email ) || ! $privacy ) {
		wp_safe_redirect( add_query_arg( 'wl_contact', 'error', $redirect ) );
		exit;
	}

	$attachments = array();

	if ( ! empty( $_FILES['contact_file']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$uploaded = wp_handle_upload(
			$_FILES['contact_file'],
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'gif'          => 'image/gif',
					'webp'         => 'image/webp',
					'pdf'          => 'application/pdf',
				),
			)
		);

		if ( isset( $uploaded['error'] ) ) {
			wp_safe_redirect( add_query_arg( 'wl_contact', 'error', $redirect ) );
			exit;
		}

		if ( ! empty( $uploaded['file'] ) ) {
			$attachments[] = $uploaded['file'];
		}
	}

	$recipient    = get_option( 'admin_email' );
	$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$mail_subject  = sprintf( __( 'Nuevo mensaje de servicio al cliente desde %s', 'weirdlings-modern' ), $site_name );
	$mail_body     = "Nuevo mensaje recibido desde el formulario de contacto.\n\n";
	$mail_body    .= 'Nombre: ' . $name . "\n";
	$mail_body    .= 'Correo: ' . $email . "\n";

	if ( '' !== $phone ) {
		$mail_body .= 'Teléfono: ' . $phone . "\n";
	}

	$mail_body .= 'Asunto: ' . $subject . "\n\n";
	$mail_body .= "Mensaje:\n" . $message . "\n";

	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'Reply-To: ' . $name . ' <' . $email . '>',
	);

	if ( ! wp_mail( $recipient, $mail_subject, $mail_body, $headers, $attachments ) ) {
		wp_safe_redirect( add_query_arg( 'wl_contact', 'error', $redirect ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'wl_contact', 'sent', $redirect ) );
	exit;
}
add_action( 'admin_post_nopriv_weirdlings_contact_form', 'weirdlings_handle_contact_form_submission' );
add_action( 'admin_post_weirdlings_contact_form', 'weirdlings_handle_contact_form_submission' );

function weirdlings_handle_customization_form_submission(): void {
	if ( ! isset( $_POST['weirdlings_customization_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['weirdlings_customization_nonce'] ) ), 'weirdlings_customization_form' ) ) {
		wp_safe_redirect( add_query_arg( 'wl_custom', 'error', weirdlings_customization_page_url() ) );
		exit;
	}

	$name        = isset( $_POST['custom_name'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_name'] ) ) : '';
	$email       = isset( $_POST['custom_email'] ) ? sanitize_email( wp_unslash( $_POST['custom_email'] ) ) : '';
	$phone       = isset( $_POST['custom_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_phone'] ) ) : '';
	$product     = isset( $_POST['custom_product'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_product'] ) ) : '';
	$idea        = isset( $_POST['custom_idea'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_idea'] ) ) : '';
	$colors      = isset( $_POST['custom_colors'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_colors'] ) ) : '';
	$size        = isset( $_POST['custom_size'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_size'] ) ) : '';
	$accessories = isset( $_POST['custom_accessories'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_accessories'] ) ) : '';
	$expression  = isset( $_POST['custom_expression'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_expression'] ) ) : '';
	$text        = isset( $_POST['custom_text'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_text'] ) ) : '';
	$reference   = isset( $_POST['custom_reference'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_reference'] ) ) : '';
	$deadline    = isset( $_POST['custom_deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_deadline'] ) ) : '';
	$privacy     = ! empty( $_POST['custom_privacy'] );
	$redirect    = weirdlings_customization_page_url();

	if ( '' === $name || '' === $email || '' === $product || '' === $idea || ! is_email( $email ) || ! $privacy ) {
		wp_safe_redirect( add_query_arg( 'wl_custom', 'error', $redirect ) );
		exit;
	}

	$attachments = array();

	if ( ! empty( $_FILES['custom_file']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$uploaded = wp_handle_upload(
			$_FILES['custom_file'],
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'gif'          => 'image/gif',
					'webp'         => 'image/webp',
					'pdf'          => 'application/pdf',
				),
			)
		);

		if ( isset( $uploaded['error'] ) ) {
			wp_safe_redirect( add_query_arg( 'wl_custom', 'error', $redirect ) );
			exit;
		}

		if ( ! empty( $uploaded['file'] ) ) {
			$attachments[] = $uploaded['file'];
		}
	}

	$recipient    = get_option( 'admin_email' );
	$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$mail_subject  = sprintf( __( 'Nueva solicitud de personalizado desde %s', 'weirdlings-modern' ), $site_name );
	$mail_body     = "Nueva solicitud de producto personalizado.\n\n";
	$mail_body    .= 'Nombre: ' . $name . "\n";
	$mail_body    .= 'Correo: ' . $email . "\n";

	if ( '' !== $phone ) {
		$mail_body .= 'WhatsApp: ' . $phone . "\n";
	}

	$mail_body .= 'Tipo de pieza: ' . $product . "\n";
	$mail_body .= 'Tamaño aproximado: ' . ( '' !== $size ? $size : 'No indicado' ) . "\n";
	$mail_body .= 'Colores preferidos: ' . ( '' !== $colors ? $colors : 'No indicados' ) . "\n";
	$mail_body .= 'Accesorios o detalles: ' . ( '' !== $accessories ? $accessories : 'No indicados' ) . "\n";
	$mail_body .= 'Expresión o rasgos: ' . ( '' !== $expression ? $expression : 'No indicados' ) . "\n";
	$mail_body .= 'Nombre o bordado: ' . ( '' !== $text ? $text : 'No indicado' ) . "\n";
	$mail_body .= 'Fecha ideal: ' . ( '' !== $deadline ? $deadline : 'No indicada' ) . "\n";
	$mail_body .= "\nIdea general:\n" . $idea . "\n";

	if ( '' !== $reference ) {
		$mail_body .= "\nReferencias:\n" . $reference . "\n";
	}

	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'Reply-To: ' . $name . ' <' . $email . '>',
	);

	if ( ! wp_mail( $recipient, $mail_subject, $mail_body, $headers, $attachments ) ) {
		wp_safe_redirect( add_query_arg( 'wl_custom', 'error', $redirect ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'wl_custom', 'sent', $redirect ) );
	exit;
}
add_action( 'admin_post_nopriv_weirdlings_customization_form', 'weirdlings_handle_customization_form_submission' );
add_action( 'admin_post_weirdlings_customization_form', 'weirdlings_handle_customization_form_submission' );

function weirdlings_seed_contact_page(): void {
	if ( get_option( 'weirdlings_contact_page_seeded' ) ) {
		return;
	}

	$page = get_page_by_path( 'contacto' );

	if ( $page instanceof WP_Post ) {
		update_post_meta( $page->ID, '_wp_page_template', 'page-contacto.php' );
		update_option( 'weirdlings_contact_page_seeded', 1 );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Contacto',
			'post_name'    => 'contacto',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		),
		true
	);

	if ( ! is_wp_error( $page_id ) ) {
		update_post_meta( (int) $page_id, '_wp_page_template', 'page-contacto.php' );
		update_option( 'weirdlings_contact_page_seeded', 1 );
	}
}
add_action( 'after_switch_theme', 'weirdlings_seed_contact_page' );
add_action( 'admin_init', 'weirdlings_seed_contact_page' );

function weirdlings_seed_customization_page(): void {
	if ( get_option( 'weirdlings_customization_page_seeded' ) ) {
		return;
	}

	$page = get_page_by_path( 'personalizados' );

	if ( $page instanceof WP_Post ) {
		update_post_meta( $page->ID, '_wp_page_template', 'page-personalizados.php' );
		update_option( 'weirdlings_customization_page_seeded', 1 );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Personalizados',
			'post_name'    => 'personalizados',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		),
		true
	);

	if ( ! is_wp_error( $page_id ) ) {
		update_post_meta( (int) $page_id, '_wp_page_template', 'page-personalizados.php' );
		update_option( 'weirdlings_customization_page_seeded', 1 );
	}
}
add_action( 'after_switch_theme', 'weirdlings_seed_customization_page' );
add_action( 'admin_init', 'weirdlings_seed_customization_page' );
add_action( 'init', 'weirdlings_seed_customization_page' );

function weirdlings_seed_blog_page(): void {
	if ( get_option( 'weirdlings_blog_page_seeded' ) ) {
		return;
	}

	$page = get_page_by_path( 'blog' );

	if ( $page instanceof WP_Post ) {
		update_post_meta( $page->ID, '_wp_page_template', 'page-blog.php' );
		if ( 0 === (int) get_option( 'page_for_posts' ) ) {
			update_option( 'page_for_posts', (int) $page->ID );
		}
		update_option( 'weirdlings_blog_page_seeded', 1 );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Blog',
			'post_name'    => 'blog',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		),
		true
	);

	if ( ! is_wp_error( $page_id ) ) {
		update_post_meta( (int) $page_id, '_wp_page_template', 'page-blog.php' );
		if ( 0 === (int) get_option( 'page_for_posts' ) ) {
			update_option( 'page_for_posts', (int) $page_id );
		}
		update_option( 'weirdlings_blog_page_seeded', 1 );
	}
}
add_action( 'after_switch_theme', 'weirdlings_seed_blog_page' );
add_action( 'admin_init', 'weirdlings_seed_blog_page' );
add_action( 'init', 'weirdlings_seed_blog_page' );

function weirdlings_seed_about_page(): void {
	if ( get_option( 'weirdlings_about_page_seeded' ) ) {
		return;
	}

	$page = get_page_by_path( 'sobre-weirdlings' );

	if ( $page instanceof WP_Post ) {
		update_post_meta( $page->ID, '_wp_page_template', 'page-sobre-weirdlings.php' );
		update_option( 'weirdlings_about_page_seeded', 1 );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Sobre Weirdlings',
			'post_name'    => 'sobre-weirdlings',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		),
		true
	);

	if ( ! is_wp_error( $page_id ) ) {
		update_post_meta( (int) $page_id, '_wp_page_template', 'page-sobre-weirdlings.php' );
		update_option( 'weirdlings_about_page_seeded', 1 );
	}
}
add_action( 'after_switch_theme', 'weirdlings_seed_about_page' );
add_action( 'admin_init', 'weirdlings_seed_about_page' );

function weirdlings_seed_privacy_page(): void {
	if ( get_option( 'weirdlings_privacy_page_seeded' ) ) {
		return;
	}

	$page = get_page_by_path( 'politica-de-privacidad' );

	if ( $page instanceof WP_Post ) {
		update_post_meta( $page->ID, '_wp_page_template', 'page-politica-privacidad.php' );
		update_option( 'weirdlings_privacy_page_seeded', 1 );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Política de privacidad',
			'post_name'    => 'politica-de-privacidad',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		),
		true
	);

	if ( ! is_wp_error( $page_id ) ) {
		update_post_meta( (int) $page_id, '_wp_page_template', 'page-politica-privacidad.php' );
		update_option( 'weirdlings_privacy_page_seeded', 1 );
	}
}
add_action( 'after_switch_theme', 'weirdlings_seed_privacy_page' );
add_action( 'admin_init', 'weirdlings_seed_privacy_page' );

function weirdlings_seed_terms_page(): void {
	if ( get_option( 'weirdlings_terms_page_seeded' ) ) {
		return;
	}

	$page = get_page_by_path( 'terminos-y-condiciones' );

	if ( $page instanceof WP_Post ) {
		update_post_meta( $page->ID, '_wp_page_template', 'page-terminos-y-condiciones.php' );
		update_option( 'weirdlings_terms_page_seeded', 1 );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Términos y condiciones',
			'post_name'    => 'terminos-y-condiciones',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		),
		true
	);

	if ( ! is_wp_error( $page_id ) ) {
		update_post_meta( (int) $page_id, '_wp_page_template', 'page-terminos-y-condiciones.php' );
		update_option( 'weirdlings_terms_page_seeded', 1 );
	}
}
add_action( 'after_switch_theme', 'weirdlings_seed_terms_page' );
add_action( 'admin_init', 'weirdlings_seed_terms_page' );

function weirdlings_seed_faq_page(): void {
	if ( get_option( 'weirdlings_faq_page_seeded' ) ) {
		return;
	}

	$page = get_page_by_path( 'preguntas-frecuentes' );

	if ( $page instanceof WP_Post ) {
		update_post_meta( $page->ID, '_wp_page_template', 'page-faq.php' );
		update_option( 'weirdlings_faq_page_seeded', 1 );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Preguntas frecuentes',
			'post_name'    => 'preguntas-frecuentes',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		),
		true
	);

	if ( ! is_wp_error( $page_id ) ) {
		update_post_meta( (int) $page_id, '_wp_page_template', 'page-faq.php' );
		update_option( 'weirdlings_faq_page_seeded', 1 );
	}
}
add_action( 'after_switch_theme', 'weirdlings_seed_faq_page' );
add_action( 'admin_init', 'weirdlings_seed_faq_page' );
