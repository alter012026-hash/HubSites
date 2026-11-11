<?php
/**
 * Configurações em abas, geradas a partir de um esquema único.
 * Responsabilidade: guardar e mostrar opções. Quem USA cada opção são os módulos do fluxo.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Esquema: aba > seções > campos. Para criar uma opção nova, basta incluí-la aqui. */
function tpop_settings_schema() {
	return array(
		'fluxo'    => array(
			'label'    => 'Fluxo automático',
			'intro'    => 'Defina até onde o sistema trabalha sozinho. A publicação sempre depende de uma pessoa.',
			'sections' => array(
				array(
					'title'  => 'Modo de operação',
					'fields' => array(
						'auto_mode' => array(
							'type'    => 'radio',
							'label'   => 'Como o fluxo deve rodar',
							'default' => 'semi',
							'options' => array(
								'manual' => array( 'Manual', 'Você aciona a coleta e a geração. A IA prepara o rascunho para revisão humana.' ),
								'semi'   => array( 'Assistido', 'O RSS é coletado automaticamente. Você escolhe as pautas e aciona a IA; os textos vão para revisão humana.' ),
								'auto'   => array( 'Rascunhos automáticos (recomendado)', 'O sistema coleta o RSS e gera rascunhos. Alertas de fonte e evidência aparecem para sua revisão; nada é publicado automaticamente.' ),
							),
						),
					),
				),
				array(
					'title'  => 'Limites da automação',
					'fields' => array(
						'auto_max_per_run'     => array( 'type' => 'number', 'label' => 'Rascunhos por rodada', 'default' => 3, 'min' => 1, 'max' => 10, 'unit' => 'a cada 15 min', 'help' => 'Limita o processamento por rodada. As pautas com maior nota entram primeiro.' ),
						'auto_max_per_day'     => array( 'type' => 'number', 'label' => 'Textos por dia', 'default' => 10, 'min' => 1, 'max' => 100, 'unit' => 'por dia', 'help' => 'Teto de custo da API e de trabalho para a revisão.' ),
					),
				),
				array(
					'title'  => 'Avisos para a equipe',
					'fields' => array(
						'notify_on'    => array( 'type' => 'toggle', 'label' => 'E-mail de novos rascunhos', 'default' => '1', 'text' => 'Avisar quando houver textos novos para revisar' ),
						'notify_email' => array( 'type' => 'email', 'label' => 'Enviar aviso para', 'default' => '', 'help' => 'Vazio = e-mail do administrador do site.' ),
					),
				),
			),
		),
		'ia'       => array(
			'label'    => 'Redação (IA)',
			'intro'    => 'A IA recebe um dossiê com os dados reais coletados e é proibida de usar qualquer coisa fora dele.',
			'sections' => array(
				array(
					'title'  => 'Provedor de redação',
					'fields' => array(
						'ai_provider' => array(
							'type' => 'select', 'label' => 'Provedor ativo', 'default' => 'openai',
							'options' => array('openai' => 'OpenAI', 'groq' => 'GroqCloud'),
							'help' => 'As chaves e os modelos ficam separados. OpenAI permanece selecionada nas instalações existentes.',
						),
					),
				),
				array(
					'title'  => 'Conexão com a OpenAI',
					'fields' => array(
						'openai_api_key' => array( 'type' => 'password', 'label' => 'Chave da API', 'default' => '', 'const' => 'TPOP_OPENAI_API_KEY', 'help' => 'Melhor guardar em wp-config.php como TPOP_OPENAI_API_KEY. A API é cobrada por uso.', 'test' => 'tpop_test_openai', 'test_label' => 'Testar conexão' ),
						'openai_model'   => array( 'type' => 'text', 'label' => 'Modelo', 'default' => 'gpt-4.1-mini', 'help' => 'Use um modelo disponível na sua conta.' ),
					),
				),
				array(
					'title'  => 'Conexão com GroqCloud',
					'fields' => array(
						'groq_api_key' => array( 'type' => 'password', 'label' => 'Chave da API GroqCloud', 'default' => '', 'const' => 'TPOP_GROQ_API_KEY', 'help' => 'Guarde em wp-config.php como TPOP_GROQ_API_KEY ou cole aqui. A chave é independente da OpenAI.', 'test' => 'tpop_test_groq', 'test_label' => 'Testar conexão GroqCloud' ),
						'groq_model'   => array( 'type' => 'text', 'label' => 'Modelo GroqCloud', 'default' => 'openai/gpt-oss-20b', 'help' => 'Padrão atual: openai/gpt-oss-20b. Você pode usar outro identificador listado e habilitado na sua conta GroqCloud.' ),
					),
				),
				array(
					'title'  => 'Dados que alimentam o texto',
					'fields' => array(
						'read_article'      => array( 'type' => 'toggle', 'label' => 'Consultar matéria da fonte', 'default' => '1', 'text' => 'Extrair trecho somente de fontes com autorização escrita (Licenciada)', 'help' => 'Fontes “somente descoberta” contribuem apenas com título, resumo e link do RSS. Para consultar texto de uma matéria, confirme autorização por escrito e marque a fonte como Licenciada. A IA deve produzir texto original e não reproduzir a matéria consultada.' ),
						'article_max_chars' => array( 'type' => 'number', 'label' => 'Trecho máximo da matéria', 'default' => 6000, 'min' => 1000, 'max' => 15000, 'unit' => 'caracteres' ),
						'ai_article_depth'  => array( 'type' => 'select', 'label' => 'Profundidade do rascunho', 'default' => 'detailed', 'options' => array( 'standard' => 'Padrão (300–500 palavras quando houver fatos)', 'detailed' => 'Detalhado (500–800 palavras quando houver fatos)' ), 'help' => 'A extensão depende do material apurado. A IA não deve preencher o texto com contexto sem fonte.' ),
					),
				),
				array(
					'title'  => 'Estilo',
					'fields' => array(
						'ai_style'       => array( 'type' => 'textarea', 'label' => 'Orientações de estilo', 'default' => '', 'help' => 'Opcional. Ex.: “Texto direto, parágrafos curtos, tratar o torcedor por você.” Não afrouxa as regras de fatos.' ),
						'ai_post_status' => array(
							'type'    => 'select',
							'label'   => 'Onde o texto cai',
							'default' => 'pending',
							'options' => array(
								'pending' => 'Aguardando revisão (recomendado)',
								'draft'   => 'Rascunho comum',
							),
						),
					),
				),
			),
		),
		'revisao'  => array(
			'label'    => 'Regras editoriais',
			'intro'    => 'A trava de publicação protege o site. Aqui você decide o quão ampla ela é.',
			'sections' => array(
				array(
					'title'  => 'Trava de publicação',
					'fields' => array(
						'enforce_all' => array( 'type' => 'toggle', 'label' => 'Valer para todos os artigos', 'default' => '0', 'text' => 'Exigir fonte e revisão marcada em qualquer artigo, não só nos vindos do fluxo automático', 'help' => 'Textos de pauta, IA e API sempre exigem fonte e revisão humana, com ou sem esta opção.' ),
						'stale_days'  => array( 'type' => 'number', 'label' => 'Alertar conteúdo parado após', 'default' => 60, 'min' => 7, 'max' => 720, 'unit' => 'dias' ),
					),
				),
			),
		),
	);
}

