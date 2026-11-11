<?php
/**
 * Plugin Name: De Olho na Bola Core
 * Description: Motor editorial do portal De Olho na Bola: fontes, pautas com pontuação, rascunhos com revisão humana obrigatória, SEO, clubes e dados esportivos.
 * Version: 3.2.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: De Olho na Bola
 * Text Domain: bola-em-campo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BEC_VERSION', '3.2.2' );
define( 'BEC_DIR', plugin_dir_path( __FILE__ ) );

$bec_includes = array(
	// Base
	'helpers',
	'post-types',
	'scoring',
	// Fluxo editorial: 1 coleta, 2 triagem, 3 redação (dossiê + IA), 4 revisão
	'sources',
	'dossier',
	'ai-writer',
	'pipeline',
	'pautas',
	'article-sources',
	'review',
	// Entrega ao leitor
	'rest',
	'seo',
	'api-football',
	'newsletter',
	// Painel
	'admin-ui',
	'admin-dashboard',
	'settings',
	'network-sites',
	'installer',
);
foreach ( $bec_includes as $bec_file ) {
	require_once BEC_DIR . 'includes/' . $bec_file . '.php';
}
unset( $bec_includes, $bec_file );

register_activation_hook( __FILE__, 'bec_activate' );
register_deactivation_hook( __FILE__, 'bec_deactivate' );
