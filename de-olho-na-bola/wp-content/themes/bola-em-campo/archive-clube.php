<?php get_header(); ?>
<div class="wrap">
	<header class="page-head">
		<?php if ( function_exists( 'bec_breadcrumbs' ) ) { bec_breadcrumbs(); } ?>
		<h1>Clubes</h1>
		<div class="description">Notícias, jogos e resultados dos principais clubes do futebol brasileiro.</div>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="clubs-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<a class="club-chip" href="<?php the_permalink(); ?>"><?php echo bec_club_badge( get_the_title() ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php the_title(); ?></span></a>
			<?php endwhile; ?>
		</div>
	<?php else : ?>
		<p>Nenhuma página de clube publicada ainda.</p>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