/** Valor atual de uma opção (com o padrão do esquema). */
function tpop_setting( $key ) {
	foreach ( tpop_settings_schema() as $tab ) {
		foreach ( $tab['sections'] as $section ) {
			if ( isset( $section['fields'][ $key ] ) ) {
				return tpop_opt( $key, $section['fields'][ $key ]['default'] );
			}
		}
	}
	return tpop_opt( $key, '' );
}

/* ------------------------------------------------------------------
 * Menu
 * ---------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'tpop-painel', 'Configurações', 'Configurações', 'manage_options', 'tpop-config', 'tpop_settings_page' );
	},
	6
);

/* ------------------------------------------------------------------
 * Gravação (uma aba por vez; as outras abas não são tocadas)
 * ---------------------------------------------------------------- */

add_action(
	'admin_post_tpop_save_settings',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'tpop_save_settings' );
		$schema = tpop_settings_schema();
		$tab    = isset( $_POST['tpop_tab'] ) ? sanitize_key( wp_unslash( $_POST['tpop_tab'] ) ) : 'fluxo';
		if ( ! isset( $schema[ $tab ] ) ) {
			$tab = 'fluxo';
		}
		$opts = get_option( 'tpop_options', array() );
		$opts = is_array( $opts ) ? $opts : array();
		$in   = isset( $_POST['tpop'] ) && is_array( $_POST['tpop'] ) ? wp_unslash( $_POST['tpop'] ) : array();

		foreach ( $schema[ $tab ]['sections'] as $section ) {
			foreach ( $section['fields'] as $key => $f ) {
				$raw = isset( $in[ $key ] ) ? $in[ $key ] : null;
				switch ( $f['type'] ) {
					case 'toggle':
						$opts[ $key ] = null !== $raw ? '1' : '0';
						break;
					case 'number':
						$n            = null === $raw || '' === $raw ? (int) $f['default'] : (int) $raw;
						$opts[ $key ] = max( (int) $f['min'], min( (int) $f['max'], $n ) );
						break;
					case 'radio':
					case 'select':
						$valid        = array_keys( $f['options'] );
						$opts[ $key ] = in_array( $raw, $valid, true ) ? $raw : $f['default'];
						break;
					case 'email':
						$opts[ $key ] = null === $raw ? '' : sanitize_email( $raw );
						break;
					case 'password':
						if ( ! empty( $f['const'] ) && defined( $f['const'] ) ) {
							break;
						}
						if ( null !== $raw && '' !== trim( (string) $raw ) ) {
							$opts[ $key ] = sanitize_text_field( $raw );
						}
						if ( ! empty( $in[ $key . '__clear' ] ) ) {
							$opts[ $key ] = '';
						}
						break;
					case 'textarea':
						$opts[ $key ] = null === $raw ? '' : sanitize_textarea_field( $raw );
						break;
					default:
						$opts[ $key ] = null === $raw ? (string) $f['default'] : sanitize_text_field( $raw );
				}
			}
		}
		update_option( 'tpop_options', $opts, false );
		tpop_flash( 'Configurações de “' . esc_html( $schema[ $tab ]['label'] ) . '” salvas.', 'success' );
		wp_safe_redirect( admin_url( 'admin.php?page=tpop-config&tab=' . $tab ) );
		exit;
	}
);

