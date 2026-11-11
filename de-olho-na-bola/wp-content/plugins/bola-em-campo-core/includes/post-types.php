<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bec_register_post_types() {
	// Pauta: fila editorial interna (nunca pública).
	register_post_type(
		'bec_pauta',
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
			'show_in_menu'    => 'bec-painel',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	// Fonte: cadastro de feeds e origens (seção 6).
	register_post_type(
		'bec_fonte',
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
			'show_in_menu'    => 'bec-painel',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		)
	);

	// Clube: páginas públicas dos clubes.
	register_post_type(
		'clube',
		array(
			'labels'       => array(
				'name'          => 'Clubes',
				'singular_name' => 'Clube',
				'add_new_item'  => 'Adicionar clube',
				'edit_item'     => 'Editar clube',
				'all_items'     => 'Todos os clubes',
			),
			'public'       => true,
			'has_archive'  => 'clubes',
			'rewrite'      => array( 'slug' => 'clubes', 'with_front' => false ),
			'menu_icon'    => 'dashicons-shield',
			'menu_position' => 6,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest' => true,
		)
	);
}
add_action( 'init', 'bec_register_post_types' );

/* ------------------------------------------------------------------
 * Metabox do clube: ID do time na API-Football
 * ---------------------------------------------------------------- */

add_action(
	'add_meta_boxes_clube',
	function () {
		add_meta_box( 'bec_clube_api', 'Dados esportivos', 'bec_clube_api_box', 'clube', 'side' );
	}
);

function bec_clube_api_box( $post ) {
	wp_nonce_field( 'bec_clube_save', 'bec_clube_nonce' );
	$team = get_post_meta( $post->ID, '_bec_api_team_id', true );
	echo '<p><label for="bec_api_team_id">ID do time na API-Football</label>';
	echo '<input type="number" class="widefat" id="bec_api_team_id" name="bec_api_team_id" value="' . esc_attr( $team ) . '"></p>';
	echo '<p class="description">Opcional. Com o ID, a página do clube mostra próximos jogos e resultados recentes. As notícias do clube são os artigos com a tag <code>' . esc_html( $post->post_name ) . '</code>.</p>';
}

add_action(
	'save_post_clube',
	function ( $post_id ) {
		if ( ! isset( $_POST['bec_clube_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bec_clube_nonce'] ) ), 'bec_clube_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_bec_api_team_id', isset( $_POST['bec_api_team_id'] ) ? absint( $_POST['bec_api_team_id'] ) : 0 );
	}
);

/* ------------------------------------------------------------------
 * Metabox da fonte
 * ---------------------------------------------------------------- */

function bec_source_types() {
	return array(
		'clube'     => 'Comunicado de clube',
		'competicao' => 'Competição / entidade organizadora',
		'federacao' => 'Federação / confederação',
		'veiculo'   => 'Veículo jornalístico (feed autorizado)',
		'outro'     => 'Outro',
	);
}

function bec_source_permissions() {
	return array(
		'descoberta' => 'Somente descoberta de pautas (não reproduzir conteúdo)',
		'oficial'    => 'Comunicado oficial (uso factual com atribuição)',
		'licenciada' => 'Licenciada / autorizada por escrito',
	);
}

add_action(
	'add_meta_boxes_bec_fonte',
	function () {
		add_meta_box( 'bec_fonte_box', 'Dados da fonte', 'bec_fonte_box', 'bec_fonte', 'normal', 'high' );
	}
);

