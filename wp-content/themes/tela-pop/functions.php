<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TPOP_THEME_VERSION', '1.0.0' );

function tpop_theme_setup() {
	load_theme_textdomain( 'tela-pop', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array( 'height' => 90, 'width' => 360, 'flex-height' => true, 'flex-width' => true ) );

	register_nav_menus(
		array(
			'primary' => 'Menu principal',
			'footer'  => 'Menu do rodapé',
		)
	);

	add_image_size( 'tpop-hero', 1200, 750, true );
	add_image_size( 'tpop-card', 640, 427, true );
}
add_action( 'after_setup_theme', 'tpop_theme_setup' );

/** Ao ativar o tema, cria o menu padrão se o plugin estiver ativo. */
add_action(
	'after_switch_theme',
	function () {
		if ( function_exists( 'tpop_setup_menu' ) ) {
			tpop_setup_menu();
		}
	}
);

function tpop_theme_assets() {
	$dir = get_template_directory();
	if ( get_theme_mod( 'tpop_google_fonts', true ) ) {
		wp_enqueue_style( 'tpop-fonts', 'https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700;800&display=swap', array(), null );
	}
	wp_enqueue_style( 'tpop-style', get_stylesheet_uri(), array(), filemtime( $dir . '/style.css' ) );
	wp_enqueue_script( 'tpop-js', get_template_directory_uri() . '/assets/js/theme.js', array(), filemtime( $dir . '/assets/js/theme.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'tpop_theme_assets' );

/** Exclui da home textos ligados a pautas cuja notícia de origem já venceu. */
function tpop_home_filter_stale_source_posts( $where, $query ) {
	if ( is_admin() || ! $query->get( 'tpop_home_recent_only' ) ) {
		return $where;
	}
	global $wpdb;
	$cutoff = time() - 3 * DAY_IN_SECONDS;
	$where .= $wpdb->prepare(
		" AND NOT EXISTS (
			SELECT 1
			FROM {$wpdb->postmeta} AS tpop_pauta_link
			INNER JOIN {$wpdb->postmeta} AS tpop_source_date
				ON tpop_source_date.post_id = CAST(tpop_pauta_link.meta_value AS UNSIGNED)
			WHERE tpop_pauta_link.post_id = {$wpdb->posts}.ID
				AND tpop_pauta_link.meta_key = '_tpop_pauta_id'
				AND tpop_source_date.meta_key = '_tpop_ts_origem'
				AND CAST(tpop_source_date.meta_value AS UNSIGNED) < %d
		)",
		$cutoff
	);
	return $where;
}
add_filter( 'posts_where', 'tpop_home_filter_stale_source_posts', 10, 2 );

/** Conecta cedo ao servidor de fontes (mais rápido no celular). */
add_filter(
	'wp_resource_hints',
	function ( $urls, $relation ) {
		if ( 'preconnect' === $relation && get_theme_mod( 'tpop_google_fonts', true ) ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' );
		}
		return $urls;
	},
	10,
	2
);

function tpop_theme_widgets() {
	register_sidebar(
		array(
			'name'          => 'Anúncios',
			'id'            => 'tpop-ads',
			'description'   => 'Cole aqui o bloco de anúncios (ex.: AdSense) em um widget HTML personalizado.',
			'before_widget' => '<div class="ad-widget">',
			'after_widget'  => '</div>',
		)
	);
	register_sidebar(
		array(
			'name'          => 'Barra lateral',
			'id'            => 'tpop-sidebar',
			'before_widget' => '<section class="widget">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3>',
			'after_title'   => '</h3>',
		)
	);
	register_sidebar(
		array(
			'name'          => 'Rodapé',
			'id'            => 'tpop-footer',
			'before_widget' => '<div class="widget">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3>',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'tpop_theme_widgets' );

function tpop_theme_customize( $wp_customize ) {
	$wp_customize->add_section( 'tpop_home', array( 'title' => 'Estação Cinema — Identidade do site' ) );
	$wp_customize->add_setting(
		'tpop_ticker',
		array(
			'default'           => 'Sua próxima parada: grandes histórias.',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control( 'tpop_ticker', array( 'label' => 'Texto da faixa superior', 'section' => 'tpop_home', 'type' => 'text' ) );

	$wp_customize->add_setting( 'tpop_google_fonts', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'tpop_google_fonts', array( 'label' => 'Carregar fontes do Google (DM Serif Display e Manrope)', 'description' => 'Desmarque para usar só fontes do sistema (mais leve, sem conexão externa).', 'section' => 'tpop_home', 'type' => 'checkbox' ) );

	$wp_customize->add_setting( 'tpop_nl_title', array( 'default' => 'Sua próxima sessão começa aqui', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'tpop_nl_title', array( 'label' => 'Newsletter: título', 'section' => 'tpop_home', 'type' => 'text' ) );
	$wp_customize->add_setting( 'tpop_nl_text', array( 'default' => 'Receba críticas, estreias e guias de streaming selecionados pela redação. Sem spam.', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'tpop_nl_text', array( 'label' => 'Newsletter: texto de apoio', 'section' => 'tpop_home', 'type' => 'textarea' ) );
}
add_action( 'customize_register', 'tpop_theme_customize' );

/* ------------------------------------------------------------------
 * Funções de template
 * ---------------------------------------------------------------- */

function tpop_primary_category_name() {
	$cats = get_the_category();
	return $cats ? $cats[0]->name : 'Filmes e séries';
}

function tpop_the_category_label() {
	echo '<span class="category">' . esc_html( tpop_primary_category_name() ) . '</span>';
}

/** Data de publicação e, quando houver mudança relevante, data de atualização. */
function tpop_the_meta() {
	echo '<div class="meta">';
	echo '<span>Por ' . esc_html( get_the_author() ) . '</span>';
	echo '<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">Publicado em ' . esc_html( get_the_date( 'd/m/Y \à\s H:i' ) ) . '</time>';
	if ( get_the_modified_time( 'U' ) - get_the_time( 'U' ) > HOUR_IN_SECONDS ) {
		echo '<time datetime="' . esc_attr( get_the_modified_date( 'c' ) ) . '">Atualizado em ' . esc_html( get_the_modified_date( 'd/m/Y \à\s H:i' ) ) . '</time>';
	}
	echo '</div>';
}

function tpop_thumb( $size = 'tpop-card' ) {
	if ( has_post_thumbnail() ) {
		the_post_thumbnail( $size, array( 'class' => 'thumb', 'loading' => 'lazy' ) );
	} else {
		echo '<span class="thumb-placeholder" aria-hidden="true"></span>';
	}
}

/** Olho em SVG: ocupa o lugar da letra "O" em "OLHO". */
function tpop_eye_svg() {
	return '<svg class="eye" viewBox="0 0 32 24" aria-hidden="true" focusable="false"><path d="M2 12C6 5 11 3 16 3s10 2 14 9c-4 7-9 9-14 9S6 19 2 12z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/><circle cx="16" cy="12" r="6.6" fill="#55edc2"/><circle cx="16" cy="12" r="3" fill="#091326"/><circle cx="17.6" cy="10.3" r="1.1" fill="#fff"/></svg>';
}

/** Logotipo oficial do portal. */
function tpop_logo_html() {
	return '<img class="station-logo" src="' . esc_url( get_theme_file_uri( 'assets/images/estacao-cinema-logo.png' ) ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" width="725" height="380">';
}

/** Sigla do clube (ex.: Flamengo = FLA, São Paulo = SP) e matiz estável para o escudo genérico. */
function tpop_club_badge( $title ) {
	$words = preg_split( '/[\s\-]+/u', trim( $title ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $words ) >= 2 ) {
		$abbr = '';
		foreach ( array_slice( $words, 0, 3 ) as $w ) {
			$abbr .= mb_substr( $w, 0, 1 );
		}
	} else {
		$abbr = mb_substr( $title, 0, 3 );
	}
	$hue = abs( crc32( $title ) ) % 360;
	return '<span class="club-badge" style="--h:' . (int) $hue . '" aria-hidden="true">' . esc_html( mb_strtoupper( $abbr ) ) . '</span>';
}

/** Links de compartilhamento do artigo atual. */
function tpop_share_links() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( get_the_title() );
	return array(
		'wa' => 'https://wa.me/?text=' . $title . '%20' . $url,
		'fb' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
		'x'  => 'https://twitter.com/intent/tweet?text=' . $title . '&url=' . $url,
	);
}

/** Bloco de notícias de uma categoria na página inicial. */
function tpop_home_section( $slug, $title, $count = 4 ) {
	$term = get_category_by_slug( $slug );
	if ( ! $term ) {
		return;
	}
	$q = new WP_Query(
		array(
			'cat'                 => $term->term_id,
			'posts_per_page'      => $count,
			'tpop_home_recent_only' => false,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( ! $q->have_posts() ) {
		return;
	}
	echo '<section class="section"><div class="section-title"><h2>' . esc_html( $title ) . '</h2><a href="' . esc_url( get_category_link( $term ) ) . '">Ver tudo</a></div><div class="rail">';
	while ( $q->have_posts() ) {
		$q->the_post();
		get_template_part( 'template-parts/card' );
	}
	echo '</div></section>';
	wp_reset_postdata();
}

function tpop_fallback_menu() {
	$items = array(
		'Início'          => '/',
		'Filmes'          => '/category/filmes/',
		'Séries'          => '/category/series/',
		'Críticas'        => '/category/criticas/',
		'Lançamentos'     => '/category/lancamentos/',
		'Onde assistir'   => '/category/onde-assistir/',
		'Listas'          => '/category/listas/',
	);
	echo '<ul class="menu">';
	foreach ( $items as $label => $path ) {
		echo '<li><a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul>';
}

/** Posts relacionados: mesma tag (clube) ou mesma categoria. */
function tpop_related_posts( $count = 4 ) {
	$args = array(
		'post__not_in'        => array( get_the_ID() ),
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	$tags = wp_get_post_tags( get_the_ID(), array( 'fields' => 'ids' ) );
	$cats = wp_get_post_categories( get_the_ID() );
	if ( $tags ) {
		$args['tag__in'] = $tags;
	} elseif ( $cats ) {
		$args['category__in'] = $cats;
	}
	return new WP_Query( $args );
}

function tpop_excerpt_length() {
	return 24;
}
add_filter( 'excerpt_length', 'tpop_excerpt_length' );
add_filter( 'excerpt_more', '__return_empty_string' );
