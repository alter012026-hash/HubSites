<?php
/**
 * Etapa 3a — Dossiê de fatos.
 * Responsabilidade: reunir os DADOS REAIS que a IA pode usar. Nada além disto entra no texto.
 * Fontes do dossiê: (1) resumo do feed RSS, (2) matéria da fonte, se a permissão deixar,
 * (3) coberturas relacionadas para confirmação editorial, (4) dados esportivos da API-Football.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Extrai o texto corrido de uma página (parágrafos), limitado em caracteres. */
function bec_fetch_article_text( $url, $max_chars = 6000 ) {
	if ( ! wp_http_validate_url( $url ) ) {
		return '';
	}
	$res = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 12,
			'redirection'         => 3,
			'limit_response_size' => 600000,
			'user-agent'          => 'DeOlhoNaBola/' . BEC_VERSION . ' (+' . home_url( '/' ) . ')',
			'headers'             => array( 'Accept' => 'text/html' ),
		)
	);
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return '';
	}
	$html = (string) wp_remote_retrieve_body( $res );
	if ( '' === $html ) {
		return '';
	}
	$html = preg_replace( '#<(script|style|noscript|nav|header|footer|aside|form)\b[^>]*>.*?</\1>#is', ' ', $html );

	// Prefere o bloco <article>; senão a página inteira.
	if ( preg_match( '#<article\b[^>]*>(.*?)</article>#is', $html, $m ) ) {
		$html = $m[1];
	}
	$paragraphs = array();
	if ( preg_match_all( '#<p\b[^>]*>(.*?)</p>#is', $html, $all ) ) {
		foreach ( $all[1] as $p ) {
			$t = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $p ), ENT_QUOTES, 'UTF-8' ) ) );
			if ( mb_strlen( $t ) >= 40 ) {
				$paragraphs[] = $t;
			}
		}
	}
	$text = implode( "\n", $paragraphs );
	return mb_substr( $text, 0, max( 500, (int) $max_chars ) );
}

/** Jogos recentes/próximos e posição na tabela do clube, em texto simples. */
function bec_sports_context( $club_key ) {
	if ( ! $club_key || '' === bec_api_key() ) {
		return '';
	}
	$club_post = get_page_by_path( $club_key, OBJECT, 'clube' );
	$team_id   = $club_post ? (int) get_post_meta( $club_post->ID, '_bec_api_team_id', true ) : 0;
	if ( ! $team_id ) {
		return '';
	}
	$lines  = array();
	$season = bec_api_season();

	$last = bec_api_get( 'fixtures', array( 'team' => $team_id, 'season' => $season, 'last' => 3 ) );
	if ( ! is_wp_error( $last ) && is_array( $last ) ) {
		foreach ( $last as $fx ) {
			if ( empty( $fx['teams']['home']['name'] ) || ! isset( $fx['goals']['home'] ) ) {
				continue;
			}
			$lines[] = sprintf(
				'Resultado: %s %s x %s %s — %s%s (%s)',
				$fx['teams']['home']['name'],
				$fx['goals']['home'],
				$fx['goals']['away'],
				$fx['teams']['away']['name'],
				isset( $fx['league']['name'] ) ? $fx['league']['name'] : '',
				! empty( $fx['league']['round'] ) ? ', ' . $fx['league']['round'] : '',
				! empty( $fx['fixture']['date'] ) ? wp_date( 'd/m/Y', strtotime( $fx['fixture']['date'] ) ) : ''
			);
		}
	}
	$next = bec_api_get( 'fixtures', array( 'team' => $team_id, 'season' => $season, 'next' => 2 ) );
	if ( ! is_wp_error( $next ) && is_array( $next ) ) {
		foreach ( $next as $fx ) {
			if ( empty( $fx['teams']['home']['name'] ) ) {
				continue;
			}
			$lines[] = sprintf(
				'Próximo jogo: %s x %s — %s (%s)%s',
				$fx['teams']['home']['name'],
				$fx['teams']['away']['name'],
				isset( $fx['league']['name'] ) ? $fx['league']['name'] : '',
				! empty( $fx['fixture']['date'] ) ? wp_date( 'd/m/Y H:i', strtotime( $fx['fixture']['date'] ) ) : '',
				! empty( $fx['fixture']['venue']['name'] ) ? ', ' . $fx['fixture']['venue']['name'] : ''
			);
		}
	}
	$st = bec_api_get( 'standings', array( 'league' => bec_api_league(), 'season' => $season ) );
	if ( ! is_wp_error( $st ) && ! empty( $st[0]['league']['standings'][0] ) ) {
		foreach ( $st[0]['league']['standings'][0] as $row ) {
			if ( isset( $row['team']['id'] ) && (int) $row['team']['id'] === $team_id ) {
				$lines[] = sprintf(
					'Classificação (%s): %dº lugar, %d pontos, %d jogos, %d vitórias, %d empates, %d derrotas, saldo de gols %d',
					isset( $st[0]['league']['name'] ) ? $st[0]['league']['name'] : 'liga',
					(int) $row['rank'],
					(int) $row['points'],
					(int) $row['all']['played'],
					(int) $row['all']['win'],
					(int) $row['all']['draw'],
					(int) $row['all']['lose'],
					(int) $row['goalsDiff']
				);
				break;
			}
		}
	}
	return implode( "\n", $lines );
}

/**
 * Monta o dossiê de uma pauta.
 *
 * @return array{text:string,chars:int,sources:array,notes:array,items:array}
 */
