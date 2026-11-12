<?php
get_header();
$hero_ids = array();
$hero_q = new WP_Query(
	array(
		'posts_per_page'      => 1,
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
		'tpop_home_recent_only' => false,
	)
);
?>
<div class="wrap home-wrap">
	<?php if ( $hero_q->have_posts() ) : ?>
		<section class="hero" aria-label="Destaque da redação">
			<?php
			while ( $hero_q->have_posts() ) :
				$hero_q->the_post();
				$hero_ids[] = get_the_ID();
				?>
				<article class="hero-main">
					<a href="<?php the_permalink(); ?>">
						<?php tpop_thumb( 'tpop-hero' ); ?>
						<div class="hero-body">
							<span class="category">EM DESTAQUE</span>
							<h1><?php the_title(); ?></h1>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24, '…' ) ); ?></p>
						</div>
					</a>
				</article>
			<?php endwhile; wp_reset_postdata(); ?>

			<?php
			$latest = new WP_Query(
				array(
					'posts_per_page'      => 5,
					'post__not_in'        => $hero_ids,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'tpop_home_recent_only' => false,
				)
			);
			?>
			<aside class="ultimas" aria-label="Mais recentes">
				<div class="section-title"><span class="eyebrow">PARA VER AGORA</span><h2>Últimas histórias</h2></div>
				<ol class="timeline">
					<?php while ( $latest->have_posts() ) : $latest->the_post(); ?>
						<li>
							<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( tpop_primary_category_name() ); ?> · <?php echo esc_html( get_the_date( 'd/m' ) ); ?></time>
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</li>
					<?php endwhile; wp_reset_postdata(); ?>
				</ol>
			</aside>
		</section>
	<?php else : ?>
		<section class="page-head welcome-panel">
			<span class="eyebrow">FILMES · SÉRIES · STREAMING</span>
			<h1>Histórias que continuam depois dos créditos.</h1>
			<p class="description">Críticas, estreias e guias para escolher o que assistir. Os textos feitos com IA chegam como rascunho e passam pela redação antes de serem publicados.</p>
			<a class="button button--accent" href="<?php echo esc_url( home_url( '/category/filmes/' ) ); ?>">Explorar a Estação</a>
		</section>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/newsletter', null, array( 'variant' => 'band', 'source' => 'home' ) ); ?>

	<?php
	tpop_home_section( 'filmes', 'Filmes', 6 );
	tpop_home_section( 'series', 'Séries', 6 );
	tpop_home_section( 'criticas', 'Críticas', 4 );
	tpop_home_section( 'lancamentos', 'Estreias e lançamentos', 4 );
	tpop_home_section( 'onde-assistir', 'Onde assistir', 4 );
	tpop_home_section( 'listas', 'Listas para escolher melhor', 4 );

	$catalog = new WP_Query( array( 'post_type' => 'tpop_titulo', 'posts_per_page' => 6, 'post_status' => 'publish', 'no_found_rows' => true ) );
	if ( $catalog->have_posts() ) :
		?>
		<section class="section catalog-section">
			<div class="section-title"><div><span class="eyebrow">GUIA DE TÍTULOS</span><h2>Catálogo</h2></div><a href="<?php echo esc_url( get_post_type_archive_link( 'tpop_titulo' ) ); ?>">Ver todos</a></div>
			<div class="grid grid-3">
				<?php while ( $catalog->have_posts() ) : $catalog->the_post(); get_template_part( 'template-parts/card' ); endwhile; wp_reset_postdata(); ?>
			</div>
		</section>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
