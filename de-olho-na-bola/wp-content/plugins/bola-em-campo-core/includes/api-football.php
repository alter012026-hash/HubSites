<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Dados esportivos via API-Football (api-sports.io) — opcional, exige chave própria.
 * Chave: constante BEC_API_FOOTBALL_KEY no wp-config.php (recomendado) ou em
 * De Olho na Bola > Configurações. Todas as respostas ficam em cache para poupar a cota.
 */

function bec_api_key() {
	return defined( 'BEC_API_FOOTBALL_KEY' ) ? (string) BEC_API_FOOTBALL_KEY : (string) bec_opt( 'api_key', '' );
}

function bec_api_get( $endpoint, $params = array() ) {
	$key = bec_api_key();
	if ( '' === $key ) {
		return new WP_Error( 'bec_api', 'Chave da API-Football não configurada.' );
	}
	ksort( $params );
	$cache_key = 'bec_api_' . md5( $endpoint . wp_json_encode( $params ) );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$res = wp_remote_get(
		add_query_arg( $params, 'https://v3.football.api-sports.io/' . $endpoint ),
		array(
			'timeout' => 15,
			'headers' => array( 'x-apisports-key' => $key ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return new WP_Error( 'bec_api', 'A API respondeu com HTTP ' . (int) wp_remote_retrieve_response_code( $res ) . '.' );
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $body ) || ! isset( $body['response'] ) ) {
		return new WP_Error( 'bec_api', 'Resposta inválida da API.' );
	}
	if ( ! empty( $body['errors'] ) ) {
		$msgs = array();
		foreach ( (array) $body['errors'] as $e ) {
			$msgs[] = is_scalar( $e ) ? (string) $e : wp_json_encode( $e );
		}
		return new WP_Error( 'bec_api', implode( '; ', $msgs ) );
	}

	$minutes = max( 5, (int) bec_opt( 'cache_minutes', 15 ) );
	set_transient( $cache_key, $body['response'], $minutes * MINUTE_IN_SECONDS );
	return $body['response'];
}

function bec_api_error_html( $err ) {
	if ( current_user_can( 'manage_options' ) ) {
		return '<p class="bec-api-error">Dados indisponíveis: ' . esc_html( $err->get_error_message() ) . '</p>';
	}
	return '<p class="bec-api-error">Dados esportivos temporariamente indisponíveis.</p>';
}

function bec_api_league() {
	return max( 1, (int) bec_opt( 'api_league', 71 ) );
}
function bec_api_season() {
	return max( 2000, (int) bec_opt( 'api_season', (int) wp_date( 'Y' ) ) );
}

/** [bec_classificacao] — tabela do campeonato configurado. */
function bec_standings_shortcode() {
	$data = bec_api_get( 'standings', array( 'league' => bec_api_league(), 'season' => bec_api_season() ) );
	if ( is_wp_error( $data ) ) {
		return bec_api_error_html( $data );
	}
	$rows = isset( $data[0]['league']['standings'][0] ) ? $data[0]['league']['standings'][0] : array();
	if ( ! $rows ) {
		return '<p>Classificação indisponível para esta competição/temporada.</p>';
	}
	$html = '<div class="bec-table-wrap"><table class="bec-table"><thead><tr><th>#</th><th>Time</th><th>P</th><th>J</th><th>V</th><th>E</th><th>D</th><th>SG</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$all   = isset( $r['all'] ) ? $r['all'] : array();
		$html .= sprintf(
			'<tr><td>%d</td><td>%s</td><td><strong>%d</strong></td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%s</td></tr>',
			absint( isset( $r['rank'] ) ? $r['rank'] : 0 ),
			esc_html( isset( $r['team']['name'] ) ? $r['team']['name'] : '' ),
			absint( isset( $r['points'] ) ? $r['points'] : 0 ),
			absint( isset( $all['played'] ) ? $all['played'] : 0 ),
			absint( isset( $all['win'] ) ? $all['win'] : 0 ),
			absint( isset( $all['draw'] ) ? $all['draw'] : 0 ),
			absint( isset( $all['lose'] ) ? $all['lose'] : 0 ),
			esc_html( isset( $r['goalsDiff'] ) ? $r['goalsDiff'] : 0 )
		);
	}
	return $html . '</tbody></table></div><p class="bec-credit">Dados: API-Football.</p>';
}
add_shortcode( 'bec_classificacao', 'bec_standings_shortcode' );
add_shortcode( 'bec_standings', 'bec_standings_shortcode' );

