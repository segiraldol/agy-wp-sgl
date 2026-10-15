<?php
namespace Agy_Wp\Auth;

use Agy_Wp\Utils\Logger;

defined('ABSPATH') || exit;

/**
 * Bóveda de almacenamiento seguro y cifrado de credenciales de Antigravity.
 * Emplea AES-256-GCM derivando la clave criptográfica de las sales maestras de WordPress.
 */
class Token_Vault {

    /**
     * Nombre de la opción en wp_options.
     */
    const OPTION_NAME = 'agy_encrypted_tokens';

    /**
     * Algoritmo de cifrado preferido.
     */
    const CIPHER_GCM = 'aes-256-gcm';
    const CIPHER_CBC = 'aes-256-cbc';

    /**
     * Guarda y cifra los tokens recibidos de OAuth.
     *
     * @param array $token_data [access_token, refresh_token, expires_in, scope, id_token]
     * @return bool True si se guardó correctamente.
     */
    public static function save_tokens(array $token_data) {
        $existing = self::get_tokens() ?: [];

        // Si no viene nuevo refresh_token en la respuesta, conservar el existente
        $refresh_token = !empty($token_data['refresh_token'])
            ? $token_data['refresh_token']
            : ($existing['refresh_token'] ?? '');

        $payload_to_encrypt = [
            'access_token'  => $token_data['access_token'] ?? '',
            'refresh_token' => $refresh_token,
            'id_token'      => $token_data['id_token'] ?? ($existing['id_token'] ?? ''),
            'scope'         => $token_data['scope'] ?? ($existing['scope'] ?? ''),
            'token_type'    => $token_data['token_type'] ?? 'Bearer',
        ];

        $expires_in = isset($token_data['expires_in']) ? (int) $token_data['expires_in'] : 3600;
        $expires_at = time() + $expires_in;

        $encrypted = self::encrypt(wp_json_encode($payload_to_encrypt));
        if (!$encrypted) {
            Logger::error('No se pudo cifrar el paquete de tokens.');
            return false;
        }

        $record = [
            'ciphertext' => $encrypted['ciphertext'],
            'iv'         => $encrypted['iv'],
            'tag'        => $encrypted['tag'],
            'cipher'     => $encrypted['cipher'],
            'expires_at' => $expires_at,
            'updated_at' => time(),
            'status'     => 'connected',
        ];

        $saved = update_option(self::OPTION_NAME, $record, false);
        update_option('agy_connection_status', 'connected', false);

        Logger::info('Tokens de Antigravity guardados y cifrados con éxito.', [
            'expires_at' => date('Y-m-d H:i:s', $expires_at),
        ]);

        return $saved;
    }

