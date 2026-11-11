<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Detecção de pautas duplicadas
 * ---------------------------------------------------------------- */

function bec_find_duplicate_pauta( $title, $url = '' ) {
	if ( $url ) {
		$hit = get_posts(
			array(
				'post_type'      => 'bec_pauta',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_bec_url_hash',
				'meta_value'     => md5( $url ),
			)
		);
		if ( $hit ) {
			return (int) $hit[0];
		}
	}

	$recent = get_posts(
		array(
			'post_type'      => 'bec_pauta',
			'post_status'    => 'any',
			'posts_per_page' => 300,
			'date_query'     => array( array( 'after' => '7 days ago' ) ),
		)
	);
	foreach ( $recent as $p ) {
		if ( bec_similarity( $title, $p->post_title ) >= 0.6 ) {
			return (int) $p->ID;
		}
	}
	return 0;
}

/**
 * Registra uma segunda (terceira...) fonte para a mesma pauta.
 * Coberturas com títulos semelhantes entram no dossiê como referências relacionadas; a equipe
 * precisa confirmar se são independentes e se tratam realmente do mesmo fato.
 */
function bec_add_corroboration( $pauta_id, $a, $title, $url ) {
	$list = get_post_meta( $pauta_id, '_bec_corrob', true );
	$list = is_array( $list ) ? $list : array();
	foreach ( $list as $c ) {
		if ( $c['url'] === $url ) {
			return;
		}
	}
	if ( count( $list ) >= 5 ) {
		return;
	}
	$name   = ! empty( $a['source_name'] ) ? $a['source_name'] : preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$list[] = array(
		'fonte_id' => absint( isset( $a['fonte_id'] ) ? $a['fonte_id'] : 0 ),
		'nome'   => sanitize_text_field( $name ),
		'url'    => $url,
		'titulo' => sanitize_text_field( $title ),
		'resumo' => ! empty( $a['summary'] ) ? mb_substr( sanitize_textarea_field( $a['summary'] ), 0, 5000 ) : '',
	);
	update_post_meta( $pauta_id, '_bec_corrob', $list );

	$sources   = bec_parse_sources( get_post_meta( $pauta_id, '_bec_fontes_texto', true ) );
	$sources[] = array( 'nome' => $name, 'url' => $url );
	update_post_meta( $pauta_id, '_bec_fontes_texto', bec_sources_to_text( $sources ) );

	// Mais fontes, mais confiança: sobe o critério e recalcula a nota.
	$vals = array();
	foreach ( array_keys( bec_criteria() ) as $k ) {
		$vals[ $k ] = (int) get_post_meta( $pauta_id, '_bec_c_' . $k, true );
	}
	$vals['confiabilidade'] = min( 100, $vals['confiabilidade'] + 10 );
	update_post_meta( $pauta_id, '_bec_c_confiabilidade', $vals['confiabilidade'] );
	update_post_meta( $pauta_id, '_bec_score', bec_calc_score( $vals ) );
	bec_log( $pauta_id, 'Segunda fonte encontrada: ' . $name . '.' );
}

/** Impede tratar notícias RSS antigas ou sem data como atuais. Janela padrão: 72 horas. */
function bec_pauta_news_age_error( $pauta_id ) {
	$source_id = absint( get_post_meta( $pauta_id, '_bec_fonte_id', true ) );
	if ( ! $source_id ) {
		return '';
	}
	$timestamp = absint( get_post_meta( $pauta_id, '_bec_ts_origem', true ) );
	if ( ! $timestamp ) {
		return 'A fonte não informou uma data de publicação verificável. Não gere como notícia atual.';
	}
	if ( $timestamp > time() + 15 * MINUTE_IN_SECONDS ) {
		return 'A data informada pela fonte está no futuro. Confira o feed antes de gerar o rascunho.';
	}
	if ( $timestamp < time() - 3 * DAY_IN_SECONDS ) {
		return 'Notícia fora da janela de 72 horas (publicada em ' . wp_date( 'd/m/Y H:i', $timestamp ) . '). Não gere como notícia atual.';
	}
	return '';
}

/* ------------------------------------------------------------------
 * Criação de pauta (usada por RSS, REST e pelo admin)
 * Guarda título, link e um resumo curto do feed. Nunca copia a matéria inteira.
 * ---------------------------------------------------------------- */

