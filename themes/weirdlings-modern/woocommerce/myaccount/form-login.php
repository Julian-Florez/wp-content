<?php
/**
 * Custom login/register form for Weirdlings.
 *
 * This template overrides WooCommerce's default my account form to provide
 * a branded login and customer registration experience.
 *
 * @package Weirdlings_Modern
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_customer_login_form' );
?>

<section class="wl-account-form-shell">
	<?php if ( function_exists( 'wc_print_notices' ) ) : ?>
		<?php wc_print_notices(); ?>
	<?php endif; ?>

	<?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>
		<div class="u-columns col2-set" id="customer_login">
			<div class="u-column1 col-1">
	<?php endif; ?>

			<div class="wl-account-form-shell__block">
				<div class="wl-account-form-shell__eyebrow"><?php esc_html_e( 'Acceso', 'weirdlings-modern' ); ?></div>
				<h2><?php esc_html_e( 'Inicia sesión', 'weirdlings-modern' ); ?></h2>
				<p class="wl-account-form-shell__text"><?php esc_html_e( 'Entra para revisar tus pedidos, descargas y direcciones.', 'weirdlings-modern' ); ?></p>

				<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>
					<?php do_action( 'woocommerce_login_form_start' ); ?>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="username"><?php esc_html_e( 'Correo o usuario', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'weirdlings-modern' ); ?></span></label>
						<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo isset( $_POST['username'] ) ? esc_attr( wp_unslash( (string) $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
					</p>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="password"><?php esc_html_e( 'Contraseña', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'weirdlings-modern' ); ?></span></label>
						<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
					</p>

					<?php do_action( 'woocommerce_login_form' ); ?>

					<p class="form-row wl-account-form-shell__actions">
						<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
							<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Recordarme', 'weirdlings-modern' ); ?></span>
						</label>
						<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
						<button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"><?php esc_html_e( 'Entrar', 'weirdlings-modern' ); ?></button>
					</p>

					<p class="woocommerce-LostPassword lost_password">
						<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( '¿Olvidaste tu contraseña?', 'weirdlings-modern' ); ?></a>
					</p>

					<?php do_action( 'woocommerce_login_form_end' ); ?>
				</form>
			</div>

	<?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>
			</div>

			<div class="u-column2 col-2">
				<div class="wl-account-form-shell__block wl-account-form-shell__block--register">
					<div class="wl-account-form-shell__eyebrow"><?php esc_html_e( 'Nuevo cliente', 'weirdlings-modern' ); ?></div>
					<h2><?php esc_html_e( 'Crea tu cuenta', 'weirdlings-modern' ); ?></h2>
					<p class="wl-account-form-shell__text"><?php esc_html_e( 'Crea tu perfil para comprar más rápido, seguir tus pedidos y guardar tus datos de envío.', 'weirdlings-modern' ); ?></p>

					<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
						<?php do_action( 'woocommerce_register_form_start' ); ?>

						<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
							<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
								<label for="reg_username"><?php esc_html_e( 'Usuario', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'weirdlings-modern' ); ?></span></label>
								<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo isset( $_POST['username'] ) ? esc_attr( wp_unslash( (string) $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
							</p>
						<?php endif; ?>

						<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
							<label for="reg_email"><?php esc_html_e( 'Correo electrónico', 'weirdlings-modern' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'weirdlings-modern' ); ?></span></label>
							<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo isset( $_POST['email'] ) ? esc_attr( wp_unslash( (string) $_POST['email'] ) ) : ''; ?>" required aria-required="true" /><?php // phpcs:ignore WordPress.Security.NonceVerification.Missing ?>
						</p>

						<?php do_action( 'woocommerce_register_form' ); ?>

						<p class="form-row wl-account-form-shell__actions">
							<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
							<button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Crear cuenta', 'weirdlings-modern' ); ?></button>
						</p>

						<?php do_action( 'woocommerce_register_form_end' ); ?>
					</form>
				</div>
			</div>
		</div>
	<?php else : ?>
		<div class="wl-account-form-shell__block wl-account-form-shell__block--register">
			<div class="wl-account-form-shell__eyebrow"><?php esc_html_e( 'Nuevo cliente', 'weirdlings-modern' ); ?></div>
			<h2><?php esc_html_e( 'La creación de cuentas está desactivada en WooCommerce.', 'weirdlings-modern' ); ?></h2>
			<p class="wl-account-form-shell__text"><?php esc_html_e( 'Activa el registro de clientes en Ajustes de WooCommerce para mostrar el formulario de alta.', 'weirdlings-modern' ); ?></p>
		</div>
	<?php endif; ?>
</section>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