/* ------------------------------------------------------------------
 * Testes de conexão
 * ---------------------------------------------------------------- */

add_action(
	'admin_post_tpop_test_openai',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'tpop_test' );
		$key = tpop_openai_key();
		if ( '' === $key ) {
			tpop_flash( 'Cole a chave da OpenAI e salve antes de testar.', 'warning' );
		} else {
			$res = wp_remote_get( 'https://api.openai.com/v1/models', array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $key ) ) );
			if ( is_wp_error( $res ) ) {
				tpop_flash( 'Sem conexão com a OpenAI: ' . esc_html( $res->get_error_message() ), 'error' );
			} else {
				$code = (int) wp_remote_retrieve_response_code( $res );
				if ( 200 === $code ) {
					tpop_flash( 'Conexão com a OpenAI funcionando.', 'success' );
				} else {
					tpop_flash( 'A OpenAI respondeu HTTP ' . $code . '. Confira a chave e o saldo da conta.', 'error' );
				}
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=tpop-config&tab=ia' ) );
		exit;
	}
);

add_action(
	'admin_post_tpop_test_groq',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'tpop_test' );
		$key = tpop_groq_key();
		if ( '' === $key ) {
			tpop_flash( 'Cole a chave da GroqCloud e salve antes de testar.', 'warning' );
		} else {
			$res = wp_remote_get( 'https://api.groq.com/openai/v1/models', array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $key ) ) );
			if ( is_wp_error( $res ) ) {
				tpop_flash( 'Sem conexão com a GroqCloud: ' . esc_html( $res->get_error_message() ), 'error' );
			} else {
				$code = (int) wp_remote_retrieve_response_code( $res );
				if ( 200 === $code ) {
					tpop_flash( 'Conexão com a GroqCloud funcionando.', 'success' );
				} else {
					tpop_flash( 'A GroqCloud respondeu HTTP ' . $code . '. Confira a chave e a disponibilidade da conta.', 'error' );
				}
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=tpop-config&tab=ia' ) );
		exit;
	}
);

