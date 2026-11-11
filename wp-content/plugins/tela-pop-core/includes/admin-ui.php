<?php
/**
 * Interface do painel: navegação por etapas, estilos e componentes compartilhados.
 * Responsabilidade: só apresentação. Nenhuma regra editorial mora aqui.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** A tela atual pertence ao plugin? (páginas do plugin e listas/edição de pautas e fontes) */
function tpop_is_admin_screen() {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return false;
	}
	$screen = get_current_screen();
	if ( ! $screen ) {
		return false;
	}
	if ( in_array( $screen->post_type, array( 'tpop_pauta', 'tpop_fonte' ), true ) ) {
		return true;
	}
	return false !== strpos( (string) $screen->id, 'tpop-' );
}

/** As etapas do fluxo, na ordem em que acontecem, e quem responde por cada uma. */
function tpop_flow_stages() {
	return array(
		'coleta'   => array( 'n' => 1, 'title' => 'Coleta', 'who' => 'Sistema', 'icon' => 'rss', 'desc' => 'Lê os feeds RSS cadastrados e cria pautas com título, resumo e link.' ),
		'triagem'  => array( 'n' => 2, 'title' => 'Triagem', 'who' => 'Sistema ou equipe', 'icon' => 'filter', 'desc' => 'Pontua cada pauta e aprova só o que cumpre as regras.' ),
		'redacao'  => array( 'n' => 3, 'title' => 'Redação', 'who' => 'IA', 'icon' => 'edit', 'desc' => 'Escreve um texto original usando só os dados reais coletados.' ),
		'revisao'  => array( 'n' => 4, 'title' => 'Revisão', 'who' => 'Pessoa', 'icon' => 'yes-alt', 'desc' => 'Confere fatos, ajusta o texto e aprova. Obrigatório.' ),
		'publicar' => array( 'n' => 5, 'title' => 'Publicação', 'who' => 'Pessoa', 'icon' => 'megaphone', 'desc' => 'Publica no site. Nada vai ao ar sozinho.' ),
	);
}

/** Itens da barra de navegação (slug da tela => rótulo, URL). */
function tpop_nav_items() {
	$items = array(
		'tpop-painel'     => array( 'Visão geral', admin_url( 'admin.php?page=tpop-painel' ) ),
		'tpop-revisao'    => array( 'Fila de revisão', admin_url( 'admin.php?page=tpop-revisao' ) ),
		'tpop_pauta'      => array( 'Pautas', admin_url( 'edit.php?post_type=tpop_pauta' ) ),
		'tpop_fonte'      => array( 'Fontes RSS', admin_url( 'edit.php?post_type=tpop_fonte' ) ),
		'tpop-newsletter' => array( 'Newsletter', admin_url( 'admin.php?page=tpop-newsletter' ) ),
	);
	if ( current_user_can( 'manage_options' ) ) {
		$items['tpop-config'] = array( 'Configurações', admin_url( 'admin.php?page=tpop-config' ) );
	}
	return $items;
}

