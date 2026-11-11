<article <?php post_class( 'card' ); ?>>
	<a href="<?php the_permalink(); ?>">
		<?php tpop_thumb(); ?>
		<?php tpop_the_category_label(); ?>
		<h3><?php the_title(); ?></h3>
	</a>
	<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20, '…' ) ); ?></p>
	<div class="meta"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'd/m/Y' ) ); ?></time></div>
</article>
