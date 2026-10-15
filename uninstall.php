<?php
/**
 * Script de desinstalación de Antigravity Connect
 *
 * Se ejecuta automáticamente al eliminar el plugin desde la interfaz de WordPress.
 * Limpia todas las opciones, credenciales cifradas y transients asociados.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Opciones del plugin
$options_to_delete = [
    'agy_settings',
    'agy_encrypted_tokens',
    'agy_connection_status',
    'agy_user_profile',
    'agy_last_ping_status',
];

foreach ($options_to_delete as $option) {
    delete_option($option);
    delete_site_option($option); // Compatibilidad con multisite
}

// Transients
delete_transient('agy_oauth_state');
delete_transient('agy_pkce_verifier');
delete_transient('agy_models_cache');
