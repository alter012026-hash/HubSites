<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * API para automações externas (Python, Node, n8n...).
 * Autenticação: token definido em wp-config.php (TPOP_REST_TOKEN), enviado em
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
				'callback'            => 'tpop_rest_create_pauta',
				'permission_callback' => 'tpop_rest_permission',
			)
		);
		register_rest_route(
			'bec/v1',
			'/rascunhos',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'tpop_rest_create_draft',
				'permission_callback' => 'tpop_rest_permission',
			)
		);
	}
);

function tpop_rest_permission( $request ) {
	if ( ! defined( 'TPOP_REST_TOKEN' ) || strlen( (string) TPOP_REST_TOKEN ) < 32 ) {
		return new WP_Error( 'tpop_sem_token', 'Defina TPOP_REST_TOKEN (mínimo de 32 caracteres) no wp-config.php.', array( 'status' => 503 ) );
	}
	$given = '';
	$auth  = (string) $request->get_header( 'authorization' );
	if ( preg_match( '/^Bearer\s+(.+)$/i', $auth, $m ) ) {
		$given = trim( $m[1] );
	}
	if ( '' === $given ) {
		$given = trim( (string) $request->get_header( 'x_tpop_token' ) );
	}
	if ( '' === $given || ! hash_equals( (string) TPOP_REST_TOKEN, $given ) ) {
		return new WP_Error( 'tpop_nao_autorizado', 'Token inválido.', array( 'status' => 401 ) );
	}
	return true;
}

function tpop_rest_author_id() {
	if ( defined( 'TPOP_REST_AUTHOR_ID' ) && get_userdata( (int) TPOP_REST_AUTHOR_ID ) ) {
		return (int) TPOP_REST_AUTHOR_ID;
	}
	$users = get_users( array( 'role__in' => array( 'editor', 'administrator' ), 'number' => 1, 'fields' => 'ID' ) );
	return $users ? (int) $users[0] : 1;
}

/** Normaliza o parâmetro "fontes": lista de {nome,url} ou de URLs. */
function tpop_rest_sources( $raw ) {
	$lines = array();
	foreach ( (array) $raw as $s ) {
		if ( is_array( $s ) && ! empty( $s['url'] ) ) {
			$lines[] = ( isset( $s['nome'] ) ? $s['nome'] : '' ) . ' | ' . $s['url'];
		} elseif ( is_string( $s ) ) {
			$lines[] = $s;
		}
	}
	return tpop_parse_sources( implode( "\n", $lines ) );
}

function tpop_rest_create_pauta( $request ) {
	$p       = $request->get_json_params();
	$sources = tpop_rest_sources( isset( $p['fontes'] ) ? $p['fontes'] : array() );
	if ( empty( $p['title'] ) ) {
		return new WP_Error( 'tpop_titulo', 'Informe "title".', array( 'status' => 400 ) );
	}
	$id = tpop_create_pauta(
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
	return new WP_REST_Response( array( 'id' => $id, 'score' => (int) get_post_meta( $id, '_tpop_score', true ), 'status' => get_post_meta( $id, '_tpop_status', true ) ), 201 );
}

function tpop_rest_create_draft( $request ) {
	$p = $request->get_json_params();
	if ( empty( $p['title'] ) || empty( $p['content'] ) ) {
		return new WP_Error( 'tpop_dados', 'Informe "title" e "content".', array( 'status' => 400 ) );
	}
	$sources = tpop_rest_sources( isset( $p['fontes'] ) ? $p['fontes'] : array() );
	if ( ! $sources ) {
		return new WP_Error( 'tpop_sem_fonte', 'Rascunhos exigem ao menos uma fonte em "fontes".', array( 'status' => 400 ) );
	}

	$source_id = isset( $p['source_id'] ) ? sanitize_text_field( $p['source_id'] ) : '';
	if ( $source_id ) {
		$dup = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_tpop_source_id',
				'meta_value'     => $source_id,
			)
		);
		if ( $dup ) {
			return new WP_REST_Response( array( 'code' => 'tpop_duplicate', 'message' => 'Rascunho já existe.', 'data' => array( 'post_id' => (int) $dup[0] ) ), 409 );
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
			'post_author'   => tpop_rest_author_id(),
			'post_category' => $cats,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, '_tpop_origem', 'api' );
	update_post_meta( $id, '_tpop_fontes_texto', tpop_sources_to_text( $sources ) );
	update_post_meta( $id, '_tpop_verificado_em', current_time( 'Y-m-d' ) );
	update_post_meta( $id, '_tpop_revisado', '0' );
	if ( $source_id ) {
		update_post_meta( $id, '_tpop_source_id', $source_id );
	}
	return new WP_REST_Response( array( 'id' => $id, 'status' => 'draft', 'link' => get_edit_post_link( $id, 'raw' ) ), 201 );
}
