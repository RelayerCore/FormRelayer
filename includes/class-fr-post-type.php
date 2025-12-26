<?php
/**
 * Custom Post Type for Form Submissions
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Type Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Post_Type {
    
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
        add_action('init', [$this, 'register']);
        add_action('admin_menu', [$this, 'remove_add_new_submenu'], 999);
    }
    
    /**
     * Remove "Add New Form" submenu - users should use Templates page instead
     */
    public function remove_add_new_submenu() {
        remove_submenu_page('edit.php?post_type=fr_form', 'post-new.php?post_type=fr_form');
    }
    
    /**
     * Register the custom post type
     */
    public function register() {
        // Register Form Definitions
        register_post_type('fr_form', [
            'labels' => [
                'name'               => _x('Forms', 'post type general name', 'form-relayer'),
                'singular_name'      => _x('Form', 'post type singular name', 'form-relayer'),
                'menu_name'          => _x('FormRelayer', 'admin menu', 'form-relayer'),
                'name_admin_bar'     => _x('Form', 'add new on admin bar', 'form-relayer'),
                'add_new'            => _x('Add New', 'form', 'form-relayer'),
                'add_new_item'       => __('Add New Form', 'form-relayer'),
                'new_item'           => __('New Form', 'form-relayer'),
                'edit_item'          => __('Edit Form', 'form-relayer'),
                'view_item'          => __('View Form', 'form-relayer'),
                'all_items'          => __('All Forms', 'form-relayer'),
                'search_items'       => __('Search Forms', 'form-relayer'),
                'not_found'          => __('No forms found.', 'form-relayer'),
                'not_found_in_trash' => __('No forms found in Trash.', 'form-relayer'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true, // Top level menu
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-email-alt',
            'supports'           => ['title'],
        ]);

        $labels = [
            'name'               => _x('Submissions', 'post type general name', 'form-relayer'),
            'singular_name'      => _x('Submission', 'post type singular name', 'form-relayer'),
            'menu_name'          => _x('Submissions', 'admin menu', 'form-relayer'),
            'name_admin_bar'     => _x('Submission', 'add new on admin bar', 'form-relayer'),
            'add_new'            => __('Add New', 'form-relayer'),
            'add_new_item'       => __('Add New Submission', 'form-relayer'),
            'new_item'           => __('New Submission', 'form-relayer'),
            'edit_item'          => __('View Submission', 'form-relayer'),
            'view_item'          => __('View Submission', 'form-relayer'),
            'all_items'          => __('All Submissions', 'form-relayer'),
            'search_items'       => __('Search Submissions', 'form-relayer'),
            'not_found'          => __('No submissions found.', 'form-relayer'),
            'not_found_in_trash' => __('No submissions found in Trash.', 'form-relayer'),
        ];
        
        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=fr_form', // Submenu of Forms
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'capabilities'       => [
                'create_posts' => 'do_not_allow',
            ],
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-email-alt',
            'supports'           => ['title'],
        ];
        
        register_post_type('fr_submission', $args);
    }
    
    /**
     * Create a new submission
     *
     * @param array $data Submission data
     * @return int|WP_Error Post ID on success, WP_Error on failure
     */
    public static function create_submission($data) {
        $post_data = [
            'post_type'   => 'fr_submission',
            'post_title'  => sanitize_text_field($data['subject'] ?? __('No Subject', 'form-relayer')),
            'post_status' => 'private',
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Save meta data
            $meta_fields = ['name', 'email', 'phone', 'location', 'message'];
            foreach ($meta_fields as $field) {
                if (isset($data[$field])) {
                    update_post_meta($post_id, '_fr_' . $field, sanitize_text_field($data[$field]));
                }
            }
            
            // Set initial status
            update_post_meta($post_id, '_fr_status', 'new');
            
            // Store IP and user agent for spam detection
            update_post_meta($post_id, '_fr_ip', sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')));
            update_post_meta($post_id, '_fr_user_agent', sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')));
            
            // Allow extensions to add more meta
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        do_action('fr_submission_created', $post_id, $data);
        }
        
        return $post_id;
    }
    
    /**
     * Get submission data
     *
     * @param int $post_id Submission post ID
     * @return array Submission data
     */
    public static function get_submission($post_id) {
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'fr_submission') {
            return [];
        }
        
        return [
            'id'         => $post_id,
            'subject'    => $post->post_title,
            'name'       => get_post_meta($post_id, '_fr_name', true),
            'email'      => get_post_meta($post_id, '_fr_email', true),
            'phone'      => get_post_meta($post_id, '_fr_phone', true),
            'location'   => get_post_meta($post_id, '_fr_location', true),
            'message'    => get_post_meta($post_id, '_fr_message', true),
            'status'     => get_post_meta($post_id, '_fr_status', true) ?: 'new',
            'date'       => $post->post_date,
            'ip'         => get_post_meta($post_id, '_fr_ip', true),
            'user_agent' => get_post_meta($post_id, '_fr_user_agent', true),
        ];
    }
    
    /**
     * Update submission status
     *
     * @param int    $post_id Submission post ID
     * @param string $status  New status (new, read, replied)
     * @return bool
     */
    public static function update_status($post_id, $status) {
        $valid_statuses = ['new', 'read', 'replied'];
        
        if (!in_array($status, $valid_statuses, true)) {
            return false;
        }
        
        return update_post_meta($post_id, '_fr_status', $status);
    }
}
