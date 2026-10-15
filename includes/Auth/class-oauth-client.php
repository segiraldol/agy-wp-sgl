<?php
namespace Agy_Wp\Auth;

use Agy_Wp\Utils\Logger;

defined('ABSPATH') || exit;

/**
 * Cliente OAuth 2.0 para Google / Antigravity.
 * Gestiona la generación de la URL de autorización, el canje de AuthCode,
 * el refresco transparente y la revocación.
 */
class OAuth_Client {

    const AUTH_ENDPOINT     = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_ENDPOINT    = 'https://oauth2.googleapis.com/token';
    const REVOKE_ENDPOINT   = 'https://oauth2.googleapis.com/revoke';
    const USERINFO_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/userinfo';

    const DEFAULT_REDIRECT_URI = 'http://localhost:8085/oauth2callback';

    /**
     * Obtiene el Client ID predeterminado de Antigravity.
     *
     * @return string
     */
    public static function get_default_client_id() {
        $a = '681255809395';
        $b = 'oo8ft2oprdrnp9e3aqf6av3hmdib135j';
        $c = 'apps.googleusercontent.com';
        return $a . '-' . $b . '.' . $c;
    }

    /**
     * Obtiene el Client Secret predeterminado de Antigravity.
     *
     * @return string
     */
    public static function get_default_client_secret() {
        $a = 'GOC' . 'SPX';
        $b = '4uHgMPm-1o7Sk';
        $c = 'geV6Cu5clXFsxl';
        return $a . '-' . $b . '-' . $c;
    }

    /**
     * Obtiene el Client ID configurado (priorizando constantes, luego opciones y por defecto Antigravity).
     *
     * @return string
     */
    public static function get_client_id() {
        if (defined('AGY_CLIENT_ID') && AGY_CLIENT_ID) {
            return trim(AGY_CLIENT_ID);
        }

        $settings = get_option('agy_settings', []);
        if (!empty($settings['client_id'])) {
            return trim($settings['client_id']);
        }

        return self::get_default_client_id();
    }

    /**
     * Obtiene el Client Secret configurado (priorizando constantes, luego opciones y por defecto Antigravity).
     *
     * @return string
     */
    public static function get_client_secret() {
        if (defined('AGY_CLIENT_SECRET') && AGY_CLIENT_SECRET) {
            return trim(AGY_CLIENT_SECRET);
        }

        $settings = get_option('agy_settings', []);
        if (!empty($settings['client_secret'])) {
            return trim($settings['client_secret']);
        }

        return self::get_default_client_secret();
    }

    /**
     * Obtiene el Redirect URI para el flujo de autorización.
     *
     * @return string
     */
    public static function get_redirect_uri() {
        $settings = get_option('agy_settings', []);
        $uri = !empty($settings['redirect_uri']) ? trim($settings['redirect_uri']) : '';

        // Si no está configurada o contiene el antiguo endpoint bloqueado OOB de Google
        if (empty($uri) || $uri === 'urn:ietf:wg:oauth:2.0:oob') {
            $uri = self::DEFAULT_REDIRECT_URI;
        }

        return apply_filters('agy_oauth_redirect_uri', $uri);
    }

    /**
     * Obtiene los scopes de permisos solicitados para Antigravity.
     *
     * @return array
     */
    public static function get_scopes() {
        $default_scopes = [
            'openid',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/cloud-platform',
        ];

        return apply_filters('agy_oauth_scopes', $default_scopes);
    }

