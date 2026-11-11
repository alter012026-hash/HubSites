<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Critérios e pesos da seção 5.4 (somam 100). */
function bec_criteria() {
	return array(
		'crescimento'    => array( 'label' => 'Crescimento no Google Trends', 'weight' => 25 ),
		'relevancia'     => array( 'label' => 'Relevância para o futebol brasileiro', 'weight' => 20 ),
		'originalidade'  => array( 'label' => 'Potencial de conteúdo original', 'weight' => 20 ),
		'busca'          => array( 'label' => 'Oportunidade de busca orgânica', 'weight' => 15 ),
		'confiabilidade' => array( 'label' => 'Confiabilidade das fontes', 'weight' => 10 ),
		'velocidade'     => array( 'label' => 'Velocidade de publicação', 'weight' => 10 ),
	);
}

/** Pontuação final de 0 a 100. */
function bec_calc_score( $values ) {
	$total = 0;
	foreach ( bec_criteria() as $key => $c ) {
		$v      = isset( $values[ $key ] ) ? max( 0, min( 100, (float) $values[ $key ] ) ) : 0;
		$total += $v * $c['weight'] / 100;
	}
	return (int) round( $total );
}

/** @return array( slug, rótulo ) */
function bec_priority( $score ) {
	if ( $score >= 75 ) {
		return array( 'alta', 'Prioridade alta' );
	}
	if ( $score >= 55 ) {
		return array( 'intermediaria', 'Prioridade intermediária' );
	}
	return array( 'baixa', 'Baixa prioridade' );
}

/** Quanto mais recente o fato, maior a nota de velocidade. */
function bec_speed_score( $timestamp ) {
	if ( ! $timestamp ) {
		return 50;
	}
	$hours = max( 0, ( time() - (int) $timestamp ) / HOUR_IN_SECONDS );
	if ( $hours <= 2 ) {
		return 100;
	}
	if ( $hours <= 6 ) {
		return 80;
	}
	if ( $hours <= 24 ) {
		return 60;
	}
	if ( $hours <= 72 ) {
		return 35;
	}
	return 15;
}

/** Alertas de fonte para o termômetro editorial; não bloqueiam a geração. */
function bec_pauta_source_warning( $pauta_id ) {
	$fonte_id = (int) get_post_meta( $pauta_id, '_bec_fonte_id', true );
	$urls     = bec_parse_sources( get_post_meta( $pauta_id, '_bec_fontes_texto', true ) );

	if ( ! $fonte_id && empty( $urls ) ) {
		return 'Nenhum link de fonte registrado; adicione e confira antes de publicar.';
	}
	if ( ! $fonte_id ) {
		return 'Fonte sem nota de confiabilidade cadastrada; confira o link antes de publicar.';
	}
	$score = (int) get_post_meta( $fonte_id, '_bec_confiabilidade', true );
	if ( $score < 60 ) {
		if ( '1' === get_post_meta( $pauta_id, '_bec_fonte_confirmada', true ) ) {
			return 'Fonte conferida manualmente; a nota cadastrada é ' . $score . '/100. Confira os detalhes antes de publicar.';
		}
		return 'Confiabilidade cadastrada da fonte: ' . $score . '/100; confira o conteúdo original antes de publicar.';
	}
	return '';
}

/** Termômetro explicável: sinaliza evidência, fonte e alertas; nunca decide publicação. */
function bec_quality_meter( $pauta_id, $dossier, $audit, $confidence ) {
	$fonte_id = (int) get_post_meta( $pauta_id, '_bec_fonte_id', true );
	$source   = $fonte_id ? max( 0, min( 100, (int) get_post_meta( $fonte_id, '_bec_confiabilidade', true ) ) ) : 25;
	if ( '1' === get_post_meta( $pauta_id, '_bec_fonte_confirmada', true ) ) {
		$source = max( 60, $source );
	}
	$chars       = isset( $dossier['chars'] ) ? (int) $dossier['chars'] : 0;
	$evidence    = min( 100, (int) round( $chars / 5 ) );
	$corrob      = get_post_meta( $pauta_id, '_bec_corrob', true );
	$corrob      = is_array( $corrob ) ? count( $corrob ) : 0;
	$corroborate = min( 100, $corrob * 30 );
	$penalty     = min( 100, count( $audit['quotes'] ) * 15 + count( $audit['numbers'] ) * 7 + count( $audit['names'] ) * 4 );
	$audit_score = 100 - $penalty;
	$ai_score    = 'alta' === $confidence ? 90 : ( 'baixa' === $confidence ? 30 : 65 );
	$score       = (int) round( $source * .25 + $evidence * .35 + $corroborate * .15 + $audit_score * .15 + $ai_score * .10 );
	$level       = $score >= 75 ? 'Bom' : ( $score >= 50 ? 'Atenção' : 'Baixo' );
	return array(
		'score'      => $score,
		'level'      => $level,
		'components' => array( 'fonte' => $source, 'dados' => $evidence, 'corroboracao' => $corroborate, 'auditoria' => $audit_score, 'ia' => $ai_score ),
	);
}
