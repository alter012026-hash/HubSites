<?php get_header(); ?>
<div class="wrap">
	<header class="page-head">
		<h1>Busca: <?php echo esc_html( get_search_query() ); ?></h1>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="grid grid-3">
			<?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/card' ); endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'prev_text' => '‹ Anteriores', 'next_text' => 'Próximas ›' ) ); ?>
	<?php else : ?>
		<p>Nada encontrado. Tente outro termo.</p>
		<?php get_search_form(); ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
