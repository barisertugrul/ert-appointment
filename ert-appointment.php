<?php
/**
 * Plugin Name:       Appointment Booking by ERT
 * Plugin URI:        https://www.ertyazilim.com/ert-appointment/
 * Description:       A powerful, extensible appointment booking system for WordPress. Manage departments, providers, working hours, custom forms and email notifications.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            ERT
 * Author URI:        https://www.ertyazilim.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ert-appointment
 * Domain Path:       /languages
 */

declare(strict_types=1);

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

// PHP version guard.
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('WP Appointment requires PHP 8.1 or higher.', 'ert-appointment')
            . '</p></div>';
    });
    return;
}

// WordPress version guard.
if (version_compare(get_bloginfo('version'), '6.0', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('WP Appointment requires WordPress 6.0 or higher.', 'ert-appointment')
            . '</p></div>';
    });
    return;
}

// Plugin constants.
define('ERTA_VERSION', '1.0.0');
define('ERTA_FILE', __FILE__);
define('ERTA_PATH', plugin_dir_path(__FILE__));
define('ERTA_URL', plugin_dir_url(__FILE__));
define('ERTA_BASENAME', plugin_basename(__FILE__));
define('ERTA_MIN_PRO_VERSION', '1.0.0');

// Autoloader.
if (file_exists(ERTA_PATH . 'vendor/autoload.php')) {
    require_once ERTA_PATH . 'vendor/autoload.php';
} else {
    // Fallback PSR-4 autoloader for environments without Composer.
    spl_autoload_register(function (string $class): void {
        $prefix   = 'ERTAppointment\\';
        $base_dir = ERTA_PATH . 'src/';

        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file     = $base_dir . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

// Boot the plugin.
add_action('plugins_loaded', function (): void {
    \ERTAppointment\Plugin::getInstance(ERTA_FILE);
}, 0);
