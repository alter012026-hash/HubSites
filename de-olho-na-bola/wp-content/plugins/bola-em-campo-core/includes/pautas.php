<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Metabox principal da pauta
 * ---------------------------------------------------------------- */

add_action(
	'add_meta_boxes_bec_pauta',
	function () {
		add_meta_box( 'bec_pauta_editorial', 'Pauta: status, fontes e pontuação', 'bec_pauta_box', 'bec_pauta', 'normal', 'high' );
		add_meta_box( 'bec_pauta_trend', 'Tendência (Google Trends)', 'bec_pauta_trend_box', 'bec_pauta', 'normal' );
		add_meta_box( 'bec_pauta_log', 'Histórico', 'bec_pauta_log_box', 'bec_pauta', 'side' );
	}
);

function bec_pauta_meta( $post_id, $key, $default = '' ) {
	$v = get_post_meta( $post_id, $key, true );
	return '' === $v ? $default : $v;
}

function bec_pauta_box( $post ) {
	wp_nonce_field( 'bec_pauta_save', 'bec_pauta_nonce' );
	$id      = $post->ID;
	$status  = bec_pauta_meta( $id, '_bec_status', 'nova_pauta' );
	$score   = (int) bec_pauta_meta( $id, '_bec_score', 0 );
	$prio    = bec_priority( $score );
	$warning = $post->post_status === 'auto-draft' ? '' : bec_pauta_source_warning( $id );
	$fontes  = get_posts( array( 'post_type' => 'bec_fonte', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) );
	$fonte_i = (int) bec_pauta_meta( $id, '_bec_fonte_id', 0 );
	$club    = bec_pauta_meta( $id, '_bec_club', '' );
	$modelo  = bec_pauta_meta( $id, '_bec_modelo', 'noticia' );
	$post_id = (int) bec_pauta_meta( $id, '_bec_post_id', 0 );
	$origin_ts = absint( bec_pauta_meta( $id, '_bec_ts_origem', 0 ) );
	$fatos = bec_pauta_meta( $id, '_bec_fatos_redacao' );
	$age_error = bec_pauta_news_age_error( $id );
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
		<tr><th><label for="bec_status">Status editorial</label></th>
			<td><select id="bec_status" name="bec_status"><?php foreach ( bec_statuses() as $k => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $status, $k, false ), esc_html( $l ) ); } ?></select></td></tr>
		<tr><th><label for="bec_keyword">Palavra-chave</label></th>
			<td><input type="text" class="regular-text" id="bec_keyword" name="bec_keyword" value="<?php echo esc_attr( bec_pauta_meta( $id, '_bec_keyword' ) ); ?>"></td></tr>
		<tr><th><label for="bec_club">Clube relacionado</label></th>
			<td><select id="bec_club" name="bec_club"><option value="">—</option><?php foreach ( bec_clubs() as $slug => $c ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $club, $slug, false ), esc_html( $c['name'] ) ); } ?></select></td></tr>
		<tr><th><label for="bec_modelo">Modelo editorial</label></th>
			<td><select id="bec_modelo" name="bec_modelo"><?php foreach ( bec_modelos() as $k => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $modelo, $k, false ), esc_html( $l ) ); } ?></select></td></tr>
		<tr><th><label for="bec_fonte_id">Fonte cadastrada</label></th>
			<td><select id="bec_fonte_id" name="bec_fonte_id"><option value="0">—</option><?php foreach ( $fontes as $f ) { printf( '<option value="%d"%s>%s</option>', (int) $f->ID, selected( $fonte_i, $f->ID, false ), esc_html( $f->post_title ) ); } ?></select></td></tr>
		<tr><th><label for="bec_fontes_texto">Links de referência</label></th>
			<td><textarea id="bec_fontes_texto" name="bec_fontes_texto" rows="4" class="large-text code" placeholder="Nome da fonte | https://..."><?php echo esc_textarea( bec_pauta_meta( $id, '_bec_fontes_texto' ) ); ?></textarea>
			<p class="description">Uma por linha. Estes links serão copiados para o rascunho e aparecerão como “Fontes consultadas”.</p></td></tr>
		<tr><th><label for="bec_fatos_redacao">Fatos confirmados pela redação</label></th>
			<td><textarea id="bec_fatos_redacao" name="bec_fatos_redacao" rows="5" class="large-text" maxlength="5000" placeholder="Registre fatos verificados, com a fonte ou link ao lado de cada um."><?php echo esc_textarea( $fatos ); ?></textarea>
			<p class="description">Opcional. Será incluído no dossiê da IA como anotação editorial. Registre somente fatos verificados e mantenha a referência no campo de links.</p></td></tr>
		<tr><th>Fonte confiável</th>
			<td><label><input type="checkbox" name="bec_fonte_confirmada" value="1" <?php checked( bec_pauta_meta( $id, '_bec_fonte_confirmada', '0' ), '1' ); ?>> Confirmei manualmente que há fonte confiável para esta pauta</label></td></tr>
		<tr><th><label for="bec_verificado_em">Última verificação</label></th>
			<td><input type="date" id="bec_verificado_em" name="bec_verificado_em" value="<?php echo esc_attr( bec_pauta_meta( $id, '_bec_verificado_em' ) ); ?>"></td></tr>
		<tr><th><label for="bec_revisor">Responsável pela revisão</label></th>
			<td><?php wp_dropdown_users( array( 'name' => 'bec_revisor', 'selected' => (int) bec_pauta_meta( $id, '_bec_revisor', 0 ), 'show_option_none' => '—', 'option_none_value' => 0, 'capability' => 'edit_posts' ) ); ?></td></tr>
	</table>

	<h4>Critérios de pontuação (0–100)</h4>
	<table class="form-table">
		<?php foreach ( bec_criteria() as $k => $c ) : ?>
			<tr><th><label for="bec_c_<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $c['label'] ); ?> <small>(peso <?php echo (int) $c['weight']; ?>%)</small></label></th>
				<td><input type="number" min="0" max="100" id="bec_c_<?php echo esc_attr( $k ); ?>" name="bec_c_<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( bec_pauta_meta( $id, '_bec_c_' . $k, '' ) ); ?>"></td></tr>
		<?php endforeach; ?>
	</table>
	<p class="description">Relevância, confiabilidade e velocidade são sugeridas automaticamente ao importar de um feed. Crescimento, originalidade e busca devem ser avaliados pela equipe. Se “Crescimento” ficar vazio e houver interesse relativo do Trends, ele é usado como valor inicial.</p>

	<?php
	$resumo = bec_pauta_meta( $id, '_bec_resumo_fonte' );
	$corrob = get_post_meta( $id, '_bec_corrob', true );
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
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bec_gerar_rascunho&pauta_id=' . $id ), 'bec_gerar_' . $id ) ); ?>">Escrever com IA</a>
			<span class="description">Gera um rascunho com o que foi extraído do RSS e envia para a <a href="<?php echo esc_url( admin_url( 'admin.php?page=bec-revisao' ) ); ?>">fila de revisão</a>. A qualidade da fonte e a quantidade de dados aparecem como alertas, sem bloquear o rascunho. Confira tudo antes de publicar.</span>
		</p>
	<?php endif; ?>
	<?php
}