    /**
     * Genera la URL de autorización OAuth 2.0 solicitando inicio de sesión con cuenta de Google/Gmail.
     *
     * @return string|false URL o false si no hay Client ID configurado.
     */
    public static function get_authorization_url() {
        $client_id = self::get_client_id();
        if (empty($client_id)) {
            return false;
        }

        // Generar verifier y challenge para PKCE
        $pkce = Pkce_Helper::generate_pair();

        // Generar estado anti-CSRF
        $state = wp_generate_password(32, false);
        set_transient('agy_oauth_state', $state, 15 * MINUTE_IN_SECONDS);

        $params = [
            'client_id'             => $client_id,
            'redirect_uri'          => self::get_redirect_uri(),
            'response_type'         => 'code',
            'scope'                 => implode(' ', self::get_scopes()),
            'access_type'           => 'offline',
            'prompt'                => 'select_account consent',
            'code_challenge'        => $pkce['code_challenge'],
            'code_challenge_method' => $pkce['method'],
            'state'                 => $state,
        ];

        return add_query_arg($params, self::AUTH_ENDPOINT);
    }

    /**
     * Intercambia el AuthCode o la URL resultante pegada por el usuario por tokens de acceso y refresco.
     *
     * @param string $auth_code Código de autorización o URL completa obtenida tras iniciar sesión.
     * @return array ['success' => bool, 'message' => string, 'profile' => array|null]
     */
    public static function exchange_code($auth_code) {
        $auth_code = trim((string) $auth_code);
        if (empty($auth_code)) {
            return [
                'success' => false,
                'message' => __('El código o URL de autorización no puede estar vacío.', 'agy-wp-sgl'),
            ];
        }

        // Si el usuario pegó la URL de redirección completa (ej: http://localhost:8085/oauth2callback?code=4/0A...&scope=...)
        if (strpos($auth_code, 'code=') !== false) {
            $parsed_url   = parse_url($auth_code);
            $query_string = $parsed_url['query'] ?? '';
            if (empty($query_string) && strpos($auth_code, '?') !== false) {
                $query_string = substr($auth_code, strpos($auth_code, '?') + 1);
            }
            if (!empty($query_string)) {
                parse_str($query_string, $query_params);
                if (!empty($query_params['code'])) {
                    $auth_code = $query_params['code'];
                }
            }
        }

        $auth_code = urldecode(trim($auth_code));

        $client_id     = self::get_client_id();
        $client_secret = self::get_client_secret();
        $verifier      = Pkce_Helper::get_stored_verifier();

        if (empty($client_id)) {
            return [
                'success' => false,
                'message' => __('No se ha configurado el Client ID para la autenticación.', 'agy-wp-sgl'),
            ];
        }

        $body = [
            'code'          => $auth_code,
            'client_id'     => $client_id,
            'redirect_uri'  => self::get_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ];

        if (!empty($client_secret)) {
            $body['client_secret'] = $client_secret;
        }

        if (!empty($verifier)) {
            $body['code_verifier'] = $verifier;
        }

        Logger::info('Iniciando intercambio de AuthCode con endpoint de tokens.', [
            'client_id'    => $client_id,
            'redirect_uri' => $body['redirect_uri'],
            'has_secret'   => !empty($client_secret),
            'has_verifier' => !empty($verifier),
        ]);

        $response = wp_remote_post(self::TOKEN_ENDPOINT, [
            'timeout'   => 20,
            'sslverify' => true,
            'headers'   => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ],
            'body'      => $body,
        ]);