function bec_build_dossier( $pauta_id ) {
	$title    = get_the_title( $pauta_id );
	$sources  = bec_parse_sources( get_post_meta( $pauta_id, '_bec_fontes_texto', true ) );
	$fonte_id = (int) get_post_meta( $pauta_id, '_bec_fonte_id', true );
	$perm     = $fonte_id ? get_post_meta( $fonte_id, '_bec_permissao', true ) : '';
	$perm     = $perm ? $perm : 'descoberta';
	$ts       = (int) get_post_meta( $pauta_id, '_bec_ts_origem', true );
	$summary  = (string) get_post_meta( $pauta_id, '_bec_resumo_fonte', true );
	$club_key = (string) get_post_meta( $pauta_id, '_bec_club', true );
	$clubs    = bec_clubs();

	$notes = array();
	$items = array();
	$text  = '';
	$chars = 0;

	// [1] Fonte principal.
	$main = $sources ? $sources[0] : array( 'nome' => get_the_title( $fonte_id ), 'url' => '' );
	$text .= '[1] FONTE PRINCIPAL — ' . $main['nome'] . ( $main['url'] ? ' (' . $main['url'] . ')' : '' ) . ( $ts ? ' — publicada em ' . wp_date( 'd/m/Y H:i', $ts ) : '' ) . "\n";
	$text .= 'Título publicado: ' . $title . "\n";
	if ( '' !== $summary ) {
		$text  .= 'Resumo do feed: ' . $summary . "\n";
		$chars += mb_strlen( $summary );
	}
	$items[] = array( 'n' => 1, 'nome' => $main['nome'], 'url' => $main['url'] );

	if ( 'licenciada' === $perm && '1' === (string) bec_setting( 'read_article' ) && $main['url'] ) {
		$article = bec_fetch_article_text( $main['url'], (int) bec_setting( 'article_max_chars' ) );
		if ( '' !== $article ) {
			$text  .= "Trecho da matéria:\n" . $article . "\n";
			$chars += mb_strlen( $article );
			$notes[] = 'Texto da matéria extraído de fonte marcada como licenciada; verifique os limites da autorização.';
		} else {
			$notes[] = 'Não foi possível ler a matéria licenciada; só título e resumo do feed.';
		}
	} else {
		$notes[] = 'Sem autorização escrita para extrair o texto integral: a IA recebeu título, resumo e link do feed.';
	}
	$text .= "\n";

	// [2..n] Coberturas relacionadas, ainda não validadas como confirmação independente.
	$corrob = get_post_meta( $pauta_id, '_bec_corrob', true );
	$n      = 1;
	if ( is_array( $corrob ) ) {
		foreach ( array_slice( $corrob, 0, 4 ) as $c ) {
			++$n;
			$text   .= '[' . $n . '] COBERTURA RELACIONADA — ' . $c['nome'] . ' (' . $c['url'] . ")\n";
			$text   .= 'Título: ' . $c['titulo'] . "\n";
			$chars  += mb_strlen( $c['titulo'] );
			if ( ! empty( $c['resumo'] ) ) {
				$text  .= 'Resumo do feed: ' . $c['resumo'] . "\n";
				$chars += mb_strlen( $c['resumo'] );
			}
			$corrob_id = isset( $c['fonte_id'] ) ? absint( $c['fonte_id'] ) : 0;
			$corrob_permission = $corrob_id ? get_post_meta( $corrob_id, '_bec_permissao', true ) : 'descoberta';
			if ( $n <= 3 && 'licenciada' === $corrob_permission && '1' === (string) bec_setting( 'read_article' ) && ! empty( $c['url'] ) ) {
				$related_text = bec_fetch_article_text( $c['url'], min( 4000, (int) bec_setting( 'article_max_chars' ) ) );
				if ( $related_text ) {
					$text  .= "Trecho da cobertura licenciada:\n" . $related_text . "\n";
					$chars += mb_strlen( $related_text );
				}
			}
			$text   .= "\n";
			$items[] = array( 'n' => $n, 'nome' => $c['nome'], 'url' => $c['url'] );
		}
		if ( $n > 1 ) {
			$notes[] = ( $n - 1 ) . ' cobertura(s) relacionada(s) localizada(s); semelhança de títulos não comprova independência nem que tratem do mesmo fato.';
		}
	}

	// Anotações inseridas pela redação permanecem identificadas e não substituem as fontes.
	$editor_facts = trim( (string) get_post_meta( $pauta_id, '_bec_fatos_redacao', true ) );
	if ( '' !== $editor_facts ) {
		$text  .= "\n[ED] ANOTAÇÕES DA REDAÇÃO — fatos confirmados manualmente; confira as referências antes de usar:\n" . $editor_facts . "\n";
		$chars += mb_strlen( $editor_facts );
		$notes[] = 'Anotações manuais da redação incluídas no dossiê.';
	}

	// [D] Dados esportivos.
	if ( '1' === (string) bec_setting( 'use_sports_data' ) ) {
		$sports = bec_sports_context( $club_key );
		if ( '' !== $sports ) {
			$club_name = isset( $clubs[ $club_key ] ) ? $clubs[ $club_key ]['name'] : '';
			$text     .= '[D] DADOS ESPORTIVOS (API-Football, consultados em ' . wp_date( 'd/m/Y H:i' ) . ') — ' . $club_name . "\n" . $sports . "\n";
			$chars    += mb_strlen( $sports );
			$notes[]   = 'Dados esportivos da API-Football incluídos.';
		}
	}

	return array(
		'text'    => trim( $text ),
		'chars'   => $chars,
		'sources' => $items,
		'notes'   => $notes,
		'perm'    => $perm,
	);
}
