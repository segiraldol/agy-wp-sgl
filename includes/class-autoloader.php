<?php
namespace Agy_Wp;

defined('ABSPATH') || exit;

/**
 * Autoloader para las clases de Antigravity Connect siguiendo las convenciones de WordPress.
 */
class Autoloader {

    /**
     * Registra el autoloader en la pila de SPL.
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Carga el archivo correspondiente a una clase dentro del namespace Agy_Wp.
     *
     * @param string $class Nombre completo de la clase con namespace.
     */
    public static function autoload($class) {
        $prefix = 'Agy_Wp\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        // Remover el prefijo del namespace base
        $relative_class = substr($class, strlen($prefix));

        // Separar partes del namespace
        $parts = explode('\\', $relative_class);
        $class_name = array_pop($parts);

        // Convertir nombre de clase a convención WordPress: Class_Name -> class-class-name.php
        $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';

        // Reconstruir la ruta
        $sub_path = empty($parts) ? '' : implode(DIRECTORY_SEPARATOR, $parts) . DIRECTORY_SEPARATOR;
        $full_path = AGY_WP_PATH . 'includes' . DIRECTORY_SEPARATOR . $sub_path . $file_name;

        if (file_exists($full_path)) {
            require_once $full_path;
        }
    }
}