function bec_render_fixtures( $items, $empty ) {
	if ( ! $items ) {
		return '<p>' . esc_html( $empty ) . '</p>';
	}
	$html = '<div class="bec-fixtures">';
	foreach ( $items as $m ) {
		$ts    = isset( $m['fixture']['timestamp'] ) ? (int) $m['fixture']['timestamp'] : 0;
		$date  = $ts ? wp_date( 'd/m H:i', $ts ) : '';
		$home  = isset( $m['teams']['home']['name'] ) ? $m['teams']['home']['name'] : 'Mandante';
		$away  = isset( $m['teams']['away']['name'] ) ? $m['teams']['away']['name'] : 'Visitante';
		$gh    = isset( $m['goals']['home'] ) ? $m['goals']['home'] : null;
		$ga    = isset( $m['goals']['away'] ) ? $m['goals']['away'] : null;
		$score = ( null !== $gh && null !== $ga ) ? absint( $gh ) . ' × ' . absint( $ga ) : 'vs';
		$stat  = isset( $m['fixture']['status']['short'] ) ? $m['fixture']['status']['short'] : '';
		$league = isset( $m['league']['name'] ) ? $m['league']['name'] : '';
		$html .= sprintf(
			'<div class="bec-fixture"><small>%s · %s · %s</small><strong>%s <span>%s</span> %s</strong></div>',
			esc_html( $date ),
			esc_html( $league ),
			esc_html( $stat ),
			esc_html( $home ),
			esc_html( $score ),
			esc_html( $away )
		);
	}
	return $html . '</div><p class="bec-credit">Dados: API-Football.</p>';
}

/** [bec_jogos days="7"] — jogos da competição nos próximos dias. */
function bec_fixtures_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'days' => 7 ), $atts );
	$days = max( 1, min( 30, (int) $atts['days'] ) );
	$data = bec_api_get(
		'fixtures',
		array(
			'league'   => bec_api_league(),
			'season'   => bec_api_season(),
			'from'     => wp_date( 'Y-m-d' ),
			'to'       => wp_date( 'Y-m-d', time() + $days * DAY_IN_SECONDS ),
			'timezone' => 'America/Sao_Paulo',
		)
	);
	if ( is_wp_error( $data ) ) {
		return bec_api_error_html( $data );
	}
	return bec_render_fixtures( $data, 'Nenhum jogo no período.' );
}
add_shortcode( 'bec_jogos', 'bec_fixtures_shortcode' );
add_shortcode( 'bec_fixtures', 'bec_fixtures_shortcode' );

/** Retorna placares encerrados com cache mais longo para manter o uso da cota previsível. */
function bec_recent_finished_fixtures( $count = 5 ) {
	$count = max( 2, min( 10, absint( $count ) ) );
	$args  = array(
		'league'   => bec_api_league(),
		'season'   => bec_api_season(),
		'last'     => $count,
		'timezone' => 'America/Sao_Paulo',
	);
	$key  = 'bec_match_center_' . md5( wp_json_encode( $args ) );
	$data = get_transient( $key );
	if ( false === $data ) {
		$data = bec_api_get( 'fixtures', $args );
		if ( ! is_wp_error( $data ) ) {
			set_transient( $key, $data, 6 * HOUR_IN_SECONDS );
		}
	}
	return $data;
}

/** Estatísticas finais: cacheia dados disponíveis por 24h e lacunas por 30min para tentar novamente. */
function bec_finished_fixture_stats( $fixture_id ) {
	$fixture_id = absint( $fixture_id );
	if ( ! $fixture_id ) {
		return new WP_Error( 'bec_fixture_id', 'Partida sem identificador válido.' );
	}
	$key  = 'bec_match_stats_' . $fixture_id;
	$data = get_transient( $key );
	if ( false === $data ) {
		$data = bec_api_get( 'fixtures/statistics', array( 'fixture' => $fixture_id ) );
		if ( ! is_wp_error( $data ) ) {
			$has_stats = false;
			foreach ( $data as $team ) {
				if ( ! empty( $team['statistics'] ) ) {
					$has_stats = true;
					break;
				}
			}
			set_transient( $key, $data, $has_stats ? DAY_IN_SECONDS : 30 * MINUTE_IN_SECONDS );
		}
	}
	return $data;
}

