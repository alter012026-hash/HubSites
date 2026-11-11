<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Migra somente o identificador antigo que foi aposentado pela Groq; preserva modelos personalizados.
add_action(
	'plugins_loaded',
	function () {
		$options = get_option( 'bec_options', array() );
		if ( is_array( $options ) && isset( $options['groq_model'] ) && 'llama-3.3-70b-versatile' === $options['groq_model'] ) {
			$options['groq_model'] = 'openai/gpt-oss-20b';
			update_option( 'bec_options', $options, false );
			bec_activity( 'ia', 'Modelo GroqCloud migrado para openai/gpt-oss-20b após aposentadoria do modelo anterior.', admin_url( 'admin.php?page=bec-config&tab=ia' ) );
		}
	}
);

function bec_activate() {
	bec_register_post_types();
	bec_install_defaults();
	bec_nl_install_table();
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'bec_pipeline_event' ) ) {
		wp_schedule_event( time() + 300, 'bec_15min', 'bec_pipeline_event' );
	}
}

function bec_deactivate() {
	wp_clear_scheduled_hook( 'bec_fetch_sources_event' );
	wp_clear_scheduled_hook( 'bec_pipeline_event' );
	wp_clear_scheduled_hook( 'bec_nl_hourly_event' );
	wp_clear_scheduled_hook( 'bec_nl_batch_event' );
	flush_rewrite_rules();
}

/** Cria as novas categorias de Notícias também em instalações já atualizadas. */
add_action(
	'init',
	function () {
		foreach ( array( 'resultados' => 'Resultados', 'jogos-na-tv' => 'Jogos na TV' ) as $slug => $name ) {
			if ( ! term_exists( $slug, 'category' ) ) {
				wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
			}
		}
	},
	20
);

/** Categorias do menu (seção 4.1). */
function bec_default_categories() {
	return array(
		'noticias'        => 'Notícias',
		'brasileirao'     => 'Brasileirão',
		'libertadores'    => 'Libertadores',
		'copa-do-brasil'  => 'Copa do Brasil',
		'mercado-da-bola' => 'Mercado da Bola',
		'analises'        => 'Análises',
		'resultados'      => 'Resultados',
		'jogos-na-tv'     => 'Jogos na TV',
	);
}

function bec_install_defaults() {
	// Categorias.
	foreach ( bec_default_categories() as $slug => $name ) {
		if ( ! term_exists( $slug, 'category' ) ) {
			wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		}
	}

	// Clubes: criados como RASCUNHO. Publique cada página só quando tiver conteúdo útil (seção 12).
	foreach ( bec_clubs() as $slug => $club ) {
		if ( get_page_by_path( $slug, OBJECT, 'clube' ) ) {
			continue;
		}
		wp_insert_post(
			array(
				'post_type'    => 'clube',
				'post_status'  => 'draft',
				'post_title'   => $club['name'],
				'post_name'    => $slug,
				'post_content' => '<!-- wp:paragraph --><p>[PREENCHER: apresentação do clube, história resumida, estádio, títulos e rivalidades. Notícias, jogos e resultados aparecem automaticamente abaixo deste texto.]</p><!-- /wp:paragraph -->',
			)
		);
	}

	// Páginas institucionais (rascunho para revisão humana).
	$pages = array(
		'sobre'              => array( 'Sobre o De Olho na Bola', 'O De Olho na Bola é um portal de notícias, informações e análises de futebol para o torcedor brasileiro. [REVISAR: acrescente a história do projeto e a equipe responsável.]' ),
		'politica-editorial' => array( 'Política editorial', 'O De Olho na Bola publica informação apurada, atribuída a fontes e revisada por pessoas antes de ir ao ar. Podemos usar ferramentas automatizadas para identificar pautas e preparar rascunhos, mas nenhum texto é publicado sem revisão humana. Rumores são identificados como rumores e fontes são sempre indicadas. [REVISAR: complete com critérios de apuração, uso de imagens e política de independência.]' ),
		'correcoes'          => array( 'Correções', 'Se encontrar um erro em qualquer matéria, escreva para [REVISAR: e-mail de contato]. Corrigimos o texto, registramos a data da atualização e, quando o erro for relevante, indicamos a correção na própria matéria.' ),
		'contato'            => array( 'Contato', '[REVISAR: informe e-mail e canais de contato da redação.]' ),
		'privacidade'        => array( 'Política de privacidade', '[REVISAR: elabore a política de privacidade conforme a LGPD, incluindo cookies, analytics e anúncios.]' ),
	);
	foreach ( $pages as $slug => $p ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => $p[0],
				'post_name'    => $slug,
				'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $p[1] ) . '</p><!-- /wp:paragraph -->',
			)
		);
	}

	// Fontes iniciais (seção 6.2). Os feeds ficam vazios: cadastre apenas feeds cujo uso seja permitido.
	$fontes = array(
		array( 'CBF — Confederação Brasileira de Futebol', 'https://www.cbf.com.br/', 'federacao', 95 ),
		array( 'CONMEBOL', 'https://www.conmebol.com/', 'competicao', 95 ),
		array( 'FIFA', 'https://www.fifa.com/', 'competicao', 95 ),
	);
	foreach ( $fontes as $f ) {
		$exists = get_posts( array( 'post_type' => 'bec_fonte', 'title' => $f[0], 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
		if ( $exists ) {
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'bec_fonte', 'post_status' => 'publish', 'post_title' => $f[0] ) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_bec_site_url', $f[1] );
			update_post_meta( $id, '_bec_tipo', $f[2] );
			update_post_meta( $id, '_bec_permissao', 'oficial' );
			update_post_meta( $id, '_bec_confiabilidade', $f[3] );
			update_post_meta( $id, '_bec_intervalo', 60 );
			update_post_meta( $id, '_bec_ativa', '0' );
		}
	}

	bec_setup_menu();
	update_option( 'bec_installed_version', BEC_VERSION, false );
}

/** Cria o "Menu principal" e o associa ao local "primary" do tema, se ainda não houver menu. */
function bec_setup_menu() {
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( ! empty( $locations['primary'] ) ) {
		return;
	}
	$menu = wp_get_nav_menu_object( 'Menu principal' );
	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( 'Menu principal' );
		if ( is_wp_error( $menu_id ) ) {
			return;
		}
		wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Início', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
		foreach ( bec_default_categories() as $slug => $name ) {
			if ( in_array( $slug, array( 'resultados', 'jogos-na-tv' ), true ) ) {
				continue; // Entram como atalhos na central de Notícias, não como itens de primeiro nível.
			}
			// "Clubes" fica antes de "Análises", como no documento técnico (seção 4.1).
			if ( 'analises' === $slug ) {
				wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Clubes', 'menu-item-url' => home_url( '/clubes/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
			}
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( $term ) {
				wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $name, 'menu-item-object' => 'category', 'menu-item-object-id' => $term->term_id, 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish' ) );
			}
		}
	} else {
		$menu_id = $menu->term_id;
	}
	$locations['primary'] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}
