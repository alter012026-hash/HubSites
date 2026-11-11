<?php
/**
 * Interface do painel: navegação por etapas, estilos e componentes compartilhados.
 * Responsabilidade: só apresentação. Nenhuma regra editorial mora aqui.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A tela atual pertence ao plugin? (páginas do plugin e listas/edição de pautas e fontes) */
function bec_is_admin_screen() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}
	$screen = get_current_screen();
	if ( ! $screen ) {
		return false;
	}
	if ( in_array( $screen->post_type, array( 'bec_pauta', 'bec_fonte' ), true ) ) {
		return true;
	}
	return false !== strpos( (string) $screen->id, 'bec-' );
}

/** As etapas do fluxo, na ordem em que acontecem, e quem responde por cada uma. */
function bec_flow_stages() {
	return array(
		'coleta'   => array( 'n' => 1, 'title' => 'Coleta', 'who' => 'Sistema', 'icon' => 'rss', 'desc' => 'Lê os feeds RSS cadastrados e cria pautas com título, resumo e link.' ),
		'triagem'  => array( 'n' => 2, 'title' => 'Triagem', 'who' => 'Sistema ou equipe', 'icon' => 'filter', 'desc' => 'Pontua cada pauta e aprova só o que cumpre as regras.' ),
		'redacao'  => array( 'n' => 3, 'title' => 'Redação', 'who' => 'IA', 'icon' => 'edit', 'desc' => 'Escreve um texto original usando só os dados reais coletados.' ),
		'revisao'  => array( 'n' => 4, 'title' => 'Revisão', 'who' => 'Pessoa', 'icon' => 'yes-alt', 'desc' => 'Confere fatos, ajusta o texto e aprova. Obrigatório.' ),
		'publicar' => array( 'n' => 5, 'title' => 'Publicação', 'who' => 'Pessoa', 'icon' => 'megaphone', 'desc' => 'Publica no site. Nada vai ao ar sozinho.' ),
	);
}

/** Itens da barra de navegação (slug da tela => rótulo, URL). */
function bec_nav_items() {
	$items = array(
		'bec-painel'     => array( 'Visão geral', admin_url( 'admin.php?page=bec-painel' ) ),
		'bec-revisao'    => array( 'Fila de revisão', admin_url( 'admin.php?page=bec-revisao' ) ),
		'bec_pauta'      => array( 'Pautas', admin_url( 'edit.php?post_type=bec_pauta' ) ),
		'bec_fonte'      => array( 'Fontes RSS', admin_url( 'edit.php?post_type=bec_fonte' ) ),
		'bec-newsletter' => array( 'Newsletter', admin_url( 'admin.php?page=bec-newsletter' ) ),
	);
	if ( current_user_can( 'manage_options' ) ) {
		$items['bec-config'] = array( 'Configurações', admin_url( 'admin.php?page=bec-config' ) );
	}
	return $items;
}

add_action(
	'in_admin_header',
	function () {
		if ( ! bec_is_admin_screen() ) {
			return;
		}
		$screen  = get_current_screen();
		$current = 'bec-painel';
		if ( in_array( $screen->post_type, array( 'bec_pauta', 'bec_fonte' ), true ) ) {
			$current = $screen->post_type;
		} else {
			foreach ( array( 'bec-revisao', 'bec-newsletter', 'bec-config', 'bec-painel' ) as $slug ) {
				if ( false !== strpos( (string) $screen->id, $slug ) ) {
					$current = $slug;
					break;
				}
			}
		}
		$waiting = function_exists( 'bec_review_count' ) ? bec_review_count() : 0;
		echo '<nav class="bec-nav" aria-label="De Olho na Bola"><span class="bec-nav__brand">De Olho na Bola</span>';
		foreach ( bec_nav_items() as $slug => $item ) {
			$badge = ( 'bec-revisao' === $slug && $waiting ) ? ' <span class="bec-nav__badge">' . (int) $waiting . '</span>' : '';
			printf(
				'<a href="%s" class="%s">%s%s</a>',
				esc_url( $item[1] ),
				$slug === $current ? 'is-active' : '',
				esc_html( $item[0] ),
				$badge // phpcs:ignore WordPress.Security.EscapeOutput
			);
		}
		echo '</nav>';
	}
);

