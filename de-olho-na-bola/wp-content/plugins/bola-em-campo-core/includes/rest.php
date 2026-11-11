<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * API para automações externas (Python, Node, n8n...).
 * Autenticação: token definido em wp-config.php (BEC_REST_TOKEN), enviado em
 * "Authorization: Bearer <token>" ou "X-BEC-Token: <token>".
 * A API só cria PAUTAS e RASCUNHOS. Ela nunca publica.
 */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'bec/v1',
			'/pautas',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'bec_rest_create_pauta',
				'permission_callback' => 'bec_rest_permission',
			)
		);
		register_rest_route(
			'bec/v1',
			'/rascunhos',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'bec_rest_create_draft',
				'permission_callback' => 'bec_rest_permission',
			)
		);
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'bec/v1',
			'/connection',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'bec_rest_connection_info',
				'permission_callback' => 'bec_rest_connection_permission',
			)
		);
		register_rest_route(
			'bec/v1',
			'/central-drafts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'bec_rest_create_central_draft',
				'permission_callback' => 'bec_rest_create_draft_permission',
			)
		);
	}
);

function bec_rest_connection_permission() {
	return current_user_can( 'read' )
		? true
		: new WP_Error( 'bec_nao_autorizado', 'Autenticação WordPress necessária.', array( 'status' => 401 ) );
}

function bec_rest_create_draft_permission() {
	return current_user_can( 'edit_posts' )
		? true
		: new WP_Error( 'bec_sem_permissao', 'O usuário precisa ter permissão para criar rascunhos.', array( 'status' => 403 ) );
}

function bec_rest_connection_info() {
	return new WP_REST_Response(
		array(
			'connector_version' => '1',
			'wp_version'        => get_bloginfo( 'version' ),
			'capabilities'      => array(
				'create_posts'      => current_user_can( 'edit_posts' ),
				'upload_files'      => current_user_can( 'upload_files' ),
				'assign_categories' => current_user_can( 'manage_categories' ),
			),
		),
		200
	);
}

function bec_rest_central_draft_response( $post, $status = 200 ) {
	return new WP_REST_Response(
		array(
			'id'     => (int) $post->ID,
			'link'   => get_permalink( $post->ID ),
			'status' => get_post_status( $post->ID ),
		),
		$status
	);
}

function bec_rest_central_find_draft( $key_hash ) {
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_bec_central_article_key',
			'meta_value'     => $key_hash,
		)
	);
	if ( ! $ids ) {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'name'           => 'bec-central-' . $key_hash,
			)
		);
	}
	return $ids ? get_post( (int) $ids[0] ) : null;
}

function bec_rest_claim_central_draft( $option_name, $key_hash ) {
	$claim = array(
		'state'      => 'processing',
		'started_at' => time(),
		'owner'      => wp_generate_uuid4(),
	);
	if ( add_option( $option_name, $claim, '', false ) ) {
		return $claim;
	}

	$existing = bec_rest_central_find_draft( $key_hash );
	if ( $existing ) {
		update_option( $option_name, array( 'state' => 'complete', 'post_id' => (int) $existing->ID ), false );
		return false;
	}

	$current = get_option( $option_name );
	$stale   = is_array( $current )
		&& ( 'complete' === ( isset( $current['state'] ) ? $current['state'] : '' )
			|| ( ! empty( $current['started_at'] ) && time() - (int) $current['started_at'] >= 600 ) );
	if ( ! $stale ) {
		return null;
	}

	global $wpdb;
	$updated = $wpdb->update(
		$wpdb->options,
		array( 'option_value' => maybe_serialize( $claim ) ),
		array(
			'option_name'  => $option_name,
			'option_value' => maybe_serialize( $current ),
		),
		array( '%s' ),
		array( '%s', '%s' )
	);
	return 1 === $updated ? $claim : null;
}

