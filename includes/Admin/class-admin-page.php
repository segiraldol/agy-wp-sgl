<?php
namespace Agy_Wp\Admin;

use Agy_Wp\Auth\OAuth_Client;
use Agy_Wp\Auth\Token_Vault;

defined('ABSPATH') || exit;

/**
 * Renderizado y gestión de la interfaz de administración de Antigravity Connect.
 */
class Admin_Page {

    const MENU_SLUG = 'agy-connect';

    /**
     * Registra el elemento en el menú de administración de WordPress.
     */
    public function register_menu() {
        add_menu_page(
            __('Antigravity Connect', 'agy-wp-sgl'),
            __('Antigravity', 'agy-wp-sgl'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page'],
            'dashicons-cloud',
            80
        );
    }

    /**
     * Encola scripts y estilos propios del panel de control.
     *
     * @param string $hook_suffix Identificador de la pantalla actual.
     */
    public function enqueue_assets($hook_suffix) {
        if ($hook_suffix !== 'toplevel_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'agy-admin-style',
            AGY_WP_URL . 'assets/css/admin.css',
            [],
            AGY_WP_VERSION
        );

        wp_enqueue_script(
            'agy-admin-script',
            AGY_WP_URL . 'assets/js/admin.js',
            ['jquery'],
            AGY_WP_VERSION,
            true
        );

        $auth_url = OAuth_Client::get_authorization_url();
        $status   = Token_Vault::get_status();
        $profile  = get_option('agy_user_profile', []);

        wp_localize_script('agy-admin-script', 'agyVars', [
            'restUrl'     => esc_url_raw(rest_url('agy/v1/')),
            'restNonce'   => wp_create_nonce('wp_rest'),
            'authUrl'     => $auth_url ? esc_url_raw($auth_url) : '',
            'status'      => $status,
            'isConnected' => ($status === 'connected'),
            'userEmail'   => $profile['email'] ?? '',
            'i18n'        => [
                'connecting'          => __('Conectando con Google Antigravity...', 'agy-wp-sgl'),
                'connectedSuccess'    => __('¡Cuenta de Gmail vinculada exitosamente con Antigravity!', 'agy-wp-sgl'),
                'disconnectConfirm'   => __('¿Estás seguro de que deseas desconectar tu cuenta de Gmail de Antigravity?', 'agy-wp-sgl'),
                'disconnecting'       => __('Desconectando y revocando tokens...', 'agy-wp-sgl'),
                'disconnectedSuccess' => __('Sesión cerrada y credenciales purgadas.', 'agy-wp-sgl'),
                'testing'             => __('Enviando ping de prueba a Antigravity...', 'agy-wp-sgl'),
                'testSuccess'         => __('Prueba exitosa. Latencia:', 'agy-wp-sgl'),
                'copySuccess'         => __('Enlace copiado al portapapeles.', 'agy-wp-sgl'),
                'emptyCode'           => __('Por favor pega la URL o el código generado tras iniciar sesión con tu cuenta de Gmail.', 'agy-wp-sgl'),
            ],
        ]);
    }

    /**
     * Renderiza la página principal de ajustes y conexión.
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos suficientes para acceder a esta página.', 'agy-wp-sgl'));
        }

        $status     = Token_Vault::get_status();
        $profile    = get_option('agy_user_profile', []);
        $expires_at = Token_Vault::get_expires_at();
        $auth_url   = OAuth_Client::get_authorization_url();
        $client_id  = OAuth_Client::get_client_id();
        $is_locked  = Settings::is_client_id_constant();
        $settings   = get_option('agy_settings', []);
        $ping_info  = get_option('agy_last_ping_status', null);
        ?>
        <div class="wrap agy-wrap">
            <header class="agy-header">
                <div class="agy-header-left">
                    <span class="agy-badge-logo">AGY</span>
                    <div>
                        <h1><?php esc_html_e('Google Antigravity Connect', 'agy-wp-sgl'); ?></h1>
                        <p class="agy-subtitle">
                            <?php esc_html_e('Conexión directa de WordPress con Google Antigravity mediante tu cuenta de Gmail.', 'agy-wp-sgl'); ?>
                        </p>
                    </div>
                </div>
                <div class="agy-header-status">
                    <?php if ($status === 'connected') : ?>
                        <span class="agy-pill agy-pill-success">
                            <span class="agy-dot"></span> <?php esc_html_e('Conectado', 'agy-wp-sgl'); ?>
                        </span>
                    <?php elseif ($status === 'expired') : ?>
                        <span class="agy-pill agy-pill-warning">
                            <span class="agy-dot"></span> <?php esc_html_e('Expirado / Reautenticar', 'agy-wp-sgl'); ?>
                        </span>
                    <?php else : ?>
                        <span class="agy-pill agy-pill-danger">
                            <span class="agy-dot"></span> <?php esc_html_e('Desconectado', 'agy-wp-sgl'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </header>

            <div id="agy-alert-box" class="agy-alert-box" style="display:none;"></div>

            <div class="agy-grid">
                <!-- Columna Izquierda: Asistente de Conexión con Gmail -->
                <div class="agy-card agy-card-primary">
                    <h2><?php esc_html_e('1. Iniciar Sesión con tu Cuenta de Gmail (Antigravity)', 'agy-wp-sgl'); ?></h2>
                    
                    <?php if ($status === 'connected') : ?>
                        <!-- Vista de Conexión Activa -->
                        <div class="agy-connected-view">
                            <div class="agy-user-card">
                                <?php if (!empty($profile['picture'])) : ?>
                                    <img src="<?php echo esc_url($profile['picture']); ?>" alt="Avatar" class="agy-user-avatar" />
                                <?php else : ?>
                                    <div class="agy-user-avatar agy-avatar-placeholder"><span class="dashicons dashicons-admin-users"></span></div>
                                <?php endif; ?>
                                <div class="agy-user-details">
                                    <h3><?php echo esc_html($profile['name'] ?? __('Cuenta Google Conectada', 'agy-wp-sgl')); ?></h3>
                                    <p class="agy-user-email"><?php echo esc_html($profile['email'] ?? ''); ?></p>
                                    <p class="agy-token-expiry">
                                        <span class="dashicons dashicons-clock"></span>
                                        <?php
                                        if ($expires_at > 0) {
                                            printf(
                                                /* translators: %s: Fecha y hora formateada */
                                                esc_html__('Vigencia del access_token actual: %s (se renueva automáticamente en segundo plano)', 'agy-wp-sgl'),
                                                '<strong>' . esc_html(wp_date('d/m/Y H:i:s', $expires_at)) . '</strong>'
                                            );
                                        } else {
                                            esc_html_e('Refresco continuo desatendido activo.', 'agy-wp-sgl');
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>

                            <div class="agy-action-buttons">
                                <button type="button" id="agy-btn-ping" class="button button-primary">
                                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Probar Conexión (Ping API)', 'agy-wp-sgl'); ?>
                                </button>
                                <button type="button" id="agy-btn-disconnect" class="button button-link-delete">
                                    <span class="dashicons dashicons-no"></span> <?php esc_html_e('Desconectar Cuenta', 'agy-wp-sgl'); ?>
                                </button>
                            </div>

                            <div id="agy-ping-result" class="agy-ping-box" style="<?php echo $ping_info ? '' : 'display:none;'; ?>">
                                <?php if ($ping_info) : ?>
                                    <span class="dashicons dashicons-yes-alt text-success"></span>
                                    <span><?php echo esc_html($ping_info['message'] ?? ''); ?></span>
                                    <small>(<?php echo esc_html(wp_date('d/m/Y H:i', $ping_info['timestamp'])); ?>)</small>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else : ?>
                        <!-- Vista de Inicio de Sesión con Gmail -->
                        <div class="agy-authcode-wizard">
                            <div class="agy-welcome-callout">
                                <div class="agy-callout-icon">
                                    <svg viewBox="0 0 24 24" width="28" height="28">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                    </svg>
                                </div>
                                <div>
                                    <strong><?php esc_html_e('Conexión directa con tu cuenta de Google', 'agy-wp-sgl'); ?></strong>
                                    <p><?php esc_html_e('Inicia sesión con la cuenta de Gmail que utilizas en Google Antigravity para autorizar el acceso de tu WordPress sin necesidad de crear proyectos ni claves en Google Cloud.', 'agy-wp-sgl'); ?></p>
                                </div>
                            </div>

                            <div class="agy-step">
                                <div class="agy-step-num">1</div>
                                <div class="agy-step-content">
                                    <h4><?php esc_html_e('Iniciar sesión en Google', 'agy-wp-sgl'); ?></h4>
                                    <p><?php esc_html_e('Haz clic en el botón a continuación para abrir la pantalla oficial de Google. Elige la cuenta de Gmail asociada a tu suscripción o acceso a Antigravity y autoriza los permisos:', 'agy-wp-sgl'); ?></p>
                                    <div class="agy-step-actions">
                                        <a href="<?php echo esc_url($auth_url); ?>" target="_blank" rel="noopener noreferrer" class="agy-google-login-btn">
                                            <svg viewBox="0 0 24 24" width="20" height="20" class="agy-google-svg">
                                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                            </svg>
                                            <span><?php esc_html_e('Iniciar sesión con Google (Gmail Antigravity)', 'agy-wp-sgl'); ?></span>
                                        </a>
                                        <button type="button" id="agy-btn-copy-url" data-url="<?php echo esc_url($auth_url); ?>" class="button button-link">
                                            <span class="dashicons dashicons-admin-page"></span> <?php esc_html_e('Copiar Enlace', 'agy-wp-sgl'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="agy-step">
                                <div class="agy-step-num">2</div>
                                <div class="agy-step-content">
                                    <h4><?php esc_html_e('Copiar el código o la URL resultante', 'agy-wp-sgl'); ?></h4>
                                    <p><?php esc_html_e('Tras conceder los permisos, copia el código que aparezca en pantalla o copia la URL completa de la barra de direcciones de tu navegador (ej: http://localhost:8085/oauth2callback?code=4/0A...).', 'agy-wp-sgl'); ?></p>
                                </div>
                            </div>

                            <div class="agy-step">
                                <div class="agy-step-num">3</div>
                                <div class="agy-step-content">
                                    <h4><?php esc_html_e('Pegar y conectar', 'agy-wp-sgl'); ?></h4>
                                    <div class="agy-input-exchange-group">
                                        <input type="text" id="agy-auth-code-input" class="regular-text code" placeholder="<?php esc_attr_e('Pega aquí el código 4/0A... o la URL completa de tu navegador', 'agy-wp-sgl'); ?>" autocomplete="off" />
                                        <button type="button" id="agy-btn-exchange" class="button button-primary button-hero">
                                            <span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Conectar con Antigravity', 'agy-wp-sgl'); ?>
                                        </button>
                                    </div>
                                    <span id="agy-exchange-spinner" class="spinner"></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Columna Derecha: Configuración y Credenciales Opcionales -->
                <div class="agy-card agy-card-secondary">
                    <h2><?php esc_html_e('2. Parámetros y Credenciales (Opcional)', 'agy-wp-sgl'); ?></h2>
                    
                    <div class="agy-preset-box">
                        <span class="dashicons dashicons-yes-alt text-success"></span>
                        <div>
                            <strong><?php esc_html_e('Autenticación Oficial Integrada', 'agy-wp-sgl'); ?></strong>
                            <p><?php esc_html_e('El plugin cuenta con credenciales nativas de Antigravity preconfiguradas. No necesitas registrar proyectos en Google Cloud Console para iniciar sesión con tu cuenta de Gmail.', 'agy-wp-sgl'); ?></p>
                        </div>
                    </div>

                    <details class="agy-advanced-settings" <?php echo (!empty($settings['client_id']) || !empty($settings['project_id'])) ? 'open' : ''; ?>>
                        <summary><strong><?php esc_html_e('Ajustes Avanzados / Credenciales Personalizadas', 'agy-wp-sgl'); ?></strong></summary>
                        <p class="description" style="margin-top:10px;">
                            <?php esc_html_e('Solo completa estos campos si deseas sobreescribir la autenticación integrada con tu propia app registrada en Google Cloud Console.', 'agy-wp-sgl'); ?>
                        </p>
                        <form method="post" action="options.php">
                            <?php
                            settings_fields(Settings::SETTINGS_GROUP);
                            ?>
                            <table class="form-table agy-form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="agy_client_id"><?php esc_html_e('OAuth Client ID', 'agy-wp-sgl'); ?></label>
                                    </th>
                                    <td>
                                        <?php if ($is_locked) : ?>
                                            <input type="text" class="regular-text code" value="<?php echo esc_attr($client_id); ?>" disabled />
                                            <p class="description"><span class="dashicons dashicons-lock"></span> <?php esc_html_e('Definido como constante AGY_CLIENT_ID en wp-config.php', 'agy-wp-sgl'); ?></p>
                                        <?php else : ?>
                                            <input type="text" id="agy_client_id" name="agy_settings[client_id]" class="regular-text code" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>" placeholder="<?php echo esc_attr(OAuth_Client::get_default_client_id()); ?>" />
                                            <p class="description"><?php esc_html_e('Por defecto usa el Client ID oficial de Antigravity.', 'agy-wp-sgl'); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="agy_client_secret"><?php esc_html_e('OAuth Client Secret', 'agy-wp-sgl'); ?></label>
                                    </th>
                                    <td>
                                        <?php if (Settings::is_client_secret_constant()) : ?>
                                            <input type="password" class="regular-text code" value="************************" disabled />
                                            <p class="description"><span class="dashicons dashicons-lock"></span> <?php esc_html_e('Definido como constante AGY_CLIENT_SECRET en wp-config.php', 'agy-wp-sgl'); ?></p>
                                        <?php else : ?>
                                            <input type="password" id="agy_client_secret" name="agy_settings[client_secret]" class="regular-text code" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>" placeholder="Opcional / Por defecto Antigravity" />
                                            <p class="description"><?php esc_html_e('Secreto de cliente OAuth personalizado (deja en blanco para el valor predeterminado).', 'agy-wp-sgl'); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="agy_project_id"><?php esc_html_e('Project ID de Antigravity', 'agy-wp-sgl'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="agy_project_id" name="agy_settings[project_id]" class="regular-text" value="<?php echo esc_attr($settings['project_id'] ?? ''); ?>" placeholder="ej. mi-proyecto-antigravity" />
                                        <p class="description"><?php esc_html_e('Identificador opcional del proyecto asociado.', 'agy-wp-sgl'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="agy_redirect_uri"><?php esc_html_e('Redirect URI', 'agy-wp-sgl'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="agy_redirect_uri" name="agy_settings[redirect_uri]" class="regular-text code" value="<?php echo esc_attr($settings['redirect_uri'] ?? OAuth_Client::DEFAULT_REDIRECT_URI); ?>" />
                                        <p class="description"><?php esc_html_e('Por defecto: http://localhost:8085/oauth2callback', 'agy-wp-sgl'); ?></p>
                                    </td>
                                </tr>
                            </table>

                            <?php submit_button(__('Guardar Ajustes Personalizados', 'agy-wp-sgl')); ?>
                        </form>
                    </details>
                </div>
            </div>
        </div>
        <?php
    }
}
