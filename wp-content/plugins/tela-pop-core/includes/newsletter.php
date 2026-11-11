<?php
/**
 * Newsletter: inscrição com dupla confirmação (LGPD), descadastro em 1 clique,
 * lista no painel, exportação CSV e resumo semanal opcional.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TPOP_NL_DB_VERSION', '1' );

/* ------------------------------------------------------------------
 * Banco de dados
 * ---------------------------------------------------------------- */

function tpop_nl_table() {
	global $wpdb;
	return $wpdb->prefix . 'tpop_subscribers';
}

function tpop_nl_install_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = tpop_nl_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			status varchar(12) NOT NULL DEFAULT 'pending',
			token varchar(40) NOT NULL,
			source varchar(40) NOT NULL DEFAULT '',
			ip_hash varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			confirmed_at datetime DEFAULT NULL,
			unsubscribed_at datetime DEFAULT NULL,
			last_sent_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			UNIQUE KEY token (token),
			KEY status (status)
		) {$charset};"
	);
	update_option( 'tpop_nl_db_version', TPOP_NL_DB_VERSION, false );
}

/** Garante a tabela mesmo quando o plugin já estava ativo antes desta versão. */
add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'tpop_nl_db_version' ) !== TPOP_NL_DB_VERSION ) {
			tpop_nl_install_table();
		}
	}
);

/* ------------------------------------------------------------------
 * Opções
 * ---------------------------------------------------------------- */

