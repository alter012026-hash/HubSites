<?php get_header(); ?>
<div class="wrap">
	<header class="page-head"><h1><?php echo is_home() && ! is_front_page() ? esc_html( get_the_title( get_option( 'page_for_posts' ) ) ) : 'Últimas notícias'; ?></h1></header>
	<?php if ( have_posts() ) : ?>
		<div class="grid grid-3">
			<?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/card' ); endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '‹ Anteriores', 'next_text' => 'Próximas ›' ) ); ?>
	<?php else : ?>
		<p>Nenhuma publicação encontrada.</p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