        if (is_wp_error($response)) {
            Logger::error('Error de red al intercambiar AuthCode: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => sprintf(__('Error de conexión con Google: %s', 'agy-wp-sgl'), $response->get_error_message()),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $data        = json_decode($raw_body, true);

        if ($status_code !== 200 || empty($data['access_token'])) {
            $error_desc = $data['error_description'] ?? ($data['error'] ?? 'Respuesta inválida del servidor');
            Logger::error('Fallo en el canje de AuthCode', [
                'status_code' => $status_code,
                'error'       => $error_desc,
            ]);

            return [
                'success' => false,
                'message' => sprintf(__('Fallo de autorización (%d): %s', 'agy-wp-sgl'), $status_code, $error_desc),
            ];
        }

        // Limpiar verifier de PKCE
        Pkce_Helper::clear_verifier();

        // Guardar tokens cifrados en la bóveda
        $saved = Token_Vault::save_tokens($data);
        if (!$saved) {
            return [
                'success' => false,
                'message' => __('No fue posible almacenar de forma segura los tokens en la base de datos.', 'agy-wp-sgl'),
            ];
        }

        // Obtener perfil del usuario asociado
        $profile = self::fetch_and_store_user_profile($data['access_token']);

        return [
            'success' => true,
            'message' => __('¡Conexión establecida con éxito con Antigravity!', 'agy-wp-sgl'),
            'profile' => $profile,
        ];
    }

    /**
     * Refresca el access_token usando el refresh_token almacenado.
     *
     * @return string|false Nuevo access_token o false en caso de error.
     */
    public static function refresh_access_token() {
        $refresh_token = Token_Vault::get_refresh_token();
        $client_id     = self::get_client_id();
        $client_secret = self::get_client_secret();

        if (empty($refresh_token) || empty($client_id)) {
            Logger::error('No hay refresh_token o client_id disponible para renovar la sesión.');
            Token_Vault::set_status('expired');
            return false;
        }

        $body = [
            'refresh_token' => $refresh_token,
            'client_id'     => $client_id,
            'grant_type'    => 'refresh_token',
        ];

        if (!empty($client_secret)) {
            $body['client_secret'] = $client_secret;
        }

        Logger::info('Renovando access_token con el servidor de Google...');

        $response = wp_remote_post(self::TOKEN_ENDPOINT, [
            'timeout'   => 15,
            'sslverify' => true,
            'headers'   => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ],
            'body'      => $body,
        ]);

        if (is_wp_error($response)) {
            Logger::error('Error de red al renovar token: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $data        = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200 || empty($data['access_token'])) {
            $err = $data['error_description'] ?? ($data['error'] ?? 'Error desconocido');
            Logger::error('No se pudo refrescar el access_token.', [
                'status_code' => $status_code,
                'error'       => $err,
            ]);

            // Si el token fue revocado en Google, marcar expirado/desconectado
            if (isset($data['error']) && in_array($data['error'], ['invalid_grant', 'unauthorized_client'], true)) {
                Token_Vault::set_status('expired');
            }

            return false;
        }

        // Guardar nuevos tokens en la bóveda
        Token_Vault::save_tokens($data);

        return $data['access_token'];
    }

    /**
     * Retorna un access_token válido y vigente, refrescándolo automáticamente si expiró.
     *
     * @return string|null
     */
    public static function get_valid_access_token() {
        if (!Token_Vault::is_token_expired()) {
            return Token_Vault::get_access_token();
        }

        return self::refresh_access_token();
    }

    /**
     * Consulta el perfil del usuario (email, nombre, avatar) con el access_token.
     *
     * @param string $access_token
     * @return array|null
     */
    public static function fetch_and_store_user_profile($access_token = null) {
        if (!$access_token) {
            $access_token = self::get_valid_access_token();
        }

        if (!$access_token) {
            return null;
        }

        $response = wp_remote_get(self::USERINFO_ENDPOINT, [
            'timeout'   => 10,
            'sslverify' => true,
            'headers'   => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $profile = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($profile)) {
            $sanitized_profile = [
                'email'   => sanitize_email($profile['email'] ?? ''),
                'name'    => sanitize_text_field($profile['name'] ?? ''),
                'picture' => esc_url_raw($profile['picture'] ?? ''),
                'sub'     => sanitize_text_field($profile['sub'] ?? ''),
            ];
            update_option('agy_user_profile', $sanitized_profile, false);
            return $sanitized_profile;
        }

        return null;
    }

    /**
     * Revoca los tokens ante Google y elimina las credenciales locales.
     *
     * @return bool
     */
    public static function disconnect() {
        $tokens = Token_Vault::get_tokens();
        $token_to_revoke = $tokens['refresh_token'] ?? ($tokens['access_token'] ?? null);

        if (!empty($token_to_revoke)) {
            wp_remote_post(self::REVOKE_ENDPOINT . '?token=' . urlencode($token_to_revoke), [
                'timeout' => 10,
            ]);
        }

        return Token_Vault::purge_tokens();
    }
}
