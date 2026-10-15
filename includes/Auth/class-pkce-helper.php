<?php
namespace Agy_Wp\Auth;

defined('ABSPATH') || exit;

/**
 * Asistente para el flujo PKCE (Proof Key for Code Exchange - RFC 7636).
 * Utilizado por CLI y aplicaciones para mitigar ataques de interceptación de código de autorización.
 */
class Pkce_Helper {

    const TRANSIENT_KEY = 'agy_pkce_verifier';

    /**
     * Genera un par verifier/challenge criptográficamente seguro y almacena el verifier en transient.
     *
     * @param int $length Longitud del verifier (43 a 128 caracteres).
     * @return array ['code_verifier' => string, 'code_challenge' => string, 'method' => 'S256']
     */
    public static function generate_pair($length = 64) {
        $length = max(43, min(128, (int) $length));
        $random_bytes = random_bytes($length);
        $verifier = self::base64url_encode($random_bytes);
        $verifier = substr($verifier, 0, $length);

        $hash = hash('sha256', $verifier, true);
        $challenge = self::base64url_encode($hash);

        // Guardar verifier temporalmente durante 15 minutos para el canje
        set_transient(self::TRANSIENT_KEY, $verifier, 15 * MINUTE_IN_SECONDS);

        return [
            'code_verifier'  => $verifier,
            'code_challenge' => $challenge,
            'method'         => 'S256',
        ];
    }

    /**
     * Obtiene el verifier almacenado para el canje.
     *
     * @return string|null
     */
    public static function get_stored_verifier() {
        $verifier = get_transient(self::TRANSIENT_KEY);
        return is_string($verifier) && !empty($verifier) ? $verifier : null;
    }

    /**
     * Limpia el verifier una vez utilizado.
     */
    public static function clear_verifier() {
        delete_transient(self::TRANSIENT_KEY);
    }

    /**
     * Codificación Base64 URL-safe (RFC 4648 §5).
     *
     * @param string $data
     * @return string
     */
    public static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
