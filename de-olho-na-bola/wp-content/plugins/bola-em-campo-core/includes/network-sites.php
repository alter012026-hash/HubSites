<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'lostpassword_url',
	function ( $url, $redirect ) {
		if ( ! is_multisite() ) {
			return $url;
		}

		$network = get_network();
		$site    = get_site();
		if ( ! $network || ! $site ) {
			return $url;
		}

		$network_path = trailingslashit( $network->path );
		$site_path    = trailingslashit( $site->path );
		if ( 0 !== strpos( $site_path, $network_path ) ) {
			return $url;
		}

		$relative_path = ltrim( substr( $site_path, strlen( $network_path ) ), '/' );
		$login_path    = ( '' !== $relative_path ? trailingslashit( $relative_path ) : '' ) . 'wp-login.php';
		$args          = array( 'action' => 'lostpassword' );
		if ( $redirect ) {
			$args['redirect_to'] = urlencode( $redirect );
		}

		return add_query_arg( $args, network_site_url( $login_path, 'login' ) );
	},
	10,
	2
);

add_action(
	'network_admin_menu',
	function () {
		if ( is_multisite() ) {
			add_submenu_page( 'sites.php', 'Sites De Olho na Bola', 'Sites De Olho na Bola', 'manage_sites', 'bec-network-sites', 'bec_network_sites_page' );
		}
	}
);

function bec_network_sites_notice( $type, $message ) {
	set_transient(
		'bec_network_sites_notice_' . get_current_user_id(),
		array(
			'type'    => $type,
			'message' => $message,
		),
		60
	);
	wp_safe_redirect( network_admin_url( 'admin.php?page=bec-network-sites' ) );
	exit;
}

function bec_network_site_plugin_options() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	$plugins        = get_plugins();
	$network_active = (array) get_site_option( 'active_sitewide_plugins', array() );
	$options        = array();
	$network_only   = array();

	foreach ( $plugins as $plugin_file => $plugin_data ) {
		if ( isset( $network_active[ $plugin_file ] ) ) {
			continue;
		}
		if ( ! empty( $plugin_data['Network'] ) ) {
			$network_only[] = $plugin_data['Name'];
			continue;
		}
		$options[ $plugin_file ] = $plugin_data;
	}

	return array(
		'options'      => $options,
		'network_only' => $network_only,
	);
}

function bec_network_site_theme_options() {
	$themes = wp_get_themes();
	foreach ( $themes as $stylesheet => $theme ) {
		if ( ! $theme->exists() || is_wp_error( $theme->errors() ) ) {
			unset( $themes[ $stylesheet ] );
		}
	}

	return $themes;
}