function bec_create_pauta( $a ) {
	$title = trim( wp_strip_all_tags( isset( $a['title'] ) ? $a['title'] : '' ) );
	if ( '' === $title ) {
		return new WP_Error( 'bec_titulo', 'Título vazio.', array( 'status' => 400 ) );
	}
	$url = ! empty( $a['url'] ) ? esc_url_raw( $a['url'], array( 'http', 'https' ) ) : '';

	$dup = bec_find_duplicate_pauta( $title, $url );
	if ( $dup ) {
		// Possível cobertura relacionada: guarda a referência para conferência editorial.
		if ( $url && md5( $url ) !== get_post_meta( $dup, '_bec_url_hash', true ) ) {
			bec_add_corroboration( $dup, $a, $title, $url );
		}
		return new WP_Error( 'bec_duplicada', 'Pauta duplicada.', array( 'status' => 409, 'post_id' => $dup ) );
	}

	$id = wp_insert_post(
		array(
			'post_type'   => 'bec_pauta',
			'post_status' => 'publish',
			'post_title'  => $title,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	$fonte_id = ! empty( $a['fonte_id'] ) ? (int) $a['fonte_id'] : 0;
	$ts       = ! empty( $a['timestamp'] ) ? (int) $a['timestamp'] : time();
	$club     = ! empty( $a['club'] ) ? sanitize_key( $a['club'] ) : bec_detect_club( $title );
	$modelos  = bec_modelos();
	$modelo   = ! empty( $a['modelo'] ) && isset( $modelos[ $a['modelo'] ] ) ? $a['modelo'] : 'noticia';

	$relevance = $club ? 90 : ( bec_detect_competition( $title ) ? 65 : 40 );
	$conf      = $fonte_id ? (int) get_post_meta( $fonte_id, '_bec_confiabilidade', true ) : 40;

	$criteria = array(
		'crescimento'    => isset( $a['crescimento'] ) ? (int) $a['crescimento'] : 50,
		'relevancia'     => $relevance,
		'originalidade'  => 50,
		'busca'          => 50,
		'confiabilidade' => $conf,
		'velocidade'     => bec_speed_score( $ts ),
	);
	foreach ( $criteria as $k => $v ) {
		update_post_meta( $id, '_bec_c_' . $k, max( 0, min( 100, $v ) ) );
	}
	$score = bec_calc_score( $criteria );

	$sources = array();
	if ( $url ) {
		$sources[] = array(
			'nome' => ! empty( $a['source_name'] ) ? $a['source_name'] : preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) ),
			'url'  => $url,
		);
	}

	update_post_meta( $id, '_bec_score', $score );
	update_post_meta( $id, '_bec_status', 'nova_pauta' );
	update_post_meta( $id, '_bec_club', $club );
	update_post_meta( $id, '_bec_modelo', $modelo );
	update_post_meta( $id, '_bec_keyword', ! empty( $a['keyword'] ) ? sanitize_text_field( $a['keyword'] ) : '' );
	update_post_meta( $id, '_bec_fonte_id', $fonte_id );
	update_post_meta( $id, '_bec_fontes_texto', bec_sources_to_text( $sources ) );
	update_post_meta( $id, '_bec_url_hash', $url ? md5( $url ) : '' );
	update_post_meta( $id, '_bec_resumo_fonte', ! empty( $a['summary'] ) ? mb_substr( sanitize_textarea_field( $a['summary'] ), 0, 5000 ) : '' );
	update_post_meta( $id, '_bec_ts_origem', $ts );
	update_post_meta( $id, '_bec_verificado_em', current_time( 'Y-m-d' ) );
	bec_log( $id, 'Pauta criada (' . ( ! empty( $a['origem'] ) ? $a['origem'] : 'manual' ) . ').' );

	$warning = bec_pauta_source_warning( $id );
	if ( $warning ) {
		bec_log( $id, 'Alerta de qualidade da fonte: ' . $warning );
	}
	return $id;
}

/* ------------------------------------------------------------------
 * Leitura de feeds
 * ---------------------------------------------------------------- */

/**
 * Retorna o melhor texto disponível no item RSS.
 * O campo content:encoded só é usado para fontes com licença/autorização registrada.
 */
