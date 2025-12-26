<?php

declare(strict_types=1);

namespace FormRelayer\Security;

/**
 * Nonce Handler
 *
 * Centralized nonce creation and verification
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Nonce
{
    private const PREFIX = 'fr_';

    /**
     * Create a nonce
     */
    public static function create(string $action): string
    {
        return wp_create_nonce(self::PREFIX . $action);
    }

    /**
     * Verify a nonce
     */
    public static function verify(string $nonce, string $action): bool
    {
        return (bool) wp_verify_nonce($nonce, self::PREFIX . $action);
    }

    /**
     * Verify nonce from request
     */
    public static function verifyRequest(string $action, string $nonceField = '_wpnonce'): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verification
        $nonce = wp_unslash($_REQUEST[$nonceField] ?? '');
        
        if (empty($nonce)) {
            return false;
        }

        return self::verify(sanitize_text_field($nonce), $action);
    }

    /**
     * Verify AJAX nonce and die if invalid
     */
    public static function verifyAjax(string $action, string $nonceField = 'nonce'): void
    {
        if (!self::verifyRequest($action, $nonceField)) {
            wp_send_json_error([
                'message' => __('Security check failed. Please refresh and try again.', 'form-relayer'),
                'code' => 'invalid_nonce',
            ], 403);
        }
    }

    /**
     * Verify REST nonce
     */
    public static function verifyRest(\WP_REST_Request $request, string $action): bool
    {
        $nonce = $request->get_header('X-WP-Nonce') ?? '';
        
        if (empty($nonce)) {
            return false;
        }

        return self::verify($nonce, $action);
    }

    /**
     * Get nonce field HTML
     */
    public static function field(string $action, bool $referer = true): string
    {
        ob_start();
        wp_nonce_field(self::PREFIX . $action, self::PREFIX . 'nonce', $referer);
        return ob_get_clean() ?: '';
    }

    /**
     * Get nonce field name for a given action
     */
    public static function getFieldName(string $action): string
    {
        return self::PREFIX . 'nonce';
    }
}