function bec_handle_create_network_site() {
	if ( ! is_multisite() || ! is_network_admin() || ! current_user_can( 'manage_sites' ) ) {
		wp_die( esc_html__( 'Você não tem permissão para criar sites nesta rede.', 'bola-em-campo' ) );
	}
	check_admin_referer( 'bec_create_network_site' );

	$name = isset( $_POST['bec_site_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bec_site_name'] ) ) : '';
	$slug = isset( $_POST['bec_site_slug'] ) ? sanitize_title( wp_unslash( $_POST['bec_site_slug'] ) ) : '';
	if ( '' === $name || '' === $slug ) {
		bec_network_sites_notice( 'error', 'Informe o nome e um endereço válido para o site.' );
	}

	$network = get_network();
	if ( ! $network ) {
		bec_network_sites_notice( 'error', 'Não foi possível identificar a rede WordPress atual.' );
	}

	$theme_slug = isset( $_POST['bec_site_theme'] ) && is_string( $_POST['bec_site_theme'] )
		? sanitize_text_field( wp_unslash( $_POST['bec_site_theme'] ) )
		: '';
	$themes     = bec_network_site_theme_options();
	if ( '' === $theme_slug || ! isset( $themes[ $theme_slug ] ) ) {
		bec_network_sites_notice( 'error', 'Selecione um tema instalado e válido para este site.' );
	}

	$plugin_selection = isset( $_POST['bec_site_plugins'] ) ? wp_unslash( $_POST['bec_site_plugins'] ) : array();
	if ( ! is_array( $plugin_selection ) ) {
		bec_network_sites_notice( 'error', 'A seleção de plugins é inválida.' );
	}
	$plugin_options   = bec_network_site_plugin_options();
	$selected_plugins = array();
	foreach ( $plugin_selection as $plugin_file ) {
		if ( ! is_string( $plugin_file ) ) {
			bec_network_sites_notice( 'error', 'A seleção de plugins é inválida.' );
		}
		$plugin_file = sanitize_text_field( $plugin_file );
		if ( ! isset( $plugin_options['options'][ $plugin_file ] ) ) {
			bec_network_sites_notice( 'error', 'Um dos plugins selecionados não pode ser ativado individualmente neste site.' );
		}
		$selected_plugins[] = $plugin_file;
	}
	$selected_plugins = array_values( array_unique( $selected_plugins ) );

	$plugin_file = plugin_basename( BEC_DIR . 'bola-em-campo-core.php' );

	$domain = $network->domain;
	$path   = trailingslashit( $network->path ) . $slug . '/';
	if ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) {
		$domain = $slug . '.' . $network->domain;
		$path   = $network->path;
	}
	if ( domain_exists( $domain, $path, (int) $network->id ) ) {
		bec_network_sites_notice( 'error', 'Esse endereço já está sendo usado por outro site da rede.' );
	}

	$site_id = wpmu_create_blog( $domain, $path, $name, get_current_user_id(), array( 'public' => 1 ), (int) $network->id );
	if ( is_wp_error( $site_id ) ) {
		bec_network_sites_notice( 'error', $site_id->get_error_message() );
	}

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$setup_errors          = array();
	$network_active        = (array) get_site_option( 'active_sitewide_plugins', array() );
	$theme_name            = $themes[ $theme_slug ]->get( 'Name' );
	$selected_plugin_names = array_map(
		static function ( $selected_plugin ) use ( $plugin_options ) {
			return $plugin_options['options'][ $selected_plugin ]['Name'];
		},
		$selected_plugins
	);

	switch_to_blog( (int) $site_id );
	try {
		$allowed_themes = get_site_option( 'allowedthemes', array() );
		if ( ! is_array( $allowed_themes ) ) {
			$allowed_themes = array();
		}
		$allowed_themes[ $theme_slug ] = true;
		update_site_option( 'allowedthemes', $allowed_themes );
		switch_theme( $theme_slug );
		if ( $theme_slug !== get_stylesheet() ) {
			$setup_errors[] = 'O tema ' . $theme_name . ' não foi ativado no novo site.';
		}

		foreach ( $selected_plugins as $selected_plugin ) {
			$activation = activate_plugin( $selected_plugin, '', false, false );
			if ( is_wp_error( $activation ) ) {
				$setup_errors[] = $plugin_options['options'][ $selected_plugin ]['Name'] . ': ' . $activation->get_error_message();
			} elseif ( ! is_plugin_active( $selected_plugin ) ) {
				$setup_errors[] = 'O plugin ' . $plugin_options['options'][ $selected_plugin ]['Name'] . ' não permaneceu ativo no novo site.';
			}
		}

		if ( isset( $network_active[ $plugin_file ] ) && function_exists( 'bec_activate' ) ) {
			bec_activate();
		}
	} finally {
		restore_current_blog();
	}

	$message = sprintf( 'Site criado: %s (%s).', $name, get_home_url( (int) $site_id ) );
	if ( $setup_errors ) {
		$message .= ' O site existe, mas houve falha ao configurar tema ou plugins: ' . implode( ' ', $setup_errors );
		bec_network_sites_notice( 'error', $message );
	}
	$plugin_summary = $selected_plugin_names
		? implode( ', ', $selected_plugin_names )
		: 'nenhum plugin ativado individualmente';
	bec_network_sites_notice( 'success', $message . ' Tema: ' . $theme_name . '. Plugins: ' . $plugin_summary . '. O conteúdo não foi copiado; as configurações editoriais são independentes.' );
}
add_action( 'admin_post_bec_create_network_site', 'bec_handle_create_network_site' );