function tpop_nl_opts() {
	$defaults = array(
		'from_name'    => get_bloginfo( 'name' ),
		'from_email'   => '',
		'digest_on'    => '0',
		'digest_day'   => '1',
		'digest_hour'  => '8',
		'digest_count' => '6',
		'batch'        => '40',
	);
	$saved = get_option( 'tpop_nl_options', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

function tpop_nl_count( $status = '' ) {
	global $wpdb;
	$table = tpop_nl_table();
	if ( '' === $status ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
}

/* ------------------------------------------------------------------
 * E-mail
 * ---------------------------------------------------------------- */

function tpop_nl_headers( $unsub_url = '' ) {
	$o       = tpop_nl_opts();
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( is_email( $o['from_email'] ) ) {
		$headers[] = 'From: ' . wp_specialchars_decode( $o['from_name'], ENT_QUOTES ) . ' <' . $o['from_email'] . '>';
	}
	if ( $unsub_url ) {
		$headers[] = 'List-Unsubscribe: <' . esc_url_raw( $unsub_url ) . '>';
	}
	return $headers;
}

function tpop_nl_action_url( $action, $token ) {
	return add_query_arg(
		array(
			'tpop_nl' => $action,
			't'      => $token,
		),
		home_url( '/' )
	);
}

/** Moldura HTML simples e compatível com clientes de e-mail (tabelas e estilos inline). */
function tpop_nl_email_wrap( $title, $body_html, $unsub_url = '' ) {
	$site = esc_html( get_bloginfo( 'name' ) );
	$out  = '<!DOCTYPE html><html><body style="margin:0;background:#f3f1e8;font-family:Arial,Helvetica,sans-serif;color:#10231a">';
	$out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f1e8"><tr><td align="center" style="padding:16px">';
	$out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden">';
	$out .= '<tr><td style="background:#07140d;padding:18px 24px;color:#c8ff3d;font-size:20px;font-weight:bold;letter-spacing:.02em">' . $site . '</td></tr>';
	$out .= '<tr><td style="padding:24px;font-size:16px;line-height:1.6">';
	if ( $title ) {
		$out .= '<h1 style="font-size:22px;line-height:1.25;margin:0 0 14px">' . esc_html( $title ) . '</h1>';
	}
	$out .= $body_html . '</td></tr>';
	$out .= '<tr><td style="padding:16px 24px;background:#f3f1e8;font-size:12px;color:#5b6a62">';
	$out .= 'Você recebe este e-mail porque se inscreveu em ' . $site . '.';
	if ( $unsub_url ) {
		$out .= ' <a href="' . esc_url( $unsub_url ) . '" style="color:#0f6b3f">Cancelar inscrição</a>.';
	}
	$out .= '</td></tr></table></td></tr></table></body></html>';
	return $out;
}

function tpop_nl_button( $url, $label ) {
	return '<p style="margin:22px 0"><a href="' . esc_url( $url ) . '" style="background:#c8ff3d;color:#07140d;text-decoration:none;font-weight:bold;padding:13px 22px;border-radius:999px;display:inline-block">' . esc_html( $label ) . '</a></p>';
}

function tpop_nl_send_confirmation( $email, $token ) {
	$url  = tpop_nl_action_url( 'confirmar', $token );
	$body = '<p>Falta só um passo: confirme seu e-mail para receber novidades de filmes e séries.</p>'
		. tpop_nl_button( $url, 'Confirmar inscrição' )
		. '<p style="font-size:13px;color:#5b6a62">Se você não pediu isso, ignore esta mensagem. Nada será enviado sem a sua confirmação.</p>';
	return wp_mail(
		$email,
		'Confirme sua inscrição — ' . get_bloginfo( 'name' ),
		tpop_nl_email_wrap( 'Confirme sua inscrição', $body ),
		tpop_nl_headers( tpop_nl_action_url( 'sair', $token ) )
	);
}

/* ------------------------------------------------------------------
 * Inscrição
 * ---------------------------------------------------------------- */

function tpop_nl_ip_hash() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
}

/**
 * Processa uma inscrição.
 *
 * @return array{ok:bool,message:string}
 */
function tpop_nl_subscribe( $email, $source = 'site' ) {
	global $wpdb;
	$email = strtolower( trim( sanitize_email( $email ) ) );
	if ( ! is_email( $email ) || strlen( $email ) > 190 ) {
		return array( 'ok' => false, 'message' => 'Digite um e-mail válido.' );
	}

	// Limite por IP: 5 tentativas por hora.
	$ip_hash = tpop_nl_ip_hash();
	$key     = 'tpop_nl_rl_' . substr( $ip_hash, 0, 16 );
	$tries   = (int) get_transient( $key );
	if ( $tries >= 5 ) {
		return array( 'ok' => false, 'message' => 'Muitas tentativas. Tente de novo mais tarde.' );
	}
	set_transient( $key, $tries + 1, HOUR_IN_SECONDS );

	$table = tpop_nl_table();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) );
	$msg   = 'Quase lá! Enviamos um e-mail para confirmar a sua inscrição. Olhe também a caixa de spam.';

	if ( $row && 'active' === $row->status ) {
		// Resposta igual à de uma inscrição nova, para não revelar quem já está na lista.
		return array( 'ok' => true, 'message' => $msg );
	}

	$now = current_time( 'mysql', true );
	if ( $row ) {
		$token = $row->token;
		$wpdb->update(
			$table,
			array(
				'status'          => 'pending',
				'unsubscribed_at' => null,
				'created_at'      => $now,
				'source'          => sanitize_key( $source ),
				'ip_hash'         => $ip_hash,
			),
			array( 'id' => (int) $row->id )
		);
	} else {
		$token = wp_generate_password( 32, false, false );
		$wpdb->insert(
			$table,
			array(
				'email'      => $email,
				'status'     => 'pending',
				'token'      => $token,
				'source'     => sanitize_key( $source ),
				'ip_hash'    => $ip_hash,
				'created_at' => $now,
			)
		);
	}
	tpop_nl_send_confirmation( $email, $token );
	return array( 'ok' => true, 'message' => $msg );
}

