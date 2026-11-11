<?php
/**
 * Etapas 4 e 5 — Revisão humana e publicação.
 * Responsabilidade: mostrar o que a IA escreveu junto com os pontos a checar, e registrar a
 * decisão de uma PESSOA (aprovar, publicar ou rejeitar). Só pessoas passam daqui.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Consulta base: textos da automação que ainda não tiveram revisão concluída. */
function tpop_review_query_args( $extra = array() ) {
	return array_merge(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'pending', 'draft' ),
			'posts_per_page' => 30,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_tpop_origem', 'value' => array( 'pauta_ia', 'api' ), 'compare' => 'IN' ),
				array( 'key' => '_tpop_revisado', 'value' => '1', 'compare' => '!=' ),
			),
		),
		$extra
	);
}

function tpop_review_count() {
	static $count = null;
	if ( null === $count ) {
		$q     = new WP_Query( tpop_review_query_args( array( 'posts_per_page' => 1, 'fields' => 'ids' ) ) );
		$count = (int) $q->found_posts;
	}
	return $count;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'tpop-painel', 'Fila de revisão', 'Fila de revisão', 'edit_posts', 'tpop-revisao', 'tpop_review_page' );
	},
	7
);

/* ------------------------------------------------------------------
 * Decisão da pessoa revisora
 * ---------------------------------------------------------------- */

add_action(
	'admin_post_tpop_review_action',
	function () {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		check_admin_referer( 'tpop_review_' . $post_id );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'Sem permissão.' );
		}
		$do    = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$title = get_the_title( $post_id );
		$pauta = (int) get_post_meta( $post_id, '_tpop_pauta_id', true );
		$back  = admin_url( 'admin.php?page=tpop-revisao' );
		$user  = wp_get_current_user();

		if ( 'reject' === $do ) {
			$why = isset( $_POST['why'] ) ? sanitize_text_field( wp_unslash( $_POST['why'] ) ) : '';
			wp_trash_post( $post_id );
			if ( $pauta ) {
				update_post_meta( $pauta, '_tpop_status', 'rejeitada' );
				delete_post_meta( $pauta, '_tpop_post_id' );
				tpop_log( $pauta, 'Texto rejeitado na revisão' . ( $why ? ': ' . $why : '.' ) );
			}
			tpop_activity( 'revisao', $user->display_name . ' rejeitou “' . $title . '”.' );
			tpop_flash( 'Texto rejeitado e enviado para a lixeira.', 'success' );
		} elseif ( 'approve' === $do || 'publish' === $do ) {
			$issues = array();
			if ( ! tpop_parse_sources( get_post_meta( $post_id, '_tpop_fontes_texto', true ) ) ) {
				$issues[] = 'registre ao menos uma fonte';
			}
			if ( false !== stripos( (string) get_post_field( 'post_content', $post_id ), '[PREENCHER' ) ) {
				$issues[] = 'remova os trechos “[PREENCHER…]”';
			}
			if ( empty( $_POST['confirm'] ) ) {
				$issues[] = 'marque a caixa confirmando que conferiu os pontos a checar';
			}
			if ( 'publish' === $do && ! current_user_can( 'publish_post', $post_id ) ) {
				$issues[] = 'seu perfil não pode publicar';
			}
			if ( $issues ) {
				tpop_flash( '<strong>Não foi possível concluir:</strong> ' . esc_html( implode( '; ', $issues ) ) . '.', 'error' );
				wp_safe_redirect( $back );
				exit;
			}
			update_post_meta( $post_id, '_tpop_revisado', '1' );
			update_post_meta( $post_id, '_tpop_revisado_por', $user->display_name );
			update_post_meta( $post_id, '_tpop_revisado_em', current_time( 'mysql' ) );
			update_post_meta( $post_id, '_tpop_verificado_em', current_time( 'Y-m-d' ) );
			if ( $pauta ) {
				update_post_meta( $pauta, '_tpop_revisor', $user->ID );
				update_post_meta( $pauta, '_tpop_status', 'aprovada' );
				tpop_log( $pauta, 'Revisão humana concluída por ' . $user->display_name . '.' );
			}
			if ( 'publish' === $do ) {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
				tpop_flash( 'Publicado: ' . esc_html( $title ), 'success' );
			} else {
				tpop_flash( 'Revisão aprovada. O texto está pronto para publicar quando você quiser.', 'success' );
			}
			tpop_activity( 'revisao', $user->display_name . ' aprovou “' . $title . '”.' . ( 'publish' === $do ? ' Publicado.' : '' ) );
		}
		wp_safe_redirect( $back );
		exit;
	}
);

