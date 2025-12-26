<?php

declare(strict_types=1);

namespace FormRelayer\Builder;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;
use FormRelayer\Security\Nonce;

/**
 * Form Preview
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class FormPreview
{
    private static ?FormPreview $instance = null;

    private function __construct()
    {
        // Match legacy action name used by builder.js
        add_action('wp_ajax_fr_form_preview', [$this, 'handlePreview']);
        add_action('template_redirect', [$this, 'handlePreviewPage']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Handle AJAX preview request
     */
    public function handlePreview(): void
    {
        // Check nonce with legacy key used by builder.js
        check_ajax_referer('fr_form_preview', 'nonce');

        $formId = absint($_POST['post_id'] ?? 0);
        
        // Fields come as JSON-encoded array from JS
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded later
        $fieldsRaw = isset($_POST['fields']) ? wp_unslash($_POST['fields']) : [];
        
        if (is_string($fieldsRaw)) {
            $fields = json_decode(wp_unslash($fieldsRaw), true);
        } else {
            // Fields sent as form data array
            $fields = is_array($fieldsRaw) ? $fieldsRaw : [];
        }

        if (!$formId) {
            wp_send_json_error(['message' => __('Invalid form ID', 'form-relayer')]);
        }

        $html = $this->renderPreview($formId, $fields);

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Handle preview page (iframe)
     */
    public function handlePreviewPage(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page load check
        if (!isset($_GET['fr_preview']) || !isset($_GET['form_id'])) {
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_die( esc_html__('Unauthorized', 'form-relayer') );
        }
        
         // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page load
        $formId = absint($_GET['form_id']);
        $form = get_post($formId);

        if (!$form || $form->post_type !== PostType::POST_TYPE) {
            wp_die( esc_html__('Form not found', 'form-relayer') );
        }

        // Get form data
        $fieldsJson = get_post_meta($formId, '_fr_fields', true);
        $fields = is_string($fieldsJson) ? json_decode($fieldsJson, true) : [];
        
        if (!is_array($fields)) {
            $fields = [];
        }

        $this->renderPreviewPage($formId, $form, $fields);
        exit;
    }

    /**
     * Render form preview HTML
     */
    private function renderPreview(int $formId, array $fields): string
    {
        $settings = [
            'button_text' => get_post_meta($formId, '_fr_button_text', true) ?: __('Submit', 'form-relayer'),
            'primary_color' => get_post_meta($formId, '_fr_primary_color', true) ?: '#6366f1',
            'button_color' => get_post_meta($formId, '_fr_button_color', true) ?: '#6366f1',
        ];

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Isolated preview page requires direct CSS ?>
            <link rel="stylesheet" href="<?php echo esc_url( Plugin::getInstance()->getPluginUrl() . 'assets/css/frontend.css?ver=' . Plugin::VERSION ); ?>">
            <style>
                html, body { 
                    background: transparent;
                    min-height: 100%;
                    margin: 0;
                    padding: 0;
                }
                .fr-preview-wrapper {
                    padding: 20px;
                    max-width: 900px;
                    margin: 0 auto;
                }
                /* Override form wrapper for preview context if needed */
                .fr-form-wrapper {
                    box-shadow: none;
                    border: 1px solid #e2e8f0;
                }
            </style>
        </head>
        <body>
            <div class="fr-preview-wrapper">
                <?php if (empty($fields)): ?>
                    <p style="color:#94a3b8;text-align:center;padding:40px 0;font-family:-apple-system,system-ui,sans-serif;">
                        <?php esc_html_e('Add fields to see preview', 'form-relayer'); ?>
                    </p>
                <?php else: ?>
                    <?php 
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buildFormHtml returns safe HTML
                    echo \FormRelayer\Forms\Shortcode::getInstance()->buildFormHtml($formId, get_post($formId), $fields, $settings, []); 
                    ?>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean() ?: '';
    }




    /**
     * Render full preview page
     */
    private function renderPreviewPage(int $formId, \WP_Post $form, array $fields): void
    {
        $primaryColor = get_post_meta($formId, '_fr_primary_color', true) ?: '#6366f1';
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($form->post_title); ?> - <?php esc_html_e('Preview', 'form-relayer'); ?></title>
            <style>
                * { box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f5f5f5;
                    margin: 0;
                    padding: 40px 20px;
                }
                .fr-preview-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: #fff;
                    border-radius: 12px;
                    padding: 32px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                }
                .fr-preview-header {
                    text-align: center;
                    margin-bottom: 24px;
                    padding-bottom: 16px;
                    border-bottom: 1px solid #e5e7eb;
                }
                .fr-preview-badge {
                    display: inline-block;
                    background: <?php echo esc_attr($primaryColor); ?>;
                    color: #fff;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: 600;
                    margin-bottom: 8px;
                }
            </style>
        </head>
        <body>
            <div class="fr-preview-container">
                <div class="fr-preview-header">
                    <span class="fr-preview-badge"><?php esc_html_e('Preview Mode', 'form-relayer'); ?></span>
                    <h2 style="margin:8px 0 0;color:#1f2937;"><?php echo esc_html($form->post_title); ?></h2>
                </div>
                <?php 
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderPreview returns safe HTML
                echo $this->renderPreview($formId, $fields); 
                ?>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Get preview URL
     */
    public static function getPreviewUrl(int $formId): string
    {
        return add_query_arg([
            'fr_preview' => 1,
            'form_id' => $formId,
        ], home_url('/'));
    }
}