/** Lê os dados do formulário (admin-post.php ou admin-ajax.php). */
function tpop_nl_handle_submit() {
	$ajax = wp_doing_ajax();

	// Isca para robôs: campo invisível que humanos não preenchem.
	$trap = isset( $_POST['tpop_website'] ) ? trim( (string) wp_unslash( $_POST['tpop_website'] ) ) : '';
	$mail = isset( $_POST['tpop_email'] ) ? (string) wp_unslash( $_POST['tpop_email'] ) : '';
	$src  = isset( $_POST['tpop_source'] ) ? sanitize_key( wp_unslash( $_POST['tpop_source'] ) ) : 'site';
	$ok   = isset( $_POST['tpop_consent'] );

	if ( '' !== $trap ) {
		$result = array( 'ok' => true, 'message' => 'Quase lá! Enviamos um e-mail para confirmar a sua inscrição.' );
	} elseif ( ! $ok ) {
		$result = array( 'ok' => false, 'message' => 'Marque a caixa de concordância para continuar.' );
	} else {
		$result = tpop_nl_subscribe( $mail, $src );
	}

	if ( $ajax ) {
		wp_send_json( $result, $result['ok'] ? 200 : 422 );
	}
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$back = remove_query_arg( array( 'tpop_nl', 't' ), $back );
	wp_safe_redirect( add_query_arg( 'tpop_nl', $result['ok'] ? 'enviado' : 'erro', $back ) . '#assine' );
	exit;
}
add_action( 'admin_post_nopriv_tpop_newsletter', 'tpop_nl_handle_submit' );
add_action( 'admin_post_tpop_newsletter', 'tpop_nl_handle_submit' );
add_action( 'wp_ajax_nopriv_tpop_newsletter', 'tpop_nl_handle_submit' );
add_action( 'wp_ajax_tpop_newsletter', 'tpop_nl_handle_submit' );

/* ------------------------------------------------------------------
 * Confirmar / cancelar pelos links do e-mail
 * ---------------------------------------------------------------- */

add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['tpop_nl'] ) || empty( $_GET['t'] ) ) {
			return;
		}
		global $wpdb;
		$action = sanitize_key( wp_unslash( $_GET['tpop_nl'] ) );
		$token  = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['t'] ) );
		if ( ! in_array( $action, array( 'confirmar', 'sair' ), true ) || '' === $token ) {
			return;
		}
		$table = tpop_nl_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) );
		$to    = home_url( '/' );
		if ( ! $row ) {
			wp_safe_redirect( add_query_arg( 'tpop_nl', 'invalido', $to ) );
			exit;
		}
		$now = current_time( 'mysql', true );
		if ( 'confirmar' === $action ) {
			$wpdb->update( $table, array( 'status' => 'active', 'confirmed_at' => $now, 'unsubscribed_at' => null ), array( 'id' => (int) $row->id ) );
			wp_safe_redirect( add_query_arg( 'tpop_nl', 'confirmado', $to ) );
		} else {
			$wpdb->update( $table, array( 'status' => 'unsubscribed', 'unsubscribed_at' => $now ), array( 'id' => (int) $row->id ) );
			wp_safe_redirect( add_query_arg( 'tpop_nl', 'cancelado', $to ) );
		}
		exit;
	}
);

/** Mensagem exibida pelo tema depois de confirmar, cancelar ou enviar o formulário sem JavaScript. */
function tpop_nl_notice() {
	if ( empty( $_GET['tpop_nl'] ) ) {
		return null;
	}
	$map = array(
		'enviado'    => array( 'ok', 'Quase lá! Enviamos um e-mail para confirmar a sua inscrição. Olhe também a caixa de spam.' ),
		'confirmado' => array( 'ok', 'Inscrição confirmada. Você vai receber novidades de filmes e séries por e-mail.' ),
		'cancelado'  => array( 'ok', 'Inscrição cancelada. Você não receberá mais nossos e-mails.' ),
		'invalido'   => array( 'erro', 'Este link não é mais válido.' ),
		'erro'       => array( 'erro', 'Não foi possível concluir. Confira o e-mail e a caixa de concordância.' ),
	);
	$key = sanitize_key( wp_unslash( $_GET['tpop_nl'] ) );
	return isset( $map[ $key ] ) ? $map[ $key ] : null;
}

/* ------------------------------------------------------------------
 * Formulário (usado pelo tema)
 * ---------------------------------------------------------------- */

/**
 * Imprime o formulário de inscrição.
 *
 * @param string $variant band|inline|footer
 * @param string $source  origem registrada (home, artigo, rodape)
 */
