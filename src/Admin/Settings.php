<?php

declare(strict_types=1);

namespace FormRelayer\Admin;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;
use FormRelayer\Security\Nonce;
use FormRelayer\Security\Sanitizer;

/**
 * Settings Page
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Settings
{
    private static ?Settings $instance = null;

    public const PAGE_SLUG = 'fr-settings';

    private function __construct()
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_fr_save_settings', [$this, 'handleAjaxSave']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add settings submenu
     */
    public function addMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostType::POST_TYPE,
            __('Settings', 'form-relayer'),
            __('Settings', 'form-relayer'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        // General settings
        register_setting('fr_settings', 'fr_recipient_email', ['sanitize_callback' => 'sanitize_email']);
        register_setting('fr_settings', 'fr_from_email', ['sanitize_callback' => 'sanitize_email']);
        register_setting('fr_settings', 'fr_from_name', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('fr_settings', 'fr_success_message', ['sanitize_callback' => 'sanitize_textarea_field']);
        
        // Auto-reply settings
        register_setting('fr_settings', 'fr_enable_auto_reply', ['sanitize_callback' => 'absint']);
        register_setting('fr_settings', 'fr_auto_reply_subject', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('fr_settings', 'fr_auto_reply_message', ['sanitize_callback' => 'sanitize_textarea_field']);
        
        // Security settings - reCAPTCHA
        register_setting('fr_settings', 'fr_recaptcha_enabled', ['sanitize_callback' => 'absint']);
        register_setting('fr_settings', 'fr_recaptcha_site_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('fr_settings', 'fr_recaptcha_secret_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('fr_settings', 'fr_recaptcha_threshold', ['sanitize_callback' => fn($v) => max(0, min(1, (float) $v))]);
        
        // Security settings - Honeypot
        register_setting('fr_settings', 'fr_honeypot_enabled', ['sanitize_callback' => 'absint', 'default' => 1]);
        
        // Security settings - GDPR
        register_setting('fr_settings', 'fr_gdpr_enabled', ['sanitize_callback' => 'absint']);
        register_setting('fr_settings', 'fr_gdpr_text', ['sanitize_callback' => 'sanitize_textarea_field']);
        register_setting('fr_settings', 'fr_gdpr_required', ['sanitize_callback' => 'absint', 'default' => 1]);
        
        // Security settings - Rate Limiting
        register_setting('fr_settings', 'fr_rate_limit', ['sanitize_callback' => 'absint', 'default' => 5]);
        register_setting('fr_settings', 'fr_rate_window', ['sanitize_callback' => 'absint', 'default' => 3600]);
        
        // Design settings
        register_setting('fr_settings', 'fr_primary_color', ['sanitize_callback' => 'sanitize_hex_color']);
        register_setting('fr_settings', 'fr_secondary_color', ['sanitize_callback' => 'sanitize_hex_color']);
        register_setting('fr_settings', 'fr_email_logo', ['sanitize_callback' => 'esc_url_raw']);
        
        // Advanced settings
        register_setting('fr_settings', 'fr_enable_debug', ['sanitize_callback' => 'absint']);
        register_setting('fr_settings', 'fr_enable_email_logging', ['sanitize_callback' => 'absint']);
        
        // Integration settings
        register_setting('fr_settings', 'fr_mailchimp_api_key', ['sanitize_callback' => 'sanitize_text_field']);
    }

    /**
     * Enqueue assets
     */
    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('fr-admin-script', Plugin::getInstance()->getPluginUrl() . 'assets/js/admin.js', ['jquery', 'wp-color-picker'], Plugin::VERSION, true);
    }

    /**
     * Render settings page
     */
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tabs = $this->getTabs();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Admin page navigation, sanitized by method
        $currentTab = Sanitizer::key(wp_unslash($_GET['tab'] ?? 'general'));
        
        if (!isset($tabs[$currentTab])) {
            $currentTab = 'general';
        }
        ?>
        <div class="wrap fr-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tabId => $tabName): ?>
                    <a href="<?php echo esc_url(add_query_arg('tab', $tabId)); ?>"
                       class="nav-tab <?php echo $currentTab === $tabId ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tabName); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="options.php" class="fr-settings-form">
                <?php
                settings_fields('fr_settings');
                $this->renderTabContent($currentTab);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get settings tabs
     *
     * @return array<string, string>
     */
    private function getTabs(): array
    {
        $tabs = [
            'general' => __('General', 'form-relayer'),
            'email' => __('Email', 'form-relayer'),
            'integrations' => __('Integrations', 'form-relayer'),
            'security' => __('Security & Spam', 'form-relayer'),
            'design' => __('Design', 'form-relayer'),
            'advanced' => __('Advanced', 'form-relayer'),
        ];

        return apply_filters('formrelayer_settings_tabs', $tabs);
    }

    /**
     * Render tab content
     */
    private function renderTabContent(string $tab): void
    {
        match ($tab) {
            'general' => $this->renderGeneralTab(),
            'email' => $this->renderEmailTab(),
            'integrations' => $this->renderIntegrationsTab(),
            'security' => $this->renderSecurityTab(),
            'design' => $this->renderDesignTab(),
            'advanced' => $this->renderAdvancedTab(),
            default => do_action('formrelayer_settings_tab_' . $tab),
        };
    }

    /**
     * Render general settings tab
     */
    private function renderGeneralTab(): void
    {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="fr_recipient_email"><?php esc_html_e('Default Recipient Email', 'form-relayer'); ?></label>
                </th>
                <td>
                    <input type="email" id="fr_recipient_email" name="fr_recipient_email" 
                           value="<?php echo esc_attr(get_option('fr_recipient_email', get_option('admin_email'))); ?>" 
                           class="regular-text">
                    <p class="description"><?php esc_html_e('Default email address for form submissions.', 'form-relayer'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fr_success_message"><?php esc_html_e('Success Message', 'form-relayer'); ?></label>
                </th>
                <td>
                    <?php
                    $success_message = get_option('fr_success_message', __("Thank you for your message! We'll get back to you soon.", 'form-relayer'));
                    wp_editor($success_message, 'fr_success_message', [
                        'textarea_name' => 'fr_success_message',
                        'textarea_rows' => 5,
                        'media_buttons' => false,
                        'teeny' => false,
                        'quicktags' => true,
                        'tinymce' => [
                            'toolbar1' => 'bold,italic,underline,separator,link,unlink,separator,bullist,numlist,separator,undo,redo',
                            'toolbar2' => '',
                        ],
                    ]);
                    ?>
                    <p class="description"><?php esc_html_e('Message shown after successful submission.', 'form-relayer'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render email settings tab
     */
    private function renderEmailTab(): void
    {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="fr_from_email"><?php esc_html_e('From Email', 'form-relayer'); ?></label>
                </th>
                <td>
                    <input type="email" id="fr_from_email" name="fr_from_email" 
                           value="<?php echo esc_attr(get_option('fr_from_email', get_option('admin_email'))); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fr_from_name"><?php esc_html_e('From Name', 'form-relayer'); ?></label>
                </th>
                <td>
                    <input type="text" id="fr_from_name" name="fr_from_name" 
                           value="<?php echo esc_attr(get_option('fr_from_name', get_bloginfo('name'))); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Auto-Reply', 'form-relayer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="fr_enable_auto_reply" value="1" 
                               <?php checked(get_option('fr_enable_auto_reply', 1)); ?>>
                        <?php esc_html_e('Enable auto-reply emails', 'form-relayer'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fr_auto_reply_subject"><?php esc_html_e('Auto-Reply Subject', 'form-relayer'); ?></label>
                </th>
                <td>
                    <input type="text" id="fr_auto_reply_subject" name="fr_auto_reply_subject" 
                           value="<?php echo esc_attr(get_option('fr_auto_reply_subject', __('Thank you for contacting us', 'form-relayer'))); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fr_auto_reply_message"><?php esc_html_e('Auto-Reply Message', 'form-relayer'); ?></label>
                </th>
                <td>
                    <?php
                    $auto_reply_message = get_option('fr_auto_reply_message', '');
                    if (empty($auto_reply_message)) {
                        $auto_reply_message = __("Dear {name},\n\nThank you for reaching out. We have received your message and will respond within 24 hours.\n\nBest regards,\n{site_name} Team", 'form-relayer');
                    }
                    wp_editor($auto_reply_message, 'fr_auto_reply_message', [
                        'textarea_name' => 'fr_auto_reply_message',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                        'teeny' => false,
                        'quicktags' => true,
                        'tinymce' => [
                            'toolbar1' => 'bold,italic,underline,separator,link,unlink,separator,bullist,numlist,separator,undo,redo',
                            'toolbar2' => '',
                        ],
                    ]);
                    ?>
                    <p class="description" style="margin-top: 10px;">
                        <?php esc_html_e('Available placeholders:', 'form-relayer'); ?>
                        <code>{name}</code>, <code>{email}</code>, <code>{site_name}</code>, <code>{site_url}</code>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render security settings tab
     */
    private function renderSecurityTab(): void
    {
        ?>
        <h2><?php esc_html_e('Google reCAPTCHA v3', 'form-relayer'); ?></h2>
        <p class="description" style="margin-bottom: 15px;">
            <?php 
            printf(
                // translators: %s is a link to Google reCAPTCHA Admin
                esc_html__('Get your free reCAPTCHA keys from %s', 'form-relayer'),
                '<a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a>'
            ); 
            ?>
        </p>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable reCAPTCHA', 'form-relayer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="fr_recaptcha_enabled" value="1" 
                               <?php checked(get_option('fr_recaptcha_enabled', 0)); ?>>
                        <?php esc_html_e('Enable Google reCAPTCHA v3 spam protection', 'form-relayer'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fr_recaptcha_site_key"><?php esc_html_e('Site Key', 'form-relayer'); ?></label>
                </th>
                <td>
                    <input type="text" id="fr_recaptcha_site_key" name="fr_recaptcha_site_key" 
                           value="<?php echo esc_attr(get_option('fr_recaptcha_site_key', '')); ?>" 
                           class="regular-text" placeholder="6Lc...">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fr_recaptcha_secret_key"><?php esc_html_e('Secret Key', 'form-relayer'); ?></label>
                </th>
                <td>
                    <input type="password" id="fr_recaptcha_secret_key" name="fr_recaptcha_secret_key" 
                           value="<?php echo esc_attr(get_option('fr_recaptcha_secret_key', '')); ?>" 
                           class="regular-text" placeholder="6Lc...">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fr_recaptcha_threshold"><?php esc_html_e('Score Threshold', 'form-relayer'); ?></label>
                </th>
                <td>
                    <input type="number" id="fr_recaptcha_threshold" name="fr_recaptcha_threshold" 
                           value="<?php echo esc_attr(get_option('fr_recaptcha_threshold', '0.5')); ?>" 
                           min="0" max="1" step="0.1" style="width: 80px;">
                    <p class="description">
                        <?php esc_html_e('Score from 0.0 (likely bot) to 1.0 (likely human). Recommended: 0.5', 'form-relayer'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Honeypot Protection', 'form-relayer'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable Honeypot', 'form-relayer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="fr_honeypot_enabled" value="1" 
                               <?php checked(get_option('fr_honeypot_enabled', 1)); ?>>
                        <?php esc_html_e('Add invisible spam trap field to forms', 'form-relayer'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Honeypot adds a hidden field that bots tend to fill out. No user interaction needed.', 'form-relayer'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('GDPR Consent', 'form-relayer'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable GDPR Checkbox', 'form-relayer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="fr_gdpr_enabled" value="1" 
                               <?php checked(get_option('fr_gdpr_enabled', 0)); ?>>
                        <?php esc_html_e('Add consent checkbox to all forms', 'form-relayer'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fr_gdpr_text"><?php esc_html_e('Consent Text', 'form-relayer'); ?></label>
                </th>
                <td>
                    <?php
                    $gdpr_text = get_option('fr_gdpr_text', __('I agree to the storage and processing of my data by this website.', 'form-relayer'));
                    wp_editor($gdpr_text, 'fr_gdpr_text', [
                        'textarea_name' => 'fr_gdpr_text',
                        'textarea_rows' => 4,
                        'media_buttons' => false,
                        'teeny' => true,
                        'quicktags' => true,
                        'tinymce' => [
                            'toolbar1' => 'bold,italic,underline,link,unlink,undo,redo',
                            'toolbar2' => '',
                        ],
                    ]);
                    ?>
                    <p class="description" style="margin-top: 10px;">
                        <?php esc_html_e('Use the link button to add links to your privacy policy.', 'form-relayer'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Required', 'form-relayer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="fr_gdpr_required" value="1" 
                               <?php checked(get_option('fr_gdpr_required', 1)); ?>>
                        <?php esc_html_e('Users must accept to submit the form', 'form-relayer'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Rate Limiting', 'form-relayer'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="fr_rate_limit"><?php esc_html_e('Submissions Limit', 'form-relayer'); ?></label>
                </th>
                <td>
                    <input type="number" id="fr_rate_limit" name="fr_rate_limit" 
                           value="<?php echo esc_attr(get_option('fr_rate_limit', 5)); ?>" 
                           min="1" max="100" style="width: 80px;">
                    <span><?php esc_html_e('submissions per', 'form-relayer'); ?></span>
                    <select name="fr_rate_window" style="width: 120px;">
                        <option value="60" <?php selected(get_option('fr_rate_window', 3600), 60); ?>><?php esc_html_e('minute', 'form-relayer'); ?></option>
                        <option value="3600" <?php selected(get_option('fr_rate_window', 3600), 3600); ?>><?php esc_html_e('hour', 'form-relayer'); ?></option>
                        <option value="86400" <?php selected(get_option('fr_rate_window', 3600), 86400); ?>><?php esc_html_e('day', 'form-relayer'); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Limit form submissions per IP address to prevent abuse.', 'form-relayer'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render design settings tab
     */
    private function renderDesignTab(): void
    {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="fr_email_logo"><?php esc_html_e('Email Logo', 'form-relayer'); ?></label>
                </th>
                <td>
                    <?php $logoUrl = get_option('fr_email_logo', ''); ?>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <input type="text" id="fr_email_logo" name="fr_email_logo" 
                               value="<?php echo esc_attr($logoUrl); ?>" class="regular-text">
                        <button type="button" class="button fr-upload-logo-btn">
                            <?php esc_html_e('Upload Logo', 'form-relayer'); ?>
                        </button>
                    </div>
                    <div class="fr-logo-preview" style="margin-top:10px;">
                        <?php if ($logoUrl): ?>
                            <img src="<?php echo esc_url($logoUrl); ?>" style="max-height:50px;border:1px solid #ddd;padding:4px;border-radius:4px;background:#fff;">
                            <button type="button" class="button-link fr-remove-logo-btn" style="color:#b32d2e;"><?php esc_html_e('Remove', 'form-relayer'); ?></button>
                        <?php endif; ?>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Upload a logo to appear at the top of emails. Recommended height: 50px.', 'form-relayer'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fr_primary_color"><?php esc_html_e('Primary Color', 'form-relayer'); ?></label>
                </th>
                <td>
                    <input type="color" id="fr_primary_color" name="fr_primary_color" 
                           value="<?php echo esc_attr(get_option('fr_primary_color', '#6366f1')); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="fr_secondary_color"><?php esc_html_e('Secondary Color', 'form-relayer'); ?></label>
                </th>
                <td>
                    <input type="color" id="fr_secondary_color" name="fr_secondary_color" 
                           value="<?php echo esc_attr(get_option('fr_secondary_color', '#4f46e5')); ?>">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render integrations tab
     */
    private function renderIntegrationsTab(): void
    {
        ?>
        <div class="fr-integrations-tab">
            <p><?php esc_html_e('Configure your third-party integrations here.', 'form-relayer'); ?></p>
            <?php do_action('formrelayer_settings_integrations_tab'); ?>
        </div>
        <?php
    }

    /**
     * Render advanced settings tab
     */
    private function renderAdvancedTab(): void
    {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Debug Mode', 'form-relayer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="fr_enable_debug" value="1" 
                               <?php checked(get_option('fr_enable_debug', 0)); ?>>
                        <?php esc_html_e('Enable debug logging', 'form-relayer'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Logs will be saved to wp-content/fr-logs/', 'form-relayer'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Email Logging', 'form-relayer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="fr_enable_email_logging" value="1" 
                               <?php checked(get_option('fr_enable_email_logging', 0)); ?>>
                        <?php esc_html_e('Log all email attempts', 'form-relayer'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php

        // Allow Pro to add content
        do_action('formrelayer_settings_advanced_tab');
    }

    /**
     * Handle AJAX settings save
     */
    public function handleAjaxSave(): void
    {
        Nonce::verifyAjax('admin');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'form-relayer')], 403);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above, sanitized by method
        $settings = Sanitizer::json(wp_unslash($_POST['settings'] ?? '{}'));

        if (!$settings) {
            wp_send_json_error(['message' => __('Invalid data', 'form-relayer')], 400);
        }

        foreach ($settings as $key => $value) {
            if (str_starts_with($key, 'fr_')) {
                update_option(Sanitizer::key($key), $value);
            }
        }

        wp_send_json_success(['message' => __('Settings saved!', 'form-relayer')]);
    }
}
