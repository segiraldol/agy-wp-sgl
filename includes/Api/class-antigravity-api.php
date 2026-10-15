<?php
namespace Agy_Wp\Api;

use Agy_Wp\Auth\OAuth_Client;
use Agy_Wp\Auth\Token_Vault;
use Agy_Wp\Utils\Logger;

defined('ABSPATH') || exit;

/**
 * Cliente de servicios y API para Google Antigravity / Gemini.
 * Realiza peticiones autenticadas usando el access_token renovado automáticamente.
 */
class Antigravity_Api {

    const GEMINI_API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Comprueba si el plugin cuenta con una conexión activa.
     *
     * @return bool
     */
    public function is_connected() {
        return Token_Vault::get_status() === 'connected' && !empty(Token_Vault::get_refresh_token());
    }

    /**
     * Realiza una prueba de diagnóstico (Ping) para verificar la vigencia de la conexión y medir latencia.
     *
     * @return array ['success' => bool, 'latency_ms' => float, 'message' => string, 'profile' => array|null]
     */
    public function ping() {
        $start_time = microtime(true);
        $token = OAuth_Client::get_valid_access_token();

        if (!$token) {
            return [
                'success'    => false,
                'latency_ms' => 0,
                'message'    => __('No hay token de acceso válido disponible.', 'agy-wp-sgl'),
            ];
        }

        $profile = OAuth_Client::fetch_and_store_user_profile($token);
        $latency = round((microtime(true) - $start_time) * 1000, 2);

        if (!$profile) {
            update_option('agy_last_ping_status', [
                'success'    => false,
                'timestamp'  => time(),
                'latency_ms' => $latency,
                'message'    => __('Fallo al validar credenciales con el servidor de Google.', 'agy-wp-sgl'),
            ], false);

            return [
                'success'    => false,
                'latency_ms' => $latency,
                'message'    => __('Fallo al validar credenciales con Google.', 'agy-wp-sgl'),
            ];
        }

        $ping_data = [
            'success'    => true,
            'timestamp'  => time(),
            'latency_ms' => $latency,
            'email'      => $profile['email'] ?? '',
            'message'    => sprintf(__('Conexión activa y verificada (%s ms)', 'agy-wp-sgl'), $latency),
        ];

        update_option('agy_last_ping_status', $ping_data, false);

        return array_merge($ping_data, ['profile' => $profile]);
    }

    /**
     * Obtiene el catálogo de modelos disponibles para la cuenta.
     *
     * @param bool $force_refresh Si es true, ignora el cache transitorio.
     * @return array
     */
    public function list_models($force_refresh = false) {
        $cache_key = 'agy_models_cache';
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = OAuth_Client::get_valid_access_token();
        if (!$token) {
            return [];
        }

        $response = wp_remote_get(self::GEMINI_API_BASE . '/models', [
            'timeout'   => 15,
            'sslverify' => true,
            'headers'   => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            Logger::error('Error al listar modelos de Antigravity');
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $models = $data['models'] ?? [];

        // Guardar en cache por 12 horas
        set_transient($cache_key, $models, 12 * HOUR_IN_SECONDS);

        return $models;
    }

    /**
     * Ejecuta una solicitud de generación de contenido (Prompt / Inferencias).
     *
     * @param string $prompt  Texto del prompt.
     * @param array  $options Opciones adicionales (model, temperature, etc.).
     * @return array ['success' => bool, 'text' => string, 'raw' => array]
     */
    public function generate_content($prompt, array $options = []) {
        $token = OAuth_Client::get_valid_access_token();
        if (!$token) {
            return [
                'success' => false,
                'text'    => '',
                'error'   => __('No autenticado con Antigravity.', 'agy-wp-sgl'),
            ];
        }

        $model = !empty($options['model']) ? sanitize_text_field($options['model']) : 'gemini-2.5-flash';
        $endpoint = sprintf('%s/models/%s:generateContent', self::GEMINI_API_BASE, $model);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => (string) $prompt],
                    ],
                ],
            ],
        ];

        if (isset($options['temperature'])) {
            $payload['generationConfig']['temperature'] = (float) $options['temperature'];
        }

        $response = wp_remote_post($endpoint, [
            'timeout'   => 45,
            'sslverify' => true,
            'headers'   => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'      => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            Logger::error('Error en generate_content: ' . $response->get_error_message());
            return [
                'success' => false,
                'text'    => '',
                'error'   => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $msg = $data['error']['message'] ?? 'Error desconocido de la API';
            return [
                'success' => false,
                'text'    => '',
                'error'   => sprintf('HTTP %d: %s', $code, $msg),
            ];
        }

        $generated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'success' => true,
            'text'    => $generated_text,
            'raw'     => $data,
        ];
    }
}