/* ------------------------------------------------------------------
 * Tela
 * ---------------------------------------------------------------- */

function tpop_render_field( $key, $f ) {
	$name  = 'tpop[' . $key . ']';
	$id    = 'tpop_f_' . $key;
	$value = tpop_opt( $key, $f['default'] );
	echo '<div class="tpop-field"><div><label class="tpop-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>';
	if ( ! empty( $f['help'] ) && 'radio' !== $f['type'] ) {
		echo '<p class="tpop-field__help">' . esc_html( $f['help'] ) . '</p>';
	}
	echo '</div><div>';
	switch ( $f['type'] ) {
		case 'radio':
			echo '<div class="tpop-radios">';
			foreach ( $f['options'] as $k => $o ) {
				printf(
					'<label class="tpop-radio"><input type="radio" name="%s" value="%s"%s><strong>%s</strong><span>%s</span></label>',
					esc_attr( $name ),
					esc_attr( $k ),
					checked( (string) $value, (string) $k, false ),
					esc_html( $o[0] ),
					esc_html( $o[1] )
				);
			}
			echo '</div>';
			break;
		case 'toggle':
			printf(
				'<label class="tpop-toggle"><input type="checkbox" id="%s" name="%s" value="1"%s><span class="tpop-toggle__box"></span><span class="tpop-toggle__text">%s</span></label>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( '1', (string) $value, false ),
				esc_html( isset( $f['text'] ) ? $f['text'] : 'Ativado' )
			);
			break;
		case 'number':
			printf( '<input type="number" id="%s" name="%s" value="%s" min="%d" max="%d" style="width:110px">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), (int) $f['min'], (int) $f['max'] );
			if ( ! empty( $f['unit'] ) ) {
				echo '<span class="tpop-unit">' . esc_html( $f['unit'] ) . '</span>';
			}
			break;
		case 'select':
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $f['options'] as $k => $l ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( (string) $value, (string) $k, false ), esc_html( $l ) );
			}
			echo '</select>';
			break;
		case 'textarea':
			printf( '<textarea id="%s" name="%s" rows="4">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
			break;
		case 'password':
			if ( ! empty( $f['const'] ) && defined( $f['const'] ) ) {
				echo '<span class="tpop-secret">✓ Definida em wp-config.php (' . esc_html( $f['const'] ) . ')</span>';
			} else {
				$has = '' !== (string) $value;
				printf( '<input type="password" id="%s" name="%s" value="" autocomplete="new-password" placeholder="%s">', esc_attr( $id ), esc_attr( $name ), $has ? '•••••••• (já salva; deixe vazio para manter)' : 'Cole a chave aqui' );
				if ( $has ) {
					echo '<p><label><input type="checkbox" name="tpop[' . esc_attr( $key ) . '__clear]" value="1"> Apagar a chave salva</label></p>';
				}
			}
			break;
		case 'email':
			printf( '<input type="email" id="%s" name="%s" value="%s" placeholder="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ), esc_attr( get_option( 'admin_email' ) ) );
			break;
		default:
			printf( '<input type="text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
	}
	if ( ! empty( $f['test'] ) ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . $f['test'] ), 'tpop_test' );
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html( $f['test_label'] ) . '</a></p>';
	}
	echo '</div></div>';
}

