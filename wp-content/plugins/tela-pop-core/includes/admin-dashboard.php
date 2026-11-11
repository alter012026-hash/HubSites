<?php
/**
 * Visão geral: onde está cada etapa do fluxo, o que precisa de atenção e o que aconteceu.
 * Responsabilidade: só leitura e atalhos. Não decide nada.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Tela Pop', 'Tela Pop', 'edit_posts', 'tpop-painel', 'tpop_dashboard_page', 'dashicons-visibility', 4 );
		add_submenu_page( 'tpop-painel', 'Visão geral', 'Visão geral', 'edit_posts', 'tpop-painel', 'tpop_dashboard_page' );
	},
	5
);

function tpop_count_pautas( $status ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'tpop_pauta',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_tpop_status', 'value' => $status ) ),
		)
	);
	return (int) $q->found_posts;
}

/** Itens do checklist de saúde: [tom, título, detalhe, link]. */
function tpop_health_items() {
	$items = array();
	$feeds = new WP_Query(
		array(
			'post_type'      => 'tpop_fonte',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => '_tpop_ativa', 'value' => '1' ),
				array( 'key' => '_tpop_feed_url', 'value' => '', 'compare' => '!=' ),
			),
		)
	);
	$n = (int) $feeds->found_posts;
	$items[] = array( $n ? 'ok' : 'err', $n ? $n . ' fonte(s) RSS ativa(s)' : 'Nenhuma fonte RSS ativa', $n ? 'A coleta tem de onde ler.' : 'Cadastre um feed, marque como Ativa e informe a URL.', admin_url( 'edit.php?post_type=tpop_fonte' ) );

	$mode_labels = array( 'manual' => 'Manual', 'semi' => 'Assistido', 'auto' => 'Rascunhos automáticos' );
	$mode        = tpop_setting( 'auto_mode' );
	$items[]     = array( 'auto' === $mode ? 'ok' : 'warn', 'Modo do fluxo: ' . ( isset( $mode_labels[ $mode ] ) ? $mode_labels[ $mode ] : 'Assistido' ), 'Os rascunhos sempre passam por revisão humana antes da publicação.', admin_url( 'admin.php?page=tpop-config&tab=fluxo' ) );

	$has_key = '' !== tpop_ai_key();
	$provider = tpop_ai_provider_label();
	$items[] = array( $has_key ? 'ok' : 'err', $has_key ? 'Chave de ' . $provider . ' configurada' : 'Chave de ' . $provider . ' ausente', $has_key ? 'Modelo: ' . tpop_ai_model() : 'Sem chave a IA não escreve.', admin_url( 'admin.php?page=tpop-config&tab=ia' ) );


	$next    = wp_next_scheduled( 'tpop_pipeline_event' );
	$cron_ok = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$items[] = array( $next ? ( $cron_ok ? 'ok' : 'warn' ) : 'err', $next ? 'Agendador ativo' : 'Agendador parado', $next ? ( $cron_ok ? 'Cron do servidor em uso.' : 'Roda só quando há visitas. Em produção use cron real.' ) : 'Desative e reative o plugin.', admin_url( 'admin.php?page=tpop-config&tab=integracoes' ) );

	$mail = is_email( tpop_setting( 'notify_email' ) ) || is_email( get_option( 'admin_email' ) );
	$items[] = array( $mail && '1' === (string) tpop_setting( 'notify_on' ) ? 'ok' : 'warn', $mail && '1' === (string) tpop_setting( 'notify_on' ) ? 'Aviso de revisão por e-mail ligado' : 'Aviso de revisão desligado', 'A equipe é avisada quando há textos novos.', admin_url( 'admin.php?page=tpop-config&tab=fluxo' ) );
	return $items;
}

