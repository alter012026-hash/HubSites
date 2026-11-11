<?php
get_header();
$competitions = array( 'brasileirao' => 'Brasileirão', 'libertadores' => 'Libertadores', 'copa-do-brasil' => 'Copa do Brasil' );
$club_pages = get_posts(
	array(
		'post_type'      => 'clube',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	)
);
?>
<div class="wrap">
	<header class="page-head news-hub-head">
		<?php if ( function_exists( 'bec_breadcrumbs' ) ) { bec_breadcrumbs(); } ?>
		<p class="news-hub-kicker">Escolha o assunto</p>
		<h1>Notícias</h1>
		<div class="description">Acompanhe as últimas notícias, encontre a cobertura do seu time e navegue por campeonato, resultados e jogos.</div>
	</header>

	<nav class="news-hub" aria-label="Navegar pelas notícias">
		<section class="news-hub-group" aria-labelledby="news-hub-topics">
			<h2 id="news-hub-topics">Assuntos</h2>
			<div class="news-topic-list">
				<a class="news-topic is-current" href="<?php echo esc_url( get_category_link( get_queried_object_id() ) ); ?>" aria-current="page"><span class="news-topic-icon" aria-hidden="true">✦</span><span><strong>Últimas notícias</strong><small>As publicações mais recentes</small></span></a>
				<?php foreach ( $competitions as $slug => $label ) : $term = get_category_by_slug( $slug ); if ( ! $term ) { continue; } ?>
					<a class="news-topic" href="<?php echo esc_url( get_category_link( $term ) ); ?>"><span class="news-topic-icon" aria-hidden="true">🏆</span><span><strong><?php echo esc_html( $label ); ?></strong><small>Notícias do campeonato</small></span></a>
				<?php endforeach; ?>
				<?php foreach ( array( 'resultados' => array( 'Resultados', 'Placares e repercussão' ), 'jogos-na-tv' => array( 'Jogos na TV', 'Guia editorial de transmissões' ) ) as $slug => $topic ) : $term = get_category_by_slug( $slug ); if ( ! $term ) { continue; } ?>
					<a class="news-topic" href="<?php echo esc_url( get_category_link( $term ) ); ?>"><span class="news-topic-icon" aria-hidden="true"><?php echo 'resultados' === $slug ? '◉' : '▣'; ?></span><span><strong><?php echo esc_html( $topic[0] ); ?></strong><small><?php echo esc_html( $topic[1] ); ?></small></span></a>
				<?php endforeach; ?>
			</div>
		</section>

		<?php if ( $club_pages ) : ?>
			<section class="news-hub-group news-hub-clubs" aria-labelledby="news-hub-clubs-title">
				<div class="section-title"><h2 id="news-hub-clubs-title">Por time</h2><a href="<?php echo esc_url( home_url( '/clubes/' ) ); ?>">Todos os clubes</a></div>
				<div class="news-club-list">
					<?php foreach ( $club_pages as $club_post ) : ?>
						<a href="<?php echo esc_url( get_permalink( $club_post ) ); ?>"><?php echo bec_club_badge( get_the_title( $club_post ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( get_the_title( $club_post ) ); ?></span></a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	</nav>

	<section class="news-latest-list" aria-labelledby="news-latest-title">
		<div class="section-title"><h2 id="news-latest-title">Últimas notícias</h2></div>
		<?php if ( have_posts() ) : ?>
			<div class="grid grid-3">
				<?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/card' ); endwhile; ?>
			</div>
			<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '‹ Anteriores', 'next_text' => 'Próximas ›' ) ); ?>
		<?php else : ?>
			<p>Ainda não há notícias publicadas nesta seção.</p>
		<?php endif; ?>
	</section>
	<p class="news-tv-note">Os horários de transmissão em TV precisam ser confirmados pela redação e atualizados em cada guia. O provedor esportivo configurado hoje informa partidas e resultados, mas não os canais de transmissão.</p>
</div>
<?php get_footer(); ?>