/* ------------------------------------------------------------------
 * Relatório da IA dentro do editor do artigo
 * ---------------------------------------------------------------- */

add_action(
	'add_meta_boxes_post',
	function ( $post ) {
		if ( get_post_meta( $post->ID, '_tpop_ai_report', true ) ) {
			add_meta_box( 'tpop_ai_report', 'Relatório da IA — o que conferir antes de publicar', 'tpop_ai_report_box', 'post', 'side', 'high' );
		}
	}
);

function tpop_confidence_pill( $c ) {
	$map = array( 'alta' => array( 'Confiança alta', 'ok' ), 'media' => array( 'Confiança média', 'warn' ), 'baixa' => array( 'Confiança baixa', 'err' ) );
	$m   = isset( $map[ $c ] ) ? $map[ $c ] : $map['media'];
	return tpop_pill( $m[0], $m[1] );
}

/** Termômetro visual da qualidade; alerta editorial, nunca aprovação automática. */
function tpop_quality_meter_html( $quality ) {
	if ( ! is_array( $quality ) || ! isset( $quality['score'] ) ) {
		return '';
	}
	$score = max( 0, min( 100, (int) $quality['score'] ) );
	$level = $score >= 75 ? 'Bom' : ( $score >= 50 ? 'Atenção' : 'Baixo' );
	$tone  = $score >= 75 ? 'good' : ( $score >= 50 ? 'warn' : 'low' );
	$labels = array( 'fonte' => 'Fonte', 'dados' => 'Dados', 'corroboracao' => 'Outras fontes', 'auditoria' => 'Conferência', 'ia' => 'Sinal da IA' );
	$parts  = array();
	if ( ! empty( $quality['components'] ) && is_array( $quality['components'] ) ) {
		foreach ( $labels as $key => $label ) {
			if ( isset( $quality['components'][ $key ] ) ) {
				$parts[] = $label . ' ' . max( 0, min( 100, (int) $quality['components'][ $key ] ) );
			}
		}
	}
	$html  = '<div class="tpop-quality tpop-quality--' . esc_attr( $tone ) . '" role="meter" aria-label="Termômetro de qualidade editorial" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( $score ) . '"><div class="tpop-quality__top"><strong>Termômetro de qualidade</strong><span>' . esc_html( $score . '/100 · ' . $level ) . '</span></div><div class="tpop-quality__track"><span style="width:' . esc_attr( $score ) . '%"></span></div>';
	if ( $parts ) {
		$html .= '<small class="tpop-quality__parts">' . esc_html( implode( ' · ', $parts ) ) . '</small><br>';
	}
	$html .= '<small>Sinal de apoio à revisão; não confirma a veracidade do conteúdo.</small></div>';
	return $html;
}

