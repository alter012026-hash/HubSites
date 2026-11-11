<?php
/**
 * Orquestração do fluxo: RSS → pautas → triagem → IA → fila de revisão.
 * Responsabilidade: decidir O QUE roda e QUANDO. Cada etapa em si mora no seu módulo
 * (sources.php, scoring.php, dossier.php + ai-writer.php, review.php).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Registro de atividade (aparece na Visão geral)
 * ---------------------------------------------------------------- */

/** @param string $type coleta|triagem|ia|revisao|publicacao|erro */
function tpop_activity( $type, $message, $link = '' ) {
	$log = get_option( 'tpop_activity', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift(
		$log,
		array(
			'time' => time(),
			'type' => $type,
			'msg'  => wp_strip_all_tags( $message ),
			'link' => $link ? esc_url_raw( $link ) : '',
		)
	);
	update_option( 'tpop_activity', array_slice( $log, 0, 40 ), false );
}

/* ------------------------------------------------------------------
 * Agendamento: uma rodada a cada 15 minutos
 * ---------------------------------------------------------------- */

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['tpop_15min'] = array( 'interval' => 900, 'display' => 'A cada 15 minutos' );
		return $s;
	}
);

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'tpop_pipeline_event' ) ) {
			wp_schedule_event( time() + 120, 'tpop_15min', 'tpop_pipeline_event' );
		}
		// Migração: a coleta horária antiga agora faz parte da rodada do fluxo.
		if ( wp_next_scheduled( 'tpop_fetch_sources_event' ) ) {
			wp_clear_scheduled_hook( 'tpop_fetch_sources_event' );
		}
	}
);

add_action(
	'tpop_pipeline_event',
	function () {
		if ( 'manual' === tpop_setting( 'auto_mode' ) ) {
			return;
		}
		tpop_pipeline_run( array( 'force_fetch' => false, 'generate' => 'auto' === tpop_setting( 'auto_mode' ) ) );
	}
);

/* ------------------------------------------------------------------
 * Etapa 2 — Preparação: ordena pautas para a geração e deixa a nota como prioridade
 * ---------------------------------------------------------------- */

/** Coloca pautas coletadas na fila de IA; alertas de qualidade não impedem a geração. */
function tpop_triage_run( $limit ) {
	$ids = get_posts(
		array(
			'post_type'      => 'tpop_pauta',
			'post_status'    => 'publish',
			'posts_per_page' => 40,
			'fields'         => 'ids',
			'meta_key'       => '_tpop_score',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'meta_query'     => array( array( 'key' => '_tpop_status', 'value' => array( 'nova_pauta', 'aguardando_confirmacao' ), 'compare' => 'IN' ) ),
		)
	);
	$approved = 0;
	foreach ( $ids as $id ) {
		if ( $approved >= $limit ) {
			break;
		}
		if ( (int) get_post_meta( $id, '_tpop_ia_tentativas', true ) >= 3 ) {
			continue;
		}
		update_post_meta( $id, '_tpop_status', 'aprovada_producao' );
		tpop_log( $id, 'Pauta encaminhada para geração de rascunho. Nota de prioridade editorial: ' . (int) get_post_meta( $id, '_tpop_score', true ) . '/100.' );
		++$approved;
	}
	if ( $approved ) {
		tpop_activity( 'triagem', $approved . ' pauta(s) pronta(s) para gerar rascunho. A revisão humana continua obrigatória.', admin_url( 'edit.php?post_type=tpop_pauta&tpop_status=aprovada_producao' ) );
	}
	return $approved;
}

/* ------------------------------------------------------------------
 * Etapa 3 — Redação: gera rascunhos para revisão humana
 * ---------------------------------------------------------------- */

