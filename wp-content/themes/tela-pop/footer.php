</main>
<footer class="site-footer"<?php echo is_front_page() ? '' : ' id="assine"'; ?>>
	<div class="wrap">
		<?php if ( function_exists( 'tpop_newsletter_form' ) ) : ?>
			<div class="footer-cta">
				<div>
					<span class="nl-kicker">Newsletter grátis</span>
					<h2><?php echo esc_html( get_theme_mod( 'tpop_nl_title', 'Sua próxima sessão começa aqui' ) ); ?></h2>
					<p><?php echo esc_html( get_theme_mod( 'tpop_nl_text', 'Receba críticas, estreias e guias de streaming selecionados pela redação. Sem spam.' ) ); ?></p>
				</div>
				<div><?php tpop_newsletter_form( 'footer', 'rodape' ); ?></div>
			</div>
		<?php endif; ?>

		<div class="footer-grid">
			<div>
				<div class="footer-brand"><?php echo tpop_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<p>Filmes e séries com contexto, opinião e informação conferida.</p>
				<?php if ( is_active_sidebar( 'tpop-footer' ) ) { dynamic_sidebar( 'tpop-footer' ); } ?>
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
		<p class="footer-note">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Tela Pop. Escudos e marcas pertencem aos respectivos titulares e são citados apenas para identificação.</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