function tpop_ai_report_box( $post ) {
	$r = get_post_meta( $post->ID, '_tpop_ai_report', true );
	if ( ! is_array( $r ) ) {
		return;
	}
	echo '<p>' . tpop_confidence_pill( isset( $r['confidence'] ) ? $r['confidence'] : 'media' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( ! empty( $r['quality'] ) ) {
		echo tpop_quality_meter_html( $r['quality'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( ! empty( $r['flags'] ) ) {
		echo '<p><strong>Conferir:</strong></p><ul style="margin:0 0 8px 16px;list-style:disc">';
		foreach ( $r['flags'] as $f ) {
			echo '<li>' . esc_html( $f ) . '</li>';
		}
		echo '</ul>';
	} else {
		echo '<p>Nenhum alerta automático. Confira mesmo assim.</p>';
	}
	if ( ! empty( $r['notes'] ) ) {
		echo '<p style="color:#5b6a62;font-size:12px">' . esc_html( implode( ' ', $r['notes'] ) ) . '</p>';
	}
	echo '<p style="color:#5b6a62;font-size:12px">Provedor: ' . esc_html( isset( $r['provider'] ) ? $r['provider'] : 'openai' ) . ' · Modelo: ' . esc_html( isset( $r['model'] ) ? $r['model'] : '' ) . '</p>';
	echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=tpop-revisao' ) ) . '">Abrir a fila de revisão</a></p>';
}

/* ------------------------------------------------------------------
 * Tela: Fila de revisão
 * ---------------------------------------------------------------- */

function tpop_review_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$q = new WP_Query( tpop_review_query_args() );
	echo '<div class="wrap tpop-ui">';
	tpop_ui_header(
		'Fila de revisão',
		'Textos escritos pela IA ou enviados por automações. Nada aqui foi publicado: a decisão é sua.',
		'<a class="button" href="' . esc_url( admin_url( 'admin.php?page=tpop-painel' ) ) . '">Voltar à visão geral</a>'
	);

	if ( ! $q->have_posts() ) {
		echo '<div class="tpop-card tpop-empty"><span class="dashicons dashicons-yes-alt"></span><h2>Fila vazia</h2><p>Nenhum texto aguardando revisão. Quando o fluxo gerar novos textos, eles aparecem aqui.</p></div></div>';
		return;
	}

	echo '<div class="tpop-review">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$id      = get_the_ID();
		$report  = get_post_meta( $id, '_tpop_ai_report', true );
		$report  = is_array( $report ) ? $report : array();
		$pauta   = (int) get_post_meta( $id, '_tpop_pauta_id', true );
		$score   = $pauta ? (int) get_post_meta( $pauta, '_tpop_score', true ) : 0;
		$sources = tpop_parse_sources( get_post_meta( $id, '_tpop_fontes_texto', true ) );
		$cats    = get_the_category( $id );
		$words   = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $id ) ) );
		$origin  = get_post_meta( $id, '_tpop_origem', true );
		?>
		<article class="tpop-rv">
			<div class="tpop-rv__head">
				<?php echo tpop_pill( 'pauta_ia' === $origin ? 'Escrito pela IA' : 'Enviado por automação', 'dark' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php echo isset( $report['confidence'] ) ? ' ' . tpop_confidence_pill( $report['confidence'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<h2><?php the_title(); ?></h2>
				<div class="tpop-rv__meta">
					<span><?php echo esc_html( $cats ? $cats[0]->name : 'Sem categoria' ); ?></span>
					<span><?php echo esc_html( $words ); ?> palavras</span>
					<span>Criado <?php echo esc_html( human_time_diff( get_post_time( 'U', true ), time() ) ); ?> atrás</span>
					<?php if ( $score ) : ?><span>Pontuação da pauta: <?php echo esc_html( $score ); ?>/100</span><?php endif; ?>
				</div>
				<?php if ( ! empty( $report['quality'] ) ) { echo tpop_quality_meter_html( $report['quality'] ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php if ( has_excerpt() ) : ?><p style="margin:10px 0 0;color:#3d4a43"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
			</div>

			<div class="tpop-rv__body">
				<div class="tpop-rv__flags">
					<h4>Pontos a checar</h4>
					<?php if ( ! empty( $report['flags'] ) ) : ?>
						<ul><?php foreach ( $report['flags'] as $f ) { echo '<li>' . esc_html( $f ) . '</li>'; } ?></ul>
					<?php else : ?>
						<p style="margin:0;font-size:13.5px">Nenhum alerta automático. Confira os fatos mesmo assim.</p>
					<?php endif; ?>
				</div>
				<div class="tpop-rv__src">
					<h4>Fontes usadas</h4>
					<?php if ( $sources ) : ?>
						<ul><?php foreach ( $sources as $s ) { printf( '<li><a href="%s" target="_blank" rel="noopener nofollow">%s</a></li>', esc_url( $s['url'] ), esc_html( $s['nome'] ) ); } ?></ul>
					<?php else : ?>
						<p style="margin:0;font-size:13.5px;color:#b3261e">Nenhuma fonte registrada. Adicione antes de aprovar.</p>
					<?php endif; ?>
					<?php if ( ! empty( $report['notes'] ) ) : ?><p style="margin:8px 0 0;font-size:12px;color:#5b6a62"><?php echo esc_html( implode( ' ', $report['notes'] ) ); ?></p><?php endif; ?>
				</div>
			</div>

			<div class="tpop-rv__foot">
				<a class="button" href="<?php echo esc_url( get_edit_post_link( $id, 'raw' ) ); ?>">Abrir e editar o texto</a>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'tpop_review_' . $id ); ?>
					<input type="hidden" name="action" value="tpop_review_action">
					<input type="hidden" name="post_id" value="<?php echo esc_attr( $id ); ?>">
					<label style="font-size:13px"><input type="checkbox" name="confirm" value="1"> Li o texto e conferi os pontos acima</label>
					<button class="button" name="do" value="approve">Aprovar revisão</button>
					<?php if ( current_user_can( 'publish_post', $id ) ) : ?><button class="button button-primary" name="do" value="publish">Aprovar e publicar</button><?php endif; ?>
					<input type="text" name="why" placeholder="Motivo (opcional)" style="width:160px">
					<button class="button button-link-delete" name="do" value="reject" onclick="return confirm('Rejeitar e enviar este texto para a lixeira?');">Rejeitar</button>
				</form>
			</div>
		</article>
		<?php
	}
	wp_reset_postdata();
	echo '</div></div>';
}