function tpop_writer_run( $limit ) {
	$out = array( 'generated' => 0, 'errors' => 0, 'new_ids' => array(), 'messages' => array() );
	$day = (int) tpop_setting( 'auto_max_per_day' );
	if ( '' === tpop_ai_key() ) {
		$out['messages'][] = 'Chave de ' . tpop_ai_provider_label() . ' não configurada.';
		return $out;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'tpop_pauta',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, $limit ),
			'fields'         => 'ids',
			'meta_key'       => '_tpop_score',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_tpop_status', 'value' => 'aprovada_producao' ),
				array( 'key' => '_tpop_ia_tentativas', 'value' => 3, 'type' => 'NUMERIC', 'compare' => '<' ),
			),
		)
	);
	// Pautas que nunca tentaram ainda não têm a meta; busca-as também.
	if ( count( $ids ) < $limit ) {
		$more = get_posts(
			array(
				'post_type'      => 'tpop_pauta',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'post__not_in'   => $ids ? $ids : array( 0 ),
				'meta_key'       => '_tpop_score',
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => '_tpop_status', 'value' => 'aprovada_producao' ),
					array( 'key' => '_tpop_ia_tentativas', 'compare' => 'NOT EXISTS' ),
				),
			)
		);
		$ids = array_slice( array_merge( $ids, $more ), 0, $limit );
	}
	foreach ( $ids as $id ) {
		if ( tpop_ai_today_count() >= $day ) {
			$out['messages'][] = 'Teto diário de ' . $day . ' textos atingido.';
			break;
		}
		$res = tpop_generate_ai_article( $id );
		if ( is_wp_error( $res ) ) {
			++$out['errors'];
			$tries = (int) get_post_meta( $id, '_tpop_ia_tentativas', true ) + 1;
			update_post_meta( $id, '_tpop_ia_tentativas', $tries );
			if ( count( $out['messages'] ) < 3 ) {
				$out['messages'][] = get_the_title( $id ) . ': ' . $res->get_error_message();
			}
			tpop_log( $id, 'Falha ao escrever (tentativa ' . $tries . '): ' . $res->get_error_message() );
			if ( $tries >= 3 && 'aprovada_producao' === get_post_meta( $id, '_tpop_status', true ) ) {
				update_post_meta( $id, '_tpop_status', 'aguardando_confirmacao' );
				tpop_activity( 'erro', 'Pauta “' . get_the_title( $id ) . '” falhou 3 vezes e voltou para a equipe.', get_edit_post_link( $id, 'raw' ) );
			}
			if ( 'tpop_no_api_key' === $res->get_error_code() ) {
				break;
			}
		} else {
			++$out['generated'];
			$out['new_ids'][] = $res;
		}
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Aviso para a equipe
 * ---------------------------------------------------------------- */

function tpop_notify_review( $new_ids ) {
	if ( ! $new_ids || '1' !== (string) tpop_setting( 'notify_on' ) ) {
		return;
	}
	$to = is_email( tpop_setting( 'notify_email' ) ) ? tpop_setting( 'notify_email' ) : get_option( 'admin_email' );
	if ( ! is_email( $to ) ) {
		return;
	}
	$lines = array();
	foreach ( $new_ids as $id ) {
		$lines[] = '• ' . get_the_title( $id );
	}
	$body  = count( $new_ids ) . " texto(s) novo(s) aguardam revisão humana:\n\n" . implode( "\n", $lines );
	$body .= "\n\nAbrir a fila de revisão:\n" . admin_url( 'admin.php?page=tpop-revisao' ) . "\n\nNada foi publicado. Confira os fatos antes de aprovar.";
	wp_mail( $to, '[' . get_bloginfo( 'name' ) . '] ' . count( $new_ids ) . ' texto(s) para revisar', $body );
}

/* ------------------------------------------------------------------
 * Uma rodada completa
 * ---------------------------------------------------------------- */

/**
 * @param array $o force_fetch (bool), generate (bool), limit (int)
 */
function tpop_pipeline_run( $o = array() ) {
	if ( get_transient( 'tpop_pipeline_lock' ) ) {
		return array( 'locked' => true );
	}
	set_transient( 'tpop_pipeline_lock', 1, 10 * MINUTE_IN_SECONDS );
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	$res = array( 'locked' => false, 'fetch' => array(), 'approved' => 0, 'generated' => 0, 'errors' => 0, 'messages' => array() );
	try {
		$res['fetch'] = tpop_fetch_all_sources( ! empty( $o['force_fetch'] ) );

		if ( ! empty( $o['generate'] ) ) {
			$limit           = isset( $o['limit'] ) ? (int) $o['limit'] : (int) tpop_setting( 'auto_max_per_run' );
			$res['approved'] = tpop_triage_run( $limit * 3 );
			$w               = tpop_writer_run( $limit );
			$res['generated'] = $w['generated'];
			$res['errors']    = $w['errors'];
			$res['messages']  = $w['messages'];
			tpop_notify_review( $w['new_ids'] );
			if ( $w['generated'] ) {
				update_option( 'tpop_last_generated', time(), false );
			}
		}
		update_option( 'tpop_last_pipeline', array( 'time' => time(), 'result' => $res ), false );
	} finally {
		delete_transient( 'tpop_pipeline_lock' );
	}
	return $res;
}

/* Botões da Visão geral */
add_action(
	'admin_post_tpop_run',
	function () {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'tpop_run' );
		$what = isset( $_POST['what'] ) ? sanitize_key( wp_unslash( $_POST['what'] ) ) : 'fetch';
		if ( 'full' === $what ) {
			$r = tpop_pipeline_run( array( 'force_fetch' => true, 'generate' => true ) );
			if ( ! empty( $r['locked'] ) ) {
				tpop_flash( 'Já existe uma rodada em andamento. Aguarde alguns minutos.', 'warning' );
			} else {
				tpop_flash(
					sprintf(
						'Rodada concluída: %d pauta(s) nova(s), %d aprovada(s) pelas regras, %d texto(s) enviado(s) para revisão, %d erro(s). %s',
						(int) $r['fetch']['novas'],
						(int) $r['approved'],
						(int) $r['generated'],
						(int) $r['errors'] + (int) $r['fetch']['erros'],
						esc_html( implode( ' | ', $r['messages'] ) )
					),
					( $r['errors'] || $r['fetch']['erros'] ) ? 'warning' : 'success'
				);
			}
		} else {
			$r = tpop_pipeline_run( array( 'force_fetch' => true, 'generate' => false ) );
			if ( ! empty( $r['locked'] ) ) {
				tpop_flash( 'Já existe uma rodada em andamento. Aguarde alguns minutos.', 'warning' );
			} else {
				tpop_flash(
					sprintf( 'Coleta concluída: %d fonte(s), %d pauta(s) nova(s), %d duplicada(s), %d erro(s).', $r['fetch']['fontes'], $r['fetch']['novas'], $r['fetch']['duplicadas'], $r['fetch']['erros'] ),
					$r['fetch']['erros'] ? 'warning' : 'success'
				);
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=tpop-painel' ) );
		exit;
	}
);
