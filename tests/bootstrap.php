<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap
 *
 * @package FormRelayer\Tests
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test bootstrap environment

// Load WordPress test environment if available
$wpTestsDir = getenv('WP_TESTS_DIR');

if (!$wpTestsDir) {
    $wpTestsDir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (file_exists($wpTestsDir . '/includes/functions.php')) {
    // WordPress test environment available
    require_once $wpTestsDir . '/includes/functions.php';

    tests_add_filter('muplugins_loaded', function (): void {
        require dirname(__DIR__) . '/form-relayer.php';
    });

    require_once $wpTestsDir . '/includes/bootstrap.php';

    define('FR_TESTING', true);
} else {
    // Standalone testing without WordPress
    define('ABSPATH', dirname(__DIR__, 4) . '/');
    define('FR_TESTING', true);
    define('FR_TESTING_STANDALONE', true);

    // Mock WordPress functions for unit tests
    require_once __DIR__ . '/mocks/wp-functions.php';
}
