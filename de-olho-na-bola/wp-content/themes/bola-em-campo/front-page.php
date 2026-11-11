<?php
get_header();

// Manchete: o post fixo mais recente, ou o mais recente com imagem destacada.
$sticky   = get_option( 'sticky_posts' );
$hero_ids = array();
$hero_q   = new WP_Query(
	array(
		'posts_per_page'      => 1,
		'date_query'          => array( array( 'after' => '30 days ago' ) ),
		'bec_home_recent_only' => true,
		'post__in'            => $sticky ? $sticky : array( 0 ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( ! $hero_q->have_posts() ) {
	$hero_q = new WP_Query(
		array(
			'posts_per_page'      => 1,
			'date_query'          => array( array( 'after' => '30 days ago' ) ),
			'bec_home_recent_only' => true,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_key'            => '_thumbnail_id',
		)
	);
}
if ( ! $hero_q->have_posts() ) {
	$hero_q = new WP_Query( array( 'posts_per_page' => 1, 'ignore_sticky_posts' => true, 'no_found_rows' => true, 'date_query' => array( array( 'after' => '30 days ago' ) ), 'bec_home_recent_only' => true ) );
}
?>
<div class="wrap">

	<?php if ( $hero_q->have_posts() ) : ?>
	<section class="hero" aria-label="Manchete">
		<?php
		while ( $hero_q->have_posts() ) :
			$hero_q->the_post();
			$hero_ids[] = get_the_ID();
			?>
			<article class="hero-main">
				<a href="<?php the_permalink(); ?>">
					<?php bec_thumb( 'bec-hero' ); ?>
					<div class="hero-body">
						<?php bec_the_category_label(); ?>
						<h2><?php the_title(); ?></h2>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26, '…' ) ); ?></p>
					</div>
				</a>
			</article>
			<?php
		endwhile;
		wp_reset_postdata();

		$latest = new WP_Query(
			array(
				'posts_per_page'      => 6,
				'date_query'          => array( array( 'after' => '30 days ago' ) ),
				'bec_home_recent_only' => true,
				'post__not_in'        => $hero_ids,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
		?>
		<aside class="ultimas" aria-label="Últimas notícias">
			<div class="section-title"><h2>Últimas</h2></div>
			<ol class="timeline" style="list-style:none">
				<?php while ( $latest->have_posts() ) : $latest->the_post(); ?>
					<li>
						<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( bec_primary_category_name() ); ?> · <?php echo esc_html( get_the_date( 'd/m H:i' ) ); ?></time>
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</li>
				<?php endwhile; wp_reset_postdata(); ?>
			</ol>
		</aside>
	</section>
	<?php else : ?>
		<section class="page-head">
			<h1>Bem-vindo ao De Olho na Bola</h1>
			<p class="description">Ainda não há publicações. Crie artigos em <strong>Posts</strong> ou gere rascunhos a partir do painel <strong>De Olho na Bola</strong>.</p>
		</section>
	<?php endif; ?>

	<?php if ( function_exists( 'bec_recent_matches_shortcode' ) ) { echo do_shortcode( '[bec_partidas count="5"]' ); } ?>

	<?php get_template_part( 'template-parts/newsletter', null, array( 'variant' => 'band', 'source' => 'home' ) ); ?>

	<?php
	bec_home_section( 'noticias', 'Notícias', 4 );
	bec_home_section( 'brasileirao', 'Brasileirão', 4 );
	bec_home_section( 'libertadores', 'Libertadores', 4 );

	$clubs = new WP_Query( array( 'post_type' => 'clube', 'posts_per_page' => 20, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
	if ( $clubs->have_posts() ) :
		?>
		<section class="section">
			<div class="section-title"><h2>Clubes</h2><a href="<?php echo esc_url( home_url( '/clubes/' ) ); ?>">Ver todos</a></div>
			<div class="clubs-strip">
				<?php while ( $clubs->have_posts() ) : $clubs->the_post(); ?>
					<a class="club-chip" href="<?php the_permalink(); ?>"><?php echo bec_club_badge( get_the_title() ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php the_title(); ?></span></a>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	bec_home_section( 'copa-do-brasil', 'Copa do Brasil', 4 );
	bec_home_section( 'mercado-da-bola', 'Mercado da Bola', 4 );

	$popular = function_exists( 'bec_popular_posts' ) ? bec_popular_posts( 5 ) : null;
	if ( $popular && $popular->have_posts() ) :
		?>
		<section class="section">
			<div class="section-title"><h2>Mais lidas</h2></div>
			<ol class="ranking">
				<?php while ( $popular->have_posts() ) : $popular->the_post(); ?>
					<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
				<?php endwhile; wp_reset_postdata(); ?>
			</ol>
		</section>
	<?php endif; ?>

	<?php bec_home_section( 'analises', 'Análises e opiniões', 4 ); ?>

	<?php if ( is_active_sidebar( 'bec-ads' ) ) : ?>
		<div class="ad-slot"><?php dynamic_sidebar( 'bec-ads' ); ?></div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
