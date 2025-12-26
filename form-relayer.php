<?php
/**
 * Plugin Name: FormRelayer
 * Plugin URI: https://github.com/RelayerCore/FormRelayer
 * Description: A powerful, lightweight contact form plugin with admin dashboard, submissions viewer, auto-reply, and file attachments (Pro).
 * Version: 2.1.1
 * Author: FormRelayer
 * Author URI: https://github.com/orgs/RelayerCore
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: form-relayer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package FormRelayer
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check PHP version
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('FormRelayer requires PHP 8.0 or higher. Please upgrade your PHP version.', 'form-relayer');
        echo '</p></div>';
    });
    return;
}

// Autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Fallback: Simple PSR-4 autoloader
    spl_autoload_register(static function (string $class): void {
        $prefix = 'FormRelayer\\';
        $baseDir = __DIR__ . '/src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

// Initialize the plugin
FormRelayer\Core\Plugin::init(__FILE__);
