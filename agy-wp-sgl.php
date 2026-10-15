<?php
/**
 * Plugin Name:       Antigravity for WordPress by SGL
 * Plugin URI:        https://hostingleal.com
 * Description:       Conecta WordPress con Google Antigravity mediante autenticación OAuth 2.0 AuthCode idéntica a agy cli, permitiendo gestión segura de tokens, diagnóstico en vivo e integración con modelos y agentes.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Antigravity Team
 * License:           GPL-2.0-or-later
 * Text Domain:       agy-wp-sgl
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;

// Definición de constantes del plugin
define('AGY_WP_VERSION', '1.0.0');
define('AGY_WP_FILE', __FILE__);
define('AGY_WP_PATH', plugin_dir_path(__FILE__));
define('AGY_WP_URL', plugin_dir_url(__FILE__));
define('AGY_WP_BASENAME', plugin_basename(__FILE__));

// Comprobación de versión de PHP requerida
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' .
            esc_html__('Antigravity Connect requiere PHP 7.4 o superior para funcionar.', 'agy-wp-sgl') .
            '</p></div>';
    });
    return;
}

// Carga del autoloader
require_once AGY_WP_PATH . 'includes/class-autoloader.php';
\Agy_Wp\Autoloader::register();

// Activación y Desactivación
register_activation_hook(__FILE__, ['\\Agy_Wp\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['\\Agy_Wp\\Plugin', 'deactivate']);

// Inicialización del plugin
add_action('plugins_loaded', function () {
    \Agy_Wp\Plugin::instance()->init();
});

/**
 * Función helper global para acceder a la instancia del cliente API de Antigravity.
 *
 * @return \Agy_Wp\Api\Antigravity_Api|null Instancia lista para usar o null si no está conectado.
 */
function agy_get_client() {
    return \Agy_Wp\Plugin::instance()->get_api_client();
}
