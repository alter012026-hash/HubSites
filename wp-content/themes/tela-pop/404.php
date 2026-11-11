<?php get_header(); ?>
<div class="wrap">
	<header class="page-head"><h1>Impedimento!</h1><div class="description">Página não encontrada. O endereço pode ter mudado ou deixou de existir.</div></header>
	<article class="content">
		<p>Tente buscar pelo que procura:</p>
		<?php get_search_form(); ?>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Voltar à página inicial</a></p>
	</article>
</div>
<?php get_footer(); ?>
