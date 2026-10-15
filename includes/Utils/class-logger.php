<?php
namespace Agy_Wp\Utils;

defined('ABSPATH') || exit;

/**
 * Logger seguro para Antigravity Connect.
 * Escribe en el archivo de depuración de WordPress (debug.log) sólo cuando WP_DEBUG está habilitado,
 * ofuscando automáticamente tokens y claves secretas.
 */
class Logger {

    /**
     * Registra un mensaje de error.
     *
     * @param string $message Mensaje.
     * @param array  $context Datos adicionales para el log.
     */
    public static function error($message, array $context = []) {
        self::log('ERROR', $message, $context);
    }

    /**
     * Registra un mensaje informativo.
     *
     * @param string $message Mensaje.
     * @param array  $context Datos adicionales para el log.
     */
    public static function info($message, array $context = []) {
        self::log('INFO', $message, $context);
    }

    /**
     * Escribe la entrada en error_log respetando WP_DEBUG.
     *
     * @param string $level   Nivel de severidad (INFO, ERROR, DEBUG).
     * @param string $message Mensaje descriptivo.
     * @param array  $context Contexto adicional a ofuscar y registrar.
     */
    protected static function log($level, $message, array $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $sanitized_context = self::mask_sensitive_data($context);
        $context_str = !empty($sanitized_context) ? ' | Context: ' . wp_json_encode($sanitized_context) : '';
        $entry = sprintf('[Antigravity Connect] [%s] %s%s', $level, $message, $context_str);

        error_log($entry);
    }

    /**
     * Ofusca claves sensibles como tokens, secretos y códigos de autorización.
     *
     * @param mixed $data Datos a revisar y ofuscar.
     * @return mixed
     */
    protected static function mask_sensitive_data($data) {
        if (!is_array($data)) {
            return $data;
        }

        $sensitive_keys = ['access_token', 'refresh_token', 'client_secret', 'auth_code', 'code', 'authorization'];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::mask_sensitive_data($value);
            } elseif (in_array(strtolower($key), $sensitive_keys, true) && is_string($value)) {
                $len = strlen($value);
                if ($len <= 8) {
                    $data[$key] = '***';
                } else {
                    $data[$key] = substr($value, 0, 4) . '...' . substr($value, -4);
                }
            }
        }

        return $data;
    }
}
