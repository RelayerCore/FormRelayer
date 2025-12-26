<?php

declare(strict_types=1);

namespace FormRelayer\Builder;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;
use FormRelayer\Security\Nonce;

/**
 * Modern Form Builder
 *
 * 2-panel form builder: Canvas | Settings
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class ModernBuilder
{
    private static ?ModernBuilder $instance = null;

    private function __construct()
    {
        add_action('add_meta_boxes', [$this, 'replaceMetaBoxes'], 100);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('admin_body_class', [$this, 'addBodyClass']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add body class for builder page
     */
    public function addBodyClass(string $classes): string
    {
        global $post_type, $pagenow;

        if ($post_type === PostType::POST_TYPE && in_array($pagenow, ['post.php', 'post-new.php'])) {
            $classes .= ' fr-builder-page';
        }

        return $classes;
    }

    /**
     * Replace default meta boxes with modern builder
     */
    public function replaceMetaBoxes(): void
    {
        global $post_type;

        if ($post_type !== PostType::POST_TYPE) {
            return;
        }

        // Remove all default meta boxes
        remove_meta_box('submitdiv', PostType::POST_TYPE, 'side');
        remove_meta_box('slugdiv', PostType::POST_TYPE, 'normal');

        // Add our builder
        add_meta_box(
            'fr_modern_builder',
            __('Form Builder', 'form-relayer'),
            [$this, 'renderBuilder'],
            PostType::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Enqueue builder assets
     */
    public function enqueueAssets(string $hook): void
    {
        global $post_type;

        if ($post_type !== PostType::POST_TYPE || !in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        // Enqueue jQuery UI for sortable
        wp_enqueue_script('jquery-ui-sortable');

        // Builder CSS
        wp_enqueue_style(
            'fr-builder',
            Plugin::getInstance()->getPluginUrl() . 'assets/css/builder.css',
            [],
            Plugin::VERSION
        );

        // Builder JS
        wp_enqueue_script(
            'fr-builder',
            Plugin::getInstance()->getPluginUrl() . 'assets/js/builder.js',
            ['jquery', 'jquery-ui-sortable'],
            Plugin::VERSION,
            true
        );

        // Localize script
        wp_localize_script('fr-builder', 'frBuilder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => Nonce::create('admin'),
            'previewNonce' => wp_create_nonce('fr_form_preview'),
            'postId' => get_the_ID(),
            'previewUrl' => FormPreview::getPreviewUrl(get_the_ID() ?: 0),
            'i18n' => $this->getI18n(),
        ]);

        // Hide WP clutter
        $this->addInlineStyles();
    }

    /**
     * Get i18n strings
     */
    private function getI18n(): array
    {
        return [
            'addElement' => __('Add Field', 'form-relayer'),
            'addFirstField' => __('Add Your First Field', 'form-relayer'),
            'emptyTitle' => __('Start Building Your Form', 'form-relayer'),
            'emptyHint' => __('Click the button below to add your first field', 'form-relayer'),
            'fieldSettings' => __('Field Settings', 'form-relayer'),
            'formSettings' => __('Form Settings', 'form-relayer'),
            'label' => __('Label', 'form-relayer'),
            'fieldId' => __('Field ID', 'form-relayer'),
            'fieldIdHint' => __('Unique identifier (used in placeholders)', 'form-relayer'),
            'placeholder' => __('Placeholder', 'form-relayer'),
            'required' => __('Required', 'form-relayer'),
            'options' => __('Options', 'form-relayer'),
            'optionsHint' => __('One option per line', 'form-relayer'),
            'cssClass' => __('CSS Class', 'form-relayer'),
            'notificationEmail' => __('Notification Email', 'form-relayer'),
            'buttonText' => __('Button Text', 'form-relayer'),
            'successMessage' => __('Success Message', 'form-relayer'),
            'redirectUrl' => __('Redirect URL', 'form-relayer'),
            'redirectHint' => __('Leave empty to show success message', 'form-relayer'),
            'primaryColor' => __('Primary Color', 'form-relayer'),
            'buttonColor' => __('Button Color', 'form-relayer'),
            'enableAutoReply' => __('Enable Auto-Reply', 'form-relayer'),
            'autoReplySubject' => __('Auto-Reply Subject', 'form-relayer'),
            'autoReplyMessage' => __('Auto-Reply Message', 'form-relayer'),
            'autoReplyHint' => __('Use {field_id} placeholders', 'form-relayer'),
            'searchFields' => __('Search fields...', 'form-relayer'),
            'basicFields' => __('Basic Fields', 'form-relayer'),
            'choiceFields' => __('Choice Fields', 'form-relayer'),
            'contentFields' => __('Content', 'form-relayer'),
            'otherFields' => __('Other', 'form-relayer'),
            'preview' => __('Preview', 'form-relayer'),
            'save' => __('Save', 'form-relayer'),
            'saved' => __('Saved!', 'form-relayer'),
            'saving' => __('Saving...', 'form-relayer'),
            'deleteField' => __('Delete Field', 'form-relayer'),
            'confirmDelete' => __('Delete this field?', 'form-relayer'),
        ];
    }

    /**
     * Add inline styles to hide WP clutter
     */
    private function addInlineStyles(): void
    {
        wp_add_inline_style('fr-builder', '
            .fr-builder-page #screen-meta,
            .fr-builder-page #screen-meta-links,
            .fr-builder-page #postdivrich,
            .fr-builder-page #postimagediv,
            .fr-builder-page #tagsdiv-post_tag,
            .fr-builder-page .postbox-header,
            .fr-builder-page #side-sortables,
            .fr-builder-page #postbox-container-1,
            .fr-builder-page #titlediv,
            .fr-builder-page .wrap > h1,
            .fr-builder-page .page-title-action { display: none !important; }
            .fr-builder-page #fr_modern_builder { border: none; box-shadow: none; background: none; margin: 0; }
            .fr-builder-page #fr_modern_builder .inside { padding: 0; margin: 0; }
            .fr-builder-page #post-body.columns-2 { margin-right: 0; }
            .fr-builder-page #post-body-content { margin-bottom: 0; }
            .fr-builder-page .wrap { padding-top: 0; }
        ');
    }

    /**
     * Render the builder
     */
    public function renderBuilder(\WP_Post $post): void
    {
        // Get form data - handle both legacy array format and new JSON string format
        $fieldsRaw = get_post_meta($post->ID, '_fr_fields', true);
        
        // Convert to JSON string for the hidden input
        if (is_array($fieldsRaw)) {
            // Legacy format: stored as PHP array
            $fieldsJson = wp_json_encode($fieldsRaw);
        } elseif (is_string($fieldsRaw) && !empty($fieldsRaw)) {
            // New format: already JSON string - validate it
            $decoded = json_decode($fieldsRaw, true);
            $fieldsJson = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) 
                ? $fieldsRaw 
                : '[]';
        } else {
            // Empty or invalid
            $fieldsJson = '[]';
        }
        
        $email = get_post_meta($post->ID, '_fr_email', true) ?: get_option('admin_email');
        $buttonText = get_post_meta($post->ID, '_fr_button_text', true) ?: __('Submit', 'form-relayer');
        $successMsg = get_post_meta($post->ID, '_fr_success_message', true) ?: '';
        $redirectUrl = get_post_meta($post->ID, '_fr_redirect_url', true) ?: '';
        $primaryColor = get_post_meta($post->ID, '_fr_primary_color', true) ?: '#6366f1';
        $buttonColor = get_post_meta($post->ID, '_fr_button_color', true) ?: '#6366f1';
        $autoReplyEnabled = get_post_meta($post->ID, '_fr_auto_reply_enabled', true) ?: '';
        $autoReplySubject = get_post_meta($post->ID, '_fr_auto_reply_subject', true) ?: '';
        $autoReplyMessage = get_post_meta($post->ID, '_fr_auto_reply_message', true) ?: '';

        wp_nonce_field('fr_save_form', 'fr_nonce');
        ?>
        <!-- Hidden inputs for form data -->
        <input type="hidden" id="fr-form-id" value="<?php echo esc_attr($post->ID); ?>">
        <input type="hidden" id="fr-fields-data" name="fr_fields_json" value="<?php echo esc_attr($fieldsJson); ?>">
        <input type="hidden" name="fr_email" value="<?php echo esc_attr($email); ?>">
        <input type="hidden" name="fr_email_template" value="<?php echo esc_attr(get_post_meta($post->ID, '_fr_email_template', true) ?: 'default'); ?>">
        <input type="hidden" name="fr_custom_email_html" value="<?php echo esc_attr(get_post_meta($post->ID, '_fr_custom_email_html', true)); ?>">
        <input type="hidden" name="fr_button_text" value="<?php echo esc_attr($buttonText); ?>">
        <input type="hidden" name="fr_confirmation_type" value="<?php echo esc_attr(get_post_meta($post->ID, '_fr_confirmation_type', true) ?: 'message'); ?>">
        <input type="hidden" name="fr_success_message" value="<?php echo esc_attr($successMsg); ?>">
        <input type="hidden" name="fr_redirect_url" value="<?php echo esc_attr($redirectUrl); ?>">
        <input type="hidden" name="fr_primary_color" value="<?php echo esc_attr($primaryColor); ?>">
        <input type="hidden" name="fr_button_color" value="<?php echo esc_attr($buttonColor); ?>">
        <input type="hidden" name="fr_auto_reply_enabled" value="<?php echo esc_attr($autoReplyEnabled); ?>">
        <input type="hidden" name="fr_auto_reply_subject" value="<?php echo esc_attr($autoReplySubject); ?>">
        <input type="hidden" name="fr_auto_reply_message" value="<?php echo esc_attr($autoReplyMessage); ?>">


        <div class="fr-builder">
            <!-- Header -->
            <div class="fr-builder-header">
                <div class="fr-builder-brand">
                    <div class="fr-builder-logo">
                        <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0iI2ZmZmZmZiI+PHBhdGggZD0iTTE3IDNIM2MtMS4xIDAtMiAuOS0yIDJ2MTBjMCAxLjEuOSAyIDIgMmgxNGMxLjEgMCAyLS45IDItMlY1YzAtMS4xLS45LTItMi0yem0tNSAxMkg0di0yaDh2MnptNC00SDRWOWgxMnYyem0wLTRINFY1aDEydjJ6Ii8+PC9zdmc+" alt="FormRelayer" style="width: 20px; height: 20px;">
                    </div>
                    <input type="text" 
                           id="fr-form-title" 
                           name="post_title" 
                           value="<?php echo esc_attr($post->post_title); ?>" 
                           placeholder="<?php esc_attr_e('Form Name', 'form-relayer'); ?>"
                           class="fr-builder-title-input">
                </div>
                
                <div class="fr-builder-actions">
                    <!-- Shortcode Copy -->
                    <div class="fr-shortcode-copy" title="<?php esc_attr_e('Click to copy shortcode', 'form-relayer'); ?>">
                        <code id="fr-shortcode-text">[form_relayer id="<?php echo esc_attr($post->ID); ?>"]</code>
                        <button type="button" id="fr-copy-shortcode" class="fr-copy-btn">
                            <span class="dashicons dashicons-admin-page"></span>
                            <span class="fr-copy-label"><?php esc_html_e('Copy', 'form-relayer'); ?></span>
                        </button>
                    </div>

                    <button type="button" id="fr-mobile-toggle-settings" class="fr-btn fr-btn-ghost fr-btn-icon fr-mobile-only" 
                            title="<?php esc_attr_e('Toggle Settings', 'form-relayer'); ?>" style="display:none;">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </button>
                    <button type="button" id="fr-btn-settings" class="fr-btn fr-btn-ghost fr-btn-icon fr-desktop-only" 
                            title="<?php esc_attr_e('Form Settings', 'form-relayer'); ?>">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </button>
                    <button type="button" id="fr-btn-preview" class="fr-btn fr-btn-ghost">
                        <span class="dashicons dashicons-visibility"></span>
                        <span class="fr-btn-label"><?php esc_html_e('Preview', 'form-relayer'); ?></span>
                    </button>
                    <button type="submit" id="fr-btn-save" name="save" class="fr-btn fr-btn-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <span class="fr-btn-label"><?php esc_html_e('Save Form', 'form-relayer'); ?></span>
                    </button>
                </div>
            </div>

            <!-- Canvas -->
            <div class="fr-form-canvas">
                <div class="fr-canvas-wrapper">
                    <!-- Minimal Device Header -->
                    <div class="fr-device-header"></div>
                    <div class="fr-canvas-fields"></div>
                </div>
            </div>

            <!-- Settings Panel -->
            <div class="fr-settings-panel">
                <div class="fr-settings-header">
                    <h3><?php esc_html_e('Settings', 'form-relayer'); ?></h3>
                    <div class="fr-settings-actions">
                        <button type="button" class="fr-settings-mobile-close fr-mobile-only" title="<?php esc_attr_e('Close Settings', 'form-relayer'); ?>">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                </div>

                <div class="fr-settings-body" data-mode="form"></div>
            </div>
        </div>

        <!-- Toast Container -->
        <div class="fr-toast-container"></div>

        <!-- Form Settings Modal (Slide-out Panel) -->
        <div id="fr-form-settings-modal" class="fr-settings-modal" style="display:none;">
            <div class="fr-settings-modal-overlay"></div>
            <div class="fr-settings-modal-panel">
                <div class="fr-settings-modal-header">
                    <h2>
                        <span style="display:inline-flex; align-items:center; margin-right:8px;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#334155"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H6v-2h6v2zm4-4H6v-2h10v2zm0-4H6V7h10v2z"/></svg></span>
                        <?php esc_html_e('Form Settings', 'form-relayer'); ?>
                    </h2>
                    <button type="button" class="fr-settings-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="fr-settings-modal-body">
                    
                    <!-- General Section -->
                    <div class="fr-modal-section">
                        <h3 class="fr-modal-section-title"><?php esc_html_e('General', 'form-relayer'); ?></h3>
                        
                        <div class="fr-modal-field">
                            <label for="fr_email_modal"><?php esc_html_e('Notification Email', 'form-relayer'); ?></label>
                            <input type="email" id="fr_email_modal" name="fr_email" 
                                   value="<?php echo esc_attr($email); ?>" 
                                   placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                            <p class="description"><?php esc_html_e('Leave empty to use global setting.', 'form-relayer'); ?></p>
                        </div>
                        
                        <div class="fr-modal-field">
                            <label for="fr_email_template_modal"><?php esc_html_e('Email Template', 'form-relayer'); ?></label>
                            <?php $emailTemplate = get_post_meta($post->ID, '_fr_email_template', true) ?: 'default'; ?>
                            <?php $isProActive = class_exists('FormRelayer\Pro\Core\Plugin'); ?>
                            <select id="fr_email_template_modal" name="fr_email_template">
                                <option value="default" <?php selected($emailTemplate, 'default'); ?>><?php esc_html_e('Default (Branded)', 'form-relayer'); ?></option>
                                <option value="plain" <?php selected($emailTemplate, 'plain'); ?>><?php esc_html_e('Plain Text', 'form-relayer'); ?></option>
                                <option value="modern" <?php selected($emailTemplate, 'modern'); ?> <?php echo esc_attr( $isProActive ? '' : 'disabled' ); ?>><?php esc_html_e('Modern', 'form-relayer'); ?><?php echo esc_html( $isProActive ? '' : ' (Pro)' ); ?></option>
                                <option value="corporate" <?php selected($emailTemplate, 'corporate'); ?> <?php echo esc_attr( $isProActive ? '' : 'disabled' ); ?>><?php esc_html_e('Corporate', 'form-relayer'); ?><?php echo esc_html( $isProActive ? '' : ' (Pro)' ); ?></option>
                                <option value="dark" <?php selected($emailTemplate, 'dark'); ?> <?php echo esc_attr( $isProActive ? '' : 'disabled' ); ?>><?php esc_html_e('Dark Mode', 'form-relayer'); ?><?php echo esc_html( $isProActive ? '' : ' (Pro)' ); ?></option>
                                <option value="custom" <?php selected($emailTemplate, 'custom'); ?> <?php echo esc_attr( $isProActive ? '' : 'disabled' ); ?>><?php esc_html_e('Custom HTML', 'form-relayer'); ?><?php echo esc_html( $isProActive ? '' : ' (Pro)' ); ?></option>
                            </select>
                            <?php if (!$isProActive): ?>
                            <p class="description"><?php printf(
                                /* translators: %s: upgrade link */
                                esc_html__('Want more templates? %s', 'form-relayer'),
                                '<a href="https://formrelayer.com/pro" target="_blank">' . esc_html__('Upgrade to Pro', 'form-relayer') . '</a>'
                            ); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Custom Email HTML (Pro) -->
                        <div class="fr-modal-field" id="fr-custom-email-container" style="display:none;">
                            <label for="fr_custom_email_html_modal"><?php esc_html_e('Custom Email HTML', 'form-relayer'); ?></label>
                            <textarea id="fr_custom_email_html_modal" rows="8" class="fr-code-editor"
                                      placeholder="<html><body>...</body></html>"><?php echo esc_textarea(get_post_meta($post->ID, '_fr_custom_email_html', true)); ?></textarea>
                            <p class="description"><?php esc_html_e('Use {all_fields} to display submission data, or use individual field tags.', 'form-relayer'); ?></p>
                        </div>
                        
                        <div class="fr-modal-field">
                            <label for="fr_button_text_modal"><?php esc_html_e('Submit Button Text', 'form-relayer'); ?></label>
                            <input type="text" id="fr_button_text_modal" name="fr_button_text" 
                                   value="<?php echo esc_attr($buttonText); ?>">
                        </div>
                    </div>
                    
                    <!-- Confirmation Section -->
                    <div class="fr-modal-section">
                        <h3 class="fr-modal-section-title"><?php esc_html_e('After Submission', 'form-relayer'); ?></h3>
                        
                        <div class="fr-modal-field">
                            <label for="fr_confirmation_type"><?php esc_html_e('Confirmation Type', 'form-relayer'); ?></label>
                            <?php $confirmationType = get_post_meta($post->ID, '_fr_confirmation_type', true) ?: 'message'; ?>
                            <select id="fr_confirmation_type" name="fr_confirmation_type">
                                <option value="message" <?php selected($confirmationType, 'message'); ?>><?php esc_html_e('Show Message', 'form-relayer'); ?></option>
                                <option value="redirect" <?php selected($confirmationType, 'redirect'); ?>><?php esc_html_e('Redirect to URL', 'form-relayer'); ?></option>
                                <option value="page" <?php selected($confirmationType, 'page'); ?>><?php esc_html_e('Show Page', 'form-relayer'); ?></option>
                            </select>
                        </div>
                        
                        <div class="fr-modal-field fr-confirmation-message">
                            <label><?php esc_html_e('Success Message', 'form-relayer'); ?></label>
                            <?php
                            wp_editor($successMsg ?: __('Thank you! Your message has been sent.', 'form-relayer'), 'fr_success_message_modal', [
                                'textarea_name' => 'fr_success_message',
                                'textarea_rows' => 4,
                                'media_buttons' => false,
                                'teeny' => true,
                                'quicktags' => true,
                                'tinymce' => [
                                    'toolbar1' => 'bold,italic,link,unlink,bullist,undo,redo',
                                    'toolbar2' => '',
                                ],
                            ]);
                            ?>
                        </div>
                        
                        <div class="fr-modal-field fr-confirmation-redirect" style="display:none;">
                            <label for="fr_redirect_url_modal"><?php esc_html_e('Redirect URL', 'form-relayer'); ?></label>
                            <input type="url" id="fr_redirect_url_modal" name="fr_redirect_url" 
                                   value="<?php echo esc_attr($redirectUrl); ?>"
                                   placeholder="https://example.com/thank-you">
                        </div>
                        
                        <div class="fr-modal-field fr-confirmation-page" style="display:none;">
                            <label for="fr_confirmation_page"><?php esc_html_e('Select Page', 'form-relayer'); ?></label>
                            <?php $confirmationPage = get_post_meta($post->ID, '_fr_confirmation_page', true); ?>
                            <?php 
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages outputs escaped HTML
                            wp_dropdown_pages([
                                'name' => 'fr_confirmation_page',
                                'id' => 'fr_confirmation_page',
                                'selected' => absint( $confirmationPage ),
                                'show_option_none' => esc_html__('— Select —', 'form-relayer'),
                            ]); 
                            ?>
                        </div>
                    </div>
                    
                    <!-- Auto-Reply Section -->
                    <div class="fr-modal-section">
                        <h3 class="fr-modal-section-title"><?php esc_html_e('Auto-Reply Email', 'form-relayer'); ?></h3>
                        
                        <div class="fr-modal-field">
                            <label class="fr-toggle-label">
                                <input type="checkbox" id="fr_use_global_autoreply" name="fr_use_global_autoreply" value="1"
                                       <?php checked(get_post_meta($post->ID, '_fr_use_global_autoreply', true) ?: '1', '1'); ?>>
                                <span><?php esc_html_e('Use global auto-reply settings', 'form-relayer'); ?></span>
                            </label>
                            <p class="description"><?php esc_html_e('Uncheck to customize for this form.', 'form-relayer'); ?></p>
                        </div>
                        
                        <div id="fr-custom-autoreply" style="display:none;">
                            <div class="fr-modal-field">
                                <label class="fr-toggle-label">
                                    <input type="checkbox" name="fr_auto_reply_enabled" value="1"
                                           <?php checked($autoReplyEnabled, '1'); ?>>
                                    <span><?php esc_html_e('Enable auto-reply for this form', 'form-relayer'); ?></span>
                                </label>
                            </div>
                            
                            <div class="fr-modal-field">
                                <label for="fr_auto_reply_subject_modal"><?php esc_html_e('Subject', 'form-relayer'); ?></label>
                                <input type="text" id="fr_auto_reply_subject_modal" name="fr_auto_reply_subject" 
                                       value="<?php echo esc_attr($autoReplySubject); ?>"
                                       placeholder="<?php esc_attr_e('Thank you for contacting us', 'form-relayer'); ?>">
                            </div>
                            
                            <div class="fr-modal-field">
                                <label><?php esc_html_e('Message', 'form-relayer'); ?></label>
                                <?php
                                wp_editor($autoReplyMessage, 'fr_auto_reply_message_modal', [
                                    'textarea_name' => 'fr_auto_reply_message',
                                    'textarea_rows' => 6,
                                    'media_buttons' => false,
                                    'teeny' => true,
                                    'quicktags' => true,
                                    'tinymce' => [
                                        'toolbar1' => 'bold,italic,link,unlink,bullist,undo,redo',
                                        'toolbar2' => '',
                                    ],
                                ]);
                                ?>
                                <p class="description"><?php esc_html_e('Placeholders: {name}, {email}, {site_name}, {site_url}', 'form-relayer'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Design Section -->
                    <div class="fr-modal-section">
                        <h3 class="fr-modal-section-title"><?php esc_html_e('Design', 'form-relayer'); ?></h3>
                        
                        <div class="fr-modal-field-row">
                            <div class="fr-modal-field">
                                <label for="fr_primary_color_modal"><?php esc_html_e('Primary Color', 'form-relayer'); ?></label>
                                <input type="color" id="fr_primary_color_modal" name="fr_primary_color" 
                                       value="<?php echo esc_attr($primaryColor); ?>">
                            </div>
                            <div class="fr-modal-field">
                                <label for="fr_button_color_modal"><?php esc_html_e('Button Color', 'form-relayer'); ?></label>
                                <input type="color" id="fr_button_color_modal" name="fr_button_color" 
                                       value="<?php echo esc_attr($buttonColor); ?>">
                            </div>
                        </div>
                        
                        <div class="fr-modal-field">
                            <label for="fr_label_position"><?php esc_html_e('Label Position', 'form-relayer'); ?></label>
                            <?php $labelPosition = get_post_meta($post->ID, '_fr_label_position', true) ?: 'top'; ?>
                            <select id="fr_label_position" name="fr_label_position">
                                <option value="top" <?php selected($labelPosition, 'top'); ?>><?php esc_html_e('Above Field', 'form-relayer'); ?></option>
                                <option value="left" <?php selected($labelPosition, 'left'); ?>><?php esc_html_e('Left of Field', 'form-relayer'); ?></option>
                                <option value="floating" <?php selected($labelPosition, 'floating'); ?>><?php esc_html_e('Floating (Inside)', 'form-relayer'); ?></option>
                                <option value="hidden" <?php selected($labelPosition, 'hidden'); ?>><?php esc_html_e('Hidden (Placeholder Only)', 'form-relayer'); ?></option>
                            </select>
                        </div>
                    <!-- Removed extra closing div here -->
                    


                    </div> <!-- End Design Section -->

                    
                    <?php do_action('formrelayer_builder_settings_sections', $post); ?>
                    
                </div>
                <div class="fr-settings-modal-footer">
                    <button type="button" class="button fr-settings-modal-close"><?php esc_html_e('Cancel', 'form-relayer'); ?></button>
                    <button type="button" id="fr-save-settings" class="button button-primary" style="margin-left:12px;"><?php esc_html_e('Apply Settings', 'form-relayer'); ?></button>
                </div>
            </div>
        </div>

        <style>
            /* Form Settings Modal */
            .fr-settings-modal {
                position: fixed;
                inset: 0;
                z-index: 999999;
            }
            .fr-settings-modal-overlay {
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(2px);
            }
            .fr-settings-modal-panel {
                position: absolute;
                right: 0;
                top: 0;
                bottom: 0;
                width: 720px;
                max-width: 100%;
                background: #fff;
                display: flex;
                flex-direction: column;
                box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
                animation: frSlideIn 0.25s ease-out;
            }
            @keyframes frSlideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }
            .fr-settings-modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 24px;
                background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                color: #fff;
            }
            .fr-settings-modal-header h2 {
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 0;
                font-size: 18px;
                font-weight: 600;
            }
            .fr-settings-modal-close {
                background: rgba(255,255,255,0.2);
                border: none;
                color: #fff;
                width: 32px;
                height: 32px;
                border-radius: 6px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .fr-settings-modal-close:hover {
                background: rgba(255,255,255,0.3);
            }
            .fr-settings-modal-body {
                flex: 1;
                overflow-y: auto;
                padding: 24px;
            }
            .fr-modal-section {
                margin-bottom: 28px;
                padding-bottom: 20px;
                border-bottom: 1px solid #e5e7eb;
            }
            .fr-modal-section:last-child {
                border-bottom: none;
            }
            .fr-modal-section-title {
                font-size: 14px;
                font-weight: 600;
                color: #374151;
                margin: 0 0 16px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .fr-modal-field {
                margin-bottom: 16px;
            }
            .fr-modal-field label {
                display: block;
                font-weight: 500;
                margin-bottom: 6px;
                color: #1f2937;
            }
            .fr-modal-field input[type="text"],
            .fr-modal-field input[type="email"],
            .fr-modal-field input[type="url"],
            .fr-modal-field select {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 14px;
            }
            .fr-modal-field input:focus,
            .fr-modal-field select:focus {
                border-color: #6366f1;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
                outline: none;
            }
            .fr-modal-field input[type="color"] {
                width: 60px;
                height: 36px;
                padding: 2px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                cursor: pointer;
            }
            .fr-modal-field .description {
                font-size: 12px;
                color: #6b7280;
                margin-top: 6px;
            }
            .fr-modal-field-row {
                display: flex;
                gap: 16px;
            }
            .fr-modal-field-row .fr-modal-field {
                flex: 1;
            }
            .fr-toggle-label {
                display: flex !important;
                align-items: center;
                gap: 10px;
                cursor: pointer;
            }
            .fr-toggle-label input[type="checkbox"] {
                width: 18px;
                height: 18px;
            }
            .fr-settings-modal-footer {
                display: flex;
                justify-content: flex-end;
                gap: 12px;
                padding: 16px 24px;
                background: #f9fafb;
                border-top: 1px solid #e5e7eb;
            }
            .fr-settings-modal .wp-editor-container {
                border-radius: 6px;
                overflow: hidden;
            }
        </style>

        <!-- Preview Modal -->
        <div id="fr-preview-modal" class="fr-preview-modal" style="display:none;">
            <div class="fr-preview-overlay" onclick="jQuery('#fr-preview-modal').fadeOut(200);"></div>
            <div class="fr-preview-content">
                <div class="fr-preview-header">
                    <div class="fr-preview-title">
                        <span class="dashicons dashicons-visibility" style="color:#6366f1;margin-right:8px;"></span>
                        <h3><?php esc_html_e('Form Preview', 'form-relayer'); ?></h3>
                    </div>
                    <button type="button" class="fr-preview-close" onclick="jQuery('#fr-preview-modal').fadeOut(200);">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="fr-preview-body">
                    <iframe id="fr-preview-frame" frameborder="0"></iframe>
                </div>
            </div>
        </div>

        <style>
            .fr-preview-modal {
                position: fixed;
                inset: 0;
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .fr-preview-overlay {
                position: absolute;
                inset: 0;
                background: rgba(15, 23, 42, 0.7);
                backdrop-filter: blur(4px);
                cursor: pointer;
            }
            .fr-preview-content {
                position: relative;
                background: #fff;
                border-radius: 16px;
                width: calc(100% - 40px);
                max-width: 720px;
                height: auto;
                max-height: calc(100vh - 40px);
                display: flex;
                flex-direction: column;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
                overflow: hidden;
            }
            .fr-preview-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 24px;
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border-bottom: 1px solid #e2e8f0;
                flex-shrink: 0;
            }
            .fr-preview-title {
                display: flex;
                align-items: center;
            }
            .fr-preview-header h3 {
                margin: 0;
                font-size: 15px;
                font-weight: 600;
                color: #1e293b;
            }
            .fr-preview-close {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                cursor: pointer;
                padding: 6px;
                color: #64748b;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .fr-preview-close:hover {
                color: #ef4444;
                border-color: #fecaca;
                background: #fef2f2;
            }
            .fr-preview-close .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
            }
            .fr-preview-body {
                flex: 1;
                overflow: hidden;
                background: #f8fafc;
            }
            #fr-preview-frame {
                width: 100%;
                height: 500px;
                border: none;
                display: block;
            }
            @media (max-height: 700px) {
                #fr-preview-frame {
                    height: 400px;
                }
            }
            @media (max-height: 550px) {
                #fr-preview-frame {
                    height: 300px;
                }
            }
        </style>
        <?php
    }
}
