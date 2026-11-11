<?php get_header(); ?>
<div class="wrap">
	<?php while ( have_posts() ) : the_post(); ?>
		<header class="page-head">
			<?php if ( function_exists( 'bec_breadcrumbs' ) ) { bec_breadcrumbs(); } ?>
			<h1><?php the_title(); ?></h1>
		</header>
		<div class="layout">
			<div class="content">
				<div class="post-body"><?php the_content(); ?></div>

				<?php echo do_shortcode( '[bec_jogos_clube]' ); ?>

				<?php
				$slug  = get_post_field( 'post_name', get_the_ID() );
				$posts = new WP_Query( array( 'tag' => $slug, 'posts_per_page' => 6, 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
				?>
				<section class="section">
					<div class="section-title"><h2>Notícias recentes</h2>
						<?php $tag = get_term_by( 'slug', $slug, 'post_tag' ); if ( $tag ) : ?><a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>">Ver todas</a><?php endif; ?></div>
					<?php if ( $posts->have_posts() ) : ?>
						<div class="grid grid-2">
							<?php while ( $posts->have_posts() ) : $posts->the_post(); get_template_part( 'template-parts/card' ); endwhile; wp_reset_postdata(); ?>
						</div>
					<?php else : ?>
						<p>Ainda não há notícias com a tag “<?php echo esc_html( $slug ); ?>”. Ao publicar, marque o artigo com essa tag.</p>
					<?php endif; ?>
				</section>
			</div>
			<aside aria-label="Classificação">
				<div class="section-title"><h2>Classificação</h2></div>
				<?php echo do_shortcode( '[bec_classificacao]' ); ?>
			</aside>
		</div>
	<?php endwhile; ?>
</div>
<?php get_footer(); ?>
