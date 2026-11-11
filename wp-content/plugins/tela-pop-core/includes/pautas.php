<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Metabox principal da pauta
 * ---------------------------------------------------------------- */

add_action(
	'add_meta_boxes_tpop_pauta',
	function () {
		add_meta_box( 'tpop_pauta_editorial', 'Pauta: status, fontes e pontuação', 'tpop_pauta_box', 'tpop_pauta', 'normal', 'high' );
		add_meta_box( 'tpop_pauta_trend', 'Tendência (Google Trends)', 'tpop_pauta_trend_box', 'tpop_pauta', 'normal' );
		add_meta_box( 'tpop_pauta_log', 'Histórico', 'tpop_pauta_log_box', 'tpop_pauta', 'side' );
	}
);

function tpop_pauta_meta( $post_id, $key, $default = '' ) {
	$v = get_post_meta( $post_id, $key, true );
	return '' === $v ? $default : $v;
}

function tpop_pauta_box( $post ) {
	wp_nonce_field( 'tpop_pauta_save', 'tpop_pauta_nonce' );
	$id      = $post->ID;
	$status  = tpop_pauta_meta( $id, '_tpop_status', 'nova_pauta' );
	$score   = (int) tpop_pauta_meta( $id, '_tpop_score', 0 );
	$prio    = tpop_priority( $score );
	$warning = $post->post_status === 'auto-draft' ? '' : tpop_pauta_source_warning( $id );
	$fontes  = get_posts( array( 'post_type' => 'tpop_fonte', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
	$fonte_i = (int) tpop_pauta_meta( $id, '_tpop_fonte_id', 0 );
	$club    = tpop_pauta_meta( $id, '_tpop_club', '' );
	$modelo  = tpop_pauta_meta( $id, '_tpop_modelo', 'noticia' );
	$post_id = (int) tpop_pauta_meta( $id, '_tpop_post_id', 0 );
	$origin_ts = absint( tpop_pauta_meta( $id, '_tpop_ts_origem', 0 ) );
	$fatos = tpop_pauta_meta( $id, '_tpop_fatos_redacao' );
	$age_error = tpop_pauta_news_age_error( $id );
	?>
	<p>
		<strong>Pontuação: <?php echo esc_html( $score ); ?>/100</strong> — <?php echo esc_html( $prio[1] ); ?>
		<?php if ( (int) $fonte_i && $origin_ts ) : ?>
			<br><strong>Data publicada pela fonte:</strong> <?php echo esc_html( wp_date( 'd/m/Y H:i', $origin_ts ) ); ?>
		<?php elseif ( (int) $fonte_i ) : ?>
			<br><strong>Data publicada pela fonte:</strong> não informada pelo feed
		<?php endif; ?>
		<?php if ( $warning ) : ?>
			<br><span style="color:#8a5a00"><strong>Alerta de qualidade:</strong> <?php echo esc_html( $warning ); ?></span>
		<?php endif; ?>
		<?php if ( $age_error ) : ?>
			<br><span style="color:#b42318"><strong>Fora da janela de notícias atuais:</strong> <?php echo esc_html( $age_error ); ?> Pautas RSS sem data ou com mais de 72 horas não podem gerar rascunho como notícia atual.</span>
		<?php endif; ?>
	</p>

	<table class="form-table">
		<tr><th><label for="tpop_status">Status editorial</label></th>
			<td><select id="tpop_status" name="tpop_status"><?php foreach ( tpop_statuses() as $k => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $status, $k, false ), esc_html( $l ) ); } ?></select></td></tr>
		<tr><th><label for="tpop_keyword">Palavra-chave</label></th>
			<td><input type="text" class="regular-text" id="tpop_keyword" name="tpop_keyword" value="<?php echo esc_attr( tpop_pauta_meta( $id, '_tpop_keyword' ) ); ?>"></td></tr>
		<tr><th><label for="tpop_club">Filme, série ou franquia</label></th>
			<td><input type="text" class="regular-text" id="tpop_club" name="tpop_club" value="<?php echo esc_attr( get_post_meta( $id, '_tpop_club', true ) ); ?>" placeholder="Ex.: Duna, The Last of Us"></td></tr>
		<tr><th><label for="tpop_modelo">Modelo editorial</label></th>
			<td><select id="tpop_modelo" name="tpop_modelo"><?php foreach ( tpop_modelos() as $k => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $modelo, $k, false ), esc_html( $l ) ); } ?></select></td></tr>
		<tr><th><label for="tpop_fonte_id">Fonte cadastrada</label></th>
			<td><select id="tpop_fonte_id" name="tpop_fonte_id"><option value="0">—</option><?php foreach ( $fontes as $f ) { printf( '<option value="%d"%s>%s</option>', (int) $f->ID, selected( $fonte_i, $f->ID, false ), esc_html( $f->post_title ) ); } ?></select></td></tr>
		<tr><th><label for="tpop_fontes_texto">Links de referência</label></th>
			<td><textarea id="tpop_fontes_texto" name="tpop_fontes_texto" rows="4" class="large-text code" placeholder="Nome da fonte | https://..."><?php echo esc_textarea( tpop_pauta_meta( $id, '_tpop_fontes_texto' ) ); ?></textarea>
			<p class="description">Uma por linha. Estes links serão copiados para o rascunho e aparecerão como “Fontes consultadas”.</p></td></tr>
		<tr><th><label for="tpop_fatos_redacao">Fatos confirmados pela redação</label></th>
			<td><textarea id="tpop_fatos_redacao" name="tpop_fatos_redacao" rows="5" class="large-text" maxlength="5000" placeholder="Registre fatos verificados, com a fonte ou link ao lado de cada um."><?php echo esc_textarea( $fatos ); ?></textarea>
			<p class="description">Será incluído no dossiê da IA como anotação editorial. Registre somente fatos verificados e mantenha a referência no campo de links. Para evitar que a IA preencha lacunas, a geração exige pelo menos 700 caracteres de evidências; se o RSS for curto, consulte a fonte e acrescente fatos confirmados. O texto gerado deve ser original, não uma cópia da matéria consultada.</p></td></tr>
		<tr><th>Fonte confiável</th>
			<td><label><input type="checkbox" name="tpop_fonte_confirmada" value="1" <?php checked( tpop_pauta_meta( $id, '_tpop_fonte_confirmada', '0' ), '1' ); ?>> Confirmei manualmente que há fonte confiável para esta pauta</label></td></tr>
		<tr><th><label for="tpop_verificado_em">Última verificação</label></th>
			<td><input type="date" id="tpop_verificado_em" name="tpop_verificado_em" value="<?php echo esc_attr( tpop_pauta_meta( $id, '_tpop_verificado_em' ) ); ?>"></td></tr>
		<tr><th><label for="tpop_revisor">Responsável pela revisão</label></th>
			<td><?php wp_dropdown_users( array( 'name' => 'tpop_revisor', 'selected' => (int) tpop_pauta_meta( $id, '_tpop_revisor', 0 ), 'show_option_none' => '—', 'option_none_value' => 0, 'capability' => 'edit_posts' ) ); ?></td></tr>
	</table>

	<h4>Critérios de pontuação (0–100)</h4>
	<table class="form-table">
		<?php foreach ( tpop_criteria() as $k => $c ) : ?>
			<tr><th><label for="tpop_c_<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $c['label'] ); ?> <small>(peso <?php echo (int) $c['weight']; ?>%)</small></label></th>
				<td><input type="number" min="0" max="100" id="tpop_c_<?php echo esc_attr( $k ); ?>" name="tpop_c_<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( tpop_pauta_meta( $id, '_tpop_c_' . $k, '' ) ); ?>"></td></tr>
		<?php endforeach; ?>
	</table>
	<p class="description">Relevância, confiabilidade e velocidade são sugeridas automaticamente ao importar de um feed. Crescimento, originalidade e busca devem ser avaliados pela equipe. Se “Crescimento” ficar vazio e houver interesse relativo do Trends, ele é usado como valor inicial.</p>

	<?php
	$resumo = tpop_pauta_meta( $id, '_tpop_resumo_fonte' );
	$corrob = get_post_meta( $id, '_tpop_corrob', true );
	if ( $resumo || $corrob || $fatos ) :
		?>
		<h4>Dados coletados da fonte (usados pela IA)</h4>
		<?php if ( $resumo ) : ?><p style="background:#f4f2ea;padding:10px 12px;border-radius:10px"><?php echo esc_html( $resumo ); ?></p><?php endif; ?>
		<?php if ( $fatos ) : ?><p><strong>Fatos confirmados pela redação:</strong><br><?php echo nl2br( esc_html( $fatos ) ); ?></p><?php endif; ?>
		<?php if ( is_array( $corrob ) && $corrob ) : ?>
			<p><strong>Coberturas relacionadas (confirme se tratam do mesmo fato):</strong></p>
			<ul style="list-style:disc;margin-left:18px"><?php foreach ( $corrob as $c ) { printf( '<li><a href="%s" target="_blank" rel="noopener">%s</a> — %s</li>', esc_url( $c['url'] ), esc_html( $c['nome'] ), esc_html( $c['titulo'] ) ); } ?></ul>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $post_id ) : ?>
		<p><a class="button" href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">Abrir texto gerado</a></p>
	<?php elseif ( 'auto-draft' !== $post->post_status && ! $age_error ) : ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpop_gerar_rascunho&pauta_id=' . $id ), 'tpop_gerar_' . $id ) ); ?>">Escrever com IA</a>
			<span class="description">Gera um rascunho com o que foi extraído do RSS e envia para a <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpop-revisao' ) ); ?>">fila de revisão</a>. A qualidade da fonte e a quantidade de dados aparecem como alertas, sem bloquear o rascunho. Confira tudo antes de publicar.</span>
		</p>
	<?php endif; ?>
	<?php
}