function tpop_newsletter_form( $variant = 'band', $source = 'site' ) {
	static $n = 0;
	++$n;
	$id      = 'tpop-nl-' . $n;
	$privacy = get_page_by_path( 'privacidade' );
	$link    = ( $privacy && 'publish' === $privacy->post_status ) ? get_permalink( $privacy ) : '';
	?>
	<form class="nl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" novalidate>
		<input type="hidden" name="action" value="tpop_newsletter">
		<input type="hidden" name="tpop_source" value="<?php echo esc_attr( $source ); ?>">
		<div class="nl-trap" aria-hidden="true"><label>Não preencha <input type="text" name="tpop_website" tabindex="-1" autocomplete="off"></label></div>
		<div class="nl-row">
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>">Seu melhor e-mail</label>
			<input class="nl-input" type="email" id="<?php echo esc_attr( $id ); ?>" name="tpop_email" placeholder="seu@email.com" autocomplete="email" inputmode="email" required>
			<button class="nl-btn" type="submit">Quero receber</button>
		</div>
		<label class="nl-consent">
			<input type="checkbox" name="tpop_consent" value="1" required>
			<span>Concordo em receber e-mails do <?php bloginfo( 'name' ); ?>.<?php if ( $link ) : ?> Veja a <a href="<?php echo esc_url( $link ); ?>">política de privacidade</a>.<?php endif; ?> Cancele quando quiser.</span>
		</label>
		<p class="nl-status" role="status" aria-live="polite"></p>
	</form>
	<?php
}

/* ------------------------------------------------------------------
 * Resumo semanal
 * ---------------------------------------------------------------- */

function tpop_nl_digest_posts() {
	$o = tpop_nl_opts();
	return get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 12, (int) $o['digest_count'] ) ),
			'ignore_sticky_posts' => true,
			'date_query'          => array( array( 'after' => '7 days ago' ) ),
			'no_found_rows'       => true,
		)
	);
}

function tpop_nl_digest_html( $posts, $unsub_url ) {
	$body = '<p>Os destaques da semana no ' . esc_html( get_bloginfo( 'name' ) ) . ':</p>';
	foreach ( $posts as $p ) {
		$cats = get_the_category( $p->ID );
		$body .= '<div style="padding:14px 0;border-top:1px solid #e4e2d6">';
		if ( $cats ) {
			$body .= '<div style="font-size:11px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;color:#0f6b3f">' . esc_html( $cats[0]->name ) . '</div>';
		}
		$body .= '<a href="' . esc_url( get_permalink( $p ) ) . '" style="font-size:18px;font-weight:bold;color:#10231a;text-decoration:none;line-height:1.3">' . esc_html( get_the_title( $p ) ) . '</a>';
		$ex = wp_trim_words( get_the_excerpt( $p ), 24, '…' );
		if ( $ex ) {
			$body .= '<div style="font-size:14px;color:#5b6a62;margin-top:4px">' . esc_html( $ex ) . '</div>';
		}
		$body .= '</div>';
	}
	$body .= tpop_nl_button( home_url( '/' ), 'Ver todas as notícias' );
	return tpop_nl_email_wrap( '', $body, $unsub_url );
}

/** Inicia uma campanha: congela assunto e artigos e libera o envio em lotes. */
function tpop_nl_start_campaign() {
	$posts = tpop_nl_digest_posts();
	if ( ! $posts ) {
		return false;
	}
	update_option(
		'tpop_nl_campaign',
		array(
			'subject' => 'Resumo da semana — ' . get_bloginfo( 'name' ) . ' (' . wp_date( 'd/m' ) . ')',
			'ids'     => wp_list_pluck( $posts, 'ID' ),
			'last_id' => 0,
			'sent'    => 0,
			'started' => time(),
		),
		false
	);
	if ( ! wp_next_scheduled( 'tpop_nl_batch_event' ) ) {
		wp_schedule_single_event( time() + 30, 'tpop_nl_batch_event' );
	}
	return true;
}

