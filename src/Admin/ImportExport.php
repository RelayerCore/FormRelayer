<?php

declare(strict_types=1);

namespace FormRelayer\Admin;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;
use FormRelayer\Security\Nonce;

/**
 * Form Import/Export Handler
 *
 * Allows users to export forms as JSON and import them back.
 *
 * @package FormRelayer
 * @since 2.1.0
 */
final class ImportExport
{
    private static ?ImportExport $instance = null;

    private function __construct()
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('wp_ajax_fr_export_form', [$this, 'handleExport']);
        add_action('wp_ajax_fr_export_all_forms', [$this, 'handleExportAll']);
        add_action('wp_ajax_fr_import_form', [$this, 'handleImport']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add import/export submenu under FormRelayer
     */
    public function addMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=fr_form',
            __('Import / Export', 'form-relayer'),
            __('Import / Export', 'form-relayer'),
            'manage_options',
            'fr-import-export',
            [$this, 'renderPage']
        );
    }

    /**
     * Enqueue assets for import/export page
     */
    public function enqueueAssets(string $hook): void
    {
        // WordPress generates hook as: {post_type}_page_{menu_slug}
        // For submenu under edit.php?post_type=fr_form, the hook is: fr_form_page_fr-import-export
        if ($hook !== 'fr_form_page_fr-import-export' && strpos($hook, 'fr-import-export') === false) {
            return;
        }

        wp_enqueue_style(
            'fr-import-export',
            Plugin::url('assets/css/import-export.css'),
            [],
            Plugin::VERSION
        );

        wp_enqueue_script(
            'fr-import-export',
            Plugin::url('assets/js/import-export.js'),
            ['jquery'],
            Plugin::VERSION,
            true
        );

        wp_localize_script('fr-import-export', 'frImportExport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fr_import_export'),
            'i18n' => [
                'selectFile' => __('Please select a file to import.', 'form-relayer'),
                'invalidFile' => __('Invalid file type. Please upload a .json file.', 'form-relayer'),
                'importing' => __('Importing...', 'form-relayer'),
                'exporting' => __('Exporting...', 'form-relayer'),
                'success' => __('Import successful!', 'form-relayer'),
                'error' => __('An error occurred. Please try again.', 'form-relayer'),
                'confirmImport' => __('This will create a new form. Continue?', 'form-relayer'),
            ],
        ]);
    }

    /**
     * Render the import/export page
     */
    public function renderPage(): void
    {
        $forms = get_posts([
            'post_type' => PostType::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <div class="wrap fr-import-export-wrap">
            <h1>
                <span class="dashicons dashicons-download" style="font-size: 1.3em; margin-right: 8px;"></span>
                <?php esc_html_e('Import / Export Forms', 'form-relayer'); ?>
            </h1>

            <div class="fr-ie-container">
                <!-- Export Section -->
                <div class="fr-ie-section fr-ie-export">
                    <div class="fr-ie-header">
                        <span class="dashicons dashicons-upload"></span>
                        <h2><?php esc_html_e('Export Forms', 'form-relayer'); ?></h2>
                    </div>
                    <div class="fr-ie-body">
                        <p class="fr-ie-description">
                            <?php esc_html_e('Export your forms as JSON files. You can use these files to backup your forms or transfer them to another site.', 'form-relayer'); ?>
                        </p>

                        <?php if (empty($forms)) : ?>
                            <div class="fr-ie-notice fr-ie-notice-warning">
                                <span class="dashicons dashicons-warning"></span>
                                <?php esc_html_e('No forms found. Create a form first before exporting.', 'form-relayer'); ?>
                            </div>
                        <?php else : ?>
                            <div class="fr-ie-form-group">
                                <label for="fr-export-form-select"><?php esc_html_e('Select a form to export:', 'form-relayer'); ?></label>
                                <select id="fr-export-form-select" class="fr-ie-select">
                                    <option value=""><?php esc_html_e('-- Select a form --', 'form-relayer'); ?></option>
                                    <?php foreach ($forms as $form) : ?>
                                        <option value="<?php echo esc_attr($form->ID); ?>">
                                            <?php echo esc_html($form->post_title ?: __('(Untitled)', 'form-relayer')); ?>
                                            (ID: <?php echo esc_html($form->ID); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="fr-ie-actions">
                                <button type="button" id="fr-export-single" class="button button-primary" disabled>
                                    <span class="dashicons dashicons-download"></span>
                                    <?php esc_html_e('Export Selected Form', 'form-relayer'); ?>
                                </button>
                                <button type="button" id="fr-export-all" class="button button-secondary">
                                    <span class="dashicons dashicons-database-export"></span>
                                    <?php esc_html_e('Export All Forms', 'form-relayer'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Import Section -->
                <div class="fr-ie-section fr-ie-import">
                    <div class="fr-ie-header">
                        <span class="dashicons dashicons-download"></span>
                        <h2><?php esc_html_e('Import Forms', 'form-relayer'); ?></h2>
                    </div>
                    <div class="fr-ie-body">
                        <p class="fr-ie-description">
                            <?php esc_html_e('Import forms from a JSON file. This will create new forms without affecting existing ones.', 'form-relayer'); ?>
                        </p>

                        <div class="fr-ie-upload-area" id="fr-import-dropzone">
                            <div class="fr-ie-upload-inner">
                                <span class="dashicons dashicons-cloud-upload"></span>
                                <p><?php esc_html_e('Drag and drop your JSON file here', 'form-relayer'); ?></p>
                                <p class="fr-ie-upload-or"><?php esc_html_e('or', 'form-relayer'); ?></p>
                                <label for="fr-import-file" class="button button-secondary">
                                    <?php esc_html_e('Choose File', 'form-relayer'); ?>
                                </label>
                                <input type="file" id="fr-import-file" accept=".json" style="display: none;">
                            </div>
                            <div class="fr-ie-file-info" style="display: none;">
                                <span class="dashicons dashicons-media-code"></span>
                                <span class="fr-ie-filename"></span>
                                <button type="button" class="fr-ie-remove-file" title="<?php esc_attr_e('Remove', 'form-relayer'); ?>">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                        </div>

                        <div class="fr-ie-actions">
                            <button type="button" id="fr-import-btn" class="button button-primary" disabled>
                                <span class="dashicons dashicons-upload"></span>
                                <?php esc_html_e('Import Form(s)', 'form-relayer'); ?>
                            </button>
                        </div>

                        <div id="fr-import-results" class="fr-ie-results" style="display: none;"></div>
                    </div>
                </div>
            </div>

            <!-- Help Section -->
            <div class="fr-ie-help">
                <h3><?php esc_html_e('Help & Tips', 'form-relayer'); ?></h3>
                <ul>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Exported forms include all fields, settings, and styling options.', 'form-relayer'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Imported forms are created as drafts - publish them when ready.', 'form-relayer'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e('Form submissions are NOT included in exports for privacy.', 'form-relayer'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Always backup your site before importing forms.', 'form-relayer'); ?>
                    </li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Export a single form as JSON
     */
    public function handleExport(): void
    {
        check_ajax_referer('fr_import_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'form-relayer')]);
        }

        $formId = absint($_POST['form_id'] ?? 0);

        if (!$formId) {
            wp_send_json_error(['message' => __('Invalid form ID', 'form-relayer')]);
        }

        $form = get_post($formId);

        if (!$form || $form->post_type !== PostType::FORM_POST_TYPE) {
            wp_send_json_error(['message' => __('Form not found', 'form-relayer')]);
        }

        $exportData = $this->prepareFormExport($form);

        wp_send_json_success([
            'filename' => sanitize_file_name($form->post_title ?: 'form') . '-' . gmdate('Y-m-d') . '.json',
            'data' => $exportData,
        ]);
    }

    /**
     * Export all forms as JSON
     */
    public function handleExportAll(): void
    {
        check_ajax_referer('fr_import_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'form-relayer')]);
        }

        $forms = get_posts([
            'post_type' => PostType::FORM_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
        ]);

        if (empty($forms)) {
            wp_send_json_error(['message' => __('No forms found', 'form-relayer')]);
        }

        $exportData = [
            'version' => Plugin::VERSION,
            'exported_at' => current_time('c'),
            'site_url' => home_url(),
            'forms' => [],
        ];

        foreach ($forms as $form) {
            $exportData['forms'][] = $this->prepareFormExport($form);
        }

        wp_send_json_success([
            'filename' => 'formrelayer-all-forms-' . gmdate('Y-m-d') . '.json',
            'data' => $exportData,
        ]);
    }

    /**
     * Prepare form data for export
     */
    private function prepareFormExport(\WP_Post $form): array
    {
        // Get form fields
        $fields = get_post_meta($form->ID, '_fr_fields', true);
        
        // Handle both legacy array and JSON string format
        if (is_string($fields) && !empty($fields)) {
            $fields = json_decode($fields, true) ?: [];
        } elseif (!is_array($fields)) {
            $fields = [];
        }

        // Get all form settings
        $settings = [
            'success_message' => get_post_meta($form->ID, '_fr_success_message', true),
            'redirect_url' => get_post_meta($form->ID, '_fr_redirect_url', true),
            'submit_button_text' => get_post_meta($form->ID, '_fr_submit_button_text', true),
            'email_to' => get_post_meta($form->ID, '_fr_email_to', true),
            'email_subject' => get_post_meta($form->ID, '_fr_email_subject', true),
            'email_from_name' => get_post_meta($form->ID, '_fr_email_from_name', true),
            'email_from_email' => get_post_meta($form->ID, '_fr_email_from_email', true),
            'enable_honeypot' => get_post_meta($form->ID, '_fr_enable_honeypot', true),
            'enable_recaptcha' => get_post_meta($form->ID, '_fr_enable_recaptcha', true),
            'form_class' => get_post_meta($form->ID, '_fr_form_class', true),
            'label_position' => get_post_meta($form->ID, '_fr_label_position', true),
        ];

        return [
            'version' => Plugin::VERSION,
            'exported_at' => current_time('c'),
            'form' => [
                'title' => $form->post_title,
                'status' => $form->post_status,
                'fields' => $fields,
                'settings' => array_filter($settings),
            ],
        ];
    }

    /**
     * Handle form import
     */
    public function handleImport(): void
    {
        check_ajax_referer('fr_import_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'form-relayer')]);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON data decoded below
        $jsonData = stripslashes(wp_unslash($_POST['json_data'] ?? ''));

        if (empty($jsonData)) {
            wp_send_json_error(['message' => __('No data provided', 'form-relayer')]);
        }

        $data = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('Invalid JSON format', 'form-relayer')]);
        }

        // Check if this is a multi-form export or single form
        if (isset($data['forms']) && is_array($data['forms'])) {
            // Multi-form import
            $imported = [];
            $errors = [];

            foreach ($data['forms'] as $formData) {
                $result = $this->importSingleForm($formData);
                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } else {
                    $imported[] = $result;
                }
            }

            if (empty($imported)) {
                wp_send_json_error([
                    'message' => __('No forms could be imported.', 'form-relayer'),
                    'errors' => $errors,
                ]);
            }

            wp_send_json_success([
                'message' => sprintf(
                    // translators: %d is the number of forms imported
                    _n(
                        '%d form imported successfully.',
                        '%d forms imported successfully.',
                        count($imported),
                        'form-relayer'
                    ),
                    count($imported)
                ),
                'imported' => $imported,
                'errors' => $errors,
            ]);
        } else {
            // Single form import
            $result = $this->importSingleForm($data);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success([
                'message' => __('Form imported successfully!', 'form-relayer'),
                'imported' => [$result],
            ]);
        }
    }

    /**
     * Import a single form from data
     */
    private function importSingleForm(array $data): array|\WP_Error
    {
        if (!isset($data['form'])) {
            return new \WP_Error('invalid_format', __('Invalid form data format', 'form-relayer'));
        }

        $formData = $data['form'];
        $title = sanitize_text_field($formData['title'] ?? __('Imported Form', 'form-relayer'));

        // Create the form post
        $postId = wp_insert_post([
            'post_type' => PostType::FORM_POST_TYPE,
            'post_title' => $title . ' ' . __('(Imported)', 'form-relayer'),
            'post_status' => 'draft', // Always import as draft for safety
        ]);

        if (is_wp_error($postId)) {
            return $postId;
        }

        // Save fields
        if (!empty($formData['fields'])) {
            $fields = $formData['fields'];
            // Generate new IDs for imported fields to avoid conflicts
            foreach ($fields as &$field) {
                $field['id'] = 'field_' . wp_generate_password(8, false);
            }
            update_post_meta($postId, '_fr_fields', wp_json_encode($fields));
        }

        // Save settings
        if (!empty($formData['settings'])) {
            $allowedSettings = [
                'success_message', 'redirect_url', 'submit_button_text',
                'email_to', 'email_subject', 'email_from_name', 'email_from_email',
                'enable_honeypot', 'enable_recaptcha', 'form_class', 'label_position',
            ];

            foreach ($formData['settings'] as $key => $value) {
                if (in_array($key, $allowedSettings, true)) {
                    update_post_meta($postId, '_fr_' . $key, sanitize_text_field($value));
                }
            }
        }

        return [
            'id' => $postId,
            'title' => $title,
            'edit_url' => get_edit_post_link($postId, 'raw'),
        ];
    }
}
