<?php get_header(); ?>
<div class="wrap">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'title-detail' ); ?>>
			<?php if ( function_exists( 'tpop_breadcrumbs' ) ) { tpop_breadcrumbs(); } ?>
			<div class="title-detail__grid">
				<div class="title-detail__poster">
					<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'large' ); else : ?><div class="thumb-placeholder" aria-hidden="true"></div><?php endif; ?>
				</div>
				<div class="title-detail__copy">
					<?php $type = get_post_meta( get_the_ID(), '_tpop_tipo', true ); ?>
					<span class="category"><?php echo 'serie' === $type ? 'SÉRIE' : 'FILME'; ?></span>
					<h1 class="post-title"><?php the_title(); ?></h1>
					<div class="title-facts">
						<?php $year = get_post_meta( get_the_ID(), '_tpop_ano', true ); if ( $year ) : ?><span><?php echo esc_html( $year ); ?></span><?php endif; ?>
						<?php $platforms = get_post_meta( get_the_ID(), '_tpop_plataformas', true ); if ( $platforms ) : ?><span>Onde assistir: <?php echo esc_html( $platforms ); ?></span><?php endif; ?>
					</div>
					<?php if ( has_excerpt() ) : ?><p class="post-lead"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
					<?php the_content(); ?>
					<?php $trailer = get_post_meta( get_the_ID(), '_tpop_trailer', true ); if ( $trailer ) : ?><a class="button button--accent" href="<?php echo esc_url( $trailer ); ?>" target="_blank" rel="noopener noreferrer">Assistir ao trailer oficial</a><?php endif; ?>
				</div>
			</div>
			<?php $related = tpop_related_posts( 4 ); if ( $related->have_posts() ) : ?>
				<section class="related section"><div class="section-title"><h2>Leia também</h2></div><div class="grid grid-3"><?php while ( $related->have_posts() ) : $related->the_post(); get_template_part( 'template-parts/card' ); endwhile; wp_reset_postdata(); ?></div></section>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>
</div>
<?php get_footer(); ?>