/** [bec_partidas count="5"] — carrossel de placares encerrados e estatísticas da competição ativa. */
function bec_recent_matches_shortcode( $atts ) {
	if ( '' === bec_api_key() ) {
		return current_user_can( 'manage_options' ) ? '<section class="section bec-match-setup"><strong>Últimos placares</strong><p>Para exibir estes cartões, configure a chave da API-Football em <a href="' . esc_url( admin_url( 'admin.php?page=bec-config&tab=esporte' ) ) . '">De Olho na Bola → Configurações → Dados esportivos</a>.</p></section>' : '';
	}
	$atts  = shortcode_atts( array( 'count' => 5 ), $atts );
	$items = bec_recent_finished_fixtures( $atts['count'] );
	if ( is_wp_error( $items ) ) {
		return bec_api_error_html( $items );
	}
	$finished = array_values(
		array_filter(
			(array) $items,
			function ( $match ) {
				return isset( $match['fixture']['status']['short'] )
					&& in_array( $match['fixture']['status']['short'], array( 'FT', 'AET', 'PEN' ), true )
					&& isset( $match['goals']['home'], $match['goals']['away'] );
			}
		)
	);
	if ( ! $finished ) {
		return current_user_can( 'manage_options' ) ? '<section class="section bec-match-setup"><strong>Últimos placares</strong><p>Nenhuma partida encerrada retornou para a liga e temporada configuradas. Confira o ID da competição, a temporada e a cobertura da sua conta da API-Football em <a href="' . esc_url( admin_url( 'admin.php?page=bec-config&tab=esporte' ) ) . '">Dados esportivos</a>.</p></section>' : '';
	}

	$metric_types = array(
		'Ball Possession' => array( 'label' => 'Posse de bola', 'suffix' => '%' ),
		'Total Shots'     => array( 'label' => 'Chutes', 'suffix' => '' ),
		'Shots on Goal'   => array( 'label' => 'Chutes ao gol', 'suffix' => '' ),
		'Corner Kicks'    => array( 'label' => 'Escanteios', 'suffix' => '' ),
	);
	$html = '<section class="section bec-match-center" aria-labelledby="bec-match-center-title"><div class="section-title"><h2 id="bec-match-center-title">Últimos placares</h2></div><div class="bec-match-track" aria-label="Deslize para ver outras partidas">';
	foreach ( $finished as $match ) {
		$fixture = $match['fixture'];
		$home    = $match['teams']['home'];
		$away    = $match['teams']['away'];
		$fixture_id = absint( $fixture['id'] );
		$stats   = bec_finished_fixture_stats( $fixture_id );
		$stat_by_team = array();
		if ( ! is_wp_error( $stats ) ) {
			foreach ( $stats as $team_stats ) {
				$team_id = isset( $team_stats['team']['id'] ) ? absint( $team_stats['team']['id'] ) : 0;
				if ( ! $team_id ) {
					continue;
				}
				foreach ( (array) ( isset( $team_stats['statistics'] ) ? $team_stats['statistics'] : array() ) as $stat ) {
					if ( isset( $stat['type'] ) ) {
						$stat_by_team[ $team_id ][ $stat['type'] ] = isset( $stat['value'] ) ? $stat['value'] : null;
					}
				}
			}
		}
		$home_id = isset( $home['id'] ) ? absint( $home['id'] ) : 0;
		$away_id = isset( $away['id'] ) ? absint( $away['id'] ) : 0;
		$date    = ! empty( $fixture['timestamp'] ) ? wp_date( 'd/m/Y · H:i', (int) $fixture['timestamp'] ) : '';
		$league  = isset( $match['league']['name'] ) ? $match['league']['name'] : '';
		$home_logo = ! empty( $home['logo'] ) ? '<img src="' . esc_url( $home['logo'] ) . '" alt="" loading="lazy">' : '<span class="bec-team-fallback">' . esc_html( mb_strtoupper( mb_substr( $home['name'], 0, 3 ) ) ) . '</span>';
		$away_logo = ! empty( $away['logo'] ) ? '<img src="' . esc_url( $away['logo'] ) . '" alt="" loading="lazy">' : '<span class="bec-team-fallback">' . esc_html( mb_strtoupper( mb_substr( $away['name'], 0, 3 ) ) ) . '</span>';
		$html .= '<article class="bec-match-card"><p class="bec-match-meta">' . esc_html( $league . ( $date ? ' · ' . $date : '' ) ) . '</p>';
		$html .= '<div class="bec-match-tabs" role="tablist" aria-label="Detalhes da partida"><button type="button" role="tab" aria-selected="true" data-match-tab="0">Placar</button><button type="button" role="tab" aria-selected="false" data-match-tab="1">Estatísticas</button></div>';
		$html .= '<div class="bec-match-panels" data-match-panels tabindex="0" aria-label="Deslize para alternar entre placar e estatísticas">';
		$html .= '<div class="bec-match-panel" role="tabpanel" data-match-panel="0"><div class="bec-scoreboard"><div class="bec-match-team">' . $home_logo . '<span>' . esc_html( $home['name'] ) . '</span></div><strong class="bec-score">' . absint( $match['goals']['home'] ) . '<i>–</i>' . absint( $match['goals']['away'] ) . '</strong><div class="bec-match-team">' . $away_logo . '<span>' . esc_html( $away['name'] ) . '</span></div></div><p class="bec-match-final">Resultado final</p></div>';
		$html .= '<div class="bec-match-panel" role="tabpanel" data-match-panel="1"><div class="bec-match-stats">';
		$visible_stats = 0;
		foreach ( $metric_types as $api_name => $metric ) {
			$hv = isset( $stat_by_team[ $home_id ][ $api_name ] ) ? $stat_by_team[ $home_id ][ $api_name ] : null;
			$av = isset( $stat_by_team[ $away_id ][ $api_name ] ) ? $stat_by_team[ $away_id ][ $api_name ] : null;
			if ( null === $hv && null === $av ) {
				continue;
			}
			++$visible_stats;
			$hn = is_numeric( $hv ) ? (float) $hv : ( is_string( $hv ) ? (float) rtrim( $hv, '%' ) : 0 );
			$an = is_numeric( $av ) ? (float) $av : ( is_string( $av ) ? (float) rtrim( $av, '%' ) : 0 );
			$sum = $hn + $an;
			$home_width = $sum > 0 ? round( 100 * $hn / $sum, 2 ) : 50;
			$home_label = null === $hv ? '—' : (string) $hv . $metric['suffix'];
			$away_label = null === $av ? '—' : (string) $av . $metric['suffix'];
			$html .= '<div class="bec-stat-row"><div class="bec-stat-values"><strong>' . esc_html( $home_label ) . '</strong><span>' . esc_html( $metric['label'] ) . '</span><strong>' . esc_html( $away_label ) . '</strong></div><div class="bec-stat-bar" aria-hidden="true"><i style="width:' . esc_attr( $home_width ) . '%"></i></div></div>';
		}
		if ( ! $visible_stats ) {
			$html .= '<p class="bec-match-no-stats">Estatísticas não disponíveis para esta partida.</p>';
		}
		$html .= '</div><p class="bec-match-final">Dados: API-Football · cobertura pode variar por competição</p></div></div></article>';
	}
	return $html . '</div></section>';
}
add_shortcode( 'bec_partidas', 'bec_recent_matches_shortcode' );

/** [bec_jogos_clube] — próximos jogos e resultados do clube da página atual. */
function bec_club_fixtures_shortcode() {
	$team = (int) get_post_meta( get_the_ID(), '_bec_api_team_id', true );
	if ( ! $team ) {
		return '';
	}
	$next = bec_api_get( 'fixtures', array( 'team' => $team, 'next' => 5, 'timezone' => 'America/Sao_Paulo' ) );
	$last = bec_api_get( 'fixtures', array( 'team' => $team, 'last' => 5, 'timezone' => 'America/Sao_Paulo' ) );
	$html = '<h2>Próximos jogos</h2>';
	$html .= is_wp_error( $next ) ? bec_api_error_html( $next ) : bec_render_fixtures( $next, 'Sem jogos agendados.' );
	$html .= '<h2>Resultados recentes</h2>';
	$html .= is_wp_error( $last ) ? bec_api_error_html( $last ) : bec_render_fixtures( $last, 'Sem resultados recentes.' );
	return $html;
}
add_shortcode( 'bec_jogos_clube', 'bec_club_fixtures_shortcode' );
