<?php
/**
 * Admin Columns Handler
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Columns Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name for backwards compatibility
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Admin_Columns {
    
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
        add_filter('manage_fr_submission_posts_columns', [$this, 'set_columns']);
        add_action('manage_fr_submission_posts_custom_column', [$this, 'render_columns'], 10, 2);
        add_filter('manage_edit-fr_submission_sortable_columns', [$this, 'set_sortable_columns']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_fr_submission', [$this, 'save_status']);
        
        // Mark as read when viewing
        add_action('edit_form_top', [$this, 'mark_as_read']);
        
        // Custom row actions
        add_filter('post_row_actions', [$this, 'row_actions'], 10, 2);
        
        // Search and filter functionality
        add_action('restrict_manage_posts', [$this, 'add_filter_dropdowns']);
        add_filter('pre_get_posts', [$this, 'filter_submissions']);
    }
    
    /**
     * Add filter dropdowns to submissions list
     */
    public function add_filter_dropdowns() {
        global $typenow;
        
        if ($typenow !== 'fr_submission') {
            return;
        }
        
        // Form filter dropdown
        $forms = get_posts([
            'post_type'      => 'fr_form',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        
        if (!empty($forms)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen filter does not require nonce
            $selected = isset($_GET['fr_form_filter']) ? absint(wp_unslash($_GET['fr_form_filter'])) : '';
            ?>
            <select name="fr_form_filter">
                <option value=""><?php esc_html_e('All Forms', 'form-relayer'); ?></option>
                <?php foreach ($forms as $form) : ?>
                    <option value="<?php echo esc_attr($form->ID); ?>" <?php selected($selected, $form->ID); ?>>
                        <?php echo esc_html($form->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
        }
        
        // Status filter dropdown
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen filter does not require nonce
        $status = isset($_GET['fr_status_filter']) ? sanitize_text_field(wp_unslash($_GET['fr_status_filter'])) : '';
        ?>
        <select name="fr_status_filter">
            <option value=""><?php esc_html_e('All Statuses', 'form-relayer'); ?></option>
            <option value="new" <?php selected($status, 'new'); ?>><?php esc_html_e('New', 'form-relayer'); ?></option>
            <option value="read" <?php selected($status, 'read'); ?>><?php esc_html_e('Read', 'form-relayer'); ?></option>
            <option value="replied" <?php selected($status, 'replied'); ?>><?php esc_html_e('Replied', 'form-relayer'); ?></option>
        </select>
        <?php
    }
    
    /**
     * Filter submissions by form, status, and search meta fields
     *
     * @param WP_Query $query
     */
    public function filter_submissions($query) {
        global $pagenow, $typenow;
        
        if (!is_admin() || $pagenow !== 'edit.php' || $typenow !== 'fr_submission') {
            return;
        }
        
        if (!$query->is_main_query()) {
            return;
        }
        
        $meta_query = [];
        
        // Filter by form
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen filter
        if (!empty($_GET['fr_form_filter'])) {
            $meta_query[] = [
                'key'   => '_parent_form_id',
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen filter
                'value' => absint(wp_unslash($_GET['fr_form_filter'])),
            ];
        }
        
        // Filter by status
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen filter
        if (!empty($_GET['fr_status_filter'])) {
            $meta_query[] = [
                'key'   => '_fr_status',
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen filter
                'value' => sanitize_text_field(wp_unslash($_GET['fr_status_filter'])),
            ];
        }
        
        // Search in meta fields (email, name, message)
        $search = $query->get('s');
        if (!empty($search)) {
            // Remove default search behavior
            $query->set('s', '');
            
            // Search in meta fields
            $meta_query['relation'] = 'OR';
            $meta_query[] = [
                'key'     => '_fr_email',
                'value'   => $search,
                'compare' => 'LIKE',
            ];
            $meta_query[] = [
                'key'     => '_fr_name',
                'value'   => $search,
                'compare' => 'LIKE',
            ];
            
            // Also search in post title
            add_filter('posts_where', function($where) use ($search) {
                global $wpdb;
                $where .= $wpdb->prepare(" OR {$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
                return $where;
            });
        }
        
        if (!empty($meta_query)) {
            $existing = $query->get('meta_query') ?: [];
            $query->set('meta_query', array_merge($existing, $meta_query));
        }
    }
    
    /**
     * Set custom columns
     *
     * @param array $columns Default columns
     * @return array
     */
    public function set_columns($columns) {
        return [
            'cb'        => $columns['cb'],
            'title'     => __('Subject', 'form-relayer'),
            'fr_name'   => __('Name', 'form-relayer'),
            'fr_email'  => __('Email', 'form-relayer'),
            'fr_status' => __('Status', 'form-relayer'),
            'date'      => __('Date', 'form-relayer'),
        ];
    }
    
    /**
     * Render custom columns
     *
     * @param string $column  Column name
     * @param int    $post_id Post ID
     */
    public function render_columns($column, $post_id) {
        switch ($column) {
            case 'fr_name':
                echo esc_html(get_post_meta($post_id, '_fr_name', true));
                break;
                
            case 'fr_email':
                $email = get_post_meta($post_id, '_fr_email', true);
                printf(
                    '<a href="mailto:%1$s">%1$s</a>',
                    esc_attr($email)
                );
                break;
                
            case 'fr_status':
                $status = get_post_meta($post_id, '_fr_status', true) ?: 'new';
                $this->render_status_badge($status);
                break;
        }
    }
    
    /**
     * Render status badge
     *
     * @param string $status Submission status
     */
    private function render_status_badge($status) {
        $badges = [
            'new'     => [
                'label' => __('New', 'form-relayer'),
                'color' => '#0073aa',
            ],
            'read'    => [
                'label' => __('Read', 'form-relayer'),
                'color' => '#72777c',
            ],
            'replied' => [
                'label' => __('Replied', 'form-relayer'),
                'color' => '#46b450',
            ],
        ];
        
        $badge = $badges[$status] ?? $badges['new'];
        
        printf(
            '<span style="display: inline-block; padding: 4px 10px; background: %s; color: #fff; border-radius: 3px; font-size: 12px; font-weight: 500;">%s</span>',
            esc_attr($badge['color']),
            esc_html($badge['label'])
        );
    }
    
    /**
     * Set sortable columns
     *
     * @param array $columns Sortable columns
     * @return array
     */
    public function set_sortable_columns($columns) {
        $columns['fr_status'] = 'fr_status';
        return $columns;
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'fr_submission_details',
            __('Submission Details', 'form-relayer'),
            [$this, 'render_details_meta_box'],
            'fr_submission',
            'normal',
            'high'
        );
        
        add_meta_box(
            'fr_submission_status',
            __('Status', 'form-relayer'),
            [$this, 'render_status_meta_box'],
            'fr_submission',
            'side',
            'high'
        );
        
        add_meta_box(
            'fr_submission_actions',
            __('Quick Actions', 'form-relayer'),
            [$this, 'render_actions_meta_box'],
            'fr_submission',
            'side',
            'default'
        );
    }
    
    /**
     * Render details meta box
     *
     * @param WP_Post $post Current post
     */
    public function render_details_meta_box($post) {
        $data = FR_Post_Type::get_submission($post->ID);
        $primary_color = get_option('fr_primary_color', '#0073aa');
        $payload = get_post_meta($post->ID, '_fr_payload', true);
        $form_id = get_post_meta($post->ID, '_parent_form_id', true);
        ?>
        <style>
            .fr-details-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            .fr-details-table th { text-align: left; padding: 12px; background: #f9f9f9; width: 140px; border: 1px solid #e5e5e5; vertical-align: top; }
            .fr-details-table td { padding: 12px; border: 1px solid #e5e5e5; }
            .fr-message-box { margin-top: 20px; padding: 20px; background: #f9f9f9; border-radius: 6px; }
            .fr-message-box h4 { margin: 0 0 10px; }
            .fr-message-content { white-space: pre-wrap; line-height: 1.6; }
            .fr-source-badge { display: inline-block; padding: 4px 10px; background: <?php echo esc_attr($primary_color); ?>; color: #fff; border-radius: 4px; font-size: 12px; margin-bottom: 15px; }
            .fr-field-section { margin-bottom: 20px; }
            .fr-field-section h4 { margin: 0 0 10px; color: #333; border-bottom: 1px solid #e5e5e5; padding-bottom: 8px; }
        </style>
        
        <?php if ($form_id) : 
            $form = get_post($form_id);
            if ($form) : ?>
                <div class="fr-source-badge">
                    <?php esc_html_e('From:', 'form-relayer'); ?> <?php echo esc_html($form->post_title); ?>
                </div>
            <?php endif; 
        endif; ?>
        
        <div class="fr-field-section">
            <h4><?php esc_html_e('Submission Details', 'form-relayer'); ?></h4>
            <table class="fr-details-table">
                <tr>
                    <th><?php esc_html_e('Submitted', 'form-relayer'); ?></th>
                    <td><?php echo esc_html(get_the_date('F j, Y \a\t g:i a', $post)); ?></td>
                </tr>
                <?php if (!empty($data['name'])) : ?>
                <tr>
                    <th><?php esc_html_e('Name', 'form-relayer'); ?></th>
                    <td><strong><?php echo esc_html($data['name']); ?></strong></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($data['email'])) : ?>
                <tr>
                    <th><?php esc_html_e('Email', 'form-relayer'); ?></th>
                    <td>
                        <a href="mailto:<?php echo esc_attr($data['email']); ?>">
                            <?php echo esc_html($data['email']); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <?php if (is_array($payload) && !empty($payload)) : ?>
        <div class="fr-field-section">
            <h4><?php esc_html_e('Form Fields', 'form-relayer'); ?></h4>
            <table class="fr-details-table">
                <?php foreach ($payload as $field) : 
                    if (!isset($field['label']) || !isset($field['value'])) continue;
                    // Skip name/email as they're shown above
                    $label_lower = strtolower($field['label']);
                    if (strpos($label_lower, 'name') !== false && strpos($label_lower, 'name') === 0) continue;
                    if (strpos($label_lower, 'email') !== false) continue;
                    
                    $value = $field['value'];
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                ?>
                <tr>
                    <th><?php echo esc_html($field['label']); ?></th>
                    <td>
                        <?php if ($field['type'] === 'textarea') : ?>
                            <div class="fr-message-content"><?php echo esc_html($value); ?></div>
                        <?php elseif ($field['type'] === 'email') : ?>
                            <a href="mailto:<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?></a>
                        <?php elseif ($field['type'] === 'tel') : ?>
                            <a href="tel:<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?></a>
                        <?php elseif ($field['type'] === 'url') : ?>
                            <a href="<?php echo esc_url($value); ?>" target="_blank"><?php echo esc_html($value); ?></a>
                        <?php else : ?>
                            <?php echo esc_html($value); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php else : ?>
        <!-- Fallback for old submissions without payload -->
        <table class="fr-details-table">
            <?php if (!empty($data['phone'])) : ?>
            <tr>
                <th><?php esc_html_e('Phone', 'form-relayer'); ?></th>
                <td><?php echo esc_html($data['phone']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($data['location'])) : ?>
            <tr>
                <th><?php esc_html_e('Location', 'form-relayer'); ?></th>
                <td><?php echo esc_html($data['location']); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <?php if (!empty($data['message'])) : ?>
        <div class="fr-message-box">
            <h4><?php esc_html_e('Message', 'form-relayer'); ?></h4>
            <div class="fr-message-content"><?php echo esc_html($data['message']); ?></div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php
        // Pro: Display attachments
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name
        do_action('fr_submission_details_after', $post, $data);
    }
    
    /**
     * Render status meta box
     *
     * @param WP_Post $post Current post
     */
    public function render_status_meta_box($post) {
        $status = get_post_meta($post->ID, '_fr_status', true) ?: 'new';
        wp_nonce_field('fr_save_status', 'fr_status_nonce');
        ?>
        <select name="fr_status" style="width: 100%;">
            <option value="new" <?php selected($status, 'new'); ?>><?php esc_html_e('New', 'form-relayer'); ?></option>
            <option value="read" <?php selected($status, 'read'); ?>><?php esc_html_e('Read', 'form-relayer'); ?></option>
            <option value="replied" <?php selected($status, 'replied'); ?>><?php esc_html_e('Replied', 'form-relayer'); ?></option>
        </select>
        <?php
    }
    
    /**
     * Render actions meta box
     *
     * @param WP_Post $post Current post
     */
    public function render_actions_meta_box($post) {
        $data = FR_Post_Type::get_submission($post->ID);
        $primary_color = get_option('fr_primary_color', '#0073aa');
        ?>
        <style>
            .fr-action-btn { display: block; width: 100%; margin-bottom: 10px; text-align: center; }
            .fr-action-btn:last-child { margin-bottom: 0; }
        </style>
        
        <a href="mailto:<?php echo esc_attr($data['email']); ?>?subject=Re: <?php echo esc_attr($post->post_title); ?>" 
           class="button button-primary fr-action-btn">
            <?php esc_html_e('Reply via Email', 'form-relayer'); ?>
        </a>
        
        <?php if (!empty($data['phone'])) : ?>
            <a href="tel:<?php echo esc_attr($data['phone']); ?>" class="button fr-action-btn">
                <?php esc_html_e('Call', 'form-relayer'); ?>
            </a>
        <?php endif; ?>
        
        <?php
        // Pro: In-admin reply button
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name
        do_action('fr_submission_actions', $post, $data);
    }
    
    /**
     * Save status on post save
     *
     * @param int $post_id Post ID
     */
    public function save_status($post_id) {
        if (!isset($_POST['fr_status_nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['fr_status_nonce'])), 'fr_save_status')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['fr_status'])) {
            FR_Post_Type::update_status($post_id, sanitize_text_field(wp_unslash($_POST['fr_status'])));
        }
    }
    
    /**
     * Mark submission as read when viewing
     *
     * @param WP_Post $post Current post
     */
    public function mark_as_read($post) {
        if ($post->post_type !== 'fr_submission') {
            return;
        }
        
        $status = get_post_meta($post->ID, '_fr_status', true);
        
        if ($status === 'new') {
            update_post_meta($post->ID, '_fr_status', 'read');
        }
    }
    
    /**
     * Custom row actions
     *
     * @param array   $actions Default actions
     * @param WP_Post $post    Current post
     * @return array
     */
    public function row_actions($actions, $post) {
        if ($post->post_type !== 'fr_submission') {
            return $actions;
        }
        
        $data = FR_Post_Type::get_submission($post->ID);
        
        // Add reply action
        $actions['reply'] = sprintf(
            '<a href="mailto:%s?subject=Re: %s">%s</a>',
            esc_attr($data['email']),
            esc_attr($post->post_title),
            __('Reply', 'form-relayer')
        );
        
        return $actions;
    }
}
