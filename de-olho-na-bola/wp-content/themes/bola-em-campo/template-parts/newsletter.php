<?php
/**
 * Bloco de newsletter.
 * Argumentos: variant (band|box), source (origem registrada).
 */
if ( ! function_exists( 'bec_newsletter_form' ) ) {
	return; // Plugin De Olho na Bola Core inativo.
}
$variant = isset( $args['variant'] ) ? $args['variant'] : 'band';
$source  = isset( $args['source'] ) ? $args['source'] : 'site';
$title   = get_theme_mod( 'bec_nl_title', 'Receba o resumo do futebol' );
$text    = get_theme_mod( 'bec_nl_text', 'Toda semana no seu e-mail: os destaques do Brasileirão, da Libertadores e do mercado da bola. Sem spam.' );
$class   = 'box' === $variant ? 'nl-box' : 'nl-band';
$id      = ( 'band' === $variant && is_front_page() ) ? ' id="assine"' : '';
?>
<section class="<?php echo esc_attr( $class ); ?>"<?php echo $id; // phpcs:ignore WordPress.Security.EscapeOutput ?> aria-labelledby="nl-t-<?php echo esc_attr( $variant ); ?>">
	<div>
		<span class="nl-kicker">Newsletter grátis</span>
		<h2 id="nl-t-<?php echo esc_attr( $variant ); ?>"><?php echo esc_html( $title ); ?></h2>
		<p><?php echo esc_html( $text ); ?></p>
	</div>
	<div><?php bec_newsletter_form( $variant, $source ); ?></div>
</section>