function bec_handle_update_network_site_setup() {
	if ( ! is_multisite() || ! is_network_admin() || ! current_user_can( 'manage_sites' ) ) {
		wp_die( esc_html__( 'Você não tem permissão para configurar sites nesta rede.', 'bola-em-campo' ) );
	}

	$site_id = isset( $_POST['bec_site_id'] ) ? absint( $_POST['bec_site_id'] ) : 0;
	check_admin_referer( 'bec_network_site_setup_' . $site_id );

	$network = get_network();
	$site    = get_site( $site_id );
	if ( ! $network || ! $site || (int) $site->site_id !== (int) $network->id ) {
		bec_network_sites_notice( 'error', 'O site selecionado não pertence a esta rede.' );
	}

	$theme_slug = isset( $_POST['bec_site_theme'] ) && is_string( $_POST['bec_site_theme'] )
		? sanitize_text_field( wp_unslash( $_POST['bec_site_theme'] ) )
		: '';
	$themes     = bec_network_site_theme_options();
	if ( '' === $theme_slug || ! isset( $themes[ $theme_slug ] ) ) {
		bec_network_sites_notice( 'error', 'Selecione um tema instalado e válido para este site.' );
	}

	$plugin_selection = isset( $_POST['bec_site_plugins'] ) ? wp_unslash( $_POST['bec_site_plugins'] ) : array();
	if ( ! is_array( $plugin_selection ) ) {
		bec_network_sites_notice( 'error', 'A seleção de plugins é inválida.' );
	}
	$plugin_options   = bec_network_site_plugin_options();
	$selected_plugins = array();
	foreach ( $plugin_selection as $plugin_file ) {
		if ( ! is_string( $plugin_file ) ) {
			bec_network_sites_notice( 'error', 'A seleção de plugins é inválida.' );
		}
		$plugin_file = sanitize_text_field( $plugin_file );
		if ( ! isset( $plugin_options['options'][ $plugin_file ] ) ) {
			bec_network_sites_notice( 'error', 'Um dos plugins selecionados não pode ser ativado individualmente neste site.' );
		}
		$selected_plugins[] = $plugin_file;
	}
	$selected_plugins = array_values( array_unique( $selected_plugins ) );

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$allowed_themes = get_site_option( 'allowedthemes', array() );
	if ( ! is_array( $allowed_themes ) ) {
		$allowed_themes = array();
	}
	$allowed_themes[ $theme_slug ] = true;
	update_site_option( 'allowedthemes', $allowed_themes );

	$setup_errors = array();
	switch_to_blog( $site_id );
	try {
		switch_theme( $theme_slug );
		if ( $theme_slug !== get_stylesheet() ) {
			$setup_errors[] = 'O tema ' . $themes[ $theme_slug ]->get( 'Name' ) . ' não foi ativado.';
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		foreach ( $selected_plugins as $selected_plugin ) {
			if ( in_array( $selected_plugin, $active_plugins, true ) ) {
				continue;
			}
			$activation = activate_plugin( $selected_plugin, '', false, false );
			if ( is_wp_error( $activation ) ) {
				$setup_errors[] = $plugin_options['options'][ $selected_plugin ]['Name'] . ': ' . $activation->get_error_message();
			}
		}

		if ( ! $setup_errors ) {
			$active_plugins = (array) get_option( 'active_plugins', array() );
			$plugins_to_deactivate = array_values(
				array_diff( $active_plugins, $selected_plugins )
			);
			if ( $plugins_to_deactivate ) {
				deactivate_plugins( $plugins_to_deactivate, false, false );
			}
		}
	} finally {
		restore_current_blog();
	}

	$site_name = get_blog_details( $site_id );
	$site_name = $site_name ? $site_name->blogname : 'Site #' . $site_id;
	if ( $setup_errors ) {
		bec_network_sites_notice( 'error', 'Configuração de ' . $site_name . ' ficou incompleta: ' . implode( ' ', $setup_errors ) . ' Os plugins antigos foram mantidos para evitar desativação parcial.' );
	}

	$plugin_names = array_map(
		static function ( $plugin_file ) use ( $plugin_options ) {
			return $plugin_options['options'][ $plugin_file ]['Name'];
		},
		$selected_plugins
	);
	$plugin_summary = $plugin_names ? implode( ', ', $plugin_names ) : 'nenhum plugin individual';
	bec_network_sites_notice(
		'success',
		'Site ' . $site_name . ' configurado. Tema: ' . $themes[ $theme_slug ]->get( 'Name' ) . '. Plugins individuais: ' . $plugin_summary . '. Plugins ativos na rede continuam ativos em todos os sites.'
	);
}
add_action( 'admin_post_bec_update_network_site_setup', 'bec_handle_update_network_site_setup' );

function bec_handle_network_site_status() {
	if ( ! is_multisite() || ! is_network_admin() || ! current_user_can( 'manage_sites' ) ) {
		wp_die( esc_html__( 'Você não tem permissão para gerenciar sites nesta rede.', 'bola-em-campo' ) );
	}

	$site_id = isset( $_POST['bec_site_id'] ) ? absint( $_POST['bec_site_id'] ) : 0;
	check_admin_referer( 'bec_network_site_status_' . $site_id );

	$network = get_network();
	$site    = get_site( $site_id );
	if ( ! $network || ! $site || (int) $site->site_id !== (int) $network->id ) {
		bec_network_sites_notice( 'error', 'O site selecionado não pertence a esta rede.' );
	}
	if ( (int) $site_id === (int) get_main_site_id( (int) $network->id ) ) {
		bec_network_sites_notice( 'error', 'O site principal da rede não pode ser arquivado por esta tela.' );
	}

	$operation = isset( $_POST['bec_site_operation'] ) ? sanitize_key( wp_unslash( $_POST['bec_site_operation'] ) ) : '';
	if ( ! in_array( $operation, array( 'archive', 'unarchive' ), true ) ) {
		bec_network_sites_notice( 'error', 'Ação inválida para o site selecionado.' );
	}

	$archived = 'archive' === $operation ? 1 : 0;
	update_blog_status( (int) $site_id, 'archived', $archived );
	$updated_site = get_site( (int) $site_id );
	if ( ! $updated_site || (int) $updated_site->archived !== $archived ) {
		bec_network_sites_notice( 'error', 'Não foi possível atualizar o status do site.' );
	}

	$message = $archived
		? 'Site arquivado. Os dados foram mantidos e ele pode ser reativado nesta tela.'
		: 'Site reativado.';
	bec_network_sites_notice( 'success', $message );
}
add_action( 'admin_post_bec_network_site_status', 'bec_handle_network_site_status' );

function bec_network_sites_page() {
	if ( ! current_user_can( 'manage_sites' ) ) {
		wp_die( esc_html__( 'Você não tem permissão para gerenciar sites nesta rede.', 'bola-em-campo' ) );
	}

	$notice_key = 'bec_network_sites_notice_' . get_current_user_id();
	$notice     = get_transient( $notice_key );
	if ( $notice ) {
		delete_transient( $notice_key );
	}

	$sites = get_sites(
		array(
			'number'  => 50,
			'orderby' => 'id',
			'order'   => 'ASC',
		)
	);
	$count = get_sites( array( 'count' => true ) );
	$plugin_data       = bec_network_site_plugin_options();
	$plugin_options    = $plugin_data['options'];
	$themes            = bec_network_site_theme_options();
	$installed_plugins = get_plugins();
	$default_plugin    = plugin_basename( BEC_DIR . 'bola-em-campo-core.php' );
	$network_active    = (array) get_site_option( 'active_sitewide_plugins', array() );
	?>
	<div class="wrap">
		<h1>Sites De Olho na Bola</h1>
		<?php if ( is_array( $notice ) && isset( $notice['message'] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( isset( $notice['type'] ) ? $notice['type'] : 'info' ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>
		<p>Crie subsites escolhendo o tema e os plugins de cada um. Os arquivos são compartilhados pela rede, mas o tema ativo e os plugins ativados individualmente ficam definidos por site. O administrador atual será incluído; conteúdo e configurações editoriais permanecem separados.</p>

		<h2>Criar site</h2>
		<form method="post" action="<?php echo esc_url( network_admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bec_create_network_site">
			<?php wp_nonce_field( 'bec_create_network_site' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bec-site-name">Nome do site</label></th>
					<td><input name="bec_site_name" id="bec-site-name" type="text" class="regular-text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="bec-site-slug">Endereço do site</label></th>
					<td>
						<input name="bec_site_slug" id="bec-site-slug" type="text" class="regular-text" required pattern="[A-Za-z0-9-]+">
						<p class="description">Use letras, números e hífens. O endereço completo será montado conforme o formato desta rede.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bec-site-theme">Tema deste site</label></th>
					<td>
						<select name="bec_site_theme" id="bec-site-theme" required>
							<option value="">Selecione um tema instalado</option>
							<?php foreach ( $themes as $stylesheet => $theme ) : ?>
								<option value="<?php echo esc_attr( $stylesheet ); ?>" <?php selected( 'bola-em-campo', $stylesheet ); ?>>
									<?php echo esc_html( $theme->get( 'Name' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">Instale os arquivos do tema na rede antes de criar o site. O tema escolhido será ativado somente neste subsite.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Plugins deste site</th>
					<td>
						<?php if ( $plugin_options ) : ?>
							<fieldset>
								<?php foreach ( $plugin_options as $plugin_file => $plugin ) : ?>
									<label style="display:block;margin:0 0 6px">
										<input type="checkbox" name="bec_site_plugins[]" value="<?php echo esc_attr( $plugin_file ); ?>" <?php checked( $default_plugin === $plugin_file ); ?>>
										<?php echo esc_html( $plugin['Name'] ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						<?php else : ?>
							<p>Nenhum plugin disponível para ativação individual. Instale os arquivos dos plugins na rede antes de criar o site.</p>
						<?php endif; ?>
						<?php if ( $network_active ) : ?>
							<p class="description">Plugins ativos na rede também ficam ativos neste site: <?php
								$network_plugin_names = array();
								foreach ( array_keys( $network_active ) as $network_plugin_file ) {
									if ( isset( $installed_plugins[ $network_plugin_file ]['Name'] ) ) {
										$network_plugin_names[] = $installed_plugins[ $network_plugin_file ]['Name'];
									}
								}
								echo esc_html( implode( ', ', $network_plugin_names ) );
							?>.</p>
						<?php endif; ?>
						<?php if ( $plugin_data['network_only'] ) : ?>
							<p class="description">Plugins que exigem ativação na rede e não podem ser ativados somente neste site: <?php echo esc_html( implode( ', ', $plugin_data['network_only'] ) ); ?>.</p>
						<?php endif; ?>
						<p class="description">Marque todos os plugins que devem ser ativados neste subsite. O De Olho na Bola Core vem pré-selecionado quando pode ser ativado individualmente.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Criar site com tema e plugins selecionados' ); ?>
		</form>

		<h2>Sites da rede (<?php echo esc_html( (string) $count ); ?>)</h2>
		<table class="widefat striped">
			<thead>
				<tr><th>Nome</th><th>Endereço</th><th>Status</th><th>Administração</th><th>Tema e plugins deste site</th></tr>
			</thead>
			<tbody>
				<?php if ( $sites ) : ?>
					<?php foreach ( $sites as $site ) : ?>
						<?php $details = get_blog_details( (int) $site->blog_id ); ?>
						<?php
						switch_to_blog( (int) $site->blog_id );
						$current_theme    = get_stylesheet();
						$current_plugins  = (array) get_option( 'active_plugins', array() );
						restore_current_blog();
						?>
						<tr>
							<td><?php echo esc_html( $details ? $details->blogname : 'Site #' . (int) $site->blog_id ); ?></td>
							<td><a href="<?php echo esc_url( get_home_url( (int) $site->blog_id ) ); ?>"><?php echo esc_html( get_home_url( (int) $site->blog_id ) ); ?></a></td>
							<td>
								<?php echo ! empty( $site->archived ) ? 'Arquivado' : 'Ativo'; ?>
								<?php if ( (int) $site->blog_id !== (int) get_main_site_id( (int) $site->site_id ) ) : ?>
									<form method="post" action="<?php echo esc_url( network_admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:8px">
										<input type="hidden" name="action" value="bec_network_site_status">
										<input type="hidden" name="bec_site_id" value="<?php echo esc_attr( (string) $site->blog_id ); ?>">
										<input type="hidden" name="bec_site_operation" value="<?php echo ! empty( $site->archived ) ? 'unarchive' : 'archive'; ?>">
										<?php wp_nonce_field( 'bec_network_site_status_' . (int) $site->blog_id ); ?>
										<?php
										submit_button(
											! empty( $site->archived ) ? 'Reativar' : 'Arquivar',
											'secondary small',
											'submit',
											false,
											! empty( $site->archived ) ? array() : array( 'onclick' => "return confirm('Arquivar este site? Os dados serão mantidos e ele poderá ser reativado.');" )
										);
										?>
									</form>
								<?php endif; ?>
							</td>
							<td><a href="<?php echo esc_url( get_admin_url( (int) $site->blog_id ) ); ?>">Abrir painel</a></td>
							<td>
								<details>
									<summary>Configurar este site</summary>
									<?php if ( $network_active ) : ?>
										<?php
										$network_plugin_names = array();
										foreach ( array_keys( $network_active ) as $network_plugin_file ) {
											$network_plugin_names[] = isset( $installed_plugins[ $network_plugin_file ]['Name'] )
												? $installed_plugins[ $network_plugin_file ]['Name']
												: $network_plugin_file;
										}
										?>
										<p class="description">Ativos para toda a rede: <?php echo esc_html( implode( ', ', $network_plugin_names ) ); ?>. Para deixar um deles exclusivo de um site, desative-o primeiro em Plugins no Administrador da rede.</p>
									<?php endif; ?>
									<form method="post" action="<?php echo esc_url( network_admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="bec_update_network_site_setup">
										<input type="hidden" name="bec_site_id" value="<?php echo esc_attr( (string) $site->blog_id ); ?>">
										<?php wp_nonce_field( 'bec_network_site_setup_' . (int) $site->blog_id ); ?>
										<p>
											<label for="bec-theme-<?php echo esc_attr( (string) $site->blog_id ); ?>">Tema</label><br>
											<select name="bec_site_theme" id="bec-theme-<?php echo esc_attr( (string) $site->blog_id ); ?>" required>
												<option value="">Selecione um tema</option>
												<?php foreach ( $themes as $stylesheet => $theme ) : ?>
													<option value="<?php echo esc_attr( $stylesheet ); ?>" <?php selected( $current_theme, $stylesheet ); ?>>
														<?php echo esc_html( $theme->get( 'Name' ) ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</p>
										<fieldset>
											<legend>Plugins individuais</legend>
											<?php foreach ( $plugin_options as $plugin_file => $plugin ) : ?>
												<label style="display:block;margin:0 0 4px">
													<input type="checkbox" name="bec_site_plugins[]" value="<?php echo esc_attr( $plugin_file ); ?>" <?php checked( in_array( $plugin_file, $current_plugins, true ) ); ?>>
													<?php echo esc_html( $plugin['Name'] ); ?>
												</label>
											<?php endforeach; ?>
										</fieldset>
										<p class="description">Marque somente os plugins deste site; os desmarcados serão desativados nele. Plugins ativados na rede aparecem em todos os subsites e não podem ser desligados aqui.</p>
										<?php submit_button( 'Salvar neste site', 'secondary small' ); ?>
									</form>
								</details>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5">Nenhum site encontrado.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php if ( $count > 50 ) : ?>
			<p>Esta lista mostra os primeiros 50 sites. A administração completa da rede está disponível em <a href="<?php echo esc_url( network_admin_url( 'sites.php' ) ); ?>">Sites → Todos os sites</a>.</p>
		<?php endif; ?>
	</div>
	<?php
}