function tpop_nl_run_batch() {
	global $wpdb;
	$c = get_option( 'tpop_nl_campaign' );
	if ( ! is_array( $c ) || empty( $c['ids'] ) ) {
		return;
	}
	$o     = tpop_nl_opts();
	$batch = max( 5, min( 200, (int) $o['batch'] ) );
	$table = tpop_nl_table();
	$rows  = $wpdb->get_results(
		$wpdb->prepare( "SELECT id, email, token FROM {$table} WHERE status = 'active' AND id > %d ORDER BY id ASC LIMIT %d", (int) $c['last_id'], $batch )
	);
	if ( ! $rows ) {
		delete_option( 'tpop_nl_campaign' );
		return;
	}
	$posts = get_posts( array( 'post__in' => $c['ids'], 'orderby' => 'post__in', 'posts_per_page' => count( $c['ids'] ), 'post_status' => 'publish', 'ignore_sticky_posts' => true ) );
	foreach ( $rows as $r ) {
		$unsub = tpop_nl_action_url( 'sair', $r->token );
		if ( wp_mail( $r->email, $c['subject'], tpop_nl_digest_html( $posts, $unsub ), tpop_nl_headers( $unsub ) ) ) {
			++$c['sent'];
		}
		$c['last_id'] = (int) $r->id;
	}
	update_option( 'tpop_nl_campaign', $c, false );
	// Próximo lote em 10 minutos, para respeitar limites de envio da hospedagem.
	wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, 'tpop_nl_batch_event' );
}
add_action( 'tpop_nl_batch_event', 'tpop_nl_run_batch' );

/** Agendamento semanal (WP-Cron, a cada hora confere se chegou o dia/hora). */
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'tpop_nl_hourly_event' ) ) {
			wp_schedule_event( time() + 600, 'hourly', 'tpop_nl_hourly_event' );
		}
	}
);
add_action(
	'tpop_nl_hourly_event',
	function () {
		$o = tpop_nl_opts();
		if ( '1' !== (string) $o['digest_on'] ) {
			return;
		}
		$now = (int) wp_date( 'G' );
		if ( (int) wp_date( 'w' ) !== (int) $o['digest_day'] || $now !== (int) $o['digest_hour'] ) {
			return;
		}
		$today = wp_date( 'Y-m-d' );
		if ( get_option( 'tpop_nl_last_digest' ) === $today ) {
			return;
		}
		update_option( 'tpop_nl_last_digest', $today, false );
		tpop_nl_start_campaign();
	}
);

