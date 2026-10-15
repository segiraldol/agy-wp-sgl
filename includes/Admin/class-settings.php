<?php
namespace Agy_Wp\Admin;

defined('ABSPATH') || exit;

/**
 * Gestión de ajustes de Antigravity mediante la Settings API de WordPress.
 */
class Settings {

    const OPTION_KEY = 'agy_settings';
    const SETTINGS_GROUP = 'agy_settings_group';

    /**
     * Inicializa el registro de opciones.
     */
    public function register() {
        register_setting(self::SETTINGS_GROUP, self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default'           => [
                'client_id'     => '',
                'client_secret' => '',
                'project_id'    => '',
                'redirect_uri'  => 'http://localhost:8085/oauth2callback',
            ],
        ]);
    }

    /**
     * Sanitiza los datos ingresados en el formulario de configuración.
     *
     * @param array $input
     * @return array
     */
    public function sanitize_settings($input) {
        $output = get_option(self::OPTION_KEY, []);
        if (!is_array($output)) {
            $output = [];
        }

        if (isset($input['client_id'])) {
            $output['client_id'] = sanitize_text_field(trim($input['client_id']));
        }

        if (isset($input['client_secret'])) {
            $output['client_secret'] = sanitize_text_field(trim($input['client_secret']));
        }

        if (isset($input['project_id'])) {
            $output['project_id'] = sanitize_text_field(trim($input['project_id']));
        }

        if (isset($input['redirect_uri'])) {
            $output['redirect_uri'] = sanitize_text_field(trim($input['redirect_uri']));
        }

        return $output;
    }

    /**
     * Comprueba si el Client ID está definido por constante en wp-config.php.
     *
     * @return bool
     */
    public static function is_client_id_constant() {
        return defined('AGY_CLIENT_ID') && !empty(AGY_CLIENT_ID);
    }

    /**
     * Comprueba si el Client Secret está definido por constante en wp-config.php.
     *
     * @return bool
     */
    public static function is_client_secret_constant() {
        return defined('AGY_CLIENT_SECRET') && !empty(AGY_CLIENT_SECRET);
    }
}
