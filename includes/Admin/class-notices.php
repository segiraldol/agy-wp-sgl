<?php
namespace Agy_Wp\Admin;

use Agy_Wp\Auth\Token_Vault;

defined('ABSPATH') || exit;

/**
 * Gestión de avisos administrativos de WordPress para el estado de la conexión con Antigravity.
 */
class Notices {

    /**
     * Muestra avisos globales en el panel si el token expiró o la sesión requiere reautenticación.
     */
    public function render_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        // Evitar duplicar el aviso si ya estamos en la propia página del plugin
        if ($screen && $screen->id === 'toplevel_page_agy-connect') {
            return;
        }

        $status = Token_Vault::get_status();

        if ($status === 'expired') {
            $settings_url = admin_url('admin.php?page=agy-connect');
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('Antigravity Connect:', 'agy-wp-sgl') . '</strong> ';
            echo esc_html__('La sesión de autorización ha expirado o fue revocada. ', 'agy-wp-sgl');
            echo '<a href="' . esc_url($settings_url) . '">' . esc_html__('Haz clic aquí para renovar la conexión con tu AuthCode.', 'agy-wp-sgl') . '</a>';
            echo '</p></div>';
        }
    }
}
