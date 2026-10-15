<?php
namespace Agy_Wp\Api;

use Agy_Wp\Auth\OAuth_Client;
use Agy_Wp\Auth\Token_Vault;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Endpoints de la REST API interna de WordPress para operaciones de administración de Antigravity.
 */
class Rest_Routes {

    const NAMESPACE = 'agy/v1';

    /**
     * Registra las rutas de la REST API.
     */
    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/exchange-code', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_exchange_code'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/ping', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_ping'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/disconnect', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_disconnect'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth-url', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handle_get_auth_url'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    /**
     * Valida que el usuario tenga privilegios de administrador.
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Endpoint para canjear el AuthCode por tokens.
     */
    public function handle_exchange_code(WP_REST_Request $request) {
        $code = sanitize_text_field($request->get_param('code'));
        if (empty($code)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('El código de autorización es obligatorio.', 'agy-wp-sgl'),
            ], 400);
        }

        $result = OAuth_Client::exchange_code($code);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Endpoint para probar la conexión en vivo.
     */
    public function handle_ping() {
        $api = new Antigravity_Api();
        $result = $api->ping();

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    /**
     * Endpoint para desconectar y revocar tokens.
     */
    public function handle_disconnect() {
        $revoked = OAuth_Client::disconnect();

        return new WP_REST_Response([
            'success' => $revoked,
            'message' => __('Desconectado de Antigravity exitosamente.', 'agy-wp-sgl'),
        ], 200);
    }

    /**
     * Endpoint para generar un nuevo enlace de autorización con PKCE.
     */
    public function handle_get_auth_url() {
        $url = OAuth_Client::get_authorization_url();
        if (!$url) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Client ID no configurado.', 'agy-wp-sgl'),
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'url'     => $url,
        ], 200);
    }
}
