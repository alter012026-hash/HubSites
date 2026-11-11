<?php
/**
 * Etapa 3a — Dossiê de fatos.
 * Responsabilidade: reunir os DADOS REAIS que a IA pode usar. Nada além disto entra no texto.
 * Fontes do dossiê: (1) resumo do feed RSS, (2) matéria da fonte, se a permissão deixar,
 * (3) coberturas relacionadas para confirmação editorial e (4) fatos apurados pela redação.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Extrai o texto corrido de uma página (parágrafos), limitado em caracteres. */
function tpop_fetch_article_text( $url, $max_chars = 6000 ) {
	if ( ! wp_http_validate_url( $url ) ) {
		return '';
	}
	$res = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 12,
			'redirection'         => 3,
			'limit_response_size' => 600000,
			'user-agent'          => 'TelaPop/' . TPOP_VERSION . ' (+' . home_url( '/' ) . ')',
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

/**
 * Monta o dossiê de uma pauta.
 *
 * @return array{text:string,chars:int,sources:array,notes:array,items:array}
 */
function tpop_build_dossier( $pauta_id ) {
	$title    = get_the_title( $pauta_id );
	$sources  = tpop_parse_sources( get_post_meta( $pauta_id, '_tpop_fontes_texto', true ) );
	$fonte_id = (int) get_post_meta( $pauta_id, '_tpop_fonte_id', true );
	$perm     = $fonte_id ? get_post_meta( $fonte_id, '_tpop_permissao', true ) : '';
	$perm     = $perm ? $perm : 'descoberta';
	$ts       = (int) get_post_meta( $pauta_id, '_tpop_ts_origem', true );
	$summary  = (string) get_post_meta( $pauta_id, '_tpop_resumo_fonte', true );

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

	if ( 'licenciada' === $perm && '1' === (string) tpop_setting( 'read_article' ) && $main['url'] ) {
		$article = tpop_fetch_article_text( $main['url'], (int) tpop_setting( 'article_max_chars' ) );
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
	$corrob = get_post_meta( $pauta_id, '_tpop_corrob', true );
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
			$corrob_permission = $corrob_id ? get_post_meta( $corrob_id, '_tpop_permissao', true ) : 'descoberta';
			if ( $n <= 3 && 'licenciada' === $corrob_permission && '1' === (string) tpop_setting( 'read_article' ) && ! empty( $c['url'] ) ) {
				$related_text = tpop_fetch_article_text( $c['url'], min( 4000, (int) tpop_setting( 'article_max_chars' ) ) );
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
	$editor_facts = trim( (string) get_post_meta( $pauta_id, '_tpop_fatos_redacao', true ) );
	if ( '' !== $editor_facts ) {
		$text  .= "\n[ED] ANOTAÇÕES DA REDAÇÃO — fatos confirmados manualmente; confira as referências antes de usar:\n" . $editor_facts . "\n";
		$chars += mb_strlen( $editor_facts );
		$notes[] = 'Anotações manuais da redação incluídas no dossiê.';
	}

	return array(
		'text'    => trim( $text ),
		'chars'   => $chars,
		'sources' => $items,
		'notes'   => $notes,
		'perm'    => $perm,
	);
}
