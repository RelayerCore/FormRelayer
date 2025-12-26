<?php

declare(strict_types=1);

/**
 * WordPress Function Mocks for Standalone Testing
 *
 * @package FormRelayer\Tests
 */

if (!defined('FR_TESTING_STANDALONE')) {
    return;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Mocking WordPress core functions

// Core constants
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Sanitization functions
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(?string $str): string {
        if ($str === null) {
            return '';
        }
        // Remove script and style tags with their contents (like WP does)
        $str = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/si', '', $str);
        // Strip remaining tags
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Mock function
        $str = strip_tags($str);
        // Normalize whitespace
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string {
        // WordPress validates AND sanitizes - invalid emails return empty string
        $sanitized = filter_var($email, FILTER_SANITIZE_EMAIL);
        if ($sanitized && filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
            return $sanitized;
        }
        return '';
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(?string $str): string {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Mock function
        return strip_tags($str ?? '');
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title(string $title): string {
        return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '-', $title));
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name(string $filename): string {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $string): string {
        return strip_tags($string, '<p><a><strong><em><ul><ol><li><br><h1><h2><h3><h4><h5><h6>');
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

// Validation functions
if (!function_exists('is_email')) {
    function is_email(string $email): bool {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

// Integer functions
if (!function_exists('absint')) {
    function absint($maybeint): int {
        return abs((int) $maybeint);
    }
}

// I18n functions
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string {
        return esc_html($text);
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string {
        return esc_attr($text);
    }
}

// Nonce functions
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = ''): string {
        return hash('sha256', $action . 'test_salt');
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, string $action = ''): bool {
        return $nonce === wp_create_nonce($action);
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer(string $action, string $queryArg = 'nonce'): bool {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Mock function
        $nonce = $_REQUEST[$queryArg] ?? '';
        return wp_verify_nonce($nonce, $action);
    }
}

// JSON functions
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0): string {
        return json_encode($data, $options);
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($response): void {
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode($response);
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null): void {
        wp_send_json(['success' => true, 'data' => $data]);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, int $statusCode = 400): void {
        http_response_code($statusCode);
        wp_send_json(['success' => false, 'data' => $data]);
    }
}

// Options functions
$_wp_options = [];

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        global $_wp_options;
        return $_wp_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value): bool {
        global $_wp_options;
        $_wp_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool {
        global $_wp_options;
        unset($_wp_options[$option]);
        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option(string $option, $value = ''): bool {
        return update_option($option, $value);
    }
}

// Transient functions
$_wp_transients = [];

if (!function_exists('get_transient')) {
    function get_transient(string $transient) {
        global $_wp_transients;
        $data = $_wp_transients[$transient] ?? null;
        
        if ($data && isset($data['expiration']) && $data['expiration'] < time()) {
            unset($_wp_transients[$transient]);
            return false;
        }
        
        return $data['value'] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, $value, int $expiration = 0): bool {
        global $_wp_transients;
        $_wp_transients[$transient] = [
            'value' => $value,
            'expiration' => $expiration > 0 ? time() + $expiration : 0,
        ];
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool {
        global $_wp_transients;
        unset($_wp_transients[$transient]);
        return true;
    }
}

// Salt function
if (!function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string {
        return 'test_salt_' . $scheme . '_12345678901234567890123456789012';
    }
}

// Other utility functions
if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value);
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type = 'mysql'): string {
        // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- Mock function
        return $type === 'mysql' ? date('Y-m-d H:i:s') : date('c');
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 24, bool $specialChars = true): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($specialChars) {
            $chars .= '!@#$%^&*()';
        }
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}

// Plugin functions
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string {
        return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}
