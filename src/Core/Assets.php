<?php

declare(strict_types=1);

namespace FormRelayer\Core;

use FormRelayer\Security\Nonce;

/**
 * Asset Management
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Assets
{
    private static ?Assets $instance = null;

    private function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontend']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontend(): void
    {
        wp_register_style(
            'formrelayer-frontend',
            $this->getAssetUrl('css/frontend.css'),
            [],
            Plugin::VERSION
        );

        // reCAPTCHA v3 script
        $recaptchaEnabled = get_option('fr_recaptcha_enabled', 0);
        $recaptchaSiteKey = get_option('fr_recaptcha_site_key', '');
        
        if ($recaptchaEnabled && $recaptchaSiteKey) {
            wp_register_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptchaSiteKey),
                [],
                // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google reCAPTCHA handles versioning
                null,
                true
            );
        }

        $deps = ['jquery'];
        if ($recaptchaEnabled && $recaptchaSiteKey) {
            $deps[] = 'google-recaptcha';
        }

        wp_register_script(
            'formrelayer-frontend',
            $this->getAssetUrl('js/frontend.js'),
            $deps,
            Plugin::VERSION,
            true
        );

        wp_localize_script('formrelayer-frontend', 'formRelayer', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('formrelayer/v1/'),
            'nonce' => Nonce::create('form_submit'),
            'recaptchaSiteKey' => $recaptchaEnabled ? $recaptchaSiteKey : '',
            'i18n' => [
                'submitting' => __('Submitting...', 'form-relayer'),
                'success' => __('Form submitted successfully!', 'form-relayer'),
                'error' => __('An error occurred. Please try again.', 'form-relayer'),
                'networkError' => __('Network error. Please check your connection.', 'form-relayer'),
                'required' => __('This field is required.', 'form-relayer'),
                'invalidEmail' => __('Please enter a valid email address.', 'form-relayer'),
            ],
        ]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdmin(string $hook): void
    {
        global $post_type;

        // Only load on our post types
        if ($post_type !== PostType::POST_TYPE && $post_type !== PostType::SUBMISSION_POST_TYPE) {
            return;
        }

        wp_enqueue_style(
            'formrelayer-admin',
            $this->getAssetUrl('css/admin.css'),
            [],
            Plugin::VERSION
        );

        wp_enqueue_script(
            'formrelayer-admin',
            $this->getAssetUrl('js/admin.js'),
            ['jquery', 'wp-util'],
            Plugin::VERSION,
            true
        );

        wp_localize_script('formrelayer-admin', 'frAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('formrelayer/v1/'),
            'nonce' => Nonce::create('admin'),
            'postType' => $post_type,
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this?', 'form-relayer'),
                'saving' => __('Saving...', 'form-relayer'),
                'saved' => __('Saved!', 'form-relayer'),
                'error' => __('Error saving. Please try again.', 'form-relayer'),
            ],
        ]);
    }

    /**
     * Get asset URL
     */
    public function getAssetUrl(string $path): string
    {
        return Plugin::getInstance()->getPluginUrl() . 'assets/' . ltrim($path, '/');
    }

    /**
     * Get asset path
     */
    public function getAssetPath(string $path): string
    {
        return Plugin::getInstance()->getPluginDir() . 'assets/' . ltrim($path, '/');
    }
}
