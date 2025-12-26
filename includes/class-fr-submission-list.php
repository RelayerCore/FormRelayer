<?php
/**
 * Submission List Handler
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Submission List Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Submission_List {
    
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
        // Add columns
        add_filter('manage_fr_submission_posts_columns', [$this, 'set_columns']);
        add_action('manage_fr_submission_posts_custom_column', [$this, 'render_columns'], 10, 2);
        
        // Filter by form
        add_action('restrict_manage_posts', [$this, 'filter_by_form']);
        add_filter('parse_query', [$this, 'handle_filter']);
        
        // Remove "Add New"
        add_action('admin_menu', [$this, 'remove_add_new_menu']);
    }
    
    /**
     * Remove "Add New" submission
     */
    public function remove_add_new_menu() {
        remove_submenu_page('form-relayer', 'post-new.php?post_type=fr_submission');
    }
    
    /**
     * Set columns
     */
    public function set_columns($columns) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('Subject', 'form-relayer');
        $new_columns['fr_name'] = __('Name', 'form-relayer');
        $new_columns['fr_email'] = __('Email', 'form-relayer');
        $new_columns['fr_source'] = __('Source Form', 'form-relayer');
        $new_columns['fr_fields'] = __('Fields Preview', 'form-relayer');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }
    
    /**
     * Render columns
     */
    public function render_columns($column, $post_id) {
        switch ($column) {
            case 'fr_name':
                $name = get_post_meta($post_id, '_fr_name', true);
                if ($name) {
                    echo '<strong>' . esc_html($name) . '</strong>';
                } else {
                    // Try to get from payload
                    $payload = get_post_meta($post_id, '_fr_payload', true);
                    if (is_array($payload)) {
                        foreach ($payload as $field) {
                            if (isset($field['label']) && stripos($field['label'], 'name') !== false) {
                                echo '<strong>' . esc_html($field['value']) . '</strong>';
                                return;
                            }
                        }
                    }
                    echo '<span style="color: #999;">—</span>';
                }
                break;
                
            case 'fr_email':
                $email = get_post_meta($post_id, '_fr_email', true);
                if ($email) {
                    echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;
                
            case 'fr_source':
                $form_id = get_post_meta($post_id, '_parent_form_id', true);
                if ($form_id) {
                    $form = get_post($form_id);
                    if ($form) {
                        echo '<a href="' . esc_url( get_edit_post_link($form_id) ) . '"><strong>' . esc_html($form->post_title) . '</strong></a>';
                    } else {
                        echo '<span style="color: #999;">' . esc_html__('(Deleted Form)', 'form-relayer') . '</span>';
                    }
                } else {
                    echo '<span style="color: #999;">' . esc_html__('(Global)', 'form-relayer') . '</span>';
                }
                break;
                
            case 'fr_fields':
                $payload = get_post_meta($post_id, '_fr_payload', true);
                if (is_array($payload) && !empty($payload)) {
                    $preview_items = [];
                    $count = 0;
                    foreach ($payload as $field) {
                        if ($count >= 3) break; // Max 3 fields in preview
                        if (isset($field['label']) && isset($field['value'])) {
                            // Skip name/email since they have their own columns
                            $label_lower = strtolower($field['label']);
                            if (strpos($label_lower, 'name') !== false || strpos($label_lower, 'email') !== false) {
                                continue;
                            }
                            $value = is_array($field['value']) ? implode(', ', $field['value']) : $field['value'];
                            $value = wp_trim_words($value, 5, '...');
                            $preview_items[] = '<span style="color:#666;"><em>' . esc_html($field['label']) . ':</em> ' . esc_html($value) . '</span>';
                            $count++;
                        }
                    }
                    if (!empty($preview_items)) {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content already escaped in loop
                        echo implode('<br>', $preview_items);
                    } else {
                        echo '<span style="color: #999;">—</span>';
                    }
                    
                    // Show count if more fields
                    $total = count($payload);
                    if ($total > 3) {
                        // translators: %d is the number of additional fields
                        echo '<br><small style="color:#0073aa;">' . sprintf( esc_html__('+ %d more fields', 'form-relayer'), intval( $total - 3 ) ) . '</small>';
                    }
                } else {
                    echo '<span style="color: #999;">' . esc_html__('No data', 'form-relayer') . '</span>';
                }
                break;
        }
    }
    
    /**
     * Filter dropdown
     */
    public function filter_by_form($post_type) {
        if ($post_type !== 'fr_submission') {
            return;
        }
        
        $forms = get_posts([
            'post_type' => 'fr_form',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin filter
        $current = isset($_GET['fr_form_id']) ? sanitize_text_field(wp_unslash($_GET['fr_form_id'])) : '';
        
        echo '<select name="fr_form_id">';
        echo '<option value="">' . esc_html__('All Forms', 'form-relayer') . '</option>';
        
        foreach ($forms as $form) {
            $selected = selected($current, $form->ID, false);
            echo '<option value="' . esc_attr($form->ID) . '" ' . esc_attr( $selected ) . '>' . esc_html($form->post_title) . '</option>';
        }
        
        echo '</select>';
    }
    
    /**
     * Handle filter query
     */
    public function handle_filter($query) {
        global $pagenow;
        
        if ($pagenow !== 'edit.php' || $query->get('post_type') !== 'fr_submission') {
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin filter
        if (!empty($_GET['fr_form_id'])) {
            $query->set('meta_key', '_parent_form_id');
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin filter
            $query->set('meta_value', absint(wp_unslash($_GET['fr_form_id'])));
        }
    }
}