function bec_pauta_trend_box( $post ) {
	$id      = $post->ID;
	$periods = array( '7d' => '7 dias', '30d' => '30 dias', '90d' => '90 dias', '12m' => '12 meses' );
	$period  = bec_pauta_meta( $id, '_bec_trend_period', '7d' );
	?>
	<p class="description">Região fixa: Brasil. O Google Trends mostra interesse relativo (0–100), não volume absoluto de buscas. Registre aqui o que foi observado em <a href="https://trends.google.com/trends/" target="_blank" rel="noopener">trends.google.com</a>.</p>
	<table class="form-table">
		<tr><th><label for="bec_trend_term">Termo pesquisado</label></th><td><input type="text" class="regular-text" id="bec_trend_term" name="bec_trend_term" value="<?php echo esc_attr( bec_pauta_meta( $id, '_bec_trend_term' ) ); ?>"></td></tr>
		<tr><th><label for="bec_trend_period">Período</label></th><td><select id="bec_trend_period" name="bec_trend_period"><?php foreach ( $periods as $k => $l ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $period, $k, false ), esc_html( $l ) ); } ?></select></td></tr>
		<tr><th><label for="bec_trend_interest">Interesse relativo (0–100)</label></th><td><input type="number" min="0" max="100" id="bec_trend_interest" name="bec_trend_interest" value="<?php echo esc_attr( bec_pauta_meta( $id, '_bec_trend_interest' ) ); ?>"></td></tr>
		<tr><th><label for="bec_trend_related">Consultas relacionadas</label></th><td><textarea id="bec_trend_related" name="bec_trend_related" rows="3" class="large-text"><?php echo esc_textarea( bec_pauta_meta( $id, '_bec_trend_related' ) ); ?></textarea></td></tr>
		<tr><th><label for="bec_trend_collected">Data da coleta</label></th><td><input type="date" id="bec_trend_collected" name="bec_trend_collected" value="<?php echo esc_attr( bec_pauta_meta( $id, '_bec_trend_collected' ) ); ?>"></td></tr>
	</table>
	<?php
}

