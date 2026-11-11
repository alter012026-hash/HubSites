<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------
 * Metabox "Fontes e verificação" nos artigos
 * ---------------------------------------------------------------- */

add_action(
	'add_meta_boxes_post',
	function () {
		add_meta_box( 'tpop_post_sources', 'Fontes e verificação (Tela Pop)', 'tpop_post_sources_box', 'post', 'normal', 'high' );
	}
);

function tpop_post_sources_box( $post ) {
	wp_nonce_field( 'tpop_post_save', 'tpop_post_nonce' );
	$text = get_post_meta( $post->ID, '_tpop_fontes_texto', true );
	$date = get_post_meta( $post->ID, '_tpop_verificado_em', true );
	$rev  = get_post_meta( $post->ID, '_tpop_revisado', true );
	?>
	<p><label for="tpop_fontes_texto_post"><strong>Fontes consultadas</strong> (uma por linha: <code>Nome | https://link</code>)</label></p>
	<textarea id="tpop_fontes_texto_post" name="tpop_fontes_texto_post" rows="4" class="large-text code"><?php echo esc_textarea( $text ); ?></textarea>
	<p><label for="tpop_verificado_em_post"><strong>Última verificação dos fatos:</strong></label>
		<input type="date" id="tpop_verificado_em_post" name="tpop_verificado_em_post" value="<?php echo esc_attr( $date ); ?>"></p>
	<p><label><input type="checkbox" name="tpop_revisado_post" value="1" <?php checked( $rev, '1' ); ?>>
		<strong>Fatos conferidos e revisão humana concluída</strong></label></p>
	<p class="description">Artigos criados a partir de pautas ou pela automação só podem ser publicados com ao menos uma fonte, a revisão marcada e sem o texto “[PREENCHER”. <strong>Salve o rascunho com as fontes antes de clicar em Publicar.</strong></p>
	<?php
}

add_action(
	'save_post_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['tpop_post_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tpop_post_nonce'] ) ), 'tpop_post_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$old = get_post_meta( $post_id, '_tpop_revisado', true );
		$raw = isset( $_POST['tpop_fontes_texto_post'] ) ? wp_unslash( $_POST['tpop_fontes_texto_post'] ) : '';
		update_post_meta( $post_id, '_tpop_fontes_texto', tpop_sources_to_text( tpop_parse_sources( $raw ) ) );
		$date = isset( $_POST['tpop_verificado_em_post'] ) ? sanitize_text_field( wp_unslash( $_POST['tpop_verificado_em_post'] ) ) : '';
		update_post_meta( $post_id, '_tpop_verificado_em', $date ? $date : current_time( 'Y-m-d' ) );
		$new = isset( $_POST['tpop_revisado_post'] ) ? '1' : '0';
		update_post_meta( $post_id, '_tpop_revisado', $new );

		if ( $new !== $old ) {
			$pauta = (int) get_post_meta( $post_id, '_tpop_pauta_id', true );
			if ( $pauta ) {
				update_post_meta( $pauta, '_tpop_status', '1' === $new ? 'aprovada' : 'em_revisao' );
				tpop_log( $pauta, '1' === $new ? 'Revisão humana concluída no artigo.' : 'Artigo voltou para revisão.' );
			}
		}
	}
);

/* ------------------------------------------------------------------
 * Trava de publicação (seção 7.4: "Bloquear publicação quando houver inconsistências")
 * ---------------------------------------------------------------- */

/** O artigo está sob controle editorial? Automatizados sempre; os demais se a opção estiver ligada. */
function tpop_post_is_controlled( $post_id ) {
	if ( get_post_meta( $post_id, '_tpop_origem', true ) || get_post_meta( $post_id, '_tpop_pauta_id', true ) ) {
		return true;
	}
	return '1' === (string) tpop_opt( 'enforce_all', '0' );
}

/** @return string[] lista de problemas; vazia quando pode publicar. */
function tpop_publish_issues( $post_id, $content = null ) {
	if ( ! tpop_post_is_controlled( $post_id ) ) {
		return array();
	}
	$issues = array();
	if ( null === $content ) {
		$content = get_post_field( 'post_content', $post_id );
	}
	if ( ! tpop_parse_sources( get_post_meta( $post_id, '_tpop_fontes_texto', true ) ) ) {
		$issues[] = 'Registre ao menos uma fonte consultada.';
	}
	if ( '1' !== get_post_meta( $post_id, '_tpop_revisado', true ) ) {
		$issues[] = 'Marque “Fatos conferidos e revisão humana concluída”.';
	}
	if ( false !== stripos( (string) $content, '[PREENCHER' ) ) {
		$issues[] = 'O texto ainda contém trechos “[PREENCHER…]”.';
	}
	return $issues;
}

/* Editor de blocos (REST): devolve erro visível ao usuário. */
add_filter(
	'rest_pre_insert_post',
	function ( $prepared, $request ) {
		$status = isset( $request['status'] ) ? $request['status'] : '';
		if ( ! in_array( $status, array( 'publish', 'future' ), true ) || empty( $prepared->ID ) ) {
			return $prepared;
		}
		if ( 'publish' === get_post_status( (int) $prepared->ID ) ) {
			return $prepared; // Edição de artigo já publicado: não derruba do ar.
		}
		$content = isset( $prepared->post_content ) ? $prepared->post_content : null;
		$issues  = tpop_publish_issues( (int) $prepared->ID, $content );
		if ( $issues ) {
			return new WP_Error( 'tpop_publicacao_bloqueada', 'Publicação bloqueada: ' . implode( ' ', $issues ), array( 'status' => 400 ) );
		}
		return $prepared;
	},
	10,
	2
);

/* Editor clássico e demais caminhos: volta para rascunho com aviso. */
add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( 'post' !== $data['post_type'] || ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
			return $data;
		}
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( ! $id || 'publish' === get_post_status( $id ) ) {
			return $data; // Novo ou já publicado: a trava vale só na publicação.
		}
		// Se o formulário clássico trouxe os campos, eles valem mais que o banco.
		if ( isset( $_POST['tpop_post_nonce'] ) ) {
			$sources  = isset( $_POST['tpop_fontes_texto_post'] ) ? tpop_parse_sources( wp_unslash( $_POST['tpop_fontes_texto_post'] ) ) : array();
			$reviewed = isset( $_POST['tpop_revisado_post'] );
			$issues   = array();
			if ( tpop_post_is_controlled( $id ) ) {
				if ( ! $sources ) {
					$issues[] = 'Registre ao menos uma fonte consultada.';
				}
				if ( ! $reviewed ) {
					$issues[] = 'Marque “Fatos conferidos e revisão humana concluída”.';
				}
				if ( false !== stripos( (string) $data['post_content'], '[PREENCHER' ) ) {
					$issues[] = 'O texto ainda contém trechos “[PREENCHER…]”.';
				}
			}
		} else {
			$issues = tpop_publish_issues( $id, $data['post_content'] );
		}
		if ( $issues ) {
			$data['post_status'] = 'draft';
			tpop_flash( '<strong>Publicação bloqueada:</strong> ' . esc_html( implode( ' ', $issues ) ), 'error' );
		}
		return $data;
	},
	10,
	2
);