function tpop_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$schema = tpop_settings_schema();
	$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'fluxo';
	if ( ! isset( $schema[ $tab ] ) && 'integracoes' !== $tab ) {
		$tab = 'fluxo';
	}
	echo '<div class="wrap tpop-ui">';
	tpop_ui_header( 'Configurações', 'Cada aba cuida de uma etapa do fluxo. Salve uma aba por vez.' );

	echo '<div class="tpop-tabs">';
	foreach ( $schema as $slug => $t ) {
		printf( '<a href="%s" class="%s">%s</a>', esc_url( admin_url( 'admin.php?page=tpop-config&tab=' . $slug ) ), $slug === $tab ? 'is-active' : '', esc_html( $t['label'] ) );
	}
	printf( '<a href="%s" class="%s">Integrações</a>', esc_url( admin_url( 'admin.php?page=tpop-config&tab=integracoes' ) ), 'integracoes' === $tab ? 'is-active' : '' );
	echo '</div>';

	if ( 'integracoes' === $tab ) {
		tpop_render_integrations();
		echo '</div>';
		return;
	}

	$t = $schema[ $tab ];
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'tpop_save_settings' );
	echo '<input type="hidden" name="action" value="tpop_save_settings"><input type="hidden" name="tpop_tab" value="' . esc_attr( $tab ) . '">';
	echo '<p style="color:var(--b-cinza);margin-top:0">' . esc_html( $t['intro'] ) . '</p>';
	foreach ( $t['sections'] as $section ) {
		echo '<section class="tpop-card"><h2>' . esc_html( $section['title'] ) . '</h2>';
		foreach ( $section['fields'] as $key => $f ) {
			tpop_render_field( $key, $f );
		}
		echo '</section>';
	}
	echo '<div class="tpop-savebar">';
	submit_button( 'Salvar ' . $t['label'], 'primary', 'submit', false );
	echo '</div></form></div>';
}

/** Aba informativa: token da API externa, cron e endereços. */
function tpop_render_integrations() {
	$token_ok = defined( 'TPOP_REST_TOKEN' ) && strlen( (string) TPOP_REST_TOKEN ) >= 32;
	$cron_off = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$next     = wp_next_scheduled( 'tpop_pipeline_event' );
	?>
	<section class="tpop-card">
		<h2>Agendador (cron)</h2>
		<p class="tpop-card__sub">O fluxo automático roda a cada 15 minutos pelo agendador do WordPress.</p>
		<ul class="tpop-check">
			<li class="<?php echo $next ? 'is-ok' : 'is-err'; ?>"><span class="dashicons dashicons-<?php echo $next ? 'yes' : 'warning'; ?>"></span><div><strong><?php echo $next ? 'Agendado' : 'Não agendado'; ?></strong><small><?php echo $next ? 'Próxima rodada: ' . esc_html( wp_date( 'd/m/Y H:i', $next ) ) : 'Desative e reative o plugin para recriar o agendamento.'; ?></small></div></li>
			<li class="<?php echo $cron_off ? 'is-ok' : 'is-warn'; ?>"><span class="dashicons dashicons-<?php echo $cron_off ? 'yes' : 'info'; ?>"></span><div><strong><?php echo $cron_off ? 'Cron do servidor ativo' : 'Cron do WordPress (depende de visitas)'; ?></strong><small>Em produção, defina <code>DISABLE_WP_CRON</code> como true e chame <code><?php echo esc_html( site_url( 'wp-cron.php' ) ); ?></code> a cada 5 minutos pelo cron da hospedagem.</small></div></li>
		</ul>
	</section>
	<section class="tpop-card">
		<h2>API para automações externas</h2>
		<p class="tpop-card__sub">Python, n8n ou Node podem enviar pautas e rascunhos. A API nunca publica.</p>
		<ul class="tpop-check">
			<li class="<?php echo $token_ok ? 'is-ok' : 'is-warn'; ?>"><span class="dashicons dashicons-<?php echo $token_ok ? 'yes' : 'info'; ?>"></span><div><strong>Token <?php echo $token_ok ? 'configurado' : 'não configurado'; ?></strong><small>Defina <code>TPOP_REST_TOKEN</code> (32+ caracteres) no wp-config.php.</small></div></li>
		</ul>
		<p>Endereços: <code><?php echo esc_html( rest_url( 'bec/v1/pautas' ) ); ?></code> e <code><?php echo esc_html( rest_url( 'bec/v1/rascunhos' ) ); ?></code></p>
	</section>
	<?php
}
