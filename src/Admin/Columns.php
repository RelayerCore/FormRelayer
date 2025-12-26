<?php

declare(strict_types=1);

namespace FormRelayer\Admin;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;

/**
 * Admin Columns for Forms and Submissions
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Columns
{
    private static ?Columns $instance = null;

    private function __construct()
    {
        // Form columns
        add_filter('manage_' . PostType::POST_TYPE . '_posts_columns', [$this, 'formColumns']);
        add_action('manage_' . PostType::POST_TYPE . '_posts_custom_column', [$this, 'formColumnContent'], 10, 2);
        
        // Submission columns
        add_filter('manage_' . PostType::SUBMISSION_POST_TYPE . '_posts_columns', [$this, 'submissionColumns']);
        add_action('manage_' . PostType::SUBMISSION_POST_TYPE . '_posts_custom_column', [$this, 'submissionColumnContent'], 10, 2);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Define form columns
     */
    public function formColumns(array $columns): array
    {
        $new = [];
        $new['cb'] = $columns['cb'];
        $new['title'] = __('Form Name', 'form-relayer');
        $new['shortcode'] = __('Shortcode', 'form-relayer');
        $new['submissions'] = __('Submissions', 'form-relayer');
        $new['fields'] = __('Fields', 'form-relayer');
        $new['date'] = $columns['date'];
        
        return $new;
    }

    /**
     * Form column content
     */
    public function formColumnContent(string $column, int $postId): void
    {
        match ($column) {
            'shortcode' => $this->renderShortcodeColumn($postId),
            'submissions' => $this->renderSubmissionsColumn($postId),
            'fields' => $this->renderFieldsColumn($postId),
            default => null,
        };
    }

    /**
     * Define submission columns
     */
    public function submissionColumns(array $columns): array
    {
        $new = [];
        $new['cb'] = $columns['cb'];
        $new['title'] = __('Submission', 'form-relayer');
        $new['form'] = __('Form', 'form-relayer');
        $new['email'] = __('Email', 'form-relayer');
        $new['status'] = __('Status', 'form-relayer');
        $new['date'] = $columns['date'];
        
        return $new;
    }

    /**
     * Submission column content
     */
    public function submissionColumnContent(string $column, int $postId): void
    {
        match ($column) {
            'form' => $this->renderFormColumn($postId),
            'email' => $this->renderEmailColumn($postId),
            'status' => $this->renderStatusColumn($postId),
            default => null,
        };
    }

    /**
     * Render shortcode column
     */
    private function renderShortcodeColumn(int $postId): void
    {
        $shortcode = sprintf('[form_relayer id="%d"]', $postId);
        ?>
        <div class="fr-shortcode-cell" style="display:flex;align-items:center;gap:6px;">
            <code style="background:#f0f0f1;padding:4px 8px;border-radius:4px;font-size:12px;user-select:all;"><?php echo esc_html($shortcode); ?></code>
            <button type="button" class="button button-small fr-copy-shortcode-btn" data-shortcode="<?php echo esc_attr($shortcode); ?>" title="<?php esc_attr_e('Copy to clipboard', 'form-relayer'); ?>">
                <span class="dashicons dashicons-admin-page" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span>
            </button>
        </div>
        <?php
    }

    /**
     * Render submissions count column
     */
    private function renderSubmissionsColumn(int $postId): void
    {
        $count = $this->getSubmissionCount($postId);
        $url = admin_url('edit.php?post_type=' . PostType::SUBMISSION_POST_TYPE . '&form_id=' . $postId);
        
        printf('<a href="%s">%d</a>', esc_url($url), intval( $count ));
    }

    /**
     * Render fields count column
     */
    private function renderFieldsColumn(int $postId): void
    {
        // Try new meta key first
        $fields = get_post_meta($postId, '_fr_fields', true);
        
        // Try legacy meta key if empty
        if (empty($fields)) {
            $fields = get_post_meta($postId, '_form_fields', true);
        }
        
        // Handle JSON string format
        if (is_string($fields) && !empty($fields)) {
            $fields = json_decode($fields, true);
        }
        
        // Ensure we have an array and count
        $count = is_array($fields) ? count($fields) : 0;
        
        echo esc_html($count);
    }

    /**
     * Render form name column for submissions
     */
    private function renderFormColumn(int $postId): void
    {
        // Check both new and legacy meta keys
        $formId = (int) get_post_meta($postId, '_fr_form_id', true);
        
        if (!$formId) {
            // Try legacy meta key
            $formId = (int) get_post_meta($postId, '_parent_form_id', true);
        }
        
        if (!$formId) {
            echo '—';
            return;
        }

        $form = get_post($formId);
        
        if (!$form) {
            echo '—';
            return;
        }

        printf(
            '<a href="%s">%s</a>',
            esc_url(get_edit_post_link($formId) ?? '#'),
            esc_html($form->post_title)
        );
    }

    /**
     * Render email column for submissions
     */
    private function renderEmailColumn(int $postId): void
    {
        // Try new meta key first
        $data = get_post_meta($postId, '_fr_submission_data', true);
        
        // Try legacy meta key if new one is empty
        if (!is_array($data) || empty($data)) {
            $data = get_post_meta($postId, '_submission_data', true);
        }
        
        if (!is_array($data)) {
            echo '—';
            return;
        }

        // Find email in submission data
        foreach ($data as $key => $value) {
            if (is_string($value) && is_email($value)) {
                printf('<a href="mailto:%s">%s</a>', esc_attr($value), esc_html($value));
                return;
            }
        }

        echo '—';
    }

    /**
     * Render status column for submissions
     */
    private function renderStatusColumn(int $postId): void
    {
        $read = get_post_meta($postId, '_fr_read', true);
        
        if ($read) {
            echo '<span style="color:#666;">✓ ' . esc_html__('Read', 'form-relayer') . '</span>';
        } else {
            echo '<span style="color:#0073aa;font-weight:600;">● ' . esc_html__('New', 'form-relayer') . '</span>';
        }
    }

    /**
     * Get submission count for a form
     */
    private function getSubmissionCount(int $formId): int
    {
        global $wpdb;
        
        // Check both legacy (_parent_form_id) and new (_fr_form_id) meta keys
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND pm.meta_key IN ('_fr_form_id', '_parent_form_id')
             AND pm.meta_value = %d",
            PostType::SUBMISSION_POST_TYPE,
            $formId
        ));
    }
}
