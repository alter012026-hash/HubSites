<?php get_header(); ?>
<div class="wrap">
	<header class="page-head catalog-head">
		<?php if ( function_exists( 'tpop_breadcrumbs' ) ) { tpop_breadcrumbs(); } ?>
		<span class="eyebrow">GUIA DE TÍTULOS</span>
		<h1>Filmes e séries</h1>
		<div class="description">Explore títulos, confira informações essenciais e veja onde assistir quando a disponibilidade estiver confirmada.</div>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="grid grid-3 catalog-grid">
			<?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/card' ); endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '‹ Anteriores', 'next_text' => 'Próximos ›' ) ); ?>
	<?php else : ?><p class="empty-state">O catálogo ainda está sendo montado.</p><?php endif; ?>
</div>
<?php get_footer(); ?>
