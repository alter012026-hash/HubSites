<?php
/**
 * Etapa 3 — Redação pela IA.
 * Responsabilidade: transformar o dossiê em um texto original, conferir o resultado contra o
 * dossiê e entregar um artigo AGUARDANDO REVISÃO. Nunca publica.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bec_openai_key() {
	return trim( defined( 'BEC_OPENAI_API_KEY' ) ? (string) BEC_OPENAI_API_KEY : (string) bec_opt( 'openai_api_key', '' ) );
}

function bec_groq_key() {
	return trim( defined( 'BEC_GROQ_API_KEY' ) ? (string) BEC_GROQ_API_KEY : (string) bec_opt( 'groq_api_key', '' ) );
}

function bec_ai_provider() {
	return 'groq' === bec_setting( 'ai_provider' ) ? 'groq' : 'openai';
}

function bec_ai_key() {
	return 'groq' === bec_ai_provider() ? bec_groq_key() : bec_openai_key();
}

function bec_ai_model() {
	return 'groq' === bec_ai_provider()
		? (string) bec_setting( 'groq_model' )
		: (string) bec_setting( 'openai_model' );
}

function bec_ai_provider_label() {
	return 'groq' === bec_ai_provider() ? 'GroqCloud' : 'OpenAI';
}

function bec_ai_endpoint() {
	return 'groq' === bec_ai_provider()
		? 'https://api.groq.com/openai/v1/chat/completions'
		: 'https://api.openai.com/v1/chat/completions';
}

/* ------------------------------------------------------------------
 * Conferência automática do texto contra o dossiê
 * Não bloqueia: aponta para a pessoa onde olhar primeiro.
 * ---------------------------------------------------------------- */

