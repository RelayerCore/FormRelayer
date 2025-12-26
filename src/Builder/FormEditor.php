<?php

declare(strict_types=1);

namespace FormRelayer\Builder;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;
use FormRelayer\Security\Nonce;

/**
 * Form Editor
 *
 * Handles the form editing meta boxes (legacy)
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class FormEditor
{
    private static ?FormEditor $instance = null;

    private function __construct()
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_' . PostType::POST_TYPE, [$this, 'saveForm'], 10, 2);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add meta boxes
     */
    public function addMetaBoxes(): void
    {
        // The modern builder replaces these, but keep for fallback
        add_meta_box(
            'fr_form_shortcode',
            __('Shortcode', 'form-relayer'),
            [$this, 'renderShortcodeBox'],
            PostType::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render shortcode meta box
     */
    public function renderShortcodeBox(\WP_Post $post): void
    {
        $shortcode = sprintf('[formrelayer id="%d"]', $post->ID);
        ?>
        <div style="margin:8px 0;">
            <input type="text" 
                   value="<?php echo esc_attr($shortcode); ?>" 
                   readonly 
                   onclick="this.select();" 
                   style="width:100%;font-family:monospace;background:#f5f5f5;cursor:pointer;">
            <p class="description" style="margin-top:8px;">
                <?php esc_html_e('Copy and paste this shortcode into any page or post.', 'form-relayer'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save form data
     */
    public function saveForm(int $postId, \WP_Post $post): void
    {
        // Check nonce
        if (!isset($_POST['fr_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fr_nonce'])), 'fr_save_form')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Save fields JSON
        if (isset($_POST['fr_fields_json'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Processed from JSON
            $fieldsJson = wp_unslash($_POST['fr_fields_json']);
            update_post_meta($postId, '_fr_fields', $fieldsJson);
        }

        // Save form settings
        $settings = [
            'fr_email' => 'sanitize_email',
            'fr_email_template' => 'sanitize_text_field',
            'fr_custom_email_html' => 'trim', // Allow full HTML for templates
            'fr_button_text' => 'sanitize_text_field',
            'fr_confirmation_type' => 'sanitize_text_field',
            'fr_success_message' => 'sanitize_textarea_field',
            'fr_redirect_url' => 'esc_url_raw',
            'fr_primary_color' => 'sanitize_hex_color',
            'fr_button_color' => 'sanitize_hex_color',
            'fr_auto_reply_enabled' => 'absint',
            'fr_auto_reply_subject' => 'sanitize_text_field',
            'fr_auto_reply_message' => 'sanitize_textarea_field',
        ];

        foreach ($settings as $key => $sanitizer) {
            if (isset($_POST[$key])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized dynamically via $sanitizer callback
                $value = call_user_func($sanitizer, wp_unslash($_POST[$key]));
                update_post_meta($postId, '_' . $key, $value);
            }
        }

        do_action('formrelayer_form_saved', $postId, $post);
    }
}
