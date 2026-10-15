# Antigravity Connect (WordPress Plugin)

Plugin de WordPress que conecta tu sitio con el ecosistema de **Google Antigravity** mediante autenticación **OAuth 2.0 AuthCode**, emulando con exactitud el flujo de autenticación utilizado por el CLI oficial (`agy auth login`).

---

## 🚀 Características Principales

* **Flujo AuthCode sin dependencias de red local**: Ideal para servidores locales (XAMPP, Docker) o entornos de producción detrás de cortafuegos. El usuario autoriza en el navegador de Google y copia el código alfanumérico generado directamente en el panel de WordPress.
* **Bóveda de Seguridad con Cifrado AES-256**: Los tokens de acceso y refresco nunca se almacenan en texto plano en la base de datos `wp_options`. Se cifran criptográficamente utilizando las sales maestras de WordPress (`SECURE_AUTH_KEY` / `LOGGED_IN_SALT`).
* **Soporte PKCE (RFC 7636)**: Generación automática de `code_verifier` y `code_challenge` S256 para prevenir ataques de interceptación de código.
* **Refresco Continuo Desatendido**: Comprobación de vigencia del token antes de cada llamada a la API; renovación automática transparente mediante el `refresh_token`.
* **Prueba de Diagnóstico en Vivo (Ping)**: Mide la latencia de respuesta y valida la conectividad en tiempo real sin recargar la página.
* **Revocación y Desconexión Limpia**: Botón de desconexión que revoca los tokens directamente ante los servidores de Google y purga la base de datos local.
* **Capa API Extensible**: Proporciona el helper global `agy_get_client()` para consumir modelos e inferencias desde temas o plugins personalizados.

---

## 📋 Requisitos

* WordPress 5.8 o superior.
* PHP 7.4 o superior (compatible con PHP 8.x).
* Extensión PHP `openssl` y `curl`.

---

## 🔑 Inicio de Sesión Directo con Gmail (Sin Google Cloud Console)

Ya **no es necesario crear proyectos ni credenciales en Google Cloud Console**. El plugin incluye la configuración oficial de Antigravity preinstalada:

1. Instala y activa el plugin en tu WordPress (colocando la carpeta `agy-wp-sgl` dentro de `wp-content/plugins/`).
2. En el menú de administración de WordPress, dirígete a **Antigravity**.
3. En la tarjeta principal (**1. Iniciar Sesión con tu Cuenta de Gmail**):
   * Haz clic en **"Iniciar sesión con Google (Gmail Antigravity)"**.
   * Selecciona la cuenta de Gmail vinculada a tu acceso o suscripción de Google Antigravity y autoriza los permisos.
   * Google te redirigirá a una pantalla de confirmación.
   * Copia el código que aparezca o simplemente **copia la URL completa de la barra de direcciones de tu navegador** (ej. `http://localhost:8085/oauth2callback?code=4/0A...`).
   * Pégala en el campo correspondiente en WordPress y haz clic en **"Conectar con Antigravity"**.
4. ¡Listo! Tu sitio quedará vinculado a Google Antigravity y verás tu avatar, correo vinculado y estado **Conectado**.

---

## ⚙️ Ajustes Avanzados y Credenciales Personalizadas (Opcional)

Si por políticas empresariales o de seguridad deseas utilizar tu propio proyecto de Google Cloud Console en lugar del cliente integrado:

1. Ve a [Google Cloud Console](https://console.cloud.google.com/) > **APIs & Services** > **Credentials**.
2. Crea una credencial de tipo **OAuth Client ID** (tipo aplicación de escritorio / desktop app).
3. En la tarjeta derecha del plugin (**2. Parámetros y Credenciales**), despliega **Ajustes Avanzados** e ingresa tu Client ID, Client Secret o Project ID personalizado.
4. Haz clic en **Guardar Ajustes Personalizados**.

---

## 🔒 Configuración Avanzada mediante `wp-config.php`

Para entornos corporativos o de alta seguridad, puedes definir las credenciales directamente en `wp-config.php` para bloquear su edición en el panel:

```php
// Credenciales maestras de Antigravity
define('AGY_CLIENT_ID', '123456789-xxxxxxxx.apps.googleusercontent.com');
define('AGY_CLIENT_SECRET', 'GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx');
```

---

## 💻 Uso en Código Personalizado (Desarrolladores)

Puedes interactuar con Antigravity desde cualquier parte de tu código de WordPress utilizando la función helper `agy_get_client()`:

```php
// Comprobar si está conectado
$agy = agy_get_client();

if ($agy && $agy->is_connected()) {
    // 1. Probar conectividad y latencia
    $ping = $agy->ping();
    error_log('Latencia: ' . $ping['latency_ms'] . ' ms');

    // 2. Generar contenido con Gemini / Antigravity
    $response = $agy->generate_content('Escribe una breve introducción sobre inteligencia artificial en WordPress.', [
        'model'       => 'gemini-2.5-flash',
        'temperature' => 0.7,
    ]);

    if ($response['success']) {
        echo wp_kses_post($response['text']);
    } else {
        echo 'Error: ' . esc_html($response['error']);
    }
}
```

---

## 📂 Estructura del Plugin

```text
agy-wp-sgl/
├── agy-wp-sgl.php                  # Bootstrap principal, cabecera y hooks
├── uninstall.php                   # Purgado seguro de opciones al desinstalar
├── README.md                       # Documentación
├── assets/
│   ├── css/admin.css               # Estilos visuales del asistente y panel
│   └── js/admin.js                 # AJAX para AuthCode, test ping y desconexión
└── includes/
    ├── class-plugin.php            # Singleton orquestador
    ├── class-autoloader.php        # Autocarga de clases
    ├── Auth/
    │   ├── class-oauth-client.php  # Flujo OAuth 2.0 y canje de AuthCode
    │   ├── class-token-vault.php   # Cifrado AES-256-GCM y bóveda de tokens
    │   └── class-pkce-helper.php   # Generación de PKCE S256
    ├── Api/
    │   ├── class-antigravity-api.php # Cliente de servicios y generación
    │   └── class-rest-routes.php     # Rutas REST API protegidas para el admin
    ├── Admin/
    │   ├── class-admin-page.php    # Vista de asistente AuthCode y configuración
    │   ├── class-settings.php      # Settings API y sanitización
    │   └── class-notices.php       # Alertas de estado de conexión
    └── Utils/
        └── class-logger.php        # Registro seguro con enmascaramiento de tokens
```
