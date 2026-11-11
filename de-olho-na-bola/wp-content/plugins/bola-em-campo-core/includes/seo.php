<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * SEO básico. Se Yoast, Rank Math ou All in One SEO estiverem ativos, este módulo
 * não imprime metadados para evitar duplicidade (apenas breadcrumbs e robots).
 */

function bec_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' );
}

function bec_meta_description() {
	if ( is_singular() ) {
		$post = get_queried_object();
		$text = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = term_description();
	} elseif ( is_home() || is_front_page() ) {
		$text = get_bloginfo( 'description' );
	} else {
		return '';
	}
	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
	return wp_html_excerpt( $text, 155, '…' );
}

add_action(
	'wp_head',
	function () {
		if ( bec_seo_plugin_active() || is_404() || is_search() ) {
			return;
		}
		$desc  = bec_meta_description();
		$title = wp_get_document_title();
		$url   = is_singular() ? get_permalink() : ( is_front_page() ? home_url( '/' ) : '' );
		$type  = is_singular( 'post' ) ? 'article' : 'website';
		$image = '';
		if ( is_singular() && has_post_thumbnail() ) {
			$image = get_the_post_thumbnail_url( null, 'large' );
		}

		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		echo '<meta property="og:locale" content="pt_BR">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( $url ) {
			echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		}
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
		}
		echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		if ( is_singular( 'post' ) ) {
			echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
			echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
		}
	},
	5
);

/* Dados estruturados: NewsArticle nos artigos e WebSite na página inicial. */
add_action(
	'wp_head',
	function () {
		if ( bec_seo_plugin_active() ) {
			return;
		}
		$graph = null;

		if ( is_singular( 'post' ) ) {
			$post   = get_queried_object();
			$logo   = '';
			$logo_id = get_theme_mod( 'custom_logo' );
			if ( $logo_id ) {
				$logo = wp_get_attachment_image_url( $logo_id, 'full' );
			}
			$graph = array(
				'@context'         => 'https://schema.org',
				'@type'            => 'NewsArticle',
				'headline'         => wp_html_excerpt( get_the_title( $post ), 110 ),
				'datePublished'    => get_the_date( 'c', $post ),
				'dateModified'     => get_the_modified_date( 'c', $post ),
				'mainEntityOfPage' => get_permalink( $post ),
				'inLanguage'       => 'pt-BR',
				'author'           => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $post->post_author ) ),
				'publisher'        => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) ),
			);
			if ( $logo ) {
				$graph['publisher']['logo'] = array( '@type' => 'ImageObject', 'url' => $logo );
			}
			if ( has_post_thumbnail( $post ) ) {
				$graph['image'] = array( get_the_post_thumbnail_url( $post, 'large' ) );
			}
			$desc = bec_meta_description();
			if ( $desc ) {
				$graph['description'] = $desc;
			}
		} elseif ( is_front_page() ) {
			$graph = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'WebSite',
				'name'            => get_bloginfo( 'name' ),
				'url'             => home_url( '/' ),
				'inLanguage'      => 'pt-BR',
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => home_url( '/?s={search_term_string}' ),
					'query-input' => 'required name=search_term_string',
				),
			);
		}

		if ( $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
		}
	},
	6
);

/* Impede indexação de páginas sem valor editorial (busca, autor, anexos, 404). */
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_search() || is_404() || is_author() || is_attachment() || is_feed() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}
);

/* robots.txt virtual (não funciona se existir um robots.txt físico na raiz). */
add_filter(
	'robots_txt',
	function ( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		$lines  = array(
			'User-agent: *',
			'Disallow: /wp-admin/',
			'Allow: /wp-admin/admin-ajax.php',
			'Disallow: /?s=',
			'Disallow: /search/',
			'',
			'Sitemap: ' . home_url( '/wp-sitemap.xml' ),
		);
		return implode( "\n", $lines ) . "\n";
	},
	10,
	2
);

/* O sitemap nativo do WordPress inclui os clubes (CPT público) e não inclui pautas/fontes. */

/* ------------------------------------------------------------------
 * Breadcrumbs (HTML com microdados schema.org)
 * ---------------------------------------------------------------- */

function bec_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}
	$crumbs = array( array( 'Início', home_url( '/' ) ) );

	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		if ( $cats ) {
			$crumbs[] = array( $cats[0]->name, get_category_link( $cats[0] ) );
		}
		$crumbs[] = array( get_the_title(), '' );
	} elseif ( is_singular( 'clube' ) ) {
		$crumbs[] = array( 'Clubes', home_url( '/clubes/' ) );
		$crumbs[] = array( get_the_title(), '' );
	} elseif ( is_page() ) {
		$crumbs[] = array( get_the_title(), '' );
	} elseif ( is_category() ) {
		$crumbs[] = array( single_cat_title( '', false ), '' );
	} elseif ( is_tag() ) {
		$crumbs[] = array( single_tag_title( '', false ), '' );
	} elseif ( is_post_type_archive( 'clube' ) ) {
		$crumbs[] = array( 'Clubes', '' );
	} elseif ( is_search() ) {
		$crumbs[] = array( 'Busca', '' );
	} else {
		return;
	}

	echo '<nav class="bec-breadcrumbs" aria-label="Você está em"><ol itemscope itemtype="https://schema.org/BreadcrumbList">';
	foreach ( $crumbs as $i => $c ) {
		echo '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
		if ( $c[1] ) {
			printf( '<a itemprop="item" href="%s"><span itemprop="name">%s</span></a>', esc_url( $c[1] ), esc_html( $c[0] ) );
		} else {
			printf( '<span itemprop="name">%s</span>', esc_html( $c[0] ) );
		}
		printf( '<meta itemprop="position" content="%d"></li>', $i + 1 );
	}
	echo '</ol></nav>';
}
