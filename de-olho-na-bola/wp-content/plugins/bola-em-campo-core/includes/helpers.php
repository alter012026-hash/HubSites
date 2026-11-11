<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Opções
 * ---------------------------------------------------------------- */

function bec_opt( $key, $default = '' ) {
	$opts = get_option( 'bec_options', array() );
	return ( is_array( $opts ) && isset( $opts[ $key ] ) && $opts[ $key ] !== '' ) ? $opts[ $key ] : $default;
}

/* ------------------------------------------------------------------
 * Status editoriais (seção 7.3 do documento técnico)
 * ---------------------------------------------------------------- */

function bec_statuses() {
	return array(
		'nova_pauta'             => 'NOVA PAUTA',
		'em_analise'             => 'EM ANÁLISE',
		'aprovada_producao'      => 'PRONTA PARA IA',
		'rascunho_gerado'        => 'RASCUNHO GERADO',
		'em_revisao'             => 'EM REVISÃO',
		'aprovada'               => 'APROVADA',
		'publicada'              => 'PUBLICADA',
		'rejeitada'              => 'REJEITADA',
		'aguardando_confirmacao' => 'REVISAR DADOS / TENTAR NOVAMENTE',
	);
}

function bec_status_label( $key ) {
	$all = bec_statuses();
	return isset( $all[ $key ] ) ? $all[ $key ] : '—';
}

/** Modelos editoriais (seção 8). */
function bec_modelos() {
	return array(
		'noticia'   => 'Notícia',
		'mercado'   => 'Mercado da Bola',
		'resultado' => 'Resultado / partida',
		'analise'   => 'Análise',
	);
}

/* ------------------------------------------------------------------
 * Clubes e competições monitorados
 * ---------------------------------------------------------------- */

function bec_clubs() {
	return array(
		'flamengo'    => array( 'name' => 'Flamengo', 'aliases' => array( 'flamengo', 'mengao' ) ),
		'corinthians' => array( 'name' => 'Corinthians', 'aliases' => array( 'corinthians', 'timao' ) ),
		'palmeiras'   => array( 'name' => 'Palmeiras', 'aliases' => array( 'palmeiras', 'verdao' ) ),
		'sao-paulo'   => array( 'name' => 'São Paulo', 'aliases' => array( 'sao paulo', 'tricolor paulista' ) ),
		'santos'      => array( 'name' => 'Santos', 'aliases' => array( 'santos' ) ),
		'gremio'      => array( 'name' => 'Grêmio', 'aliases' => array( 'gremio' ) ),
		'internacional' => array( 'name' => 'Internacional', 'aliases' => array( 'internacional', 'colorado' ) ),
		'atletico-mg' => array( 'name' => 'Atlético-MG', 'aliases' => array( 'atletico mg', 'atletico mineiro', 'galo' ) ),
		'cruzeiro'    => array( 'name' => 'Cruzeiro', 'aliases' => array( 'cruzeiro' ) ),
		'botafogo'    => array( 'name' => 'Botafogo', 'aliases' => array( 'botafogo' ) ),
		'fluminense'  => array( 'name' => 'Fluminense', 'aliases' => array( 'fluminense' ) ),
		'vasco'       => array( 'name' => 'Vasco', 'aliases' => array( 'vasco', 'vasco da gama' ) ),
	);
}

function bec_competition_keywords() {
	return array(
		'brasileirao', 'campeonato brasileiro', 'libertadores', 'copa do brasil',
		'sul americana', 'conmebol', 'cbf', 'selecao brasileira', 'estadual', 'fifa',
	);
}

/* ------------------------------------------------------------------
 * Texto
 * ---------------------------------------------------------------- */

/** Minúsculas, sem acentos, só letras e números separados por espaço único. */
function bec_normalize( $text ) {
	$text = remove_accents( wp_strip_all_tags( (string) $text ) );
	$text = strtolower( $text );
	$text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
	return trim( $text );
}

function bec_tokens( $text ) {
	$stop   = array( 'de', 'da', 'do', 'das', 'dos', 'para', 'com', 'que', 'uma', 'por', 'em', 'no', 'na', 'os', 'as', 'um', 'ao', 'se' );
	$tokens = array();
	foreach ( explode( ' ', bec_normalize( $text ) ) as $word ) {
		if ( strlen( $word ) > 2 && ! in_array( $word, $stop, true ) ) {
			$tokens[ $word ] = true;
		}
	}
	return array_keys( $tokens );
}