add_filter(
	'admin_body_class',
	function ( $classes ) {
		return bec_is_admin_screen() ? $classes . ' bec-admin' : $classes;
	}
);

/** Menu lateral na ordem do fluxo, com rótulos claros e o contador da fila de revisão. */
add_action(
	'admin_menu',
	function () {
		global $submenu;
		if ( empty( $submenu['bec-painel'] ) ) {
			return;
		}
		$waiting = function_exists( 'bec_review_count' ) ? bec_review_count() : 0;
		$labels  = array(
			'bec-painel'                   => 'Visão geral',
			'bec-revisao'                  => 'Fila de revisão' . ( $waiting ? ' <span class="awaiting-mod">' . (int) $waiting . '</span>' : '' ),
			'edit.php?post_type=bec_pauta' => 'Pautas',
			'edit.php?post_type=bec_fonte' => 'Fontes RSS',
			'bec-newsletter'               => 'Newsletter',
			'bec-config'                   => 'Configurações',
		);
		$by_slug = array();
		foreach ( $submenu['bec-painel'] as $item ) {
			$by_slug[ $item[2] ] = $item;
		}
		$ordered = array();
		foreach ( $labels as $slug => $label ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$item      = $by_slug[ $slug ];
				$item[0]   = $label;
				$ordered[] = $item;
				unset( $by_slug[ $slug ] );
			}
		}
		foreach ( $by_slug as $item ) {
			$ordered[] = $item;
		}
		$submenu['bec-painel'] = $ordered; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	},
	999
);