function bec_ai_audit( $content_html, $dossier_text ) {
	$plain   = html_entity_decode( wp_strip_all_tags( (string) $content_html ), ENT_QUOTES, 'UTF-8' );
	$dossier = html_entity_decode( (string) $dossier_text, ENT_QUOTES, 'UTF-8' );
	$d_norm  = ' ' . bec_normalize( $dossier ) . ' ';
	$out     = array( 'numbers' => array(), 'quotes' => array(), 'names' => array() );

	// Números (placares, minutos, valores) que não aparecem nos dados de origem.
	preg_match_all( '/\d+(?:[.,]\d+)?/', $dossier, $dn );
	$known = array_flip( $dn[0] );
	preg_match_all( '/\d+(?:[.,]\d+)?/', $plain, $pn );
	foreach ( array_unique( $pn[0] ) as $num ) {
		if ( ! isset( $known[ $num ] ) ) {
			$out['numbers'][] = $num;
		}
	}

	// Citações entre aspas que não estão nos dados de origem.
	if ( preg_match_all( '/[“"]([^”"]{8,})[”"]/u', $plain, $q ) ) {
		foreach ( $q[1] as $quote ) {
			if ( false === strpos( $d_norm, bec_normalize( $quote ) ) ) {
				$out['quotes'][] = mb_substr( $quote, 0, 90 );
			}
		}
	}

	// Nomes próprios que não aparecem nos dados de origem.
	$skip = array( 'brasil', 'brasileirao', 'copa', 'libertadores', 'serie', 'rodada', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro', 'fontes', 'segundo', 'nesta', 'neste', 'depois', 'antes', 'tambem', 'apesar', 'durante', 'contudo', 'porem', 'enquanto', 'quando', 'assim', 'ainda', 'agora', 'ontem', 'hoje', 'entre', 'sobre', 'mesmo', 'cada', 'esta', 'este', 'esse', 'essa', 'isso', 'outro', 'outra', 'todos', 'todas', 'mais', 'muito', 'para', 'como', 'onde', 'pelo', 'pela', 'com', 'sem', 'diante', 'alem', 'logo', 'entao', 'dessa', 'desse', 'nessa', 'nesse', 'foram', 'seria', 'estao', 'tinha', 'havia', 'fonte', 'confirmou', 'informou', 'disse', 'afirmou', 'clube', 'time', 'equipe', 'jogo', 'partida', 'torcida', 'tecnico', 'jogador' );
	if ( preg_match_all( '/\b[A-ZÁÉÍÓÚÂÊÔÃÕÇ][a-záéíóúâêôãõç]{3,}\b/u', $plain, $nm ) ) {
		foreach ( array_unique( $nm[0] ) as $name ) {
			$n = bec_normalize( $name );
			if ( '' !== $n && ! in_array( $n, $skip, true ) && false === strpos( $d_norm, ' ' . $n . ' ' ) ) {
				$out['names'][] = $name;
			}
		}
	}
	$out['numbers'] = array_slice( $out['numbers'], 0, 12 );
	$out['quotes']  = array_slice( $out['quotes'], 0, 6 );
	$out['names']   = array_slice( $out['names'], 0, 12 );
	return $out;
}

/** Junta alertas da IA e da conferência automática numa lista legível. */
function bec_ai_flags( $json, $audit ) {
	$flags = array();
	if ( ! empty( $json['unverified'] ) ) {
		foreach ( (array) $json['unverified'] as $u ) {
			if ( is_scalar( $u ) && '' !== trim( (string) $u ) ) {
				$flags[] = sanitize_text_field( (string) $u );
			}
		}
	}
	if ( $audit['quotes'] ) {
		$flags[] = 'Citações que não aparecem nos dados de origem (possível invenção): “' . implode( '”, “', $audit['quotes'] ) . '”.';
	}
	if ( $audit['numbers'] ) {
		$flags[] = 'Números que não aparecem nos dados de origem: ' . implode( ', ', $audit['numbers'] ) . '.';
	}
	if ( $audit['names'] ) {
		$flags[] = 'Nomes que não aparecem nos dados de origem: ' . implode( ', ', $audit['names'] ) . '.';
	}
	return $flags;
}

/* ------------------------------------------------------------------
 * Controle de custo: teto diário
 * ---------------------------------------------------------------- */

function bec_ai_today_count() {
	$d = get_option( 'bec_ai_daily', array() );
	return ( is_array( $d ) && isset( $d['date'] ) && wp_date( 'Y-m-d' ) === $d['date'] ) ? (int) $d['count'] : 0;
}

function bec_ai_today_bump() {
	update_option( 'bec_ai_daily', array( 'date' => wp_date( 'Y-m-d' ), 'count' => bec_ai_today_count() + 1 ), false );
}

/* ------------------------------------------------------------------
 * Geração
 * ---------------------------------------------------------------- */

/**
 * Gera o rascunho de uma pauta coletada.
 *
 * @return int|WP_Error ID do artigo criado.
 */
function bec_generate_ai_article( $pauta_id ) {
	$api_key = bec_ai_key();
	if ( '' === $api_key ) {
		return new WP_Error( 'bec_no_api_key', 'Configure a chave de ' . bec_ai_provider_label() . ' em De Olho na Bola → Configurações → Redação (IA).' );
	}
	if ( 'bec_pauta' !== get_post_type( $pauta_id ) ) {
		return new WP_Error( 'bec_invalid', 'Pauta inválida.' );
	}
	$age_error = bec_pauta_news_age_error( $pauta_id );
	if ( $age_error ) {
		return new WP_Error( 'bec_news_outdated', $age_error . ' Atualize a pauta com uma fonte recente ou trate-a como conteúdo histórico.' );
	}
	if ( get_post_meta( $pauta_id, '_bec_post_id', true ) ) {
		return new WP_Error( 'bec_exists', 'Esta pauta já possui um artigo vinculado.' );
	}
	$status = get_post_meta( $pauta_id, '_bec_status', true );
	if ( ! in_array( $status, array( 'nova_pauta', 'em_analise', 'aprovada_producao', 'aguardando_confirmacao' ), true ) ) {
		return new WP_Error( 'bec_status', 'Esta pauta não está disponível para gerar um rascunho. Confira se ela não foi rejeitada ou processada.' );
	}
	// 1) Dossiê com dados reais.
	$dossier = bec_build_dossier( $pauta_id );
	$source_warning = bec_pauta_source_warning( $pauta_id );

	$modelo   = get_post_meta( $pauta_id, '_bec_modelo', true );
	$club_key = get_post_meta( $pauta_id, '_bec_club', true );
	$clubs    = bec_clubs();
	$club     = isset( $clubs[ $club_key ] ) ? $clubs[ $club_key ]['name'] : '';
	$keyword  = get_post_meta( $pauta_id, '_bec_keyword', true );

	$system = 'Você é redator de um portal esportivo brasileiro. Escreve em português do Brasil, com clareza e sem sensacionalismo. '
		. 'Você só pode afirmar o que está escrito no DOSSIÊ. Tudo o que não estiver no dossiê não existe para você.';
	$style = trim( (string) bec_setting( 'ai_style' ) );

	$prompt  = "TAREFA: escreva um artigo jornalístico ORIGINAL sobre o fato descrito no dossiê.\n\n";
	$prompt .= "REGRAS INEGOCIÁVEIS\n";
	$prompt .= "1. Use SOMENTE informações do dossiê. Não invente nem complete de memória: placares, datas, horários, escalações, minutos, declarações, estatísticas, lesões, valores, idades ou negociações.\n";
	$prompt .= "2. Atribua cada informação à sua fonte no texto (ex.: “segundo o GE”, “de acordo com a CBF”). Use os nomes das fontes do dossiê.\n";
	$prompt .= "3. Aspas só se a frase aparecer idêntica no dossiê. Sem isso, parafraseie sem aspas.\n";
	$prompt .= "4. Rumor é rumor: use “segundo”, “de acordo com” e deixe claro que não foi confirmado. Se só uma fonte noticiou, diga isso.\n";
	$prompt .= "5. Não copie frases do dossiê; reescreva com suas palavras.\n";
	$prompt .= "6. Se o dossiê for curto ou tiver apenas título e link, produza um rascunho proporcionalmente curto, atribua a informação ao feed e não acrescente contexto de memória. Indique em unverified que faltam detalhes para confirmação.\n";
	$prompt .= "7. Liste em \"unverified\" tudo que você incluiu e que a pessoa revisora deve conferir, e o que faltou saber.\n";
	$depth = 'standard' === bec_setting( 'ai_article_depth' ) ? 'standard' : 'detailed';
	if ( 'detailed' === $depth ) {
		$prompt .= "8. Quando o dossiê trouxer fatos suficientes, escreva aproximadamente 500–800 palavras: explique o fato, organize detalhes confirmados, contexto presente no dossiê e o que acontece a seguir. Não repita ideias para atingir a extensão. Se faltarem fatos, encurte e sinalize a lacuna.\n";
		$max_tokens = 4200;
	} else {
		$prompt .= "8. Quando o dossiê trouxer fatos suficientes, escreva aproximadamente 300–500 palavras, com contexto apenas quando estiver documentado. Não repita ideias para atingir a extensão. Se faltarem fatos, encurte e sinalize a lacuna.\n";
		$max_tokens = 2800;
	}
	if ( '' !== $style ) {
		$prompt .= "\nORIENTAÇÕES DE ESTILO DA REDAÇÃO (não valem contra as regras acima):\n" . $style . "\n";
	}
	$prompt .= "\nMODELO EDITORIAL: " . ( $modelo ? $modelo : 'noticia' ) . "\nCLUBE: " . ( $club ? $club : 'não especificado' ) . "\nPALAVRA-CHAVE: " . ( $keyword ? $keyword : 'não especificada' ) . "\n";
	$prompt .= "\n===== DOSSIÊ =====\n" . $dossier['text'] . "\n===== FIM DO DOSSIÊ =====\n\n";
	$prompt .= 'Responda SOMENTE com JSON válido e estas chaves: '
		. 'title (objetivo, sem clickbait), excerpt (até 30 palavras), content (HTML simples: <p> e <h2>; não inclua lista de fontes), '
		. 'seo_title (até 60 caracteres), meta_description (até 155 caracteres), '
		. 'claims (lista de {"afirmacao": "...", "fonte": número da fonte no dossiê, ex. 1}), '
		. 'unverified (lista de textos curtos para a pessoa conferir) e confidence ("alta", "media" ou "baixa").';

	$body = wp_json_encode(
		array(
			'model'           => bec_ai_model(),
			'temperature'     => 0.3,
			'max_completion_tokens' => $max_tokens,
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
		)
	);
	$args = array(
		'timeout' => 90,
		'headers' => array( 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ),
		'body'    => $body,
	);

	$provider = bec_ai_provider_label();
	$response = wp_remote_post( bec_ai_endpoint(), $args );
	$http     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	if ( is_wp_error( $response ) || 429 === $http || $http >= 500 ) {
		sleep( 3 );
		$response = wp_remote_post( bec_ai_endpoint(), $args );
		$http     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	}
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'bec_api_request', 'Falha de conexão com ' . $provider . ': ' . $response->get_error_message() );
	}
	$resp = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $http < 200 || $http >= 300 ) {
		$message = isset( $resp['error']['message'] ) ? $resp['error']['message'] : 'A API retornou HTTP ' . $http;
		return new WP_Error( 'bec_api_error', 'Erro na API ' . $provider . ': ' . sanitize_text_field( $message ) );
	}
	$raw  = isset( $resp['choices'][0]['message']['content'] ) ? (string) $resp['choices'][0]['message']['content'] : '';
	$raw  = trim( preg_replace( '/^```(?:json)?|```$/m', '', $raw ) );
	$json = json_decode( $raw, true );
	if ( ! is_array( $json ) || empty( $json['content'] ) || empty( $json['title'] ) ) {
		return new WP_Error( 'bec_bad_response', 'A IA retornou um conteúdo incompleto. Tente novamente.' );
	}

	// 2) Conferência automática contra o dossiê.
	$content = wp_kses_post( $json['content'] );
	$audit   = bec_ai_audit( $content, $dossier['text'] );
	$flags   = bec_ai_flags( $json, $audit );
	$conf    = isset( $json['confidence'] ) ? sanitize_key( $json['confidence'] ) : 'media';
	if ( ! in_array( $conf, array( 'alta', 'media', 'baixa' ), true ) ) {
		$conf = 'media';
	}
	if ( $audit['quotes'] || count( $audit['numbers'] ) > 3 ) {
		$conf = 'baixa';
	}
	if ( $source_warning ) {
		$flags[] = $source_warning;
	}
	if ( $dossier['chars'] < 250 ) {
		$flags[] = 'Poucos dados extraídos (' . (int) $dossier['chars'] . ' caracteres); confira a matéria original e complete o texto.';
	}
	$quality = bec_quality_meter( $pauta_id, $dossier, $audit, $conf );

	// 3) Cria o artigo na fila de revisão.
	$cat_map = array( 'noticia' => 'noticias', 'mercado' => 'mercado-da-bola', 'resultado' => 'resultados', 'analise' => 'analises' );
	$cat     = get_category_by_slug( isset( $cat_map[ $modelo ] ) ? $cat_map[ $modelo ] : 'noticias' );
	$status  = 'draft' === bec_setting( 'ai_post_status' ) ? 'draft' : 'pending';

	$new_id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => $status,
			'post_title'    => sanitize_text_field( $json['title'] ),
			'post_excerpt'  => isset( $json['excerpt'] ) ? sanitize_text_field( $json['excerpt'] ) : '',
			'post_content'  => $content,
			'post_author'   => get_current_user_id() ? get_current_user_id() : bec_rest_author_id(),
			'post_category' => $cat ? array( $cat->term_id ) : array(),
			'tags_input'    => $club ? array( $club ) : array(),
		),
		true
	);
	if ( is_wp_error( $new_id ) ) {
		return $new_id;
	}
	$used = array();
	foreach ( $dossier['sources'] as $s ) {
		if ( ! empty( $s['url'] ) ) {
			$used[] = array( 'nome' => $s['nome'], 'url' => $s['url'] );
		}
	}
	update_post_meta( $new_id, '_bec_pauta_id', $pauta_id );
	update_post_meta( $new_id, '_bec_origem', 'pauta_ia' );
	update_post_meta( $new_id, '_bec_fontes_texto', bec_sources_to_text( $used ) );
	update_post_meta( $new_id, '_bec_verificado_em', current_time( 'Y-m-d' ) );
	update_post_meta( $new_id, '_bec_revisado', '0' );
	update_post_meta(
		$new_id,
		'_bec_ai_report',
		array(
			'provider'   => bec_ai_provider_label(),
			'model'      => bec_ai_model(),
			'time'       => current_time( 'mysql' ),
			'confidence' => $conf,
			'quality'    => $quality,
			'flags'      => $flags,
			'claims'     => isset( $json['claims'] ) && is_array( $json['claims'] ) ? array_slice( $json['claims'], 0, 25 ) : array(),
			'notes'      => $dossier['notes'],
			'dossier'    => mb_substr( $dossier['text'], 0, 4000 ),
			'chars'      => $dossier['chars'],
		)
	);
	if ( ! empty( $json['seo_title'] ) ) {
		update_post_meta( $new_id, '_bec_seo_title', sanitize_text_field( $json['seo_title'] ) );
	}
	if ( ! empty( $json['meta_description'] ) ) {
		update_post_meta( $new_id, '_bec_meta_description', sanitize_text_field( $json['meta_description'] ) );
	}
	update_post_meta( $pauta_id, '_bec_post_id', $new_id );
	update_post_meta( $pauta_id, '_bec_status', 'rascunho_gerado' );
	bec_ai_today_bump();
	bec_log( $pauta_id, 'Artigo escrito pela IA (#' . $new_id . ', qualidade editorial ' . $quality['score'] . '/100, confiança ' . $conf . ', ' . count( $flags ) . ' ponto(s) a checar) e enviado para revisão.' );
	bec_activity( 'ia', 'IA escreveu “' . get_the_title( $new_id ) . '” e enviou para revisão (' . count( $flags ) . ' ponto(s) a checar).', get_edit_post_link( $new_id, 'raw' ) );
	return $new_id;
}

