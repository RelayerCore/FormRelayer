<?php
/**
 * Assets Handler
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assets Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Assets {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend() {
        wp_enqueue_style(
            'form-relayer-frontend',
            FR_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            FR_VERSION
        );
        
        wp_enqueue_script(
            'form-relayer-frontend',
            FR_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            FR_VERSION,
            true
        );
        
        // reCAPTCHA
        $site_key = get_option('fr_recaptcha_site_key');
        if ($site_key) {
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google reCAPTCHA handles versioning
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($site_key), [], null, true);
        }
        
        // Localize script
        wp_localize_script('form-relayer-frontend', 'formRelayer', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('fr_form_nonce'),
            'recaptchaSiteKey' => $site_key,
            'i18n'    => [
                'sending'     => __('Sending...', 'form-relayer'),
                'error'       => __('An error occurred. Please try again.', 'form-relayer'),
                'networkError' => __('Network error. Please check your connection.', 'form-relayer'),
            ],
        ]);
        
        // Allow Pro to add assets
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        do_action('fr_enqueue_frontend_assets');
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page
     */
    public function enqueue_admin($hook) {
        $screen = get_current_screen();
        
        if (!$screen) {
            return;
        }
        
        // Load on FormRelayer pages (fr_form, fr_submission, settings)
        $is_fr_page = in_array($screen->post_type, ['fr_form', 'fr_submission']) 
                      || strpos($hook, 'fr-settings') !== false;
        
        if (!$is_fr_page) {
            return;
        }
        
        // Main admin styles
        wp_enqueue_style(
            'fr-admin-style',
            FR_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            FR_VERSION
        );
        
        // jQuery UI Sortable for drag-drop
        wp_enqueue_script('jquery-ui-sortable');
        
        // Color picker for settings
        if (strpos($hook, 'fr-settings') !== false || $screen->post_type === 'fr_form') {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }
        
        // Copy shortcode button on All Forms page
        if ($screen->post_type === 'fr_form' && $hook === 'edit.php') {
            wp_add_inline_script('jquery-core', '
                jQuery(document).ready(function($) {
                    $(document).on("click", ".copy-shortcode", function(e) {
                        e.preventDefault();
                        var code = $(this).data("code");
                        var $btn = $(this);
                        var originalText = $btn.text();
                        
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(code).then(function() {
                                $btn.text("' . esc_js(__('Copied!', 'form-relayer')) . '");
                                setTimeout(function() {
                                    $btn.text(originalText);
                                }, 2000);
                            });
                        } else {
                            // Fallback for older browsers
                            var temp = $("<textarea>");
                            $("body").append(temp);
                            temp.val(code).select();
                            document.execCommand("copy");
                            temp.remove();
                            $btn.text("' . esc_js(__('Copied!', 'form-relayer')) . '");
                            setTimeout(function() {
                                $btn.text(originalText);
                            }, 2000);
                        }
                    });
                });
            ');
        }
        
        // Allow Pro to add admin assets
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        do_action('fr_enqueue_admin_assets', $hook);
    }
}
