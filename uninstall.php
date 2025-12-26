<?php
/**
 * FormRelayer Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package FormRelayer
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove all plugin data on uninstall
 */
function formrelayer_uninstall() {
    global $wpdb;
    
    // Only run if user explicitly wants to delete data
    // You could add an option to control this behavior
    $delete_data = get_option('fr_delete_data_on_uninstall', false);
    
    if (!$delete_data) {
        // Just delete options, keep submissions
        formrelayer_delete_options();
        return;
    }
    
    // Delete all submissions
    $submissions = get_posts([
        'post_type'      => 'fr_submission',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ]);
    
    foreach ($submissions as $submission_id) {
        wp_delete_post($submission_id, true);
    }
    
    // Delete post meta
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup on uninstall
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_fr_%'");
    
    // Delete options
    formrelayer_delete_options();
    
    // Delete transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup on uninstall
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fr_%'");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup on uninstall
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_fr_%'");
    
    // Clear any scheduled cron jobs
    wp_clear_scheduled_hook('fr_daily_cleanup');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Delete plugin options
 */
function formrelayer_delete_options() {
    $options = [
        'fr_recipient_email',
        'fr_success_message',
        'fr_enable_auto_reply',
        'fr_auto_reply_subject',
        'fr_auto_reply_message',
        'fr_form_locations',
        'fr_primary_color',
        'fr_secondary_color',
        'fr_activated_time',
        'fr_delete_data_on_uninstall',
        'fr_license_key',
        'fr_license_status',
    ];
    
    foreach ($options as $option) {
        delete_option($option);
    }
}

// Run uninstall
formrelayer_uninstall();