function tpop_pauta_trend_box( $post ) {
	$id      = $post->ID;
	$periods = array( '7d' => '7 dias', '30d' => '30 dias', '90d' => '90 dias', '12m' => '12 meses' );
	$period  = tpop_pauta_meta( $id, '_tpop_trend_period', '7d' );
	?>
	<p class="description">Região fixa: Brasil. O Google Trends mostra interesse relativo (0–100), não volume absoluto de buscas. Registre aqui o que foi observado em <a href="https://trends.google.com/trends/" target="_blank" rel="noopener">trends.google.com</a>.</p>
	<table class="form-table">
		<tr><th><label for="tpop_trend_term">Termo pesquisado</label></th><td><input type="text" class="regular-text" id="tpop_trend_term" name="tpop_trend_term" value="<?php echo esc_attr( tpop_pauta_meta( $id, '_tpop_trend_term' ) ); ?>"></td></tr>
		<tr><th><label for="tpop_trend_period">Período</label></th><td><select id="tpop_trend_period" name="tpop_trend_period"><?php foreach ( $periods as $k => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $period, $k, false ), esc_html( $l ) ); } ?></select></td></tr>
		<tr><th><label for="tpop_trend_interest">Interesse relativo (0–100)</label></th><td><input type="number" min="0" max="100" id="tpop_trend_interest" name="tpop_trend_interest" value="<?php echo esc_attr( tpop_pauta_meta( $id, '_tpop_trend_interest' ) ); ?>"></td></tr>
		<tr><th><label for="tpop_trend_related">Consultas relacionadas</label></th><td><textarea id="tpop_trend_related" name="tpop_trend_related" rows="3" class="large-text"><?php echo esc_textarea( tpop_pauta_meta( $id, '_tpop_trend_related' ) ); ?></textarea></td></tr>
		<tr><th><label for="tpop_trend_collected">Data da coleta</label></th><td><input type="date" id="tpop_trend_collected" name="tpop_trend_collected" value="<?php echo esc_attr( tpop_pauta_meta( $id, '_tpop_trend_collected' ) ); ?>"></td></tr>
	</table>
	<?php
}

