<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tpop_register_post_types() {
	// Pauta: fila editorial interna (nunca pública).
	register_post_type(
		'tpop_pauta',
		array(
			'labels'          => array(
				'name'          => 'Pautas',
				'singular_name' => 'Pauta',
				'add_new_item'  => 'Adicionar pauta',
				'edit_item'     => 'Editar pauta',
				'all_items'     => 'Fila de pautas',
				'search_items'  => 'Buscar pautas',
				'not_found'     => 'Nenhuma pauta encontrada.',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'tpop-painel',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	// Fonte: cadastro de feeds e origens (seção 6).
	register_post_type(
		'tpop_fonte',
		array(
			'labels'          => array(
				'name'          => 'Fontes',
				'singular_name' => 'Fonte',
				'add_new_item'  => 'Adicionar fonte',
				'edit_item'     => 'Editar fonte',
				'all_items'     => 'Fontes e feeds',
				'not_found'     => 'Nenhuma fonte cadastrada.',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'tpop-painel',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	// Catálogo editorial de filmes e séries.
	register_post_type(
		'tpop_titulo',
		array(
			'labels'       => array(
				'name'          => 'Catálogo',
				'singular_name' => 'Filme ou série',
				'add_new_item'  => 'Adicionar título ao catálogo',
				'edit_item'     => 'Editar título',
				'all_items'     => 'Filmes e séries',
			),
			'public'       => true,
			'has_archive'  => 'catalogo',
			'rewrite'      => array( 'slug' => 'titulo', 'with_front' => false ),
			'menu_icon'    => 'dashicons-format-video',
			'menu_position' => 6,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments' ),
			'taxonomies'   => array( 'category', 'post_tag' ),
			'show_in_rest' => true,
		)
	);
}
add_action( 'init', 'tpop_register_post_types' );

/* ------------------------------------------------------------------
	 * Ficha do título
 * ---------------------------------------------------------------- */

add_action(
	'add_meta_boxes_tpop_titulo',
	function () {
		add_meta_box( 'tpop_titulo_dados', 'Ficha do filme ou série', 'tpop_titulo_box', 'tpop_titulo', 'normal', 'high' );
	}
);

function tpop_titulo_box( $post ) {
	wp_nonce_field( 'tpop_titulo_save', 'tpop_titulo_nonce' );
	$type = get_post_meta( $post->ID, '_tpop_tipo', true );
	$year = get_post_meta( $post->ID, '_tpop_ano', true );
	$platforms = get_post_meta( $post->ID, '_tpop_plataformas', true );
	$trailer = get_post_meta( $post->ID, '_tpop_trailer', true );
	?>
	<p><label for="tpop_tipo"><strong>Tipo</strong></label><br><select id="tpop_tipo" name="tpop_tipo"><option value="filme" <?php selected( $type, 'filme' ); ?>>Filme</option><option value="serie" <?php selected( $type, 'serie' ); ?>>Série</option></select></p>
	<p><label for="tpop_ano"><strong>Ano de lançamento</strong></label><br><input type="number" min="1888" max="2100" id="tpop_ano" name="tpop_ano" value="<?php echo esc_attr( $year ); ?>"></p>
	<p><label for="tpop_plataformas"><strong>Onde assistir</strong></label><br><input type="text" class="widefat" id="tpop_plataformas" name="tpop_plataformas" value="<?php echo esc_attr( $platforms ); ?>" placeholder="Ex.: Netflix, Prime Video"></p>
	<p><label for="tpop_trailer"><strong>Trailer oficial</strong></label><br><input type="url" class="widefat" id="tpop_trailer" name="tpop_trailer" value="<?php echo esc_url( $trailer ); ?>" placeholder="https://"></p>
	<p class="description">Use a imagem destacada como pôster. Informe apenas plataformas e datas confirmadas.</p>
	<?php
}

add_action(
	'save_post_tpop_titulo',
	function ( $post_id ) {
		if ( ! isset( $_POST['tpop_titulo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tpop_titulo_nonce'] ) ), 'tpop_titulo_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_tpop_tipo', isset( $_POST['tpop_tipo'] ) && 'serie' === $_POST['tpop_tipo'] ? 'serie' : 'filme' );
		update_post_meta( $post_id, '_tpop_ano', isset( $_POST['tpop_ano'] ) ? absint( $_POST['tpop_ano'] ) : 0 );
		update_post_meta( $post_id, '_tpop_plataformas', isset( $_POST['tpop_plataformas'] ) ? sanitize_text_field( wp_unslash( $_POST['tpop_plataformas'] ) ) : '' );
		update_post_meta( $post_id, '_tpop_trailer', isset( $_POST['tpop_trailer'] ) ? esc_url_raw( wp_unslash( $_POST['tpop_trailer'] ) ) : '' );
	}
);

/* ------------------------------------------------------------------
 * Metabox da fonte
 * ---------------------------------------------------------------- */

function tpop_source_types() {
	return array(
		'estudio'   => 'Estúdio / distribuidora',
		'streaming' => 'Plataforma de streaming',
		'festival'  => 'Festival / organizador',
		'veiculo'   => 'Veículo de entretenimento',
		'outro'     => 'Outro',
	);
}

function tpop_source_permissions() {
	return array(
		'descoberta' => 'Somente descoberta de pautas (não reproduzir conteúdo)',
		'oficial'    => 'Comunicado oficial (uso factual com atribuição)',
		'licenciada' => 'Licenciada / autorizada por escrito',
	);
}

add_action(
	'add_meta_boxes_tpop_fonte',
	function () {
		add_meta_box( 'tpop_fonte_box', 'Dados da fonte', 'tpop_fonte_box', 'tpop_fonte', 'normal', 'high' );
	}
);

function tpop_fonte_box( $post ) {
	wp_nonce_field( 'tpop_fonte_save', 'tpop_fonte_nonce' );
	$g = function ( $k, $d = '' ) use ( $post ) {
		$v = get_post_meta( $post->ID, $k, true );
		return '' === $v ? $d : $v;
	};
	?>
	<table class="form-table">
		<tr><th><label for="tpop_site_url">Site da fonte</label></th>
			<td><input type="url" class="large-text" id="tpop_site_url" name="tpop_site_url" value="<?php echo esc_attr( $g( '_tpop_site_url' ) ); ?>" placeholder="https://"></td></tr>
		<tr><th><label for="tpop_feed_url">URL do feed RSS</label></th>
			<td><input type="url" class="large-text" id="tpop_feed_url" name="tpop_feed_url" value="<?php echo esc_attr( $g( '_tpop_feed_url' ) ); ?>" placeholder="https://.../feed">
			<p class="description">Só cadastre feeds cujo uso seja permitido. Deixe vazio para uma fonte consultada apenas manualmente.</p></td></tr>
		<tr><th><label for="tpop_tipo">Tipo</label></th>
			<td><select id="tpop_tipo" name="tpop_tipo"><?php foreach ( tpop_source_types() as $k => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $g( '_tpop_tipo', 'outro' ), $k, false ), esc_html( $l ) ); } ?></select></td></tr>
		<tr><th><label for="tpop_permissao">Permissão de uso</label></th>
			<td><select id="tpop_permissao" name="tpop_permissao"><?php foreach ( tpop_source_permissions() as $k => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $g( '_tpop_permissao', 'descoberta' ), $k, false ), esc_html( $l ) ); } ?></select></td></tr>
		<tr><th><label for="tpop_confiabilidade">Confiabilidade (0–100)</label></th>
			<td><input type="number" min="0" max="100" id="tpop_confiabilidade" name="tpop_confiabilidade" value="<?php echo esc_attr( $g( '_tpop_confiabilidade', 50 ) ); ?>">
			<p class="description">Pautas só são liberadas para produção automaticamente quando a fonte tem confiabilidade igual ou superior a 60.</p></td></tr>
		<tr><th><label for="tpop_intervalo">Consultar a cada (minutos)</label></th>
			<td><input type="number" min="15" max="1440" id="tpop_intervalo" name="tpop_intervalo" value="<?php echo esc_attr( $g( '_tpop_intervalo', 60 ) ); ?>"></td></tr>
		<tr><th>Ativa</th>
			<td><label><input type="checkbox" name="tpop_ativa" value="1" <?php checked( $g( '_tpop_ativa', '0' ), '1' ); ?>> Consultar este feed automaticamente</label></td></tr>
		<tr><th>Última consulta</th>
			<td><?php
			$last = $g( '_tpop_ultima_consulta' );
			echo $last ? esc_html( wp_date( 'd/m/Y H:i', (int) $last ) ) : 'Nunca';
			$err = $g( '_tpop_ultimo_erro' );
			if ( $err ) {
				echo ' — <span style="color:#b32d2e">' . esc_html( $err ) . '</span>';
			}
			?></td></tr>
	</table>
	<?php
}

add_action(
	'save_post_tpop_fonte',
	function ( $post_id ) {
		if ( ! isset( $_POST['tpop_fonte_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tpop_fonte_nonce'] ) ), 'tpop_fonte_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$types = array_keys( tpop_source_types() );
		$perms = array_keys( tpop_source_permissions() );
		$tipo  = isset( $_POST['tpop_tipo'] ) ? sanitize_key( wp_unslash( $_POST['tpop_tipo'] ) ) : 'outro';
		$perm  = isset( $_POST['tpop_permissao'] ) ? sanitize_key( wp_unslash( $_POST['tpop_permissao'] ) ) : 'descoberta';

		update_post_meta( $post_id, '_tpop_site_url', isset( $_POST['tpop_site_url'] ) ? esc_url_raw( wp_unslash( $_POST['tpop_site_url'] ) ) : '' );
		update_post_meta( $post_id, '_tpop_feed_url', isset( $_POST['tpop_feed_url'] ) ? esc_url_raw( wp_unslash( $_POST['tpop_feed_url'] ) ) : '' );
		update_post_meta( $post_id, '_tpop_tipo', in_array( $tipo, $types, true ) ? $tipo : 'outro' );
		update_post_meta( $post_id, '_tpop_permissao', in_array( $perm, $perms, true ) ? $perm : 'descoberta' );
		update_post_meta( $post_id, '_tpop_confiabilidade', isset( $_POST['tpop_confiabilidade'] ) ? max( 0, min( 100, absint( $_POST['tpop_confiabilidade'] ) ) ) : 50 );
		update_post_meta( $post_id, '_tpop_intervalo', isset( $_POST['tpop_intervalo'] ) ? max( 15, min( 1440, absint( $_POST['tpop_intervalo'] ) ) ) : 60 );
		update_post_meta( $post_id, '_tpop_ativa', isset( $_POST['tpop_ativa'] ) ? '1' : '0' );
	}
);