/** Similaridade de Jaccard entre dois títulos (0 a 1). */
function bec_similarity( $a, $b ) {
	$ta = bec_tokens( $a );
	$tb = bec_tokens( $b );
	if ( empty( $ta ) || empty( $tb ) ) {
		return 0;
	}
	$inter = count( array_intersect( $ta, $tb ) );
	$union = count( array_unique( array_merge( $ta, $tb ) ) );
	return $union ? $inter / $union : 0;
}

function bec_detect_club( $text ) {
	$haystack = ' ' . bec_normalize( $text ) . ' ';
	foreach ( bec_clubs() as $slug => $club ) {
		foreach ( $club['aliases'] as $alias ) {
			if ( false !== strpos( $haystack, ' ' . bec_normalize( $alias ) . ' ' ) ) {
				return $slug;
			}
		}
	}
	return '';
}

function bec_detect_competition( $text ) {
	$haystack = ' ' . bec_normalize( $text ) . ' ';
	foreach ( bec_competition_keywords() as $kw ) {
		if ( false !== strpos( $haystack, ' ' . $kw . ' ' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Converte um texto "Nome | URL" (uma fonte por linha) em lista.
 * Linhas só com URL são aceitas; o nome passa a ser o domínio.
 */
function bec_parse_sources( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$name = '';
		$url  = $line;
		if ( false !== strpos( $line, '|' ) ) {
			list( $name, $url ) = array_map( 'trim', explode( '|', $line, 2 ) );
		}
		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( ! $url ) {
			continue;
		}
		if ( '' === $name ) {
			$name = preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
		}
		$out[] = array( 'nome' => sanitize_text_field( $name ), 'url' => $url );
	}
	return $out;
}

function bec_sources_to_text( $sources ) {
	$lines = array();
	foreach ( (array) $sources as $s ) {
		if ( ! empty( $s['url'] ) ) {
			$lines[] = ( ! empty( $s['nome'] ) ? $s['nome'] : '' ) . ' | ' . $s['url'];
		}
	}
	return implode( "\n", $lines );
}

/* ------------------------------------------------------------------
 * Histórico de alterações (seção 7.4)
 * ---------------------------------------------------------------- */

function bec_log( $post_id, $message ) {
	$user = wp_get_current_user();
	add_post_meta(
		$post_id,
		'_bec_historico',
		array(
			'time'    => current_time( 'mysql' ),
			'user'    => $user && $user->exists() ? $user->display_name : 'sistema',
			'message' => sanitize_text_field( $message ),
		)
	);
}

function bec_get_log( $post_id, $limit = 20 ) {
	$rows = get_post_meta( $post_id, '_bec_historico' );
	if ( ! is_array( $rows ) ) {
		return array();
	}
	return array_slice( array_reverse( $rows ), 0, $limit );
}

/* ------------------------------------------------------------------
 * Avisos do admin que sobrevivem a redirecionamentos
 * ---------------------------------------------------------------- */

function bec_flash( $message, $type = 'warning' ) {
	set_transient( 'bec_flash_' . get_current_user_id(), array( $type, $message ), 60 );
}

add_action(
	'admin_notices',
	function () {
		$key  = 'bec_flash_' . get_current_user_id();
		$data = get_transient( $key );
		if ( $data ) {
			delete_transient( $key );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $data[0] ), wp_kses_post( $data[1] ) );
		}
	}
);

/* ------------------------------------------------------------------
 * Contador simples de leituras (artigos populares)
 * ---------------------------------------------------------------- */

add_action(
	'template_redirect',
	function () {
		if ( ! is_singular( 'post' ) || is_user_logged_in() || is_preview() ) {
			return;
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|monitor/', $ua ) ) {
			return;
		}
		$id    = get_queried_object_id();
		$views = (int) get_post_meta( $id, '_bec_views', true );
		update_post_meta( $id, '_bec_views', $views + 1 );
	}
);

/** Artigos mais lidos dos últimos 30 dias; se ainda não há leituras, os mais recentes. */
function bec_popular_posts( $count = 5 ) {
	$q = new WP_Query(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => $count,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_key'            => '_bec_views',
			'orderby'             => 'meta_value_num',
			'order'               => 'DESC',
			'date_query'          => array( array( 'after' => '30 days ago' ) ),
		)
	);
	if ( ! $q->have_posts() ) {
		$q = new WP_Query(
			array(
				'post_type'           => 'post',
				'posts_per_page'      => $count,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
	}
	return $q;
}
