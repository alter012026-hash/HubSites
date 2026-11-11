</main>
<footer class="site-footer"<?php echo is_front_page() ? '' : ' id="assine"'; ?>>
	<div class="wrap">
		<?php if ( function_exists( 'bec_newsletter_form' ) ) : ?>
			<div class="footer-cta">
				<div>
					<span class="nl-kicker">Newsletter grátis</span>
					<h2><?php echo esc_html( get_theme_mod( 'bec_nl_title', 'Receba o resumo do futebol' ) ); ?></h2>
					<p><?php echo esc_html( get_theme_mod( 'bec_nl_text', 'Toda semana no seu e-mail: os destaques do Brasileirão, da Libertadores e do mercado da bola. Sem spam.' ) ); ?></p>
				</div>
				<div><?php bec_newsletter_form( 'footer', 'rodape' ); ?></div>
			</div>
		<?php endif; ?>

		<div class="footer-grid">
			<div>
				<div class="footer-brand"><?php echo bec_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<p>Futebol com informação, contexto e credibilidade.</p>
				<?php if ( is_active_sidebar( 'bec-footer' ) ) { dynamic_sidebar( 'bec-footer' ); } ?>
			</div>
			<div>
				<?php
				if ( has_nav_menu( 'footer' ) ) {
					wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'menu_class' => 'footer-menu', 'depth' => 1 ) );
				} else {
					$links = array( 'sobre' => 'Sobre', 'politica-editorial' => 'Política editorial', 'correcoes' => 'Correções', 'contato' => 'Contato', 'privacidade' => 'Privacidade' );
					echo '<ul class="footer-menu">';
					foreach ( $links as $slug => $label ) {
						$page = get_page_by_path( $slug );
						if ( $page && 'publish' === $page->post_status ) {
							echo '<li><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( $label ) . '</a></li>';
						}
					}
					echo '</ul>';
				}
				?>
			</div>
		</div>
		<p class="footer-note">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> De Olho na Bola. Escudos e marcas pertencem aos respectivos titulares e são citados apenas para identificação.</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