/* ------------------------------------------------------------------
 * Exibição pública: fontes, verificação e correções
 * ---------------------------------------------------------------- */

function tpop_render_sources( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$sources = tpop_parse_sources( get_post_meta( $post_id, '_tpop_fontes_texto', true ) );
	$date    = get_post_meta( $post_id, '_tpop_verificado_em', true );
	if ( ! $sources && ! $date ) {
		return;
	}
	echo '<aside class="tpop-sources" aria-label="Fontes consultadas">';
	if ( $sources ) {
		echo '<h2 class="tpop-sources__title">Fontes consultadas</h2><ul>';
		foreach ( $sources as $s ) {
			printf( '<li><a href="%s" rel="noopener nofollow" target="_blank">%s</a></li>', esc_url( $s['url'] ), esc_html( $s['nome'] ) );
		}
		echo '</ul>';
	}
	if ( $date ) {
		printf( '<p class="tpop-sources__date">Última verificação dos fatos: %s.</p>', esc_html( mysql2date( 'd/m/Y', $date ) ) );
	}
	$page = get_page_by_path( 'correcoes' );
	if ( $page && 'publish' === $page->post_status ) {
		printf( '<p class="tpop-sources__fix">Encontrou um erro? Veja como pedir <a href="%s">correções</a>.</p>', esc_url( get_permalink( $page ) ) );
	}
	echo '</aside>';
}
