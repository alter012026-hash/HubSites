<?php get_header(); ?>
<div class="wrap">
	<?php while ( have_posts() ) : the_post(); ?>
		<header class="page-head">
			<?php if ( function_exists( 'bec_breadcrumbs' ) ) { bec_breadcrumbs(); } ?>
			<h1><?php the_title(); ?></h1>
		</header>
		<article <?php post_class( 'content' ); ?>>
			<div class="post-body"><?php the_content(); ?></div>
		</article>
	<?php endwhile; ?>
</div>
<?php get_footer(); ?>
