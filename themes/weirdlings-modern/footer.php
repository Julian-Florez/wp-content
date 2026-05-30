<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
  </main>

  <footer class="wl-footer">
    <div class="wl-container">
      <div class="wl-footer__newsletter">
        <div>
          <h2>Únete a la manada</h2>
          <p>Recibe novedades, lanzamientos y criaturas nuevas directamente en tu correo.</p>
        </div>
        <form class="wl-footer__form" action="#" method="post">
          <input type="email" name="email" placeholder="Tu correo electrónico" aria-label="Tu correo electrónico">
          <button class="wl-button wl-button--primary" type="submit">Suscribirme</button>
        </form>
      </div>

      <div class="wl-wave" aria-hidden="true"></div>

      <div class="wl-footer__bar">
        <div>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Weirdlings. Hecho a mano con amor y magia oscura.</div>
        <div class="wl-footer__links">
          <a href="<?php echo esc_url( home_url( '/tienda/' ) ); ?>">Tienda</a>
          <a href="<?php echo esc_url( home_url( '/contacto/' ) ); ?>">Contacto</a>
          <a href="<?php echo esc_url( home_url( '/politica-de-privacidad/' ) ); ?>">Privacidad</a>
          <a href="<?php echo esc_url( home_url( '/terminos-y-condiciones/' ) ); ?>">Términos</a>
          <a href="<?php echo esc_url( home_url( '/preguntas-frecuentes/' ) ); ?>">FAQ</a>
        </div>
      </div>
    </div>
  </footer>
</div>

<?php wp_footer(); ?>
</body>
</html>
