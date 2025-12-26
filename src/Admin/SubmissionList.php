<?php

declare(strict_types=1);

namespace FormRelayer\Admin;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;

/**
 * Submission List View
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class SubmissionList
{
    private static ?SubmissionList $instance = null;

    private function __construct()
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_filter('parse_query', [$this, 'filterByForm']);
        add_action('restrict_manage_posts', [$this, 'addFormFilter']);
        add_action('admin_action_fr_mark_read', [$this, 'markAsRead']);
        add_action('admin_action_fr_mark_unread', [$this, 'markAsUnread']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add meta boxes for submission view
     */
    public function addMetaBoxes(): void
    {
        add_meta_box(
            'fr_submission_data',
            __('Submission Data', 'form-relayer'),
            [$this, 'renderSubmissionData'],
            PostType::SUBMISSION_POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'fr_submission_meta',
            __('Submission Info', 'form-relayer'),
            [$this, 'renderSubmissionMeta'],
            PostType::SUBMISSION_POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render submission data meta box
     */
    public function renderSubmissionData(\WP_Post $post): void
    {
        // Try new meta key first, fall back to legacy
        $data = get_post_meta($post->ID, '_fr_submission_data', true);
        if (!is_array($data) || empty($data)) {
            $data = get_post_meta($post->ID, '_submission_data', true);
        }
        
        $formId = (int) get_post_meta($post->ID, '_fr_form_id', true);
        if (!$formId) {
            $formId = (int) get_post_meta($post->ID, '_parent_form_id', true);
        }
        
        // Mark as read
        if (!get_post_meta($post->ID, '_fr_read', true)) {
            update_post_meta($post->ID, '_fr_read', 1);
        }

        if (!is_array($data) || empty($data)) {
            echo '<p>' . esc_html__('No data available.', 'form-relayer') . '</p>';
            return;
        }

        // Get field labels from form
        $fieldLabels = $this->getFieldLabels($formId);

        echo '<table class="widefat striped" style="margin-top:12px;">';
        echo '<thead><tr><th style="width:30%;">' . esc_html__('Field', 'form-relayer') . '</th><th>' . esc_html__('Value', 'form-relayer') . '</th></tr></thead>';
        echo '<tbody>';

        foreach ($data as $key => $value) {
            $label = $fieldLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
            
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            echo '<tr>';
            echo '<td><strong>' . esc_html($label) . '</strong></td>';
            echo '<td>' . nl2br(esc_html($value)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Render submission meta box
     */
    public function renderSubmissionMeta(\WP_Post $post): void
    {
        $formId = (int) get_post_meta($post->ID, '_fr_form_id', true);
        $submittedAt = get_post_meta($post->ID, '_fr_submitted_at', true);
        $ipAddress = get_post_meta($post->ID, '_fr_ip_address', true);
        $userAgent = get_post_meta($post->ID, '_fr_user_agent', true);

        echo '<ul style="margin:0;">';

        if ($formId) {
            $form = get_post($formId);
            echo '<li><strong>' . esc_html__('Form:', 'form-relayer') . '</strong> ';
            if ($form) {
                echo '<a href="' . esc_url(get_edit_post_link($formId) ?? '#') . '">' . esc_html($form->post_title) . '</a>';
            } else {
                echo esc_html__('(Deleted)', 'form-relayer');
            }
            echo '</li>';
        }

        if ($submittedAt) {
            echo '<li><strong>' . esc_html__('Submitted:', 'form-relayer') . '</strong> ' . esc_html($submittedAt) . '</li>';
        }

        if ($ipAddress) {
            echo '<li><strong>' . esc_html__('IP Address:', 'form-relayer') . '</strong> ' . esc_html($ipAddress) . '</li>';
        }

        if ($userAgent) {
            echo '<li><strong>' . esc_html__('User Agent:', 'form-relayer') . '</strong> <small>' . esc_html(mb_substr($userAgent, 0, 50)) . '...</small></li>';
        }

        echo '</ul>';
    }

    /**
     * Filter submissions by form
     */
    public function filterByForm(\WP_Query $query): void
    {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'edit.php') {
            return;
        }

        if (!isset($query->query_vars['post_type']) || $query->query_vars['post_type'] !== PostType::SUBMISSION_POST_TYPE) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin filter
        if (!empty($_GET['form_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin filter
            $formId = absint(wp_unslash($_GET['form_id']));
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Filtering submissions by parent form requires meta query
            $query->query_vars['meta_key'] = '_fr_form_id';
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Filtering submissions by parent form requires meta query
            $query->query_vars['meta_value'] = $formId;
        }
    }

    /**
     * Add form filter dropdown
     */
    public function addFormFilter(): void
    {
        global $typenow;

        if ($typenow !== PostType::SUBMISSION_POST_TYPE) {
            return;
        }

        $forms = get_posts([
            'post_type' => PostType::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        if (empty($forms)) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin filter
        $selectedForm = absint(wp_unslash($_GET['form_id'] ?? 0));

        echo '<select name="form_id">';
        echo '<option value="">' . esc_html__('All Forms', 'form-relayer') . '</option>';

        foreach ($forms as $form) {
            printf(
                '<option value="%d" %s>%s</option>',
                intval( $form->ID ),
                esc_attr( selected($selectedForm, $form->ID, false) ),
                esc_html($form->post_title)
            );
        }

        echo '</select>';
    }

    /**
     * Get field labels from form
     */
    private function getFieldLabels(int $formId): array
    {
        if (!$formId) {
            return [];
        }

        $fieldsRaw = get_post_meta($formId, '_fr_fields', true);
        
        // Handle both legacy array and new JSON string format
        if (is_array($fieldsRaw)) {
            $fields = $fieldsRaw;
        } elseif (is_string($fieldsRaw) && !empty($fieldsRaw)) {
            $fields = json_decode($fieldsRaw, true);
            $fields = is_array($fields) ? $fields : [];
        } else {
            return [];
        }

        $labels = [];
        foreach ($fields as $field) {
            if (!empty($field['id']) && !empty($field['label'])) {
                $labels[$field['id']] = $field['label'];
            }
        }

        return $labels;
    }

    /**
     * Mark submission as read
     */
    public function markAsRead(): void
    {
        $postId = absint($_GET['post'] ?? 0);
        
        if (!$postId || !current_user_can('edit_post', $postId)) {
            wp_die( esc_html__('Unauthorized', 'form-relayer') );
        }

        check_admin_referer('fr_mark_read_' . $postId);
        update_post_meta($postId, '_fr_read', 1);

        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=' . PostType::SUBMISSION_POST_TYPE));
        exit;
    }

    /**
     * Mark submission as unread
     */
    public function markAsUnread(): void
    {
        $postId = absint($_GET['post'] ?? 0);
        
        if (!$postId || !current_user_can('edit_post', $postId)) {
            wp_die( esc_html__('Unauthorized', 'form-relayer') );
        }

        check_admin_referer('fr_mark_unread_' . $postId);
        delete_post_meta($postId, '_fr_read');

        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=' . PostType::SUBMISSION_POST_TYPE));
        exit;
    }
}