    /**
     * Recupera y descifra los tokens almacenados.
     *
     * @return array|null Array con [access_token, refresh_token, ...] o null si no hay o falla.
     */
    public static function get_tokens() {
        $record = get_option(self::OPTION_NAME, null);
        if (!is_array($record) || empty($record['ciphertext'])) {
            return null;
        }

        $decrypted_json = self::decrypt($record);
        if (!$decrypted_json) {
            Logger::error('Error al descifrar el paquete de tokens.');
            return null;
        }

        $data = json_decode($decrypted_json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Obtiene únicamente el access_token actual descifrado.
     *
     * @return string|null
     */
    public static function get_access_token() {
        $tokens = self::get_tokens();
        return $tokens['access_token'] ?? null;
    }

    /**
     * Obtiene el refresh_token descifrado.
     *
     * @return string|null
     */
    public static function get_refresh_token() {
        $tokens = self::get_tokens();
        return $tokens['refresh_token'] ?? null;
    }

    /**
     * Determina si el token actual está expirado o a punto de expirar (margen de 300 segundos).
     *
     * @param int $buffer_seconds Margen preventivo en segundos (por defecto 300 = 5 min).
     * @return bool
     */
    public static function is_token_expired($buffer_seconds = 300) {
        $expires_at = self::get_expires_at();
        if ($expires_at <= 0) {
            return true;
        }

        return (time() + $buffer_seconds) >= $expires_at;
    }

    /**
     * Obtiene la marca de tiempo Unix de caducidad del token.
     *
     * @return int
     */
    public static function get_expires_at() {
        $record = get_option(self::OPTION_NAME, null);
        return isset($record['expires_at']) ? (int) $record['expires_at'] : 0;
    }

    /**
     * Obtiene el estado de la conexión.
     *
     * @return string 'connected', 'expired', 'error', 'disconnected'
     */
    public static function get_status() {
        $record = get_option(self::OPTION_NAME, null);
        if (!is_array($record) || empty($record['ciphertext'])) {
            return 'disconnected';
        }

        return $record['status'] ?? 'disconnected';
    }

    /**
     * Actualiza el estado de la conexión en base de datos.
     *
     * @param string $status
     */
    public static function set_status($status) {
        $record = get_option(self::OPTION_NAME, []);
        if (is_array($record)) {
            $record['status'] = sanitize_key($status);
            update_option(self::OPTION_NAME, $record, false);
        }
        update_option('agy_connection_status', sanitize_key($status), false);
    }

    /**
     * Elimina todos los tokens almacenados (desconexión).
     *
     * @return bool
     */
    public static function purge_tokens() {
        delete_option(self::OPTION_NAME);
        update_option('agy_connection_status', 'disconnected', false);
        delete_option('agy_user_profile');
        delete_option('agy_last_ping_status');
        Logger::info('Credenciales de Antigravity purgadas satisfactoriamente.');
        return true;
    }

    /**
     * Cifra un texto plano mediante OpenSSL (GCM o CBC con HMAC).
     *
     * @param string $plaintext
     * @return array|false [ciphertext, iv, tag, cipher]
     */
    protected static function encrypt($plaintext) {
        if (!function_exists('openssl_encrypt')) {
            Logger::error('La extensión PHP OpenSSL no está instalada o habilitada.');
            return false;
        }

        $key = self::derive_encryption_key();
        $use_gcm = in_array(self::CIPHER_GCM, openssl_get_cipher_methods(), true);

        if ($use_gcm) {
            $cipher = self::CIPHER_GCM;
            $iv_len = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($iv_len);
            $tag = '';

            $ciphertext = openssl_encrypt(
                $plaintext,
                $cipher,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                16
            );

            if ($ciphertext === false) {
                return false;
            }

            return [
                'ciphertext' => base64_encode($ciphertext),
                'iv'         => base64_encode($iv),
                'tag'        => base64_encode($tag),
                'cipher'     => $cipher,
            ];
        }

        // Fallback CBC con HMAC de autenticación
        $cipher = self::CIPHER_CBC;
        $iv_len = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($iv_len);

        $ciphertext = openssl_encrypt(
            $plaintext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            return false;
        }

        $hmac = hash_hmac('sha256', $ciphertext . $iv, $key, true);

        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv'         => base64_encode($iv),
            'tag'        => base64_encode($hmac),
            'cipher'     => $cipher,
        ];
    }

    /**
     * Descifra un paquete cifrado.
     *
     * @param array $payload [ciphertext, iv, tag, cipher]
     * @return string|false Texto plano o false en caso de fallo o alteración.
     */
    protected static function decrypt(array $payload) {
        if (!function_exists('openssl_decrypt')) {
            return false;
        }

        $ciphertext = base64_decode($payload['ciphertext'] ?? '');
        $iv         = base64_decode($payload['iv'] ?? '');
        $tag        = base64_decode($payload['tag'] ?? '');
        $cipher     = $payload['cipher'] ?? self::CIPHER_GCM;
        $key        = self::derive_encryption_key();

        if (empty($ciphertext) || empty($iv)) {
            return false;
        }

        if ($cipher === self::CIPHER_GCM) {
            if (empty($tag)) {
                return false;
            }

            return openssl_decrypt(
                $ciphertext,
                $cipher,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
        }

        // CBC fallback: verificación HMAC antes de descifrar
        $calculated_hmac = hash_hmac('sha256', $ciphertext . $iv, $key, true);
        if (!hash_equals($tag, $calculated_hmac)) {
            Logger::error('Fallo en la verificación de integridad HMAC del token.');
            return false;
        }

        return openssl_decrypt(
            $ciphertext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    /**
     * Deriva una clave simétrica de 256 bits a partir de las sales maestras de WordPress.
     *
     * @return string 32 bytes binarios.
     */
    protected static function derive_encryption_key() {
        $secret = '';
        if (defined('SECURE_AUTH_KEY') && SECURE_AUTH_KEY) {
            $secret .= SECURE_AUTH_KEY;
        }
        if (defined('LOGGED_IN_SALT') && LOGGED_IN_SALT) {
            $secret .= LOGGED_IN_SALT;
        }

        // Si por alguna razón extrema no estuvieran definidas en wp-config, recurrir a fallback
        if (empty($secret)) {
            $secret = get_option('agy_vault_salt_fallback');
            if (!$secret) {
                $secret = wp_generate_password(64, true, true);
                update_option('agy_vault_salt_fallback', $secret, false);
            }
        }

        return hash('sha256', 'AGY_WP_VAULT_KEY:' . $secret, true);
    }
}
