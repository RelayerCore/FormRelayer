<?php

declare(strict_types=1);

namespace FormRelayer\Admin;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;

/**
 * Dashboard Widget
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Dashboard
{
    private static ?Dashboard $instance = null;

    private function __construct()
    {
        add_action('wp_dashboard_setup', [$this, 'addWidget']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add dashboard widget
     */
    public function addWidget(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        wp_add_dashboard_widget(
            'fr_dashboard_widget',
            __('FormRelayer Overview', 'form-relayer'),
            [$this, 'renderWidget']
        );
    }

    /**
     * Render widget content
     */
    public function renderWidget(): void
    {
        $stats = $this->getStats();
        ?>
        <div class="fr-dashboard-widget">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
                <div style="text-align:center;padding:16px;background:#f0f0f1;border-radius:4px;">
                    <div style="font-size:24px;font-weight:600;color:#1d2327;"><?php echo esc_html($stats['forms']); ?></div>
                    <div style="color:#50575e;font-size:12px;"><?php esc_html_e('Forms', 'form-relayer'); ?></div>
                </div>
                <div style="text-align:center;padding:16px;background:#f0f0f1;border-radius:4px;">
                    <div style="font-size:24px;font-weight:600;color:#1d2327;"><?php echo esc_html($stats['submissions']); ?></div>
                    <div style="color:#50575e;font-size:12px;"><?php esc_html_e('Submissions', 'form-relayer'); ?></div>
                </div>
                <div style="text-align:center;padding:16px;background:#dff0d8;border-radius:4px;">
                    <div style="font-size:24px;font-weight:600;color:#3c763d;"><?php echo esc_html($stats['unread']); ?></div>
                    <div style="color:#3c763d;font-size:12px;"><?php esc_html_e('Unread', 'form-relayer'); ?></div>
                </div>
            </div>

            <?php if (!empty($stats['recent'])): ?>
                <h4 style="margin:0 0 8px;font-size:13px;"><?php esc_html_e('Recent Submissions', 'form-relayer'); ?></h4>
                <ul style="margin:0;padding:0;list-style:none;">
                    <?php foreach ($stats['recent'] as $submission): ?>
                        <li style="padding:8px 0;border-bottom:1px solid #f0f0f1;">
                            <a href="<?php echo esc_url(get_edit_post_link($submission->ID) ?? '#'); ?>">
                                <?php echo esc_html($submission->post_title); ?>
                            </a>
                            <span style="float:right;color:#50575e;font-size:12px;">
                                <?php echo esc_html(human_time_diff(strtotime($submission->post_date), current_time('timestamp')) . ' ago'); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="color:#50575e;text-align:center;margin:16px 0;">
                    <?php esc_html_e('No submissions yet.', 'form-relayer'); ?>
                </p>
            <?php endif; ?>

            <p style="margin:16px 0 0;text-align:center;">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . PostType::POST_TYPE)); ?>" class="button">
                    <?php esc_html_e('Manage Forms', 'form-relayer'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . PostType::SUBMISSION_POST_TYPE)); ?>" class="button">
                    <?php esc_html_e('View Submissions', 'form-relayer'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Get dashboard stats
     */
    private function getStats(): array
    {
        $formCount = wp_count_posts(PostType::POST_TYPE);
        $submissionCount = wp_count_posts(PostType::SUBMISSION_POST_TYPE);

        // Count unread submissions
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance
        $unread = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_fr_read'
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND (pm.meta_value IS NULL OR pm.meta_value = '')",
            PostType::SUBMISSION_POST_TYPE
        ));

        // Get recent submissions
        $recent = get_posts([
            'post_type' => PostType::SUBMISSION_POST_TYPE,
            'posts_per_page' => 5,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return [
            'forms' => $formCount->publish ?? 0,
            'submissions' => $submissionCount->publish ?? 0,
            'unread' => $unread,
            'recent' => $recent,
        ];
    }
}