/* ------------------------------------------------------------------
 * Painel
 * ---------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'tpop-painel', 'Newsletter', 'Newsletter', 'manage_options', 'tpop-newsletter', 'tpop_nl_admin_page' );
	},
	20
);

add_action(
	'admin_post_tpop_nl_export',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tpop_nl_export' ) ) {
			wp_die( 'Sem permissão.' );
		}
		global $wpdb;
		$table = tpop_nl_table();
		$rows  = $wpdb->get_results( "SELECT email, status, source, created_at, confirmed_at, unsubscribed_at FROM {$table} ORDER BY id ASC", ARRAY_A );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="newsletter-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'email', 'status', 'origem', 'inscrito_em_utc', 'confirmado_em_utc', 'cancelado_em_utc' ) );
		foreach ( $rows as $r ) {
			// Evita que planilhas executem fórmulas em células que começam com = + - @.
			$email = preg_match( '/^[=+\-@]/', $r['email'] ) ? "'" . $r['email'] : $r['email'];
			fputcsv( $out, array( $email, $r['status'], $r['source'], $r['created_at'], $r['confirmed_at'], $r['unsubscribed_at'] ) );
		}
		fclose( $out );
		exit;
	}
);

add_action(
	'admin_post_tpop_nl_action',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tpop_nl_action' ) ) {
			wp_die( 'Sem permissão.' );
		}
		global $wpdb;
		$table = tpop_nl_table();
		$do    = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		if ( 'save' === $do ) {
			$in = array(
				'from_name'    => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '',
				'from_email'   => isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '',
				'digest_on'    => isset( $_POST['digest_on'] ) ? '1' : '0',
				'digest_day'   => isset( $_POST['digest_day'] ) ? (string) max( 0, min( 6, (int) $_POST['digest_day'] ) ) : '1',
				'digest_hour'  => isset( $_POST['digest_hour'] ) ? (string) max( 0, min( 23, (int) $_POST['digest_hour'] ) ) : '8',
				'digest_count' => isset( $_POST['digest_count'] ) ? (string) max( 1, min( 12, (int) $_POST['digest_count'] ) ) : '6',
				'batch'        => isset( $_POST['batch'] ) ? (string) max( 5, min( 200, (int) $_POST['batch'] ) ) : '40',
			);
			update_option( 'tpop_nl_options', $in, false );
			tpop_flash( 'Configurações da newsletter salvas.', 'success' );
		} elseif ( 'test' === $do ) {
			$posts = tpop_nl_digest_posts();
			$user  = wp_get_current_user();
			if ( ! $posts ) {
				tpop_flash( 'Não há artigos publicados nos últimos 7 dias para montar o resumo.', 'warning' );
			} else {
				$unsub = home_url( '/' );
				$sent  = wp_mail( $user->user_email, '[TESTE] Resumo da semana — ' . get_bloginfo( 'name' ), tpop_nl_digest_html( $posts, $unsub ), tpop_nl_headers() );
				tpop_flash( $sent ? 'Teste enviado para ' . esc_html( $user->user_email ) . '.' : 'O WordPress não conseguiu enviar o e-mail. Configure um plugin de SMTP.', $sent ? 'success' : 'error' );
			}
		} elseif ( 'send' === $do ) {
			if ( tpop_nl_start_campaign() ) {
				tpop_flash( 'Envio iniciado. Os e-mails saem em lotes pelo WP-Cron.', 'success' );
			} else {
				tpop_flash( 'Não há artigos publicados nos últimos 7 dias.', 'warning' );
			}
		} elseif ( 'cancel_campaign' === $do ) {
			delete_option( 'tpop_nl_campaign' );
			wp_clear_scheduled_hook( 'tpop_nl_batch_event' );
			tpop_flash( 'Envio interrompido.', 'success' );
		} elseif ( 'delete' === $do && ! empty( $_POST['id'] ) ) {
			$wpdb->delete( $table, array( 'id' => (int) $_POST['id'] ) );
			tpop_flash( 'Inscrito removido.', 'success' );
		} elseif ( 'add' === $do && ! empty( $_POST['email'] ) ) {
			$email = strtolower( sanitize_email( wp_unslash( $_POST['email'] ) ) );
			if ( is_email( $email ) ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$table} (email, status, token, source, created_at, confirmed_at) VALUES (%s, 'active', %s, 'manual', %s, %s)",
						$email,
						wp_generate_password( 32, false, false ),
						current_time( 'mysql', true ),
						current_time( 'mysql', true )
					)
				);
				tpop_flash( 'Inscrito adicionado. Só adicione quem já deu consentimento.', 'success' );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=tpop-newsletter' ) );
		exit;
	}
);

function tpop_nl_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$table  = tpop_nl_table();
	$o      = tpop_nl_opts();
	$filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
	if ( ! in_array( $filter, array( 'active', 'pending', 'unsubscribed' ), true ) ) {
		$filter = '';
	}
	$rows = $filter
		? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT 200", $filter ) )
		: $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200" );
	$camp   = get_option( 'tpop_nl_campaign' );
	$days   = array( 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado' );
	$labels = array( 'active' => 'Ativo', 'pending' => 'Aguardando confirmação', 'unsubscribed' => 'Cancelado' );
	$post   = esc_url( admin_url( 'admin-post.php' ) );
	?>
	<div class="wrap">
		<h1>Newsletter</h1>
		<p>
			<strong><?php echo esc_html( tpop_nl_count( 'active' ) ); ?></strong> ativos ·
			<?php echo esc_html( tpop_nl_count( 'pending' ) ); ?> aguardando confirmação ·
			<?php echo esc_html( tpop_nl_count( 'unsubscribed' ) ); ?> cancelados
		</p>
		<?php if ( is_array( $camp ) ) : ?>
			<div class="notice notice-info inline"><p>Envio em andamento: <?php echo esc_html( (int) $camp['sent'] ); ?> e-mails enviados até agora.</p></div>
		<?php endif; ?>

		<h2>Configurações</h2>
		<form method="post" action="<?php echo $post; ?>">
			<?php wp_nonce_field( 'tpop_nl_action' ); ?>
			<input type="hidden" name="action" value="tpop_nl_action">
			<input type="hidden" name="do" value="save">
			<table class="form-table" role="presentation">
				<tr><th><label for="from_name">Nome do remetente</label></th><td><input class="regular-text" id="from_name" name="from_name" value="<?php echo esc_attr( $o['from_name'] ); ?>"></td></tr>
				<tr><th><label for="from_email">E-mail do remetente</label></th><td><input class="regular-text" type="email" id="from_email" name="from_email" value="<?php echo esc_attr( $o['from_email'] ); ?>"><p class="description">Use um e-mail do seu domínio (ex.: contato@seudominio.com.br). Vazio = padrão do WordPress.</p></td></tr>
				<tr><th>Resumo semanal automático</th><td>
					<label><input type="checkbox" name="digest_on" value="1" <?php checked( '1', (string) $o['digest_on'] ); ?>> Enviar toda semana</label><br>
					<select name="digest_day"><?php foreach ( $days as $i => $d ) { printf( '<option value="%d"%s>%s</option>', $i, selected( (int) $o['digest_day'], $i, false ), esc_html( $d ) ); } ?></select>
					às <input type="number" min="0" max="23" name="digest_hour" value="<?php echo esc_attr( $o['digest_hour'] ); ?>" style="width:60px">h
					· <input type="number" min="1" max="12" name="digest_count" value="<?php echo esc_attr( $o['digest_count'] ); ?>" style="width:60px"> matérias
				</td></tr>
				<tr><th><label for="batch">E-mails por lote</label></th><td><input type="number" min="5" max="200" id="batch" name="batch" value="<?php echo esc_attr( $o['batch'] ); ?>" style="width:80px"><p class="description">Um lote a cada 10 minutos. Hospedagem compartilhada costuma limitar o envio por hora; se for o caso, use 20–40.</p></td></tr>
			</table>
			<?php submit_button( 'Salvar', 'primary', 'submit', false ); ?>
		</form>

		<h2>Resumo da semana</h2>
		<form method="post" action="<?php echo $post; ?>" style="display:flex;gap:8px;flex-wrap:wrap">
			<?php wp_nonce_field( 'tpop_nl_action' ); ?>
			<input type="hidden" name="action" value="tpop_nl_action">
			<button class="button" name="do" value="test">Enviar teste para mim</button>
			<button class="button button-primary" name="do" value="send" onclick="return confirm('Enviar o resumo para todos os inscritos ativos?');">Enviar agora para todos</button>
			<?php if ( is_array( $camp ) ) : ?><button class="button" name="do" value="cancel_campaign">Interromper envio</button><?php endif; ?>
		</form>

		<h2>Inscritos</h2>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpop-newsletter' ) ); ?>">Todos</a> ·
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpop-newsletter&status=active' ) ); ?>">Ativos</a> ·
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpop-newsletter&status=pending' ) ); ?>">Pendentes</a> ·
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tpop-newsletter&status=unsubscribed' ) ); ?>">Cancelados</a> ·
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpop_nl_export' ), 'tpop_nl_export' ) ); ?>">Exportar CSV</a>
		</p>
		<table class="widefat striped">
			<thead><tr><th>E-mail</th><th>Status</th><th>Origem</th><th>Inscrição (UTC)</th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="5">Nenhum inscrito ainda.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) : ?>
				<tr>
					<td><?php echo esc_html( $r->email ); ?></td>
					<td><?php echo esc_html( isset( $labels[ $r->status ] ) ? $labels[ $r->status ] : $r->status ); ?></td>
					<td><?php echo esc_html( $r->source ); ?></td>
					<td><?php echo esc_html( $r->created_at ); ?></td>
					<td>
						<form method="post" action="<?php echo $post; ?>" onsubmit="return confirm('Remover este inscrito?');">
							<?php wp_nonce_field( 'tpop_nl_action' ); ?>
							<input type="hidden" name="action" value="tpop_nl_action">
							<input type="hidden" name="do" value="delete">
							<input type="hidden" name="id" value="<?php echo esc_attr( $r->id ); ?>">
							<button class="button-link-delete">Remover</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">Mostrando até 200. Use a exportação para a lista completa.</p>

		<h2>Adicionar manualmente</h2>
		<form method="post" action="<?php echo $post; ?>" style="display:flex;gap:8px;flex-wrap:wrap">
			<?php wp_nonce_field( 'tpop_nl_action' ); ?>
			<input type="hidden" name="action" value="tpop_nl_action">
			<input type="hidden" name="do" value="add">
			<input type="email" name="email" class="regular-text" placeholder="email@exemplo.com" required>
			<button class="button">Adicionar</button>
		</form>
	</div>
	<?php
}
