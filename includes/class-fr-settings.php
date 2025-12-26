<?php
/**
 * Settings Page
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Settings {
    
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
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Add settings submenu
     */
    public function add_menu() {
        add_submenu_page(
            'edit.php?post_type=fr_form',
            __('Settings', 'form-relayer'),
            __('Settings', 'form-relayer'),
            'manage_options',
            'fr-settings',
            [$this, 'render_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General section
        add_settings_section(
            'fr_general_section',
            __('General Settings', 'form-relayer'),
            '__return_null',
            'fr-settings'
        );
        
        register_setting('fr_settings', 'fr_recipient_email', [
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email'),
        ]);
        
        add_settings_field(
            'fr_recipient_email',
            __('Recipient Email', 'form-relayer'),
            [$this, 'render_email_field'],
            'fr-settings',
            'fr_general_section'
        );
        
        register_setting('fr_settings', 'fr_success_message', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        
        add_settings_field(
            'fr_success_message',
            __('Success Message', 'form-relayer'),
            [$this, 'render_success_message_field'],
            'fr-settings',
            'fr_general_section'
        );
        
        // Appearance section
        add_settings_section(
            'fr_appearance_section',
            __('Appearance', 'form-relayer'),
            '__return_null',
            'fr-settings'
        );
        
        register_setting('fr_settings', 'fr_primary_color', [
            'sanitize_callback' => 'sanitize_hex_color',
            'default' => '#0073aa',
        ]);
        
        add_settings_field(
            'fr_primary_color',
            __('Primary Color', 'form-relayer'),
            [$this, 'render_primary_color_field'],
            'fr-settings',
            'fr_appearance_section'
        );
        
        register_setting('fr_settings', 'fr_secondary_color', [
            'sanitize_callback' => 'sanitize_hex_color',
            'default' => '#005177',
        ]);
        
        add_settings_field(
            'fr_secondary_color',
            __('Secondary Color', 'form-relayer'),
            [$this, 'render_secondary_color_field'],
            'fr-settings',
            'fr_appearance_section'
        );
        
        // Form Fields section
        add_settings_section(
            'fr_form_fields_section',
            __('Form Fields', 'form-relayer'),
            [$this, 'render_form_fields_section_description'],
            'fr-settings'
        );
        
        register_setting('fr_settings', 'fr_form_locations', [
            'sanitize_callback' => [$this, 'sanitize_locations'],
            'default' => [],
        ]);
        
        add_settings_field(
            'fr_form_locations',
            __('Location Options', 'form-relayer'),
            [$this, 'render_locations_field'],
            'fr-settings',
            'fr_form_fields_section'
        );
        
        // Auto-reply section
        add_settings_section(
            'fr_autoreply_section',
            __('Auto-Reply Settings', 'form-relayer'),
            [$this, 'render_autoreply_description'],
            'fr-settings'
        );
        
        register_setting('fr_settings', 'fr_enable_auto_reply', [
            'sanitize_callback' => 'absint',
            'default' => 1,
        ]);
        
        add_settings_field(
            'fr_enable_auto_reply',
            __('Enable Auto-Reply', 'form-relayer'),
            [$this, 'render_autoreply_checkbox'],
            'fr-settings',
            'fr_autoreply_section'
        );
        
        register_setting('fr_settings', 'fr_auto_reply_subject', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        add_settings_field(
            'fr_auto_reply_subject',
            __('Auto-Reply Subject', 'form-relayer'),
            [$this, 'render_autoreply_subject_field'],
            'fr-settings',
            'fr_autoreply_section'
        );
        
        register_setting('fr_settings', 'fr_auto_reply_message', [
            'sanitize_callback' => 'wp_kses_post',
        ]);
        
        add_settings_field(
            'fr_auto_reply_message',
            __('Auto-Reply Message', 'form-relayer'),
            [$this, 'render_autoreply_message_field'],
            'fr-settings',
            'fr_autoreply_section'
        );
        
        // Privacy section
        add_settings_section(
            'fr_privacy_section',
            __('Privacy Settings', 'form-relayer'),
            '__return_null',
            'fr-settings'
        );
        
        register_setting('fr_settings', 'fr_enable_gdpr', [
            'sanitize_callback' => 'absint',
            'default' => 0,
        ]);
        
        add_settings_field(
            'fr_enable_gdpr',
            __('Enable Privacy Checkbox', 'form-relayer'),
            [$this, 'render_gdpr_checkbox'],
            'fr-settings',
            'fr_privacy_section'
        );
        
        register_setting('fr_settings', 'fr_gdpr_message', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        add_settings_field(
            'fr_gdpr_message',
            __('Privacy Message', 'form-relayer'),
            [$this, 'render_gdpr_message_field'],
            'fr-settings',
            'fr_privacy_section'
        );

        // Spam Protection Section
        add_settings_section(
            'fr_spam_section',
            __('Spam Protection (reCAPTCHA v3)', 'form-relayer'),
            '__return_null',
            'fr-settings'
        );
        
        register_setting('fr_settings', 'fr_recaptcha_site_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('fr_settings', 'fr_recaptcha_secret_key', ['sanitize_callback' => 'sanitize_text_field']);
        
        add_settings_field(
            'fr_recaptcha_site_key',
            __('Site Key', 'form-relayer'),
            [$this, 'render_recaptcha_site_key'],
            'fr-settings',
            'fr_spam_section'
        );
        
        add_settings_field(
            'fr_recaptcha_secret_key',
            __('Secret Key', 'form-relayer'),
            [$this, 'render_recaptcha_secret_key'],
            'fr-settings',
            'fr_spam_section'
        );

        // Pro features section placeholder
        if (!FormRelayer::is_pro()) {
            add_settings_section(
                'fr_pro_section',
                __('Pro Features', 'form-relayer'),
                [$this, 'render_pro_upsell'],
                'fr-settings'
            );
        }
    }
    
    /**
     * Render settings page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get current values
        $recipient = get_option('fr_recipient_email', get_option('admin_email'));
        $success_msg = get_option('fr_success_message', __('Thank you for your message!', 'form-relayer'));
        $primary_color = get_option('fr_primary_color', '#0073aa');
        $enable_auto = get_option('fr_enable_auto_reply', 1);
        $auto_subject = get_option('fr_auto_reply_subject', __('Thank you for contacting us', 'form-relayer'));
        $auto_message = get_option('fr_auto_reply_message', __("Dear {name},\n\nThank you for reaching out to us. We have received your message and will respond within 24 hours.\n\nIf you have any urgent concerns, please don't hesitate to call us directly.\n\nBest regards,\n{site_name} Team", 'form-relayer'));
        $enable_gdpr = get_option('fr_enable_gdpr', 0);
        $gdpr_message = get_option('fr_gdpr_message', '');
        $recaptcha_site = get_option('fr_recaptcha_site_key', '');
        $recaptcha_secret = get_option('fr_recaptcha_secret_key', '');
        ?>
        <div class="wrap fr-settings-wrap">
            <h1>
                <span class="dashicons dashicons-forms" style="font-size: 28px; margin-right: 10px;"></span>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>
            
            <div class="fr-settings-layout">
                <!-- Left: Main Settings -->
                <div class="fr-settings-main">
                    <form method="post" action="options.php">
                        <?php settings_fields('fr_settings'); ?>
                        
                        <div class="fr-tabs-wrapper fr-settings-tabs">
                            <nav class="fr-tabs-nav">
                                <button type="button" class="fr-tab-btn active" data-tab="general">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    <?php esc_html_e('General', 'form-relayer'); ?>
                                </button>
                                <button type="button" class="fr-tab-btn" data-tab="autoreply">
                                    <span class="dashicons dashicons-email"></span>
                                    <?php esc_html_e('Auto-Reply', 'form-relayer'); ?>
                                </button>
                                <button type="button" class="fr-tab-btn" data-tab="privacy">
                                    <span class="dashicons dashicons-shield"></span>
                                    <?php esc_html_e('Privacy & Spam', 'form-relayer'); ?>
                                </button>
                            </nav>
                            
                            <!-- Tab: General -->
                            <div class="fr-tab-content active" id="fr-tab-general">
                                <div class="fr-settings-card">
                                    <h3><?php esc_html_e('Email Settings', 'form-relayer'); ?></h3>
                                    <div class="fr-settings-grid">
                                        <div class="fr-setting-group">
                                            <label for="fr_recipient_email"><?php esc_html_e('Recipient Email', 'form-relayer'); ?></label>
                                            <input type="email" name="fr_recipient_email" id="fr_recipient_email" value="<?php echo esc_attr($recipient); ?>">
                                            <p class="description"><?php esc_html_e('Where form submissions will be sent.', 'form-relayer'); ?></p>
                                        </div>
                                        <div class="fr-setting-group">
                                            <label for="fr_primary_color"><?php esc_html_e('Primary Color', 'form-relayer'); ?></label>
                                            <input type="color" name="fr_primary_color" id="fr_primary_color" value="<?php echo esc_attr($primary_color); ?>" style="height: 40px; width: 80px;">
                                        </div>
                                        <div class="fr-setting-group fr-full-width">
                                            <label for="fr_success_message"><?php esc_html_e('Success Message', 'form-relayer'); ?></label>
                                            <textarea name="fr_success_message" id="fr_success_message" rows="3"><?php echo esc_textarea($success_msg); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tab: Auto-Reply -->
                            <div class="fr-tab-content" id="fr-tab-autoreply">
                                <div class="fr-settings-card">
                                    <h3><?php esc_html_e('Auto-Reply Email', 'form-relayer'); ?></h3>
                                    <div class="fr-settings-grid">
                                        <div class="fr-setting-group fr-full-width">
                                            <label>
                                                <input type="checkbox" name="fr_enable_auto_reply" value="1" <?php checked($enable_auto, 1); ?>>
                                                <?php esc_html_e('Send auto-reply email to sender', 'form-relayer'); ?>
                                            </label>
                                            <p class="description"><?php esc_html_e('Global default. Can be overridden per-form.', 'form-relayer'); ?></p>
                                        </div>
                                        <div class="fr-setting-group fr-full-width">
                                            <label for="fr_auto_reply_subject"><?php esc_html_e('Subject', 'form-relayer'); ?></label>
                                            <input type="text" name="fr_auto_reply_subject" id="fr_auto_reply_subject" value="<?php echo esc_attr($auto_subject); ?>">
                                        </div>
                                        <div class="fr-setting-group fr-full-width">
                                            <label for="fr_auto_reply_message"><?php esc_html_e('Message', 'form-relayer'); ?></label>
                                            <?php 
                                            wp_editor($auto_message, 'fr_auto_reply_message', [
                                                'textarea_name' => 'fr_auto_reply_message',
                                                'textarea_rows' => 8,
                                                'media_buttons' => false,
                                                'teeny' => true,
                                            ]);
                                            ?>
                                            <p class="description">
                                                <?php esc_html_e('Placeholders:', 'form-relayer'); ?>
                                                <code>{name}</code>, <code>{email}</code>, <code>{site_name}</code>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tab: Privacy & Spam -->
                            <div class="fr-tab-content" id="fr-tab-privacy">
                                <div class="fr-settings-card">
                                    <h3><?php esc_html_e('GDPR Compliance', 'form-relayer'); ?></h3>
                                    <div class="fr-settings-grid">
                                        <div class="fr-setting-group fr-full-width">
                                            <label>
                                                <input type="checkbox" name="fr_enable_gdpr" value="1" <?php checked($enable_gdpr, 1); ?>>
                                                <?php esc_html_e('Show GDPR consent checkbox on forms', 'form-relayer'); ?>
                                            </label>
                                        </div>
                                        <div class="fr-setting-group fr-full-width">
                                            <label for="fr_gdpr_message"><?php esc_html_e('Consent Message', 'form-relayer'); ?></label>
                                            <textarea name="fr_gdpr_message" id="fr_gdpr_message" rows="2"><?php echo esc_textarea($gdpr_message); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="fr-settings-card">
                                    <h3><?php esc_html_e('Google reCAPTCHA v3', 'form-relayer'); ?></h3>
                                    <div class="fr-settings-grid">
                                        <div class="fr-setting-group">
                                            <label for="fr_recaptcha_site_key"><?php esc_html_e('Site Key', 'form-relayer'); ?></label>
                                            <input type="text" name="fr_recaptcha_site_key" id="fr_recaptcha_site_key" value="<?php echo esc_attr($recaptcha_site); ?>">
                                        </div>
                                        <div class="fr-setting-group">
                                            <label for="fr_recaptcha_secret_key"><?php esc_html_e('Secret Key', 'form-relayer'); ?></label>
                                            <input type="password" name="fr_recaptcha_secret_key" id="fr_recaptcha_secret_key" value="<?php echo esc_attr($recaptcha_secret); ?>">
                                        </div>
                                        <div class="fr-setting-group fr-full-width">
                                            <p class="description">
                                            <?php 
                                            // translators: %s is the URL to Google reCAPTCHA Admin
                                            printf( wp_kses( __( 'Get your keys from <a href="%s" target="_blank">Google reCAPTCHA Admin</a> (Select v3).', 'form-relayer' ), array( 'a' => array( 'href' => array(), 'target' => array() ) ) ), 'https://www.google.com/recaptcha/admin/create' ); 
                                            ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php submit_button(__('Save Settings', 'form-relayer')); ?>
                    </form>
                </div>
                
                <!-- Right: Sidebar -->
                <div class="fr-settings-sidebar">
                    <div class="fr-shortcode-box">
                        <h3>📋 <?php esc_html_e('Quick Start', 'form-relayer'); ?></h3>
                        <p><?php esc_html_e('Use this shortcode to display a form:', 'form-relayer'); ?></p>
                        <code>[form_relayer id="123"]</code>
                        <p class="description" style="margin-top: 10px;"><?php esc_html_e('Replace 123 with your Form ID.', 'form-relayer'); ?></p>
                    </div>
                    
                    <?php if (!FormRelayer::is_pro()) : ?>
                    <div class="fr-pro-upsell">
                        <h3>🚀 <?php esc_html_e('Upgrade to Pro', 'form-relayer'); ?></h3>
                        <p><?php esc_html_e('Unlock powerful features:', 'form-relayer'); ?></p>
                        <ul>
                            <li>✅ <?php esc_html_e('File Attachments', 'form-relayer'); ?></li>
                            <li>✅ <?php esc_html_e('Export to CSV/Excel', 'form-relayer'); ?></li>
                            <li>✅ <?php esc_html_e('Conditional Logic', 'form-relayer'); ?></li>
                            <li>✅ <?php esc_html_e('Zapier Integration', 'form-relayer'); ?></li>
                            <li>✅ <?php esc_html_e('Priority Support', 'form-relayer'); ?></li>
                        </ul>
                        <a href="https://formrelayer.com/pro" target="_blank" class="button button-primary"><?php esc_html_e('Get Pro →', 'form-relayer'); ?></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            // Tab switching
            $('.fr-settings-tabs .fr-tab-btn').on('click', function() {
                var tab = $(this).data('tab');
                $('.fr-settings-tabs .fr-tab-btn').removeClass('active');
                $(this).addClass('active');
                $('.fr-settings-tabs .fr-tab-content').removeClass('active');
                $('#fr-tab-' + tab).addClass('active');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Field Renderers
     */
    public function render_email_field() {
        $value = get_option('fr_recipient_email', get_option('admin_email'));
        ?>
        <input type="email" name="fr_recipient_email" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php esc_html_e('Where form submissions will be sent.', 'form-relayer'); ?></p>
        <?php
    }
    
    public function render_success_message_field() {
        $value = get_option('fr_success_message', __('Thank you for your message! We\'ll get back to you within 24 hours.', 'form-relayer'));
        ?>
        <textarea name="fr_success_message" rows="3" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <?php
    }
    
    public function render_primary_color_field() {
        $value = get_option('fr_primary_color', '#0073aa');
        ?>
        <input type="text" name="fr_primary_color" value="<?php echo esc_attr($value); ?>" class="fr-color-picker" data-default-color="#0073aa">
        <span class="fr-color-preview" style="background: <?php echo esc_attr($value); ?>;"></span>
        <?php
    }
    
    public function render_secondary_color_field() {
        $value = get_option('fr_secondary_color', '#005177');
        ?>
        <input type="text" name="fr_secondary_color" value="<?php echo esc_attr($value); ?>" class="fr-color-picker" data-default-color="#005177">
        <span class="fr-color-preview" style="background: <?php echo esc_attr($value); ?>;"></span>
        <?php
    }
    
    public function render_form_fields_section_description() {
        echo '<p>' . esc_html__('Configure the location dropdown options that appear in the contact form.', 'form-relayer') . '</p>';
    }
    
    public function render_locations_field() {
        $locations = get_option('fr_form_locations', []);
        if (!is_array($locations)) {
            $locations = [];
        }
        ?>
        <div id="fr-locations-repeater">
            <?php if (!empty($locations)) : ?>
                <?php foreach ($locations as $index => $location) : ?>
                    <div class="fr-location-row">
                        <input type="text" name="fr_form_locations[]" value="<?php echo esc_attr($location); ?>" placeholder="<?php esc_attr_e('Location name', 'form-relayer'); ?>">
                        <button type="button" class="button fr-remove-location"><?php esc_html_e('Remove', 'form-relayer'); ?></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <button type="button" class="button fr-add-location"><?php esc_html_e('+ Add Location', 'form-relayer'); ?></button>
        <p class="description"><?php esc_html_e('Add locations that users can select in the contact form. Leave empty to hide the location field.', 'form-relayer'); ?></p>
        
        <script>
        jQuery(function($) {
            $('.fr-add-location').on('click', function() {
                var row = '<div class="fr-location-row">' +
                    '<input type="text" name="fr_form_locations[]" placeholder="<?php esc_attr_e('Location name', 'form-relayer'); ?>">' +
                    '<button type="button" class="button fr-remove-location"><?php esc_html_e('Remove', 'form-relayer'); ?></button>' +
                    '</div>';
                $('#fr-locations-repeater').append(row);
            });
            
            $(document).on('click', '.fr-remove-location', function() {
                $(this).closest('.fr-location-row').remove();
            });
        });
        </script>
        
        <style>
            .fr-location-row { margin-bottom: 8px; display: flex; gap: 10px; align-items: center; }
            .fr-location-row input { width: 300px; }
            .fr-add-location { margin-top: 10px; }
        </style>
        <?php
    }
    
    public function sanitize_locations($input) {
        if (!is_array($input)) {
            return [];
        }
        return array_filter(array_map('sanitize_text_field', $input));
    }
    
    public function render_autoreply_description() {
        echo '<p>' . esc_html__('Configure automatic reply emails sent to users after form submission.', 'form-relayer') . '</p>';
    }
    
    public function render_autoreply_checkbox() {
        $value = get_option('fr_enable_auto_reply', 1);
        ?>
        <label>
            <input type="checkbox" name="fr_enable_auto_reply" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Send auto-reply email to sender', 'form-relayer'); ?>
        </label>
        <?php
    }
    
    public function render_autoreply_subject_field() {
        $value = get_option('fr_auto_reply_subject', __('Thank you for contacting us', 'form-relayer'));
        ?>
        <input type="text" name="fr_auto_reply_subject" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php
    }
    
    public function render_autoreply_message_field() {
        $value = get_option('fr_auto_reply_message', __("Dear {name},\n\nThank you for reaching out. We have received your message and will respond within 24 hours.\n\nBest regards,\n{site_name} Team", 'form-relayer'));
        
        wp_editor($value, 'fr_auto_reply_message', [
            'textarea_name' => 'fr_auto_reply_message',
            'textarea_rows' => 10,
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
            <?php esc_html_e('Placeholders:', 'form-relayer'); ?>
            <code>{name}</code>,
            <code>{email}</code>,
            <code>{subject}</code>,
            <code>{site_name}</code>
        </p>
        <?php
    }
    
    public function render_gdpr_checkbox() {
        $value = get_option('fr_enable_gdpr', 0);
        ?>
        <label>
            <input type="checkbox" name="fr_enable_gdpr" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Show privacy consent checkbox on the form', 'form-relayer'); ?>
        </label>
        <?php
    }
    
    public function render_gdpr_message_field() {
        $value = get_option('fr_gdpr_message', __('I consent to having this website store my submitted information so they can respond to my inquiry.', 'form-relayer'));
        ?>
        <input type="text" name="fr_gdpr_message" value="<?php echo esc_attr($value); ?>" class="large-text">
        <p class="description"><?php esc_html_e('The text displayed next to the consent checkbox.', 'form-relayer'); ?></p>
        <?php
    }
    
    public function render_recaptcha_site_key() {
        $value = get_option('fr_recaptcha_site_key', '');
        ?>
        <input type="text" name="fr_recaptcha_site_key" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php 
        // translators: %s is the URL to Google reCAPTCHA Admin
        printf( wp_kses( __( 'Get your keys from <a href="%s" target="_blank">Google reCAPTCHA Admin</a> (Select v3).', 'form-relayer' ), array( 'a' => array( 'href' => array(), 'target' => array() ) ) ), 'https://www.google.com/recaptcha/admin/create' ); 
        ?>
        <?php
    }
    
    public function render_recaptcha_secret_key() {
        $value = get_option('fr_recaptcha_secret_key', '');
        ?>
        <input type="password" name="fr_recaptcha_secret_key" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php
    }
    
    public function render_pro_upsell() {
        ?>
        <div class="fr-pro-upsell">
            <h3>🚀 <?php esc_html_e('Upgrade to FormRelayer Pro', 'form-relayer'); ?></h3>
            <p><?php esc_html_e('Unlock powerful features to supercharge your contact forms:', 'form-relayer'); ?></p>
            <ul>
                <li>✅ <?php esc_html_e('File Attachments', 'form-relayer'); ?></li>
                <li>✅ <?php esc_html_e('Google reCAPTCHA / hCaptcha Integration', 'form-relayer'); ?></li>
                <li>✅ <?php esc_html_e('Export Submissions to CSV/Excel', 'form-relayer'); ?></li>
                <li>✅ <?php esc_html_e('Advanced Email Templates', 'form-relayer'); ?></li>
                <li>✅ <?php esc_html_e('Multiple Forms Support', 'form-relayer'); ?></li>
                <li>✅ <?php esc_html_e('Conditional Logic', 'form-relayer'); ?></li>
                <li>✅ <?php esc_html_e('Zapier/Webhook Integrations', 'form-relayer'); ?></li>
                <li>✅ <?php esc_html_e('Priority Support', 'form-relayer'); ?></li>
            </ul>
            <a href="https://formrelayer.com/pro" target="_blank" class="button button-large"><?php esc_html_e('Get FormRelayer Pro →', 'form-relayer'); ?></a>
        </div>
        <?php
    }
}