/* ------------------------------------------------------------------
 * Botão na pauta e ação em massa na lista
 * ---------------------------------------------------------------- */

add_action(
	'admin_post_bec_gerar_rascunho',
	function () {
		$pauta_id = isset( $_GET['pauta_id'] ) ? absint( $_GET['pauta_id'] ) : 0;
		check_admin_referer( 'bec_gerar_' . $pauta_id );
		if ( ! $pauta_id || ! current_user_can( 'edit_post', $pauta_id ) || ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Sem permissão ou pauta inválida.' );
		}
		$result = bec_generate_ai_article( $pauta_id );
		if ( is_wp_error( $result ) ) {
			bec_flash( esc_html( $result->get_error_message() ), 'error' );
			wp_safe_redirect( get_edit_post_link( $pauta_id, 'raw' ) );
		} else {
			bec_flash( 'Texto enviado para a fila de revisão.', 'success' );
			wp_safe_redirect( admin_url( 'admin.php?page=bec-revisao' ) );
		}
		exit;
	}
);

add_filter(
	'bulk_actions-edit-bec_pauta',
	function ( $actions ) {
		$actions['bec_generate_ai'] = 'Gerar rascunho com IA (enviar para revisão)';
		return $actions;
	}
);

add_filter(
	'handle_bulk_actions-edit-bec_pauta',
	function ( $redirect, $action, $post_ids ) {
		if ( 'bec_generate_ai' !== $action || ! current_user_can( 'edit_posts' ) ) {
			return $redirect;
		}
		$done     = 0;
		$errors   = 0;
		$messages = array();
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		foreach ( array_slice( array_map( 'absint', $post_ids ), 0, 10 ) as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				++$errors;
				continue;
			}
			$result = bec_generate_ai_article( $id );
			if ( is_wp_error( $result ) ) {
				++$errors;
				if ( count( $messages ) < 3 ) {
					$messages[] = get_the_title( $id ) . ': ' . $result->get_error_message();
				}
			} else {
				++$done;
			}
		}
		return add_query_arg( array( 'bec_ai_done' => $done, 'bec_ai_errors' => $errors, 'bec_ai_msg' => rawurlencode( implode( ' | ', $messages ) ) ), $redirect );
	},
	10,
	3
);