function tpop_pauta_log_box( $post ) {
	$rows = tpop_get_log( $post->ID );
	if ( ! $rows ) {
		echo '<p>Sem registros.</p>';
		return;
	}
	echo '<ul style="margin:0">';
	foreach ( $rows as $r ) {
		printf(
			'<li><small>%s — %s</small><br>%s</li>',
			esc_html( mysql2date( 'd/m H:i', $r['time'] ) ),
			esc_html( $r['user'] ),
			esc_html( $r['message'] )
		);
	}
	echo '</ul>';
}

/* ------------------------------------------------------------------
 * Salvamento
 * ---------------------------------------------------------------- */

add_action(
	'save_post_tpop_pauta',
	function ( $post_id ) {
		if ( ! isset( $_POST['tpop_pauta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tpop_pauta_nonce'] ) ), 'tpop_pauta_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$in = function ( $key ) {
			return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		};

		$old_status = get_post_meta( $post_id, '_tpop_status', true );
		$new_status = sanitize_key( $in( 'tpop_status' ) );
		$statuses   = tpop_statuses();
		if ( ! isset( $statuses[ $new_status ] ) ) {
			$new_status = $old_status ? $old_status : 'nova_pauta';
		}

		$club   = sanitize_text_field( $in( 'tpop_club' ) );
		$modelo = sanitize_key( $in( 'tpop_modelo' ) );
		$models = tpop_modelos();
		$period = sanitize_key( $in( 'tpop_trend_period' ) );

		update_post_meta( $post_id, '_tpop_keyword', sanitize_text_field( $in( 'tpop_keyword' ) ) );
		update_post_meta( $post_id, '_tpop_club', $club );
		update_post_meta( $post_id, '_tpop_modelo', isset( $models[ $modelo ] ) ? $modelo : 'noticia' );
		update_post_meta( $post_id, '_tpop_fonte_id', absint( $in( 'tpop_fonte_id' ) ) );
		update_post_meta( $post_id, '_tpop_fontes_texto', tpop_sources_to_text( tpop_parse_sources( $in( 'tpop_fontes_texto' ) ) ) );
		update_post_meta( $post_id, '_tpop_fatos_redacao', mb_substr( sanitize_textarea_field( $in( 'tpop_fatos_redacao' ) ), 0, 5000 ) );
		update_post_meta( $post_id, '_tpop_fonte_confirmada', isset( $_POST['tpop_fonte_confirmada'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_tpop_verificado_em', sanitize_text_field( $in( 'tpop_verificado_em' ) ) );
		update_post_meta( $post_id, '_tpop_revisor', absint( $in( 'tpop_revisor' ) ) );

		update_post_meta( $post_id, '_tpop_trend_term', sanitize_text_field( $in( 'tpop_trend_term' ) ) );
		update_post_meta( $post_id, '_tpop_trend_period', in_array( $period, array( '7d', '30d', '90d', '12m' ), true ) ? $period : '7d' );
		$interest = $in( 'tpop_trend_interest' );
		update_post_meta( $post_id, '_tpop_trend_interest', '' === $interest ? '' : max( 0, min( 100, absint( $interest ) ) ) );
		update_post_meta( $post_id, '_tpop_trend_related', sanitize_textarea_field( $in( 'tpop_trend_related' ) ) );
		update_post_meta( $post_id, '_tpop_trend_collected', sanitize_text_field( $in( 'tpop_trend_collected' ) ) );

		$values = array();
		foreach ( array_keys( tpop_criteria() ) as $k ) {
			$raw = $in( 'tpop_c_' . $k );
			if ( '' === $raw && 'crescimento' === $k && '' !== $interest ) {
				$raw = $interest;
			}
			$values[ $k ] = '' === $raw ? 0 : max( 0, min( 100, absint( $raw ) ) );
			update_post_meta( $post_id, '_tpop_c_' . $k, $values[ $k ] );
		}
		$score = tpop_calc_score( $values );
		update_post_meta( $post_id, '_tpop_score', $score );

		update_post_meta( $post_id, '_tpop_status', $new_status );
		if ( $new_status !== $old_status ) {
			tpop_log( $post_id, 'Status: ' . tpop_status_label( $old_status ) . ' → ' . tpop_status_label( $new_status ) . ' (pontuação ' . $score . ').' );
		}
	}
);

/* ------------------------------------------------------------------
 * Lista de pautas no admin: colunas, ordenação e filtro por status
 * ---------------------------------------------------------------- */

add_filter(
	'manage_tpop_pauta_posts_columns',
	function () {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => 'Título provisório',
			'tpop_score' => 'Pontuação',
			'tpop_stat'  => 'Status',
			'tpop_club'  => 'Clube',
			'tpop_fonte' => 'Fonte',
			'date'      => 'Data',
		);
	}
);

add_action(
	'manage_tpop_pauta_posts_custom_column',
	function ( $col, $post_id ) {
		switch ( $col ) {
			case 'tpop_score':
				$s = (int) get_post_meta( $post_id, '_tpop_score', true );
				$p = tpop_priority( $s );
				$tones = array( 'alta' => 'ok', 'intermediaria' => 'warn', 'baixa' => 'mute' );
				echo '<strong style="font-size:15px">' . (int) $s . '</strong> ' . tpop_pill( $p[1], $tones[ $p[0] ] ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'tpop_stat':
				$st = get_post_meta( $post_id, '_tpop_status', true );
				echo tpop_pill( tpop_status_label( $st ), tpop_status_tone( $st ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'tpop_club':
				echo esc_html( get_post_meta( $post_id, '_tpop_club', true ) ?: '—' );
				break;
			case 'tpop_fonte':
				$f = (int) get_post_meta( $post_id, '_tpop_fonte_id', true );
				echo $f ? esc_html( get_the_title( $f ) ) : '—';
				break;
		}
	},
	10,
	2
);

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || 'tpop_pauta' !== $q->get( 'post_type' ) ) {
			return;
		}
		if ( ! $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_tpop_score' );
			$q->set( 'orderby', 'meta_value_num' );
			$q->set( 'order', 'DESC' );
		}
		if ( ! empty( $_GET['tpop_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['tpop_status'] ) );
			$all_status = tpop_statuses();
			if ( isset( $all_status[ $status ] ) ) {
				$q->set( 'meta_query', array( array( 'key' => '_tpop_status', 'value' => $status ) ) );
			}
		}
	}
);

add_action(
	'restrict_manage_posts',
	function ( $post_type ) {
		if ( 'tpop_pauta' !== $post_type ) {
			return;
		}
		$current = isset( $_GET['tpop_status'] ) ? sanitize_key( wp_unslash( $_GET['tpop_status'] ) ) : '';
		echo '<select name="tpop_status"><option value="">Todos os status</option>';
		foreach ( tpop_statuses() as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $current, $k, false ), esc_html( $l ) );
		}
		echo '</select>';
	}
);
