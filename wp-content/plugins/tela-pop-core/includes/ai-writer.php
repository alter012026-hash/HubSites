<?php
/**
 * Etapa 3 — Redação pela IA.
 * Responsabilidade: transformar o dossiê em um texto original, conferir o resultado contra o
 * dossiê e entregar um artigo AGUARDANDO REVISÃO. Nunca publica.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tpop_openai_key() {
	return trim( defined( 'TPOP_OPENAI_API_KEY' ) ? (string) TPOP_OPENAI_API_KEY : (string) tpop_opt( 'openai_api_key', '' ) );
}

function tpop_groq_key() {
	return trim( defined( 'TPOP_GROQ_API_KEY' ) ? (string) TPOP_GROQ_API_KEY : (string) tpop_opt( 'groq_api_key', '' ) );
}

function tpop_ai_provider() {
	return 'groq' === tpop_setting( 'ai_provider' ) ? 'groq' : 'openai';
}

function tpop_ai_key() {
	return 'groq' === tpop_ai_provider() ? tpop_groq_key() : tpop_openai_key();
}

function tpop_ai_model() {
	return 'groq' === tpop_ai_provider()
		? (string) tpop_setting( 'groq_model' )
		: (string) tpop_setting( 'openai_model' );
}

function tpop_ai_provider_label() {
	return 'groq' === tpop_ai_provider() ? 'GroqCloud' : 'OpenAI';
}

function tpop_ai_endpoint() {
	return 'groq' === tpop_ai_provider()
		? 'https://api.groq.com/openai/v1/chat/completions'
		: 'https://api.openai.com/v1/chat/completions';
}

/* ------------------------------------------------------------------
 * Conferência automática do texto contra o dossiê
 * Não bloqueia: aponta para a pessoa onde olhar primeiro.
 * ---------------------------------------------------------------- */

