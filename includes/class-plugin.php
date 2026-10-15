<?php
namespace Agy_Wp;

use Agy_Wp\Admin\Admin_Page;
use Agy_Wp\Admin\Notices;
use Agy_Wp\Admin\Settings;
use Agy_Wp\Api\Antigravity_Api;
use Agy_Wp\Api\Rest_Routes;

defined('ABSPATH') || exit;

/**
 * Clase principal orquestadora de Antigravity Connect.
 * Implementa el patrón Singleton para inicializar todos los componentes del plugin.
 */
class Plugin {

    /**
     * Instancia única de la clase.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Instancia del cliente de la API de Antigravity.
     *
     * @var Antigravity_Api|null
     */
    private $api_client = null;

    /**
     * Obtiene la instancia Singleton.
     *
     * @return Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado para restringir instanciación externa.
     */
    private function __construct() {}

    /**
     * Inicializa los componentes, ganchos y controladores del plugin.
     */
    public function init() {
        // Carga de traducciones
        load_plugin_textdomain('agy-wp-sgl', false, dirname(AGY_WP_BASENAME) . '/languages');

        // Inicializar cliente API
        $this->api_client = new Antigravity_Api();

        // Registrar rutas REST API
        $rest_routes = new Rest_Routes();
        add_action('rest_api_init', [$rest_routes, 'register_routes']);

        // Componentes para el panel de administración
        if (is_admin()) {
            $settings = new Settings();
            add_action('admin_init', [$settings, 'register']);

            $admin_page = new Admin_Page();
            add_action('admin_menu', [$admin_page, 'register_menu']);
            add_action('admin_enqueue_scripts', [$admin_page, 'enqueue_assets']);

            $notices = new Notices();
            add_action('admin_notices', [$notices, 'render_notices']);

            // Enlace rápido a Ajustes en la lista de plugins instalados
            add_filter('plugin_action_links_' . AGY_WP_BASENAME, [$this, 'add_settings_link']);
        }
    }

    /**
     * Añade un enlace directo a Ajustes en la fila del plugin en plugins.php.
     *
     * @param array $links
     * @return array
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=agy-connect')),
            esc_html__('Ajustes y Conexión', 'agy-wp-sgl')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Obtiene la instancia del cliente API para consultas externas.
     *
     * @return Antigravity_Api
     */
    public function get_api_client() {
        if (null === $this->api_client) {
            $this->api_client = new Antigravity_Api();
        }
        return $this->api_client;
    }

    /**
     * Acción ejecutada durante la activación del plugin.
     */
    public static function activate() {
        // Inicializar opciones básicas si no existen
        if (false === get_option('agy_connection_status')) {
            add_option('agy_connection_status', 'disconnected', '', 'no');
        }

        if (false === get_option('agy_settings')) {
            add_option('agy_settings', [
                'client_id'     => '',
                'client_secret' => '',
                'project_id'    => '',
                'redirect_uri'  => 'urn:ietf:wg:oauth:2.0:oob',
            ], '', 'no');
        }
    }

    /**
     * Acción ejecutada durante la desactivación del plugin.
     */
    public static function deactivate() {
        // Limpiar transients temporales
        delete_transient('agy_oauth_state');
        delete_transient('agy_pkce_verifier');
        delete_transient('agy_models_cache');
    }
}