function bec_rest_create_central_draft( $request ) {
	$payload = $request->get_json_params();
	$key     = isset( $payload['idempotency_key'] ) ? trim( (string) $payload['idempotency_key'] ) : '';
	$title   = isset( $payload['title'] ) ? sanitize_text_field( $payload['title'] ) : '';
	$content = isset( $payload['content'] ) ? wp_kses_post( $payload['content'] ) : '';

	if ( '' === $key || strlen( $key ) > 128 || ! preg_match( '/^[A-Za-z0-9._:-]+$/', $key ) ) {
		return new WP_Error( 'bec_idempotency', 'Informe uma chave de idempotência válida.', array( 'status' => 400 ) );
	}
	if ( '' === $title || '' === trim( wp_strip_all_tags( $content ) ) ) {
		return new WP_Error( 'bec_dados', 'Informe título e conteúdo para o rascunho.', array( 'status' => 400 ) );
	}

	$key_hash    = hash( 'sha256', get_current_blog_id() . ':' . $key );
	$option_name = 'bec_central_draft_' . $key_hash;
	$existing    = bec_rest_central_find_draft( $key_hash );
	if ( $existing ) {
		update_option( $option_name, array( 'state' => 'complete', 'post_id' => (int) $existing->ID ), false );
		return bec_rest_central_draft_response( $existing );
	}

	$claim = bec_rest_claim_central_draft( $option_name, $key_hash );
	if ( false === $claim ) {
		$existing = bec_rest_central_find_draft( $key_hash );
		return $existing
			? bec_rest_central_draft_response( $existing )
			: new WP_Error( 'bec_duplicate', 'A solicitação já foi processada.', array( 'status' => 409 ) );
	}
	if ( ! is_array( $claim ) ) {
		return new WP_Error( 'bec_em_andamento', 'Uma solicitação com essa chave ainda está em processamento.', array( 'status' => 409 ) );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_name'    => 'bec-central-' . $key_hash,
			'post_content' => $content,
			'post_author'  => get_current_user_id(),
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		$current = get_option( $option_name );
		if ( is_array( $current ) && isset( $current['owner'] ) && hash_equals( $claim['owner'], $current['owner'] ) ) {
			delete_option( $option_name );
		}
		return new WP_Error( 'bec_insert', 'O WordPress não conseguiu criar o rascunho.', array( 'status' => 500 ) );
	}

	update_post_meta( $post_id, '_bec_central_article_key', $key_hash );
	update_option( $option_name, array( 'state' => 'complete', 'post_id' => (int) $post_id ), false );

	return bec_rest_central_draft_response( get_post( $post_id ), 201 );
}

function bec_rest_permission( $request ) {
	if ( ! defined( 'BEC_REST_TOKEN' ) || strlen( (string) BEC_REST_TOKEN ) < 32 ) {
		return new WP_Error( 'bec_sem_token', 'Defina BEC_REST_TOKEN (mínimo de 32 caracteres) no wp-config.php.', array( 'status' => 503 ) );
	}
	$given = '';
	$auth  = (string) $request->get_header( 'authorization' );
	if ( preg_match( '/^Bearer\s+(.+)$/i', $auth, $m ) ) {
		$given = trim( $m[1] );
	}
	if ( '' === $given ) {
		$given = trim( (string) $request->get_header( 'x_bec_token' ) );
	}
	if ( '' === $given || ! hash_equals( (string) BEC_REST_TOKEN, $given ) ) {
		return new WP_Error( 'bec_nao_autorizado', 'Token inválido.', array( 'status' => 401 ) );
	}
	return true;
}

function bec_rest_author_id() {
	if ( defined( 'BEC_REST_AUTHOR_ID' ) && get_userdata( (int) BEC_REST_AUTHOR_ID ) ) {
		return (int) BEC_REST_AUTHOR_ID;
	}
	$users = get_users( array( 'role__in' => array( 'editor', 'administrator' ), 'number' => 1, 'fields' => 'ID' ) );
	return $users ? (int) $users[0] : 1;
}