function tpop_ai_audit( $content_html, $dossier_text ) {
	$plain   = html_entity_decode( wp_strip_all_tags( (string) $content_html ), ENT_QUOTES, 'UTF-8' );
	$dossier = html_entity_decode( (string) $dossier_text, ENT_QUOTES, 'UTF-8' );
	$d_norm  = ' ' . tpop_normalize( $dossier ) . ' ';
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
			if ( false === strpos( $d_norm, tpop_normalize( $quote ) ) ) {
				$out['quotes'][] = mb_substr( $quote, 0, 90 );
			}
		}
	}

	// Nomes próprios que não aparecem nos dados de origem.
	$skip = array( 'brasil', 'filme', 'filmes', 'serie', 'series', 'temporada', 'episodio', 'estreia', 'lancamento', 'critica', 'streaming', 'plataforma', 'elenco', 'diretor', 'ator', 'atriz', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo', 'janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro', 'fontes', 'segundo', 'nesta', 'neste', 'depois', 'antes', 'tambem', 'apesar', 'durante', 'contudo', 'porem', 'enquanto', 'quando', 'assim', 'ainda', 'agora', 'ontem', 'hoje', 'entre', 'sobre', 'mesmo', 'cada', 'esta', 'este', 'esse', 'essa', 'isso', 'outro', 'outra', 'todos', 'todas', 'mais', 'muito', 'para', 'como', 'onde', 'pelo', 'pela', 'com', 'sem', 'diante', 'alem', 'logo', 'entao', 'dessa', 'desse', 'nessa', 'nesse', 'foram', 'seria', 'estao', 'tinha', 'havia', 'fonte', 'confirmou', 'informou', 'disse', 'afirmou', 'obra', 'historia', 'personagem', 'cinema', 'trailer', 'produtora', 'distribuidora' );
	if ( preg_match_all( '/\b[A-ZÁÉÍÓÚÂÊÔÃÕÇ][a-záéíóúâêôãõç]{3,}\b/u', $plain, $nm ) ) {
		foreach ( array_unique( $nm[0] ) as $name ) {
			$n = tpop_normalize( $name );
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
function tpop_ai_flags( $json, $audit ) {
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

function tpop_ai_today_count() {
	$d = get_option( 'tpop_ai_daily', array() );
	return ( is_array( $d ) && isset( $d['date'] ) && wp_date( 'Y-m-d' ) === $d['date'] ) ? (int) $d['count'] : 0;
}

function tpop_ai_today_bump() {
	update_option( 'tpop_ai_daily', array( 'date' => wp_date( 'Y-m-d' ), 'count' => tpop_ai_today_count() + 1 ), false );
}

/* ------------------------------------------------------------------
 * Geração
 * ---------------------------------------------------------------- */

/**
 * Gera o rascunho de uma pauta coletada.
 *
 * @return int|WP_Error ID do artigo criado.
 */
function tpop_generate_ai_article( $pauta_id ) {
	$api_key = tpop_ai_key();
	if ( '' === $api_key ) {
		return new WP_Error( 'tpop_no_api_key', 'Configure a chave de ' . tpop_ai_provider_label() . ' em Tela Pop → Configurações → Redação (IA).' );
	}
	if ( 'tpop_pauta' !== get_post_type( $pauta_id ) ) {
		return new WP_Error( 'tpop_invalid', 'Pauta inválida.' );
	}
	$age_error = tpop_pauta_news_age_error( $pauta_id );
	if ( $age_error ) {
		return new WP_Error( 'tpop_news_outdated', $age_error . ' Atualize a pauta com uma fonte recente ou trate-a como conteúdo histórico.' );
	}
	if ( get_post_meta( $pauta_id, '_tpop_post_id', true ) ) {
		return new WP_Error( 'tpop_exists', 'Esta pauta já possui um artigo vinculado.' );
	}
	$status = get_post_meta( $pauta_id, '_tpop_status', true );
	if ( ! in_array( $status, array( 'nova_pauta', 'em_analise', 'aprovada_producao', 'aguardando_confirmacao' ), true ) ) {
		return new WP_Error( 'tpop_status', 'Esta pauta não está disponível para gerar um rascunho. Confira se ela não foi rejeitada ou processada.' );
	}
	// 1) Dossiê com dados reais.
	$dossier = tpop_build_dossier( $pauta_id );
	$source_warning = tpop_pauta_source_warning( $pauta_id );

	$modelo   = get_post_meta( $pauta_id, '_tpop_modelo', true );
	$club     = trim( (string) get_post_meta( $pauta_id, '_tpop_club', true ) );
	$keyword  = get_post_meta( $pauta_id, '_tpop_keyword', true );

	$system = 'Você é redator de um portal brasileiro de filmes e séries. Escreve em português do Brasil, com clareza, personalidade e sem sensacionalismo. '
		. 'Você só pode afirmar o que está escrito no DOSSIÊ. Tudo o que não estiver no dossiê não existe para você.';
	$style = trim( (string) tpop_setting( 'ai_style' ) );

	$prompt  = "TAREFA: escreva um artigo jornalístico ORIGINAL sobre o fato descrito no dossiê.\n\n";
	$prompt .= "REGRAS INEGOCIÁVEIS\n";
	$prompt .= "1. Use SOMENTE informações do dossiê. Não invente nem complete de memória: datas de estreia, elenco, direção, temporadas, episódios, sinopse, bilheteria, classificação indicativa ou disponibilidade em plataformas.\n";
	$prompt .= "2. Atribua cada informação factual à sua fonte no texto. Use os nomes das fontes do dossiê.\n";
	$prompt .= "3. Aspas só se a frase aparecer idêntica no dossiê. Sem isso, parafraseie sem aspas.\n";
	$prompt .= "4. Rumor é rumor: deixe claro quando uma notícia não foi confirmada. Disponibilidade em streaming deve ser atribuída e tratada como sujeita a mudança.\n";
	$prompt .= "5. Não copie frases do dossiê; reescreva com suas palavras.\n";
	$prompt .= "6. Em críticas, separe opinião editorial de fatos verificáveis e não invente detalhes da obra. Evite spoilers importantes sem aviso claro.\n";
	$prompt .= "7. Se o dossiê for curto ou tiver apenas título e link, produza um rascunho proporcionalmente curto, atribua a informação ao feed e não acrescente contexto de memória. Indique em unverified que faltam detalhes para confirmação.\n";
	$prompt .= "8. Liste em \"unverified\" tudo que você incluiu e que a pessoa revisora deve conferir, e o que faltou saber.\n";
	$depth = 'standard' === tpop_setting( 'ai_article_depth' ) ? 'standard' : 'detailed';
	if ( 'detailed' === $depth ) {
		$prompt .= "9. Quando o dossiê trouxer fatos suficientes, escreva aproximadamente 500–800 palavras: explique a novidade ou a obra, organize detalhes confirmados, contexto presente no dossiê e o que acontece a seguir. Em críticas, desenvolva a avaliação sem revelar grandes spoilers. Não repita ideias para atingir a extensão. Se faltarem fatos, encurte e sinalize a lacuna.\n";
		$max_tokens = 4200;
	} else {
		$prompt .= "9. Quando o dossiê trouxer fatos suficientes, escreva aproximadamente 300–500 palavras, com contexto apenas quando estiver documentado. Em críticas, desenvolva uma avaliação original sem inventar detalhes e sem revelar grandes spoilers. Não repita ideias para atingir a extensão. Se faltarem fatos, encurte e sinalize a lacuna.\n";
		$max_tokens = 2800;
	}
	if ( '' !== $style ) {
		$prompt .= "\nORIENTAÇÕES DE ESTILO DA REDAÇÃO (não valem contra as regras acima):\n" . $style . "\n";
	}
	$prompt .= "\nMODELO EDITORIAL: " . ( $modelo ? $modelo : 'noticia' ) . "\nFILME/SÉRIE: " . ( $club ? $club : 'não especificado' ) . "\nPALAVRA-CHAVE: " . ( $keyword ? $keyword : 'não especificada' ) . "\n";
	$prompt .= "\n===== DOSSIÊ =====\n" . $dossier['text'] . "\n===== FIM DO DOSSIÊ =====\n\n";
	$prompt .= 'Responda SOMENTE com JSON válido e estas chaves: '
		. 'title (objetivo, sem clickbait), excerpt (até 30 palavras), content (HTML simples: <p> e <h2>; não inclua lista de fontes), '
		. 'seo_title (até 60 caracteres), meta_description (até 155 caracteres), '
		. 'claims (lista de {"afirmacao": "...", "fonte": número da fonte no dossiê, ex. 1}), '
		. 'unverified (lista de textos curtos para a pessoa conferir) e confidence ("alta", "media" ou "baixa").';

	$body = wp_json_encode(
		array(
			'model'           => tpop_ai_model(),
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

	$provider = tpop_ai_provider_label();
	$response = wp_remote_post( tpop_ai_endpoint(), $args );
	$http     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	if ( is_wp_error( $response ) || 429 === $http || $http >= 500 ) {
		sleep( 3 );
		$response = wp_remote_post( tpop_ai_endpoint(), $args );
		$http     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	}
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'tpop_api_request', 'Falha de conexão com ' . $provider . ': ' . $response->get_error_message() );
	}
	$resp = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $http < 200 || $http >= 300 ) {
		$message = isset( $resp['error']['message'] ) ? $resp['error']['message'] : 'A API retornou HTTP ' . $http;
		return new WP_Error( 'tpop_api_error', 'Erro na API ' . $provider . ': ' . sanitize_text_field( $message ) );
	}
	$raw  = isset( $resp['choices'][0]['message']['content'] ) ? (string) $resp['choices'][0]['message']['content'] : '';
	$raw  = trim( preg_replace( '/^```(?:json)?|```$/m', '', $raw ) );
	$json = json_decode( $raw, true );
	if ( ! is_array( $json ) || empty( $json['content'] ) || empty( $json['title'] ) ) {
		return new WP_Error( 'tpop_bad_response', 'A IA retornou um conteúdo incompleto. Tente novamente.' );
	}

	// 2) Conferência automática contra o dossiê.
	$content = wp_kses_post( $json['content'] );
	$audit   = tpop_ai_audit( $content, $dossier['text'] );
	$flags   = tpop_ai_flags( $json, $audit );
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
	$quality = tpop_quality_meter( $pauta_id, $dossier, $audit, $conf );

	// 3) Cria o artigo na fila de revisão.
	$cat_map = array( 'noticia' => 'lancamentos', 'estreia' => 'lancamentos', 'critica' => 'criticas', 'guia' => 'onde-assistir', 'lista' => 'listas' );
	$cat     = get_category_by_slug( isset( $cat_map[ $modelo ] ) ? $cat_map[ $modelo ] : 'filmes' );
	$status  = 'draft' === tpop_setting( 'ai_post_status' ) ? 'draft' : 'pending';

	$new_id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => $status,
			'post_title'    => sanitize_text_field( $json['title'] ),
			'post_excerpt'  => isset( $json['excerpt'] ) ? sanitize_text_field( $json['excerpt'] ) : '',
			'post_content'  => $content,
			'post_author'   => get_current_user_id() ? get_current_user_id() : tpop_rest_author_id(),
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
	update_post_meta( $new_id, '_tpop_pauta_id', $pauta_id );
	update_post_meta( $new_id, '_tpop_origem', 'pauta_ia' );
	update_post_meta( $new_id, '_tpop_fontes_texto', tpop_sources_to_text( $used ) );
	update_post_meta( $new_id, '_tpop_verificado_em', current_time( 'Y-m-d' ) );
	update_post_meta( $new_id, '_tpop_revisado', '0' );
	update_post_meta(
		$new_id,
		'_tpop_ai_report',
		array(
			'provider'   => tpop_ai_provider_label(),
			'model'      => tpop_ai_model(),
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
		update_post_meta( $new_id, '_tpop_seo_title', sanitize_text_field( $json['seo_title'] ) );
	}
	if ( ! empty( $json['meta_description'] ) ) {
		update_post_meta( $new_id, '_tpop_meta_description', sanitize_text_field( $json['meta_description'] ) );
	}
	update_post_meta( $pauta_id, '_tpop_post_id', $new_id );
	update_post_meta( $pauta_id, '_tpop_status', 'rascunho_gerado' );
	tpop_ai_today_bump();
	tpop_log( $pauta_id, 'Artigo escrito pela IA (#' . $new_id . ', qualidade editorial ' . $quality['score'] . '/100, confiança ' . $conf . ', ' . count( $flags ) . ' ponto(s) a checar) e enviado para revisão.' );
	tpop_activity( 'ia', 'IA escreveu “' . get_the_title( $new_id ) . '” e enviou para revisão (' . count( $flags ) . ' ponto(s) a checar).', get_edit_post_link( $new_id, 'raw' ) );
	return $new_id;
}

/* ------------------------------------------------------------------
 * Botão na pauta e ação em massa na lista
 * ---------------------------------------------------------------- */

add_action(
	'admin_post_tpop_gerar_rascunho',
	function () {
		$pauta_id = isset( $_GET['pauta_id'] ) ? absint( $_GET['pauta_id'] ) : 0;
		check_admin_referer( 'tpop_gerar_' . $pauta_id );
		if ( ! $pauta_id || ! current_user_can( 'edit_post', $pauta_id ) || ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Sem permissão ou pauta inválida.' );
		}
		$result = tpop_generate_ai_article( $pauta_id );
		if ( is_wp_error( $result ) ) {
			tpop_flash( esc_html( $result->get_error_message() ), 'error' );
			wp_safe_redirect( get_edit_post_link( $pauta_id, 'raw' ) );
		} else {
			tpop_flash( 'Texto enviado para a fila de revisão.', 'success' );
			wp_safe_redirect( admin_url( 'admin.php?page=tpop-revisao' ) );
		}
		exit;
	}
);

add_filter(
	'bulk_actions-edit-tpop_pauta',
	function ( $actions ) {
		$actions['tpop_generate_ai'] = 'Gerar rascunho com IA (enviar para revisão)';
		return $actions;
	}
);

add_filter(
	'handle_bulk_actions-edit-tpop_pauta',
	function ( $redirect, $action, $post_ids ) {
		if ( 'tpop_generate_ai' !== $action || ! current_user_can( 'edit_posts' ) ) {
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
			$result = tpop_generate_ai_article( $id );
			if ( is_wp_error( $result ) ) {
				++$errors;
				if ( count( $messages ) < 3 ) {
					$messages[] = get_the_title( $id ) . ': ' . $result->get_error_message();
				}
			} else {
				++$done;
			}
		}
		return add_query_arg( array( 'tpop_ai_done' => $done, 'tpop_ai_errors' => $errors, 'tpop_ai_msg' => rawurlencode( implode( ' | ', $messages ) ) ), $redirect );
	},
	10,
	3
);

add_action(
	'admin_notices',
	function () {
		if ( ! isset( $_GET['tpop_ai_done'] ) ) {
			return;
		}
		$done   = absint( $_GET['tpop_ai_done'] );
		$errors = isset( $_GET['tpop_ai_errors'] ) ? absint( $_GET['tpop_ai_errors'] ) : 0;
		$msg    = isset( $_GET['tpop_ai_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['tpop_ai_msg'] ) ) : '';
		printf(
			'<div class="notice notice-info is-dismissible"><p><strong>Redação por IA:</strong> %d texto(s) enviado(s) para revisão; %d pauta(s) não processada(s). %s <a href="%s">Abrir fila de revisão</a></p></div>',
			$done, // phpcs:ignore WordPress.Security.EscapeOutput
			$errors, // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $msg ),
			esc_url( admin_url( 'admin.php?page=tpop-revisao' ) )
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
		$pauta = (int) get_post_meta( $post->ID, '_tpop_pauta_id', true );
		if ( $pauta ) {
			update_post_meta( $pauta, '_tpop_status', 'publicada' );
			tpop_log( $pauta, 'Artigo #' . $post->ID . ' publicado.' );
		}
		if ( get_post_meta( $post->ID, '_tpop_origem', true ) ) {
			tpop_activity( 'publicacao', 'Publicado: “' . $post->post_title . '”.', get_permalink( $post ) );
		}
	},
	10,
	3
);