add_action(
	'in_admin_header',
	function () {
		if ( ! tpop_is_admin_screen() ) {
			return;
		}
		$screen  = get_current_screen();
		$current = 'tpop-painel';
		if ( in_array( $screen->post_type, array( 'tpop_pauta', 'tpop_fonte' ), true ) ) {
			$current = $screen->post_type;
		} else {
			foreach ( array( 'tpop-revisao', 'tpop-newsletter', 'tpop-config', 'tpop-painel' ) as $slug ) {
				if ( false !== strpos( (string) $screen->id, $slug ) ) {
					$current = $slug;
					break;
				}
			}
		}
		$waiting = function_exists( 'tpop_review_count' ) ? tpop_review_count() : 0;
		echo '<nav class="tpop-nav" aria-label="Tela Pop"><span class="tpop-nav__brand">Tela Pop</span>';
		foreach ( tpop_nav_items() as $slug => $item ) {
			$badge = ( 'tpop-revisao' === $slug && $waiting ) ? ' <span class="tpop-nav__badge">' . (int) $waiting . '</span>' : '';
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
		return tpop_is_admin_screen() ? $classes . ' tpop-admin' : $classes;
	}
);

/** Menu lateral na ordem do fluxo, com rótulos claros e o contador da fila de revisão. */
add_action(
	'admin_menu',
	function () {
		global $submenu;
		if ( empty( $submenu['tpop-painel'] ) ) {
			return;
		}
		$waiting = function_exists( 'tpop_review_count' ) ? tpop_review_count() : 0;
		$labels  = array(
			'tpop-painel'                   => 'Visão geral',
			'tpop-revisao'                  => 'Fila de revisão' . ( $waiting ? ' <span class="awaiting-mod">' . (int) $waiting . '</span>' : '' ),
			'edit.php?post_type=tpop_pauta' => 'Pautas',
			'edit.php?post_type=tpop_fonte' => 'Fontes RSS',
			'tpop-newsletter'               => 'Newsletter',
			'tpop-config'                   => 'Configurações',
		);
		$by_slug = array();
		foreach ( $submenu['tpop-painel'] as $item ) {
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
		$submenu['tpop-painel'] = $ordered; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	},
	999
);

/** Cabeçalho padrão das páginas do plugin. */
function tpop_ui_header( $title, $subtitle = '', $actions_html = '' ) {
	echo '<header class="tpop-head"><div><h1>' . esc_html( $title ) . '</h1>';
	if ( $subtitle ) {
		echo '<p>' . esc_html( $subtitle ) . '</p>';
	}
	echo '</div>';
	if ( $actions_html ) {
		echo '<div class="tpop-head__actions">' . $actions_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</header>';
}

/** Selo colorido. $tone: ok | warn | err | info | mute */
function tpop_pill( $text, $tone = 'mute' ) {
	return '<span class="tpop-pill tpop-pill--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
}

/** Cor do selo para cada status de pauta. */
function tpop_status_tone( $status ) {
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
		if ( ! tpop_is_admin_screen() ) {
			return;
		}
		?>
<style id="tpop-admin-css">
body.tpop-admin { --b-noite:#07140d; --b-noite2:#0e2217; --b-verde:#0f6b3f; --b-lima:#c8ff3d; --b-papel:#f4f2ea; --b-linha:#e2dfd2; --b-cinza:#5b6a62; --b-tinta:#10231a; --b-ok:#1a7f37; --b-warn:#a15c00; --b-err:#b3261e; --b-info:#1f5fbf; background:var(--b-papel); }
body.tpop-admin #wpcontent { background:var(--b-papel); }
body.tpop-admin #wpbody-content { padding-bottom:40px; }
body.tpop-admin .wrap { max-width:1180px; margin:0 20px 0 20px; color:var(--b-tinta); }
body.tpop-admin .wrap h1.wp-heading-inline { font-size:26px; font-weight:800; letter-spacing:-.01em; }
body.tpop-admin .notice { border-radius:10px; border-left-width:5px; box-shadow:none; }

.tpop-nav { display:flex; align-items:center; gap:2px; background:var(--b-noite); margin:0 0 0 -20px; padding:0 20px; overflow-x:auto; white-space:nowrap; position:sticky; top:32px; z-index:20; }
.tpop-nav__brand { color:var(--b-lima); font-weight:800; letter-spacing:.04em; text-transform:uppercase; font-size:13px; margin-right:18px; }
.tpop-nav a { color:#c9dfd1; text-decoration:none; padding:15px 14px 12px; font-weight:600; font-size:13.5px; border-bottom:3px solid transparent; }
.tpop-nav a:hover { color:#fff; }
.tpop-nav a.is-active { color:#fff; border-bottom-color:var(--b-lima); }
.tpop-nav__badge { background:var(--b-lima); color:var(--b-noite); border-radius:999px; font-size:11px; font-weight:800; padding:1px 7px; margin-left:4px; }
@media (max-width:782px){ .tpop-nav { top:46px; margin-left:-10px; padding:0 10px; } }

.tpop-ui { max-width:1180px; margin:22px 20px 0 0; color:var(--b-tinta); }
.tpop-ui * { box-sizing:border-box; }
.tpop-head { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
.tpop-head h1 { margin:0; padding:0; font-size:28px; line-height:1.1; font-weight:800; letter-spacing:-.015em; }
.tpop-head p { margin:6px 0 0; color:var(--b-cinza); font-size:14px; max-width:68ch; }
.tpop-head__actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.tpop-ui .button, .tpop-ui .button-primary { border-radius:999px; padding:4px 16px; min-height:36px; line-height:2; font-weight:600; border-color:var(--b-noite); color:var(--b-noite); background:#fff; }
.tpop-ui .button:hover { background:var(--b-lima); border-color:var(--b-noite); color:var(--b-noite); }
.tpop-ui .button-primary { background:var(--b-noite); color:#fff; border-color:var(--b-noite); }
.tpop-ui .button-primary:hover { background:var(--b-verde); color:#fff; border-color:var(--b-verde); }
.tpop-ui .button-link-delete { color:var(--b-err); }

.tpop-card { background:#fff; border:1px solid var(--b-linha); border-radius:16px; padding:20px; margin-bottom:18px; }
.tpop-card > h2, .tpop-card__title { margin:0 0 4px; font-size:17px; font-weight:800; padding:0; }
.tpop-card__sub { margin:0 0 14px; color:var(--b-cinza); font-size:13.5px; }
.tpop-grid { display:grid; gap:18px; grid-template-columns:minmax(0,1fr); }
.tpop-grid > *, .tpop-card { min-width:0; }
.tpop-card { overflow-wrap:anywhere; }
.tpop-feed a { color:var(--b-tinta); text-decoration:none; } .tpop-feed a:hover { text-decoration:underline; color:var(--b-verde); }
@media (min-width:900px){ .tpop-grid--2 { grid-template-columns:minmax(0,1.2fr) minmax(0,1fr); } .tpop-grid--3 { grid-template-columns:repeat(3,minmax(0,1fr)); } }

.tpop-pill { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11.5px; font-weight:700; letter-spacing:.02em; line-height:1.6; white-space:nowrap; }
.tpop-pill--ok { background:#e3f4e8; color:var(--b-ok); } .tpop-pill--warn { background:#fdf0d5; color:var(--b-warn); }
.tpop-pill--err { background:#fde5e3; color:var(--b-err); } .tpop-pill--info { background:#e3edfb; color:var(--b-info); }
.tpop-pill--mute { background:#ecebe3; color:var(--b-cinza); } .tpop-pill--dark { background:var(--b-noite); color:var(--b-lima); }

/* Etapas do fluxo */
.tpop-steps { display:grid; gap:12px; grid-template-columns:1fr; margin-bottom:18px; counter-reset:step; }
@media (min-width:700px){ .tpop-steps { grid-template-columns:repeat(5,1fr); } }
.tpop-step { position:relative; background:#fff; border:1px solid var(--b-linha); border-radius:16px; padding:16px 16px 14px; text-decoration:none; color:inherit; display:flex; flex-direction:column; gap:6px; transition:transform .15s, box-shadow .15s; }
a.tpop-step:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(7,20,13,.08); color:inherit; }
.tpop-step__top { display:flex; align-items:center; justify-content:space-between; }
.tpop-step__n { width:26px; height:26px; border-radius:50%; background:var(--b-noite); color:var(--b-lima); font-weight:800; font-size:13px; display:grid; place-items:center; }
.tpop-step--human .tpop-step__n { background:var(--b-lima); color:var(--b-noite); }
.tpop-step h3 { margin:6px 0 0; font-size:16px; font-weight:800; }
.tpop-step__num { font-size:34px; line-height:1; font-weight:800; letter-spacing:-.02em; }
.tpop-step__num small { font-size:12px; font-weight:600; color:var(--b-cinza); letter-spacing:0; margin-left:4px; }
.tpop-step p { margin:0; color:var(--b-cinza); font-size:12.5px; line-height:1.4; }
.tpop-step--alert { border-color:var(--b-lima); box-shadow:0 0 0 3px rgba(200,255,61,.45); }

/* Tabelas e listas */
.tpop-ui table.widefat { border:0; box-shadow:none; background:transparent; }
.tpop-ui table.widefat th { font-size:11.5px; text-transform:uppercase; letter-spacing:.06em; color:var(--b-cinza); border-bottom:1px solid var(--b-linha); }
.tpop-ui table.widefat td { border-bottom:1px solid var(--b-linha); vertical-align:middle; }
.tpop-ui table.widefat.striped > tbody > :nth-child(odd) { background:transparent; }
.tpop-feed { list-style:none; margin:0; padding:0; }
.tpop-feed li { display:flex; gap:10px; padding:9px 0; border-bottom:1px solid var(--b-linha); font-size:13px; align-items:flex-start; }
.tpop-feed li:last-child { border:0; }
.tpop-feed time { color:var(--b-cinza); font-size:12px; min-width:84px; flex:none; }
.tpop-check { list-style:none; margin:0; padding:0; }
.tpop-check li { display:flex; gap:10px; padding:10px 0; border-bottom:1px solid var(--b-linha); align-items:flex-start; font-size:13.5px; }
.tpop-check li:last-child { border:0; }
.tpop-check .dashicons { flex:none; }
.tpop-check .is-ok .dashicons { color:var(--b-ok); } .tpop-check .is-warn .dashicons { color:var(--b-warn); } .tpop-check .is-err .dashicons { color:var(--b-err); }
.tpop-check small { display:block; color:var(--b-cinza); }

/* Configurações */
.tpop-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
.tpop-tabs a { text-decoration:none; padding:8px 16px; border-radius:999px; background:#fff; border:1px solid var(--b-linha); color:var(--b-tinta); font-weight:600; font-size:13.5px; }
.tpop-tabs a:hover { border-color:var(--b-noite); }
.tpop-tabs a.is-active { background:var(--b-noite); color:#fff; border-color:var(--b-noite); }
.tpop-field { display:grid; gap:6px 24px; padding:16px 0; border-bottom:1px solid var(--b-linha); grid-template-columns:1fr; }
.tpop-field:last-child { border-bottom:0; }
@media (min-width:782px){ .tpop-field { grid-template-columns:minmax(220px,300px) 1fr; } }
.tpop-field__label { font-weight:700; font-size:14px; }
.tpop-field__help { color:var(--b-cinza); font-size:12.5px; margin:4px 0 0; line-height:1.45; }
.tpop-field input[type=text], .tpop-field input[type=email], .tpop-field input[type=password], .tpop-field input[type=number], .tpop-field select, .tpop-field textarea { border-radius:10px; border:1px solid #c9c6b8; padding:7px 12px; min-height:38px; max-width:100%; background:#fff; }
.tpop-field input[type=text], .tpop-field input[type=email], .tpop-field input[type=password], .tpop-field textarea { width:100%; max-width:520px; }
.tpop-field input:focus, .tpop-field select:focus, .tpop-field textarea:focus { border-color:var(--b-verde); box-shadow:0 0 0 3px rgba(15,107,63,.18); outline:0; }
.tpop-unit { color:var(--b-cinza); font-size:13px; margin-left:6px; }
.tpop-toggle { display:inline-flex; align-items:center; gap:12px; cursor:pointer; }
.tpop-toggle input { position:absolute; opacity:0; width:1px; height:1px; }
.tpop-toggle__box { width:44px; height:26px; border-radius:999px; background:#c9c6b8; position:relative; transition:background .15s; flex:none; }
.tpop-toggle__box::after { content:""; position:absolute; top:3px; left:3px; width:20px; height:20px; border-radius:50%; background:#fff; transition:transform .15s; box-shadow:0 1px 3px rgba(0,0,0,.25); }
.tpop-toggle input:checked + .tpop-toggle__box { background:var(--b-verde); }
.tpop-toggle input:checked + .tpop-toggle__box::after { transform:translateX(18px); }
.tpop-toggle input:focus-visible + .tpop-toggle__box { outline:3px solid var(--b-lima); outline-offset:2px; }
.tpop-toggle__text { font-size:13.5px; }
.tpop-radios { display:grid; gap:10px; grid-template-columns:1fr; }
@media (min-width:782px){ .tpop-radios { grid-template-columns:repeat(3,1fr); } }
.tpop-radio { position:relative; display:block; border:2px solid var(--b-linha); border-radius:14px; padding:14px 14px 12px; cursor:pointer; background:#fff; }
.tpop-radio input { position:absolute; opacity:0; }
.tpop-radio strong { display:block; font-size:14.5px; margin-bottom:4px; }
.tpop-radio span { color:var(--b-cinza); font-size:12.5px; line-height:1.45; display:block; }
.tpop-radio:has(input:checked) { border-color:var(--b-noite); background:#f8ffe0; box-shadow:0 0 0 3px rgba(200,255,61,.5); }
.tpop-radio:has(input:focus-visible) { outline:3px solid var(--b-verde); outline-offset:2px; }
.tpop-savebar { position:sticky; bottom:0; background:rgba(244,242,234,.94); backdrop-filter:blur(6px); padding:14px 0; border-top:1px solid var(--b-linha); display:flex; gap:10px; align-items:center; }
.tpop-secret { font-size:12.5px; color:var(--b-ok); font-weight:600; }

/* Fila de revisão */
.tpop-review { display:grid; gap:16px; }
.tpop-rv { background:#fff; border:1px solid var(--b-linha); border-radius:16px; overflow:hidden; }
.tpop-rv__head { padding:18px 20px 12px; }
.tpop-rv__head h2 { margin:6px 0 6px; font-size:19px; line-height:1.25; padding:0; font-weight:800; }
.tpop-rv__meta { color:var(--b-cinza); font-size:12.5px; display:flex; gap:6px 14px; flex-wrap:wrap; }
.tpop-quality { margin-top:12px; max-width:520px; padding:10px 12px; border-radius:11px; background:#f4f2ea; }
.tpop-quality__top { display:flex; justify-content:space-between; gap:10px; font-size:12px; color:var(--b-noite); }
.tpop-quality__track { height:8px; margin:7px 0 5px; overflow:hidden; border-radius:99px; background:#dedbd0; }
.tpop-quality__track span { display:block; height:100%; border-radius:inherit; background:#b3261e; }
.tpop-quality--warn .tpop-quality__track span { background:#bd7b00; }
.tpop-quality--good .tpop-quality__track span { background:#176b42; }
.tpop-quality small { color:var(--b-cinza); font-size:11px; }
.tpop-quality__parts { display:block; margin-bottom:3px; line-height:1.6; }
.tpop-rv__body { padding:0 20px 14px; display:grid; gap:14px; grid-template-columns:1fr; }
@media (min-width:900px){ .tpop-rv__body { grid-template-columns:1fr 1fr; } }
.tpop-rv h4 { margin:0 0 6px; font-size:12px; text-transform:uppercase; letter-spacing:.07em; color:var(--b-cinza); }
.tpop-rv ul { margin:0; padding-left:18px; font-size:13.5px; } .tpop-rv li { margin-bottom:3px; }
.tpop-rv__flags { background:#fff8e6; border:1px solid #f1dca4; border-radius:12px; padding:12px 14px; }
.tpop-rv__src { background:#f4f2ea; border-radius:12px; padding:12px 14px; }
.tpop-rv__foot { border-top:1px solid var(--b-linha); padding:14px 20px; background:#faf9f4; display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between; }
.tpop-rv__foot form { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:0; }
.tpop-empty { text-align:center; padding:42px 20px; color:var(--b-cinza); }
.tpop-empty .dashicons { font-size:42px; width:42px; height:42px; color:var(--b-verde); }

/* Telas nativas de pautas e fontes */
body.tpop-admin .wp-list-table { border-radius:14px; overflow:hidden; border-color:var(--b-linha); }
body.tpop-admin .wp-list-table thead th { background:#fff; }
body.tpop-admin .postbox { border-radius:14px; border-color:var(--b-linha); box-shadow:none; }
body.tpop-admin .postbox .postbox-header { border-radius:14px 14px 0 0; }
body.tpop-admin .page-title-action { border-radius:999px; background:var(--b-noite); color:#fff; border-color:var(--b-noite); }
body.tpop-admin .page-title-action:hover { background:var(--b-verde); color:#fff; }
</style>
		<?php
	}
);
