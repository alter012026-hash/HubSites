<?php
/**
 * Plugin Name: Tela Pop Core
 * Description: Esteira editorial Tela Pop: coleta de fontes, pautas, geração por IA, revisão humana e SEO.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Tela Pop
 * Text Domain: tela-pop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TPOP_VERSION', '1.0.0' );
define( 'TPOP_DIR', plugin_dir_path( __FILE__ ) );

$tpop_includes = array(
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
	'newsletter',
	// Painel
	'admin-ui',
	'admin-dashboard',
	'settings',
	'installer',
);
foreach ( $tpop_includes as $tpop_file ) {
	require_once TPOP_DIR . 'includes/' . $tpop_file . '.php';
}
unset( $tpop_includes, $tpop_file );

register_activation_hook( __FILE__, 'tpop_activate' );
register_deactivation_hook( __FILE__, 'tpop_deactivate' );
