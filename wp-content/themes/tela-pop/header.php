<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#24172f">
	<script>(function(){var p='';try{p=localStorage.getItem('tpop-theme')}catch(e){}if(p!=='light'&&p!=='dark'){try{p=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}catch(e){p='light'}}document.documentElement.setAttribute('data-theme',p)})();</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text" href="#conteudo">Ir para o conteúdo</a>

<?php
$tpop_notice = function_exists( 'tpop_nl_notice' ) ? tpop_nl_notice() : null;
if ( $tpop_notice ) :
	?>
	<div class="toast toast--<?php echo esc_attr( $tpop_notice[0] ); ?>" role="status" data-toast>
		<p><?php echo esc_html( $tpop_notice[1] ); ?></p>
		<button class="toast-x" type="button" aria-label="Fechar aviso" data-toast-close>&times;</button>
	</div>
<?php endif; ?>

<div class="topbar">
	<div class="wrap"><span class="dot" aria-hidden="true"></span><span><?php echo esc_html( get_theme_mod( 'tpop_ticker', 'Sua próxima parada: grandes histórias.' ) ); ?></span></div>
</div>

<header class="site-header">
	<div class="wrap header-inner">
		<?php if ( has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php else : ?>
			<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo tpop_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		<?php endif; ?>

		<div class="header-actions">
			<button class="hbtn hbtn--icon theme-toggle" type="button" data-theme-toggle aria-label="Modo escuro" aria-pressed="false" title="Modo claro ativo. Clique para modo escuro.">
				<svg class="theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 15.5A8.5 8.5 0 0 1 8.5 3.5 8.5 8.5 0 1 0 20.5 15.5Z"/></svg>
				<svg class="theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
				<span class="screen-reader-text">Alternar tema de cores</span>
			</button>
			<button class="hbtn hbtn--icon search-toggle" type="button" aria-expanded="false" aria-controls="search-panel" aria-label="Buscar">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>
			</button>
			<a class="hbtn hbtn--cta" href="#assine">Receba novidades</a>
			<button class="hbtn hbtn--icon menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav" aria-label="Abrir menu">
				<svg class="menu-icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
				<svg class="menu-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
			</button>
		</div>

		<nav id="main-nav" class="main-nav" aria-label="Menu principal">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'fallback_cb'    => 'tpop_fallback_menu',
					'depth'          => 1,
				)
			);
			?>
			<a class="main-nav-cta" href="#assine">Receba novidades <span aria-hidden="true">→</span></a>
		</nav>

		<div id="search-panel" class="search-panel"><?php get_search_form(); ?></div>
	</div>
</header>
<main id="conteudo" class="site-main">
