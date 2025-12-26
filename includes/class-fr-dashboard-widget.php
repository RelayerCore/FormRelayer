<?php
/**
 * Dashboard Widget
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard Widget Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Dashboard_Widget {
    
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
        add_action('wp_dashboard_setup', [$this, 'add_widget']);
    }
    
    /**
     * Add dashboard widget
     */
    public function add_widget() {
        if (!current_user_can('edit_posts')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'fr_submissions_widget',
            __('Example: Recent Form Submissions', 'form-relayer'),
            [$this, 'render_widget']
        );
    }
    
    /**
     * Render widget content
     */
    public function render_widget() {
        $args = [
            'post_type'      => 'fr_submission',
            'posts_per_page' => 5,
            'post_status'    => 'any',
        ];
        
        $submissions = get_posts($args);
        
        if (empty($submissions)) {
            echo '<p>' . esc_html__('No submissions yet.', 'form-relayer') . '</p>';
            return;
        }
        
        echo '<div class="fr-dashboard-widget">';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>' . esc_html__('From', 'form-relayer') . '</th><th>' . esc_html__('Subject', 'form-relayer') . '</th><th>' . esc_html__('Date', 'form-relayer') . '</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($submissions as $post) {
            $name = get_post_meta($post->ID, '_fr_name', true);
            $email = get_post_meta($post->ID, '_fr_email', true);
            $edit_link = get_edit_post_link($post->ID);
            
            echo '<tr>';
            echo '<td>';
            echo '<strong>' . esc_html($name) . '</strong><br>';
            echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
            echo '</td>';
            echo '<td>';
            if ($edit_link) {
                echo '<a href="' . esc_url($edit_link) . '">' . esc_html($post->post_title) . '</a>';
            } else {
                echo esc_html($post->post_title);
            }
            echo '</td>';
            echo '<td>' . esc_html(get_the_date('M j', $post)) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        $all_link = admin_url('edit.php?post_type=fr_submission');
        echo '<p class="text-right" style="margin-top: 10px; text-align: right;"><a href="' . esc_url($all_link) . '" class="button button-primary">' . esc_html__('View All Submissions', 'form-relayer') . '</a></p>';
        echo '</div>';
    }
}
