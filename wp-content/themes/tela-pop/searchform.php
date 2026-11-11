<?php $tpop_sid = wp_unique_id( 'busca-' ); ?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $tpop_sid ); ?>">Buscar</label>
	<input type="search" id="<?php echo esc_attr( $tpop_sid ); ?>" name="s" placeholder="Buscar times, jogos, notícias…" value="<?php echo esc_attr( get_search_query() ); ?>">
	<button type="submit">Buscar</button>
</form>