/** Normaliza o parâmetro "fontes": lista de {nome,url} ou de URLs. */
function bec_rest_sources( $raw ) {
	$lines = array();
	foreach ( (array) $raw as $s ) {
		if ( is_array( $s ) && ! empty( $s['url'] ) ) {
			$lines[] = ( isset( $s['nome'] ) ? $s['nome'] : '' ) . ' | ' . $s['url'];
		} elseif ( is_string( $s ) ) {
			$lines[] = $s;
		}
	}
	return bec_parse_sources( implode( "\n", $lines ) );
}

function bec_rest_create_pauta( $request ) {
	$p       = $request->get_json_params();
	$sources = bec_rest_sources( isset( $p['fontes'] ) ? $p['fontes'] : array() );
	if ( empty( $p['title'] ) ) {
		return new WP_Error( 'bec_titulo', 'Informe "title".', array( 'status' => 400 ) );
	}
	$id = bec_create_pauta(
		array(
			'title'       => $p['title'],
			'url'         => $sources ? $sources[0]['url'] : '',
			'source_name' => $sources ? $sources[0]['nome'] : '',
			'keyword'     => isset( $p['keyword'] ) ? $p['keyword'] : '',
			'club'        => isset( $p['club'] ) ? $p['club'] : '',
			'modelo'      => isset( $p['modelo'] ) ? $p['modelo'] : '',
			'crescimento' => isset( $p['crescimento'] ) ? $p['crescimento'] : 50,
			'origem'      => 'api',
		)
	);
	if ( is_wp_error( $id ) ) {
		$data = $id->get_error_data();
		return new WP_REST_Response(
			array( 'code' => $id->get_error_code(), 'message' => $id->get_error_message(), 'data' => $data ),
			isset( $data['status'] ) ? (int) $data['status'] : 400
		);
	}
	return new WP_REST_Response( array( 'id' => $id, 'score' => (int) get_post_meta( $id, '_bec_score', true ), 'status' => get_post_meta( $id, '_bec_status', true ) ), 201 );
}

function bec_rest_create_draft( $request ) {
	$p = $request->get_json_params();
	if ( empty( $p['title'] ) || empty( $p['content'] ) ) {
		return new WP_Error( 'bec_dados', 'Informe "title" e "content".', array( 'status' => 400 ) );
	}
	$sources = bec_rest_sources( isset( $p['fontes'] ) ? $p['fontes'] : array() );
	if ( ! $sources ) {
		return new WP_Error( 'bec_sem_fonte', 'Rascunhos exigem ao menos uma fonte em "fontes".', array( 'status' => 400 ) );
	}

	$source_id = isset( $p['source_id'] ) ? sanitize_text_field( $p['source_id'] ) : '';
	if ( $source_id ) {
		$dup = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_bec_source_id',
				'meta_value'     => $source_id,
			)
		);
		if ( $dup ) {
			return new WP_REST_Response( array( 'code' => 'bec_duplicate', 'message' => 'Rascunho já existe.', 'data' => array( 'post_id' => (int) $dup[0] ) ), 409 );
		}
	}

	$cats = array();
	if ( ! empty( $p['category_id'] ) && term_exists( (int) $p['category_id'], 'category' ) ) {
		$cats[] = (int) $p['category_id'];
	}

	$id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'draft', // Sempre rascunho.
			'post_title'    => sanitize_text_field( $p['title'] ),
			'post_content'  => wp_kses_post( $p['content'] ),
			'post_excerpt'  => isset( $p['excerpt'] ) ? sanitize_textarea_field( $p['excerpt'] ) : '',
			'post_name'     => isset( $p['slug'] ) ? sanitize_title( $p['slug'] ) : '',
			'post_author'   => bec_rest_author_id(),
			'post_category' => $cats,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, '_bec_origem', 'api' );
	update_post_meta( $id, '_bec_fontes_texto', bec_sources_to_text( $sources ) );
	update_post_meta( $id, '_bec_verificado_em', current_time( 'Y-m-d' ) );
	update_post_meta( $id, '_bec_revisado', '0' );
	if ( $source_id ) {
		update_post_meta( $id, '_bec_source_id', $source_id );
	}
	return new WP_REST_Response( array( 'id' => $id, 'status' => 'draft', 'link' => get_edit_post_link( $id, 'raw' ) ), 201 );
}
