<?php get_header(); ?>
<div class="wrap">
	<header class="page-head">
		<?php if ( function_exists( 'bec_breadcrumbs' ) ) { bec_breadcrumbs(); } ?>
		<h1><?php the_archive_title(); ?></h1>
		<?php the_archive_description( '<div class="description">', '</div>' ); ?>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="grid grid-3">
			<?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/card' ); endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '‹ Anteriores', 'next_text' => 'Próximas ›' ) ); ?>
	<?php else : ?>
		<p>Ainda não há conteúdo nesta seção.</p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