function tpop_dashboard_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$mode_labels = array( 'manual' => 'Manual', 'semi' => 'Assistido', 'auto' => 'Rascunhos automáticos' );
	$mode        = tpop_setting( 'auto_mode' );
	$stats       = get_option( 'tpop_last_fetch_stats', array() );
	$waiting     = tpop_review_count();
	$stages      = tpop_flow_stages();

	$feed_count = (int) ( new WP_Query( array( 'post_type' => 'tpop_fonte', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_tpop_ativa', 'value' => '1' ) ) ) ) )->found_posts;
	$published  = (int) ( new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'date_query' => array( array( 'after' => '7 days ago' ) ), 'meta_query' => array( array( 'key' => '_tpop_origem', 'compare' => 'EXISTS' ) ) ) ) )->found_posts;

	$numbers = array(
		'coleta'   => array( $feed_count, 'fontes ativas', $stats ? 'Última: ' . wp_date( 'd/m H:i', (int) $stats['time'] ) : 'Ainda não coletou', admin_url( 'edit.php?post_type=tpop_fonte' ) ),
		'triagem'  => array( tpop_count_pautas( 'nova_pauta' ), 'pautas novas', tpop_count_pautas( 'aguardando_confirmacao' ) . ' aguardando confirmação', admin_url( 'edit.php?post_type=tpop_pauta&tpop_status=nova_pauta' ) ),
		'redacao'  => array( tpop_count_pautas( 'aprovada_producao' ), 'prontas para IA', 'Hoje: ' . tpop_ai_today_count() . ' de ' . (int) tpop_setting( 'auto_max_per_day' ), admin_url( 'edit.php?post_type=tpop_pauta&tpop_status=aprovada_producao' ) ),
		'revisao'  => array( $waiting, 'para revisar', $waiting ? 'Precisa de você' : 'Tudo em dia', admin_url( 'admin.php?page=tpop-revisao' ) ),
		'publicar' => array( $published, 'em 7 dias', 'Textos do fluxo publicados', admin_url( 'edit.php?post_status=publish&post_type=post' ) ),
	);

	$top = new WP_Query(
		array(
			'post_type'      => 'tpop_pauta',
			'post_status'    => 'publish',
			'posts_per_page' => 8,
			'meta_key'       => '_tpop_score',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'meta_query'     => array( array( 'key' => '_tpop_status', 'value' => array( 'nova_pauta', 'em_analise', 'aguardando_confirmacao' ), 'compare' => 'IN' ) ),
		)
	);
	$activity = get_option( 'tpop_activity', array() );
	$activity = is_array( $activity ) ? array_slice( $activity, 0, 10 ) : array();
	$tones    = array( 'coleta' => 'info', 'triagem' => 'info', 'ia' => 'warn', 'revisao' => 'ok', 'publicacao' => 'ok', 'erro' => 'err' );
	$names    = array( 'coleta' => 'Coleta', 'triagem' => 'Triagem', 'ia' => 'IA', 'revisao' => 'Revisão', 'publicacao' => 'Publicação', 'erro' => 'Atenção' );

	$actions  = '<span class="tpop-pill tpop-pill--dark">Modo: ' . esc_html( $mode_labels[ $mode ] ) . '</span>';
	$actions .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:8px;margin:0">';
	$actions .= wp_nonce_field( 'tpop_run', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="tpop_run">';
	$actions .= '<button class="button" name="what" value="fetch">Coletar RSS agora</button>';
	$actions .= '<button class="button button-primary" name="what" value="full">Rodar fluxo completo</button></form>';
	?>
	<div class="wrap tpop-ui">
		<?php tpop_ui_header( 'Visão geral', 'Do RSS à publicação: onde cada etapa está e quem responde por ela.', $actions ); ?>

		<div class="tpop-steps">
			<?php
			foreach ( $stages as $key => $st ) :
				$num    = $numbers[ $key ];
				$human  = in_array( $key, array( 'revisao', 'publicar' ), true );
				$alert  = 'revisao' === $key && $waiting > 0;
				?>
				<a class="tpop-step<?php echo $human ? ' tpop-step--human' : ''; ?><?php echo $alert ? ' tpop-step--alert' : ''; ?>" href="<?php echo esc_url( $num[3] ); ?>">
					<div class="tpop-step__top"><span class="tpop-step__n"><?php echo (int) $st['n']; ?></span><?php echo tpop_pill( $st['who'], $human ? 'ok' : 'mute' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<h3><?php echo esc_html( $st['title'] ); ?></h3>
					<div class="tpop-step__num"><?php echo (int) $num[0]; ?><small><?php echo esc_html( $num[1] ); ?></small></div>
					<p><?php echo esc_html( $num[2] ); ?></p>
					<p style="opacity:.85"><?php echo esc_html( $st['desc'] ); ?></p>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="tpop-grid tpop-grid--2">
			<section class="tpop-card">
				<h2>Pautas mais fortes esperando decisão</h2>
				<p class="tpop-card__sub">A nota organiza a prioridade. No modo Rascunhos automáticos, os textos seguem para revisão com alertas de qualidade.</p>
				<table class="widefat"><thead><tr><th>Nota</th><th>Título</th><th>Situação</th></tr></thead><tbody>
					<?php if ( $top->have_posts() ) : while ( $top->have_posts() ) : $top->the_post(); $st = get_post_meta( get_the_ID(), '_tpop_status', true ); ?>
						<tr><td><strong><?php echo (int) get_post_meta( get_the_ID(), '_tpop_score', true ); ?></strong></td>
							<td><a href="<?php echo esc_url( get_edit_post_link() ); ?>"><?php the_title(); ?></a></td>
							<td><?php echo tpop_pill( tpop_status_label( $st ), tpop_status_tone( $st ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td></tr>
					<?php endwhile; wp_reset_postdata(); else : ?>
						<tr><td colspan="3">Nenhuma pauta aberta. Rode a coleta para buscar notícias.</td></tr>
					<?php endif; ?>
				</tbody></table>
			</section>

			<section class="tpop-card">
				<h2>Atividade recente</h2>
				<p class="tpop-card__sub">O que o fluxo e a equipe fizeram por último.</p>
				<?php if ( $activity ) : ?>
					<ul class="tpop-feed">
						<?php foreach ( $activity as $a ) : ?>
							<li><time><?php echo esc_html( wp_date( 'd/m H:i', (int) $a['time'] ) ); ?></time>
								<div><?php echo tpop_pill( $names[ $a['type'] ] ?? 'Fluxo', $tones[ $a['type'] ] ?? 'mute' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<?php echo $a['link'] ? '<a href="' . esc_url( $a['link'] ) . '">' . esc_html( $a['msg'] ) . '</a>' : esc_html( $a['msg'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p>Ainda sem atividade. Use “Rodar fluxo completo” para começar.</p>
				<?php endif; ?>
			</section>
		</div>

		<section class="tpop-card">
			<h2>Está tudo configurado?</h2>
			<p class="tpop-card__sub">Verificações rápidas. Clique em um item para ajustar.</p>
			<ul class="tpop-check">
				<?php foreach ( tpop_health_items() as $h ) : ?>
					<li class="is-<?php echo esc_attr( $h[0] ); ?>"><span class="dashicons dashicons-<?php echo 'ok' === $h[0] ? 'yes-alt' : ( 'err' === $h[0] ? 'dismiss' : 'warning' ); ?>"></span>
						<div><a href="<?php echo esc_url( $h[3] ); ?>"><strong><?php echo esc_html( $h[1] ); ?></strong></a><small><?php echo esc_html( $h[2] ); ?></small></div></li>
				<?php endforeach; ?>
			</ul>
		</section>

		<p style="color:var(--b-cinza);font-size:12.5px">Métricas de busca (impressões, cliques, posição) vêm do <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a>, ainda não integrado a este painel.</p>
	</div>
	<?php
}