function bec_pauta_log_box( $post ) {
	$rows = bec_get_log( $post->ID );
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
	'save_post_bec_pauta',
	function ( $post_id ) {
		if ( ! isset( $_POST['bec_pauta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bec_pauta_nonce'] ) ), 'bec_pauta_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$in = function ( $key ) {
			return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		};

		$old_status = get_post_meta( $post_id, '_bec_status', true );
		$new_status = sanitize_key( $in( 'bec_status' ) );
		$statuses   = bec_statuses();
		if ( ! isset( $statuses[ $new_status ] ) ) {
			$new_status = $old_status ? $old_status : 'nova_pauta';
		}

		$clubs  = bec_clubs();
		$club   = sanitize_key( $in( 'bec_club' ) );
		$modelo = sanitize_key( $in( 'bec_modelo' ) );
		$models = bec_modelos();
		$period = sanitize_key( $in( 'bec_trend_period' ) );

		update_post_meta( $post_id, '_bec_keyword', sanitize_text_field( $in( 'bec_keyword' ) ) );
		update_post_meta( $post_id, '_bec_club', isset( $clubs[ $club ] ) ? $club : '' );
		update_post_meta( $post_id, '_bec_modelo', isset( $models[ $modelo ] ) ? $modelo : 'noticia' );
		update_post_meta( $post_id, '_bec_fonte_id', absint( $in( 'bec_fonte_id' ) ) );
		update_post_meta( $post_id, '_bec_fontes_texto', bec_sources_to_text( bec_parse_sources( $in( 'bec_fontes_texto' ) ) ) );
		update_post_meta( $post_id, '_bec_fatos_redacao', mb_substr( sanitize_textarea_field( $in( 'bec_fatos_redacao' ) ), 0, 5000 ) );
		update_post_meta( $post_id, '_bec_fonte_confirmada', isset( $_POST['bec_fonte_confirmada'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_bec_verificado_em', sanitize_text_field( $in( 'bec_verificado_em' ) ) );
		update_post_meta( $post_id, '_bec_revisor', absint( $in( 'bec_revisor' ) ) );

		update_post_meta( $post_id, '_bec_trend_term', sanitize_text_field( $in( 'bec_trend_term' ) ) );
		update_post_meta( $post_id, '_bec_trend_period', in_array( $period, array( '7d', '30d', '90d', '12m' ), true ) ? $period : '7d' );
		$interest = $in( 'bec_trend_interest' );
		update_post_meta( $post_id, '_bec_trend_interest', '' === $interest ? '' : max( 0, min( 100, absint( $interest ) ) ) );
		update_post_meta( $post_id, '_bec_trend_related', sanitize_textarea_field( $in( 'bec_trend_related' ) ) );
		update_post_meta( $post_id, '_bec_trend_collected', sanitize_text_field( $in( 'bec_trend_collected' ) ) );

		$values = array();
		foreach ( array_keys( bec_criteria() ) as $k ) {
			$raw = $in( 'bec_c_' . $k );
			if ( '' === $raw && 'crescimento' === $k && '' !== $interest ) {
				$raw = $interest;
			}
			$values[ $k ] = '' === $raw ? 0 : max( 0, min( 100, absint( $raw ) ) );
			update_post_meta( $post_id, '_bec_c_' . $k, $values[ $k ] );
		}
		$score = bec_calc_score( $values );
		update_post_meta( $post_id, '_bec_score', $score );

		update_post_meta( $post_id, '_bec_status', $new_status );
		if ( $new_status !== $old_status ) {
			bec_log( $post_id, 'Status: ' . bec_status_label( $old_status ) . ' → ' . bec_status_label( $new_status ) . ' (pontuação ' . $score . ').' );
		}
	}
);

/* ------------------------------------------------------------------
 * Lista de pautas no admin: colunas, ordenação e filtro por status
 * ---------------------------------------------------------------- */

add_filter(
	'manage_bec_pauta_posts_columns',
	function () {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => 'Título provisório',
			'bec_score' => 'Pontuação',
			'bec_stat'  => 'Status',
			'bec_club'  => 'Clube',
			'bec_fonte' => 'Fonte',
			'date'      => 'Data',
		);
	}
);

add_action(
	'manage_bec_pauta_posts_custom_column',
	function ( $col, $post_id ) {
		switch ( $col ) {
			case 'bec_score':
				$s = (int) get_post_meta( $post_id, '_bec_score', true );
				$p = bec_priority( $s );
				$tones = array( 'alta' => 'ok', 'intermediaria' => 'warn', 'baixa' => 'mute' );
				echo '<strong style="font-size:15px">' . (int) $s . '</strong> ' . bec_pill( $p[1], $tones[ $p[0] ] ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'bec_stat':
				$st = get_post_meta( $post_id, '_bec_status', true );
				echo bec_pill( bec_status_label( $st ), bec_status_tone( $st ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'bec_club':
				$clubs = bec_clubs();
				$c     = get_post_meta( $post_id, '_bec_club', true );
				echo isset( $clubs[ $c ] ) ? esc_html( $clubs[ $c ]['name'] ) : '—';
				break;
			case 'bec_fonte':
				$f = (int) get_post_meta( $post_id, '_bec_fonte_id', true );
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
		if ( ! is_admin() || ! $q->is_main_query() || 'bec_pauta' !== $q->get( 'post_type' ) ) {
			return;
		}
		if ( ! $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_bec_score' );
			$q->set( 'orderby', 'meta_value_num' );
			$q->set( 'order', 'DESC' );
		}
		if ( ! empty( $_GET['bec_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['bec_status'] ) );
			$all_status = bec_statuses();
			if ( isset( $all_status[ $status ] ) ) {
				$q->set( 'meta_query', array( array( 'key' => '_bec_status', 'value' => $status ) ) );
			}
		}
	}
);

add_action(
	'restrict_manage_posts',
	function ( $post_type ) {
		if ( 'bec_pauta' !== $post_type ) {
			return;
		}
		$current = isset( $_GET['bec_status'] ) ? sanitize_key( wp_unslash( $_GET['bec_status'] ) ) : '';
		echo '<select name="bec_status"><option value="">Todos os status</option>';
		foreach ( bec_statuses() as $k => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $current, $k, false ), esc_html( $l ) );
		}
		echo '</select>';
	}
);