add_action(
	'admin_notices',
	function () {
		if ( ! isset( $_GET['bec_ai_done'] ) ) {
			return;
		}
		$done   = absint( $_GET['bec_ai_done'] );
		$errors = isset( $_GET['bec_ai_errors'] ) ? absint( $_GET['bec_ai_errors'] ) : 0;
		$msg    = isset( $_GET['bec_ai_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['bec_ai_msg'] ) ) : '';
		printf(
			'<div class="notice notice-info is-dismissible"><p><strong>Redação por IA:</strong> %d texto(s) enviado(s) para revisão; %d pauta(s) não processada(s). %s <a href="%s">Abrir fila de revisão</a></p></div>',
			$done, // phpcs:ignore WordPress.Security.EscapeOutput
			$errors, // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $msg ),
			esc_url( admin_url( 'admin.php?page=bec-revisao' ) )
		);
	}
);

/* Quando o artigo é publicado, a pauta passa a PUBLICADA. */
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( 'post' !== $post->post_type || 'publish' !== $new || 'publish' === $old ) {
			return;
		}
		$pauta = (int) get_post_meta( $post->ID, '_bec_pauta_id', true );
		if ( $pauta ) {
			update_post_meta( $pauta, '_bec_status', 'publicada' );
			bec_log( $pauta, 'Artigo #' . $post->ID . ' publicado.' );
		}
		if ( get_post_meta( $post->ID, '_bec_origem', true ) ) {
			bec_activity( 'publicacao', 'Publicado: “' . $post->post_title . '”.', get_permalink( $post ) );
		}
	},
	10,
	3
);