function bec_fonte_box( $post ) {
	wp_nonce_field( 'bec_fonte_save', 'bec_fonte_nonce' );
	$g = function ( $k, $d = '' ) use ( $post ) {
		$v = get_post_meta( $post->ID, $k, true );
		return '' === $v ? $d : $v;
	};
	?>
	<table class="form-table">
		<tr><th><label for="bec_site_url">Site da fonte</label></th>
			<td><input type="url" class="large-text" id="bec_site_url" name="bec_site_url" value="<?php echo esc_attr( $g( '_bec_site_url' ) ); ?>" placeholder="https://"></td></tr>
		<tr><th><label for="bec_feed_url">URL do feed RSS</label></th>
			<td><input type="url" class="large-text" id="bec_feed_url" name="bec_feed_url" value="<?php echo esc_attr( $g( '_bec_feed_url' ) ); ?>" placeholder="https://.../feed">
			<p class="description">Só cadastre feeds cujo uso seja permitido. Deixe vazio para uma fonte consultada apenas manualmente.</p></td></tr>
		<tr><th><label for="bec_tipo">Tipo</label></th>
			<td><select id="bec_tipo" name="bec_tipo"><?php foreach ( bec_source_types() as $k => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $g( '_bec_tipo', 'outro' ), $k, false ), esc_html( $l ) ); } ?></select></td></tr>
		<tr><th><label for="bec_permissao">Permissão de uso</label></th>
			<td><select id="bec_permissao" name="bec_permissao"><?php foreach ( bec_source_permissions() as $k => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $g( '_bec_permissao', 'descoberta' ), $k, false ), esc_html( $l ) ); } ?></select></td></tr>
		<tr><th><label for="bec_confiabilidade">Confiabilidade (0–100)</label></th>
			<td><input type="number" min="0" max="100" id="bec_confiabilidade" name="bec_confiabilidade" value="<?php echo esc_attr( $g( '_bec_confiabilidade', 50 ) ); ?>">
			<p class="description">Pautas só são liberadas para produção automaticamente quando a fonte tem confiabilidade igual ou superior a 60.</p></td></tr>
		<tr><th><label for="bec_intervalo">Consultar a cada (minutos)</label></th>
			<td><input type="number" min="15" max="1440" id="bec_intervalo" name="bec_intervalo" value="<?php echo esc_attr( $g( '_bec_intervalo', 60 ) ); ?>"></td></tr>
		<tr><th>Ativa</th>
			<td><label><input type="checkbox" name="bec_ativa" value="1" <?php checked( $g( '_bec_ativa', '0' ), '1' ); ?>> Consultar este feed automaticamente</label></td></tr>
		<tr><th>Última consulta</th>
			<td><?php
			$last = $g( '_bec_ultima_consulta' );
			echo $last ? esc_html( wp_date( 'd/m/Y H:i', (int) $last ) ) : 'Nunca';
			$err = $g( '_bec_ultimo_erro' );
			if ( $err ) {
				echo ' — <span style="color:#b32d2e">' . esc_html( $err ) . '</span>';
			}
			?></td></tr>
	</table>
	<?php
}

add_action(
	'save_post_bec_fonte',
	function ( $post_id ) {
		if ( ! isset( $_POST['bec_fonte_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bec_fonte_nonce'] ) ), 'bec_fonte_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$types = array_keys( bec_source_types() );
		$perms = array_keys( bec_source_permissions() );
		$tipo  = isset( $_POST['bec_tipo'] ) ? sanitize_key( wp_unslash( $_POST['bec_tipo'] ) ) : 'outro';
		$perm  = isset( $_POST['bec_permissao'] ) ? sanitize_key( wp_unslash( $_POST['bec_permissao'] ) ) : 'descoberta';

		update_post_meta( $post_id, '_bec_site_url', isset( $_POST['bec_site_url'] ) ? esc_url_raw( wp_unslash( $_POST['bec_site_url'] ) ) : '' );
		update_post_meta( $post_id, '_bec_feed_url', isset( $_POST['bec_feed_url'] ) ? esc_url_raw( wp_unslash( $_POST['bec_feed_url'] ) ) : '' );
		update_post_meta( $post_id, '_bec_tipo', in_array( $tipo, $types, true ) ? $tipo : 'outro' );
		update_post_meta( $post_id, '_bec_permissao', in_array( $perm, $perms, true ) ? $perm : 'descoberta' );
		update_post_meta( $post_id, '_bec_confiabilidade', isset( $_POST['bec_confiabilidade'] ) ? max( 0, min( 100, absint( $_POST['bec_confiabilidade'] ) ) ) : 50 );
		update_post_meta( $post_id, '_bec_intervalo', isset( $_POST['bec_intervalo'] ) ? max( 15, min( 1440, absint( $_POST['bec_intervalo'] ) ) ) : 60 );
		update_post_meta( $post_id, '_bec_ativa', isset( $_POST['bec_ativa'] ) ? '1' : '0' );
	}
);
