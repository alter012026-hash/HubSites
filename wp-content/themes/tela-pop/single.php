<?php get_header(); ?>
<div class="wrap">
	<div class="progress" aria-hidden="true"><i></i></div>
	<?php while ( have_posts() ) : the_post(); ?>
		<div class="layout article-head">
			<article <?php post_class( 'content' ); ?>>
				<?php if ( function_exists( 'tpop_breadcrumbs' ) ) { tpop_breadcrumbs(); } ?>
				<?php tpop_the_category_label(); ?>
				<h1 class="post-title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?><p class="post-lead"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
				<?php tpop_the_meta(); ?>

				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="post-image">
						<?php the_post_thumbnail( 'tpop-hero' ); ?>
						<?php $caption = get_the_post_thumbnail_caption(); ?>
						<?php if ( $caption ) : ?><figcaption><?php echo wp_kses_post( $caption ); ?></figcaption><?php endif; ?>
					</figure>
				<?php endif; ?>

				<?php $share = tpop_share_links(); ?>
				<div class="share" data-share data-url="<?php echo esc_url( get_permalink() ); ?>">
					<span class="share-label">Compartilhe</span>
					<a class="share-wa" href="<?php echo esc_url( $share['wa'] ); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
					<a href="<?php echo esc_url( $share['fb'] ); ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
					<a href="<?php echo esc_url( $share['x'] ); ?>" target="_blank" rel="noopener noreferrer">X</a>
					<button type="button" data-copy>Copiar link</button>
				</div>

				<div class="post-body"><?php the_content(); wp_link_pages(); ?></div>

				<?php if ( function_exists( 'tpop_render_sources' ) ) { tpop_render_sources(); } ?>

				<?php get_template_part( 'template-parts/newsletter', null, array( 'variant' => 'box', 'source' => 'artigo' ) ); ?>

				<?php if ( is_active_sidebar( 'tpop-ads' ) ) : ?>
					<div class="ad-slot"><?php dynamic_sidebar( 'tpop-ads' ); ?></div>
				<?php endif; ?>

				<?php the_tags( '<p class="tags">Tags: ', ' ', '</p>' ); ?>

				<?php
				$related = tpop_related_posts( 4 );
				if ( $related->have_posts() ) :
					?>
					<section class="related">
						<div class="section-title"><h2>Leia também</h2></div>
						<div class="grid grid-2">
							<?php while ( $related->have_posts() ) : $related->the_post(); get_template_part( 'template-parts/card' ); endwhile; wp_reset_postdata(); ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
			</article>

			<aside aria-label="Barra lateral">
				<?php
				if ( is_active_sidebar( 'tpop-sidebar' ) ) {
					dynamic_sidebar( 'tpop-sidebar' );
				} elseif ( function_exists( 'tpop_popular_posts' ) ) {
					$pop = tpop_popular_posts( 5 );
					if ( $pop->have_posts() ) {
						echo '<div class="section-title"><h2>Mais lidas</h2></div><ol class="ranking">';
						while ( $pop->have_posts() ) {
							$pop->the_post();
							echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
						}
						echo '</ol>';
						wp_reset_postdata();
					}
				}
				?>
			</aside>
		</div>
	<?php endwhile; ?>
</div>
<?php get_footer(); ?>