/** Cabeçalho padrão das páginas do plugin. */
function bec_ui_header( $title, $subtitle = '', $actions_html = '' ) {
	echo '<header class="bec-head"><div><h1>' . esc_html( $title ) . '</h1>';
	if ( $subtitle ) {
		echo '<p>' . esc_html( $subtitle ) . '</p>';
	}
	echo '</div>';
	if ( $actions_html ) {
		echo '<div class="bec-head__actions">' . $actions_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</header>';
}

/** Selo colorido. $tone: ok | warn | err | info | mute */
function bec_pill( $text, $tone = 'mute' ) {
	return '<span class="bec-pill bec-pill--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
}

/** Cor do selo para cada status de pauta. */
function bec_status_tone( $status ) {
	$map = array(
		'nova_pauta'             => 'info',
		'em_analise'             => 'info',
		'aprovada_producao'      => 'ok',
		'rascunho_gerado'        => 'warn',
		'em_revisao'             => 'warn',
		'aprovada'               => 'ok',
		'publicada'              => 'ok',
		'rejeitada'              => 'err',
		'aguardando_confirmacao' => 'err',
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : 'mute';
}

add_action(
	'admin_head',
	function () {
		if ( ! bec_is_admin_screen() ) {
			return;
		}
		?>
<style id="bec-admin-css">
body.bec-admin { --b-noite:#07140d; --b-noite2:#0e2217; --b-verde:#0f6b3f; --b-lima:#c8ff3d; --b-papel:#f4f2ea; --b-linha:#e2dfd2; --b-cinza:#5b6a62; --b-tinta:#10231a; --b-ok:#1a7f37; --b-warn:#a15c00; --b-err:#b3261e; --b-info:#1f5fbf; background:var(--b-papel); }
body.bec-admin #wpcontent { background:var(--b-papel); }
body.bec-admin #wpbody-content { padding-bottom:40px; }
body.bec-admin .wrap { max-width:1180px; margin:0 20px 0 20px; color:var(--b-tinta); }
body.bec-admin .wrap h1.wp-heading-inline { font-size:26px; font-weight:800; letter-spacing:-.01em; }
body.bec-admin .notice { border-radius:10px; border-left-width:5px; box-shadow:none; }

.bec-nav { display:flex; align-items:center; gap:2px; background:var(--b-noite); margin:0 0 0 -20px; padding:0 20px; overflow-x:auto; white-space:nowrap; position:sticky; top:32px; z-index:20; }
.bec-nav__brand { color:var(--b-lima); font-weight:800; letter-spacing:.04em; text-transform:uppercase; font-size:13px; margin-right:18px; }
.bec-nav a { color:#c9dfd1; text-decoration:none; padding:15px 14px 12px; font-weight:600; font-size:13.5px; border-bottom:3px solid transparent; }
.bec-nav a:hover { color:#fff; }
.bec-nav a.is-active { color:#fff; border-bottom-color:var(--b-lima); }
.bec-nav__badge { background:var(--b-lima); color:var(--b-noite); border-radius:999px; font-size:11px; font-weight:800; padding:1px 7px; margin-left:4px; }
@media (max-width:782px){ .bec-nav { top:46px; margin-left:-10px; padding:0 10px; } }

.bec-ui { max-width:1180px; margin:22px 20px 0 0; color:var(--b-tinta); }
.bec-ui * { box-sizing:border-box; }
.bec-head { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
.bec-head h1 { margin:0; padding:0; font-size:28px; line-height:1.1; font-weight:800; letter-spacing:-.015em; }
.bec-head p { margin:6px 0 0; color:var(--b-cinza); font-size:14px; max-width:68ch; }
.bec-head__actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.bec-ui .button, .bec-ui .button-primary { border-radius:999px; padding:4px 16px; min-height:36px; line-height:2; font-weight:600; border-color:var(--b-noite); color:var(--b-noite); background:#fff; }
.bec-ui .button:hover { background:var(--b-lima); border-color:var(--b-noite); color:var(--b-noite); }
.bec-ui .button-primary { background:var(--b-noite); color:#fff; border-color:var(--b-noite); }
.bec-ui .button-primary:hover { background:var(--b-verde); color:#fff; border-color:var(--b-verde); }
.bec-ui .button-link-delete { color:var(--b-err); }

.bec-card { background:#fff; border:1px solid var(--b-linha); border-radius:16px; padding:20px; margin-bottom:18px; }
.bec-card > h2, .bec-card__title { margin:0 0 4px; font-size:17px; font-weight:800; padding:0; }
.bec-card__sub { margin:0 0 14px; color:var(--b-cinza); font-size:13.5px; }
.bec-grid { display:grid; gap:18px; grid-template-columns:minmax(0,1fr); }
.bec-grid > *, .bec-card { min-width:0; }
.bec-card { overflow-wrap:anywhere; }
.bec-feed a { color:var(--b-tinta); text-decoration:none; } .bec-feed a:hover { text-decoration:underline; color:var(--b-verde); }
@media (min-width:900px){ .bec-grid--2 { grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); } .bec-grid--3 { grid-template-columns:repeat(3,minmax(0,1fr)); } }

.bec-pill { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11.5px; font-weight:700; letter-spacing:.02em; line-height:1.6; white-space:nowrap; }
.bec-pill--ok { background:#e3f4e8; color:var(--b-ok); } .bec-pill--warn { background:#fdf0d5; color:var(--b-warn); }
.bec-pill--err { background:#fde5e3; color:var(--b-err); } .bec-pill--info { background:#e3edfb; color:var(--b-info); }
.bec-pill--mute { background:#ecebe3; color:var(--b-cinza); } .bec-pill--dark { background:var(--b-noite); color:var(--b-lima); }

/* Etapas do fluxo */
.bec-steps { display:grid; gap:12px; grid-template-columns:1fr; margin-bottom:18px; counter-reset:step; }
@media (min-width:700px){ .bec-steps { grid-template-columns:repeat(5,1fr); } }
.bec-step { position:relative; background:#fff; border:1px solid var(--b-linha); border-radius:16px; padding:16px 16px 14px; text-decoration:none; color:inherit; display:flex; flex-direction:column; gap:6px; transition:transform .15s, box-shadow .15s; }
a.bec-step:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(7,20,13,.08); color:inherit; }
.bec-step__top { display:flex; align-items:center; justify-content:space-between; }
.bec-step__n { width:26px; height:26px; border-radius:50%; background:var(--b-noite); color:var(--b-lima); font-weight:800; font-size:13px; display:grid; place-items:center; }
.bec-step--human .bec-step__n { background:var(--b-lima); color:var(--b-noite); }
.bec-step h3 { margin:6px 0 0; font-size:16px; font-weight:800; }
.bec-step__num { font-size:34px; line-height:1; font-weight:800; letter-spacing:-.02em; }
.bec-step__num small { font-size:12px; font-weight:600; color:var(--b-cinza); letter-spacing:0; margin-left:4px; }
.bec-step p { margin:0; color:var(--b-cinza); font-size:12.5px; line-height:1.4; }
.bec-step--alert { border-color:var(--b-lima); box-shadow:0 0 0 3px rgba(200,255,61,.45); }

/* Tabelas e listas */
.bec-ui table.widefat { border:0; box-shadow:none; background:transparent; }
.bec-ui table.widefat th { font-size:11.5px; text-transform:uppercase; letter-spacing:.06em; color:var(--b-cinza); border-bottom:1px solid var(--b-linha); }
.bec-ui table.widefat td { border-bottom:1px solid var(--b-linha); vertical-align:middle; }
.bec-ui table.widefat.striped > tbody > :nth-child(odd) { background:transparent; }
.bec-feed { list-style:none; margin:0; padding:0; }
.bec-feed li { display:flex; gap:10px; padding:9px 0; border-bottom:1px solid var(--b-linha); font-size:13px; align-items:flex-start; }
.bec-feed li:last-child { border:0; }
.bec-feed time { color:var(--b-cinza); font-size:12px; min-width:84px; flex:none; }
.bec-check { list-style:none; margin:0; padding:0; }
.bec-check li { display:flex; gap:10px; padding:10px 0; border-bottom:1px solid var(--b-linha); align-items:flex-start; font-size:13.5px; }
.bec-check li:last-child { border:0; }
.bec-check .dashicons { flex:none; }
.bec-check .is-ok .dashicons { color:var(--b-ok); } .bec-check .is-warn .dashicons { color:var(--b-warn); } .bec-check .is-err .dashicons { color:var(--b-err); }
.bec-check small { display:block; color:var(--b-cinza); }

/* Configurações */
.bec-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
.bec-tabs a { text-decoration:none; padding:8px 16px; border-radius:999px; background:#fff; border:1px solid var(--b-linha); color:var(--b-tinta); font-weight:600; font-size:13.5px; }
.bec-tabs a:hover { border-color:var(--b-noite); }
.bec-tabs a.is-active { background:var(--b-noite); color:#fff; border-color:var(--b-noite); }
.bec-field { display:grid; gap:6px 24px; padding:16px 0; border-bottom:1px solid var(--b-linha); grid-template-columns:1fr; }
.bec-field:last-child { border-bottom:0; }
@media (min-width:782px){ .bec-field { grid-template-columns:minmax(220px,300px) 1fr; } }
.bec-field__label { font-weight:700; font-size:14px; }
.bec-field__help { color:var(--b-cinza); font-size:12.5px; margin:4px 0 0; line-height:1.45; }
.bec-field input[type=text], .bec-field input[type=email], .bec-field input[type=password], .bec-field input[type=number], .bec-field select, .bec-field textarea { border-radius:10px; border:1px solid #c9c6b8; padding:7px 12px; min-height:38px; max-width:100%; background:#fff; }
.bec-field input[type=text], .bec-field input[type=email], .bec-field input[type=password], .bec-field textarea { width:100%; max-width:520px; }
.bec-field input:focus, .bec-field select:focus, .bec-field textarea:focus { border-color:var(--b-verde); box-shadow:0 0 0 3px rgba(15,107,63,.18); outline:0; }
.bec-unit { color:var(--b-cinza); font-size:13px; margin-left:6px; }
.bec-toggle { display:inline-flex; align-items:center; gap:12px; cursor:pointer; }
.bec-toggle input { position:absolute; opacity:0; width:1px; height:1px; }
.bec-toggle__box { width:44px; height:26px; border-radius:999px; background:#c9c6b8; position:relative; transition:background .15s; flex:none; }
.bec-toggle__box::after { content:""; position:absolute; top:3px; left:3px; width:20px; height:20px; border-radius:50%; background:#fff; transition:transform .15s; box-shadow:0 1px 3px rgba(0,0,0,.25); }
.bec-toggle input:checked + .bec-toggle__box { background:var(--b-verde); }
.bec-toggle input:checked + .bec-toggle__box::after { transform:translateX(18px); }
.bec-toggle input:focus-visible + .bec-toggle__box { outline:3px solid var(--b-lima); outline-offset:2px; }
.bec-toggle__text { font-size:13.5px; }
.bec-radios { display:grid; gap:10px; grid-template-columns:1fr; }
@media (min-width:782px){ .bec-radios { grid-template-columns:repeat(3,1fr); } }
.bec-radio { position:relative; display:block; border:2px solid var(--b-linha); border-radius:14px; padding:14px 14px 12px; cursor:pointer; background:#fff; }
.bec-radio input { position:absolute; opacity:0; }
.bec-radio strong { display:block; font-size:14.5px; margin-bottom:4px; }
.bec-radio span { color:var(--b-cinza); font-size:12.5px; line-height:1.45; display:block; }
.bec-radio:has(input:checked) { border-color:var(--b-noite); background:#f8ffe0; box-shadow:0 0 0 3px rgba(200,255,61,.5); }
.bec-radio:has(input:focus-visible) { outline:3px solid var(--b-verde); outline-offset:2px; }
.bec-savebar { position:sticky; bottom:0; background:rgba(244,242,234,.94); backdrop-filter:blur(6px); padding:14px 0; border-top:1px solid var(--b-linha); display:flex; gap:10px; align-items:center; }
.bec-secret { font-size:12.5px; color:var(--b-ok); font-weight:600; }

/* Fila de revisão */
.bec-review { display:grid; gap:16px; }
.bec-rv { background:#fff; border:1px solid var(--b-linha); border-radius:16px; overflow:hidden; }
.bec-rv__head { padding:18px 20px 12px; }
.bec-rv__head h2 { margin:6px 0 6px; font-size:19px; line-height:1.25; padding:0; font-weight:800; }
.bec-rv__meta { color:var(--b-cinza); font-size:12.5px; display:flex; gap:6px 14px; flex-wrap:wrap; }
.bec-quality { margin-top:12px; max-width:520px; padding:10px 12px; border-radius:11px; background:#f4f2ea; }
.bec-quality__top { display:flex; justify-content:space-between; gap:10px; font-size:12px; color:var(--b-noite); }
.bec-quality__track { height:8px; margin:7px 0 5px; overflow:hidden; border-radius:99px; background:#dedbd0; }
.bec-quality__track span { display:block; height:100%; border-radius:inherit; background:#b3261e; }
.bec-quality--warn .bec-quality__track span { background:#bd7b00; }
.bec-quality--good .bec-quality__track span { background:#176b42; }
.bec-quality small { color:var(--b-cinza); font-size:11px; }
.bec-quality__parts { display:block; margin-bottom:3px; line-height:1.6; }
.bec-rv__body { padding:0 20px 14px; display:grid; gap:14px; grid-template-columns:1fr; }
@media (min-width:900px){ .bec-rv__body { grid-template-columns:1fr 1fr; } }
.bec-rv h4 { margin:0 0 6px; font-size:12px; text-transform:uppercase; letter-spacing:.07em; color:var(--b-cinza); }
.bec-rv ul { margin:0; padding-left:18px; font-size:13.5px; } .bec-rv li { margin-bottom:3px; }
.bec-rv__flags { background:#fff8e6; border:1px solid #f1dca4; border-radius:12px; padding:12px 14px; }
.bec-rv__src { background:#f4f2ea; border-radius:12px; padding:12px 14px; }
.bec-rv__foot { border-top:1px solid var(--b-linha); padding:14px 20px; background:#faf9f4; display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between; }
.bec-rv__foot form { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:0; }
.bec-empty { text-align:center; padding:42px 20px; color:var(--b-cinza); }
.bec-empty .dashicons { font-size:42px; width:42px; height:42px; color:var(--b-verde); }

/* Telas nativas de pautas e fontes */
body.bec-admin .wp-list-table { border-radius:14px; overflow:hidden; border-color:var(--b-linha); }
body.bec-admin .wp-list-table thead th { background:#fff; }
body.bec-admin .postbox { border-radius:14px; border-color:var(--b-linha); box-shadow:none; }
body.bec-admin .postbox .postbox-header { border-radius:14px 14px 0 0; }
body.bec-admin .page-title-action { border-radius:999px; background:var(--b-noite); color:#fff; border-color:var(--b-noite); }
body.bec-admin .page-title-action:hover { background:var(--b-verde); color:#fff; }
</style>
		<?php
	}
);