function bec_rss_item_summary( $item, $fonte_id ) {
	$description = trim( preg_replace( '/\\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $item->get_description(), ENT_QUOTES, 'UTF-8' ) ) ) );
	$permission  = get_post_meta( $fonte_id, '_bec_permissao', true );
	if ( 'licenciada' !== $permission || ! method_exists( $item, 'get_content' ) ) {
		return $description;
	}
	$content = trim( preg_replace( '/\\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $item->get_content(), ENT_QUOTES, 'UTF-8' ) ) ) );
	return mb_strlen( $content ) > mb_strlen( $description ) ? $content : $description;
}

function bec_fetch_source( $fonte_id, &$stats ) {
	$feed_url = get_post_meta( $fonte_id, '_bec_feed_url', true );
	if ( ! $feed_url || ! wp_http_validate_url( $feed_url ) ) {
		return;
	}

	if ( ! function_exists( 'fetch_feed' ) ) {
		require_once ABSPATH . WPINC . '/feed.php';
	}
	$lifetime = function () {
		return 600;
	};
	add_filter( 'wp_feed_cache_transient_lifetime', $lifetime );
	$feed = fetch_feed( $feed_url );
	remove_filter( 'wp_feed_cache_transient_lifetime', $lifetime );

	update_post_meta( $fonte_id, '_bec_ultima_consulta', time() );

	if ( is_wp_error( $feed ) ) {
		update_post_meta( $fonte_id, '_bec_ultimo_erro', wp_strip_all_tags( $feed->get_error_message() ) );
		$stats['erros']++;
		return;
	}
	delete_post_meta( $fonte_id, '_bec_ultimo_erro' );

	$source_name = get_the_title( $fonte_id );
	foreach ( $feed->get_items( 0, 15 ) as $item ) {
		$title = wp_strip_all_tags( html_entity_decode( (string) $item->get_title(), ENT_QUOTES, 'UTF-8' ) );
		$link  = esc_url_raw( (string) $item->get_link(), array( 'http', 'https' ) );
		$ts    = (int) $item->get_date( 'U' );
		if ( '' === $title || ! $link ) {
			continue;
		}
		if ( ! $ts ) {
			$stats['sem_data'] = isset( $stats['sem_data'] ) ? $stats['sem_data'] + 1 : 1;
			continue; // Sem data não podemos rotular a notícia como atual.
		}
		if ( $ts > time() + 15 * MINUTE_IN_SECONDS ) {
			$stats['datas_futuras'] = isset( $stats['datas_futuras'] ) ? $stats['datas_futuras'] + 1 : 1;
			continue;
		}
		if ( $ts < time() - 3 * DAY_IN_SECONDS ) {
			$stats['antigas'] = isset( $stats['antigas'] ) ? $stats['antigas'] + 1 : 1;
			continue; // Janela de atualidade: 72 horas.
		}
		$summary = bec_rss_item_summary( $item, $fonte_id );
		$res = bec_create_pauta(
			array(
				'summary'     => $summary,
				'title'       => $title,
				'url'         => $link,
				'fonte_id'    => $fonte_id,
				'source_name' => $source_name,
				'timestamp'   => $ts ? $ts : time(),
				'origem'      => 'feed: ' . $source_name,
			)
		);
		if ( is_wp_error( $res ) ) {
			if ( 'bec_duplicada' === $res->get_error_code() ) {
				$stats['duplicadas']++;
			}
			continue;
		}
		$stats['novas']++;
	}
}

/**
 * Consulta as fontes ativas respeitando o intervalo de cada uma.
 * @param bool $force Ignora o intervalo (botão "consultar agora").
 */
function bec_fetch_all_sources( $force = false ) {
	$stats = array( 'novas' => 0, 'duplicadas' => 0, 'erros' => 0, 'fontes' => 0, 'antigas' => 0, 'sem_data' => 0, 'datas_futuras' => 0 );

	$fontes = get_posts(
		array(
			'post_type'      => 'bec_fonte',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'meta_query'     => array(
				array( 'key' => '_bec_ativa', 'value' => '1' ),
				array( 'key' => '_bec_feed_url', 'value' => '', 'compare' => '!=' ),
			),
		)
	);

	foreach ( $fontes as $f ) {
		$interval = max( 15, (int) get_post_meta( $f->ID, '_bec_intervalo', true ) ) * MINUTE_IN_SECONDS;
		$last     = (int) get_post_meta( $f->ID, '_bec_ultima_consulta', true );
		if ( ! $force && $last && ( time() - $last ) < $interval ) {
			continue;
		}
		$stats['fontes']++;
		bec_fetch_source( $f->ID, $stats );
	}
	update_option( 'bec_last_fetch_stats', $stats + array( 'time' => time() ), false );
	if ( $stats['fontes'] ) {
		bec_activity( $stats['erros'] ? 'erro' : 'coleta', sprintf( 'Coleta RSS: %d fonte(s), %d pauta(s) nova(s), %d duplicada(s), %d antiga(s) ignorada(s), %d sem data ignorada(s), %d erro(s).', $stats['fontes'], $stats['novas'], $stats['duplicadas'], $stats['antigas'], $stats['sem_data'], $stats['erros'] ), admin_url( 'edit.php?post_type=bec_pauta' ) );
	}
	return $stats;
}

add_action( 'bec_fetch_sources_event', function () {
	bec_fetch_all_sources( false );
} );

add_action(
	'admin_post_bec_consultar_fontes',
	function () {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'bec_consultar_fontes' );
		$s = bec_fetch_all_sources( true );
		bec_flash(
				sprintf( 'Consulta concluída: %d fonte(s), %d pauta(s) nova(s), %d duplicada(s) ignorada(s), %d notícia(s) antiga(s) ignorada(s), %d sem data ignorada(s), %d com data futura ignorada(s), %d erro(s). Janela de atualidade: 72 horas.', $s['fontes'], $s['novas'], $s['duplicadas'], $s['antigas'], $s['sem_data'], $s['datas_futuras'], $s['erros'] ),
			$s['erros'] ? 'warning' : 'success'
		);
		wp_safe_redirect( admin_url( 'admin.php?page=bec-painel' ) );
		exit;
	}
);
