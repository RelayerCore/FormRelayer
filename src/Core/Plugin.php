<?php

declare(strict_types=1);

namespace FormRelayer\Core;

use FormRelayer\Admin\Columns;
use FormRelayer\Admin\Dashboard;
use FormRelayer\Admin\ImportExport;
use FormRelayer\Admin\Settings;
use FormRelayer\Admin\SubmissionList;
use FormRelayer\Builder\FormEditor;
use FormRelayer\Builder\FormPreview;
use FormRelayer\Builder\ModernBuilder;
use FormRelayer\Email\Mailer;
use FormRelayer\Forms\Handler;
use FormRelayer\Forms\Shortcode;
use FormRelayer\Forms\Templates;

/**
 * Main Plugin Class
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    public const VERSION = '2.0.0';
    public const SLUG = 'form-relayer';
    public const TEXT_DOMAIN = 'form-relayer';

    private function __construct(
        private readonly string $pluginFile,
        private readonly string $pluginDir,
        private readonly string $pluginUrl,
    ) {}

    /**
     * Get plugin instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Plugin not initialized. Call Plugin::init() first.');
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin
     */
    public static function init(string $pluginFile): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = new self(
            pluginFile: $pluginFile,
            pluginDir: plugin_dir_path($pluginFile),
            pluginUrl: plugin_dir_url($pluginFile),
        );

        self::$instance->bootstrap();

        return self::$instance;
    }

    /**
     * Bootstrap the plugin
     */
    private function bootstrap(): void
    {
        // Register activation/deactivation hooks
        register_activation_hook($this->pluginFile, [$this, 'activate']);
        register_deactivation_hook($this->pluginFile, [$this, 'deactivate']);

        // Initialize components
        add_action('init', [$this, 'loadTextdomain']);
        add_action('plugins_loaded', [$this, 'initComponents']);
        
        // Settings link
        add_filter(
            'plugin_action_links_' . plugin_basename($this->pluginFile),
            [$this, 'addSettingsLink']
        );
    }





    /**
     * Initialize plugin components
     */
    public function initComponents(): void
    {
        // Core
        PostType::getInstance();
        Assets::getInstance();

        // Builder
        FormEditor::getInstance();
        FormPreview::getInstance();
        ModernBuilder::getInstance();

        // Forms
        Shortcode::getInstance();
        Handler::getInstance();
        Templates::getInstance();

        // Admin
        Settings::getInstance();
        Columns::getInstance();
        Dashboard::getInstance();
        SubmissionList::getInstance();
        ImportExport::getInstance();

        // Email
        Mailer::getInstance();

        // Allow Pro to hook in
        do_action('formrelayer_loaded', $this);
    }

    /**
     * Load text domain
     */
    public function loadTextdomain(): void
    {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Kept for older WP version compatibility
        load_plugin_textdomain(
            'form-relayer',
            false,
            dirname(plugin_basename($this->pluginFile)) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Register post type first
        PostType::getInstance()->register();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set default options
        $this->setDefaultOptions();

        // Store activation time
        add_option('fr_activated_time', time());
        add_option('fr_version', self::VERSION);

        do_action('formrelayer_activated');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        flush_rewrite_rules();
        do_action('formrelayer_deactivated');
    }

    /**
     * Set default plugin options
     */
    private function setDefaultOptions(): void
    {
        $defaults = [
            'fr_recipient_email' => get_option('admin_email'),
            'fr_success_message' => __("Thank you for your message! We'll get back to you within 24 hours.", 'form-relayer'),
            'fr_enable_auto_reply' => 1,
            'fr_auto_reply_subject' => __('Thank you for contacting us', 'form-relayer'),
            'fr_auto_reply_message' => __(
                "Dear {name},\n\nThank you for reaching out. We have received your message and will respond within 24 hours.\n\nBest regards,\n{site_name} Team",
                'form-relayer'
            ),
            'fr_primary_color' => '#6366f1',
            'fr_secondary_color' => '#4f46e5',
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Add settings link to plugin list
     */
    public function addSettingsLink(array $links): array
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('edit.php?post_type=fr_form&page=fr-settings')),
            __('Settings', 'form-relayer')
        );
        array_unshift($links, $settingsLink);
        return $links;
    }

    /**
     * Check if Pro version is active
     */
    public static function isPro(): bool
    {
        return defined('FR_PRO_VERSION') || class_exists(\FormRelayer\Pro\Core\Plugin::class);
    }

    /**
     * Get plugin directory path
     */
    public function getPluginDir(): string
    {
        return $this->pluginDir;
    }

    /**
     * Get plugin URL
     */
    public function getPluginUrl(): string
    {
        return $this->pluginUrl;
    }

    /**
     * Get plugin file
     */
    public function getPluginFile(): string
    {
        return $this->pluginFile;
    }

    /**
     * Static helper to get plugin directory path
     */
    public static function path(string $path = ''): string
    {
        return self::getInstance()->pluginDir . ltrim($path, '/');
    }

    /**
     * Static helper to get plugin URL
     */
    public static function url(string $path = ''): string
    {
        return self::getInstance()->pluginUrl . ltrim($path, '/');
    }
}
