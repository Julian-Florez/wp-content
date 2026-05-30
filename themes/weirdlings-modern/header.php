<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
$logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'full', false, array( 'class' => 'wl-brand__image', 'alt' => get_bloginfo( 'name' ) ) ) : '';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
  <div class="wl-topbar">
    <div class="wl-container wl-topbar__inner">
      <div class="wl-topbar__left">Envíos a todo Colombia</div>
      <div class="wl-topbar__center">Hechos a mano con amor y un poquito de magia oscura</div>
      <div class="wl-topbar__right wl-topbar__social" aria-label="Redes sociales">
        <a href="#" aria-label="Instagram" title="Instagram">
          <?php echo weirdlings_render_header_icon( 'instagram' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </a>
        <a href="#" aria-label="TikTok" title="TikTok">
          <?php echo weirdlings_render_header_icon( 'tiktok' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </a>
      </div>
    </div>
  </div>

  <header class="wl-site-header">
    <div class="wl-container wl-site-header__inner">
      <a class="wl-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
        <?php if ( $logo ) : ?>
          <?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
          <span class="wl-brand__mark"><?php echo esc_html( get_bloginfo( 'name', 'display' ) ?: 'Weirdlings' ); ?></span>
          <span class="wl-brand__tag"><?php echo esc_html( get_bloginfo( 'description', 'display' ) ?: 'crochet creatures' ); ?></span>
        <?php endif; ?>
      </a>

      <nav class="wl-nav" aria-label="<?php esc_attr_e( 'Menú principal', 'weirdlings-modern' ); ?>">
        <?php echo weirdlings_render_menu_items( 'primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </nav>

      <div class="wl-header-actions">
        <a class="wl-action-icon" href="<?php echo esc_url( $shop_url ); ?>" aria-label="<?php esc_attr_e( 'Tienda', 'weirdlings-modern' ); ?>">
          <?php echo weirdlings_render_header_icon( 'shop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </a>
        <a class="wl-action-icon" href="<?php echo esc_url( $account_url ); ?>" aria-label="<?php esc_attr_e( 'Mi cuenta', 'weirdlings-modern' ); ?>">
          <?php echo weirdlings_render_header_icon( 'account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </a>
        <a class="wl-action-icon" href="<?php echo esc_url( $cart_url ); ?>" aria-label="<?php esc_attr_e( 'Carrito', 'weirdlings-modern' ); ?>">
          <?php echo weirdlings_render_header_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </a>
        <button class="wl-menu-toggle" type="button" aria-controls="wl-mobile-menu" aria-expanded="false" data-menu-toggle>
          <?php echo weirdlings_render_header_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </button>
      </div>
    </div>

    <div class="wl-mobile-panel">
      <div id="wl-mobile-menu" class="wl-mobile-panel__menu" data-mobile-menu>
        <?php echo weirdlings_render_menu_items( 'primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>
    </div>
  </header>
  <main class="wl-main">
