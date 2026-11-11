<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Migra somente o identificador antigo que foi aposentado pela Groq; preserva modelos personalizados.
add_action(
	'plugins_loaded',
	function () {
		$options = get_option( 'tpop_options', array() );
		if ( is_array( $options ) && isset( $options['groq_model'] ) && 'llama-3.3-70b-versatile' === $options['groq_model'] ) {
			$options['groq_model'] = 'openai/gpt-oss-20b';
			update_option( 'tpop_options', $options, false );
			tpop_activity( 'ia', 'Modelo GroqCloud migrado para openai/gpt-oss-20b após aposentadoria do modelo anterior.', admin_url( 'admin.php?page=tpop-config&tab=ia' ) );
		}
	}
);

function tpop_activate() {
	tpop_register_post_types();
	tpop_install_defaults();
	tpop_nl_install_table();
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'tpop_pipeline_event' ) ) {
		wp_schedule_event( time() + 300, 'tpop_15min', 'tpop_pipeline_event' );
	}
}

function tpop_deactivate() {
	wp_clear_scheduled_hook( 'tpop_fetch_sources_event' );
	wp_clear_scheduled_hook( 'tpop_pipeline_event' );
	wp_clear_scheduled_hook( 'tpop_nl_hourly_event' );
	wp_clear_scheduled_hook( 'tpop_nl_batch_event' );
	flush_rewrite_rules();
}

/** Cria as novas categorias de Notícias também em instalações já atualizadas. */
add_action(
	'init',
	function () {
		foreach ( array( 'onde-assistir' => 'Onde assistir', 'listas' => 'Listas' ) as $slug => $name ) {
			if ( ! term_exists( $slug, 'category' ) ) {
				wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
			}
		}
	},
	20
);

/** Categorias do menu (seção 4.1). */
function tpop_default_categories() {
	return array(
		'filmes'          => 'Filmes',
		'series'          => 'Séries',
		'criticas'        => 'Críticas',
		'lancamentos'     => 'Lançamentos',
		'onde-assistir'   => 'Onde assistir',
		'listas'          => 'Listas',
	);
}

function tpop_install_defaults() {
	// Categorias.
	foreach ( tpop_default_categories() as $slug => $name ) {
		if ( ! term_exists( $slug, 'category' ) ) {
			wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		}
	}

	// Páginas institucionais e de navegação (rascunho para revisão humana).
	$pages = array(
		'sobre'              => array( 'Sobre o Tela Pop', 'O Tela Pop é um portal brasileiro de filmes, séries, críticas e guias de streaming. [REVISAR: acrescente a história do projeto e a equipe responsável.]' ),
		'politica-editorial' => array( 'Política editorial', 'O Tela Pop publica notícias, críticas e guias com fontes identificadas e revisão humana antes de ir ao ar. Ferramentas automatizadas podem localizar pautas e preparar rascunhos, mas nenhum texto é publicado sem revisão humana. Datas, elenco, disponibilidade e informações de catálogo são conferidos antes da publicação. [REVISAR: complete com critérios de apuração, uso de imagens e política de independência.]' ),
		'correcoes'          => array( 'Correções', 'Se encontrar um erro em qualquer matéria, escreva para [REVISAR: e-mail de contato]. Corrigimos o texto, registramos a data da atualização e, quando o erro for relevante, indicamos a correção na própria matéria.' ),
		'contato'            => array( 'Contato', '[REVISAR: informe e-mail e canais de contato da redação.]' ),
		'onde-assistir'      => array( 'Onde assistir', 'Guias de disponibilidade por plataforma. [REVISAR: publique somente onde assistir que tenha sido confirmado em fonte atual.]' ),
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

	// As fontes são cadastradas pelo editor conforme o feed e os termos de uso de cada veículo.
	$fontes = array();
	foreach ( $fontes as $f ) {
		$exists = get_posts( array( 'post_type' => 'tpop_fonte', 'title' => $f[0], 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
		if ( $exists ) {
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'tpop_fonte', 'post_status' => 'publish', 'post_title' => $f[0] ) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_tpop_site_url', $f[1] );
			update_post_meta( $id, '_tpop_tipo', $f[2] );
			update_post_meta( $id, '_tpop_permissao', 'oficial' );
			update_post_meta( $id, '_tpop_confiabilidade', $f[3] );
			update_post_meta( $id, '_tpop_intervalo', 60 );
			update_post_meta( $id, '_tpop_ativa', '0' );
		}
	}

	tpop_setup_menu();
	update_option( 'tpop_installed_version', TPOP_VERSION, false );
}

/** Cria o "Menu principal" e o associa ao local "primary" do tema, se ainda não houver menu. */
function tpop_setup_menu() {
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
		foreach ( tpop_default_categories() as $slug => $name ) {
			if ( 'listas' === $slug ) {
				wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Catálogo', 'menu-item-url' => home_url( '/catalogo/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
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
