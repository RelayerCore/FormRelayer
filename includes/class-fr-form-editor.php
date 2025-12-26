<?php
/**
 * Form Editor Handler
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Editor Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Form_Editor {
    
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
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_fr_form', [$this, 'save_form']);
        
        // Add columns to form list
        add_filter('manage_fr_form_posts_columns', [$this, 'set_columns']);
        add_action('manage_fr_form_posts_custom_column', [$this, 'render_columns'], 10, 2);
        
        // Row actions
        add_filter('post_row_actions', [$this, 'add_row_actions'], 10, 2);
        
        // Handle duplication
        add_action('admin_action_fr_duplicate_form', [$this, 'handle_duplication']);
    }

    // ... (meta box methods remain unchanged) ...

    /**
     * Set columns
     */
    public function set_columns($columns) {
        $new_columns = [];
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['shortcode'] = __('Shortcode', 'form-relayer');
        $new_columns['submission_count'] = __('Submissions', 'form-relayer');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }
    
    /**
     * Render columns
     */
    public function render_columns($column, $post_id) {
        if ($column === 'shortcode') {
            echo '<code>[form_relayer id="' . esc_attr( $post_id ) . '"]</code>';
            echo '<button type="button" class="button button-small copy-shortcode" data-code="[form_relayer id=&quot;' . esc_attr( $post_id ) . '&quot;]" style="margin-left: 5px;">' . esc_html__('Copy', 'form-relayer') . '</button>';
        }
        
        if ($column === 'submission_count') {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Meta query required for counting submissions by parent form ID
            $query = new \WP_Query([
                'post_type' => 'fr_submission',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Meta query required for counting submissions
                'meta_key' => '_parent_form_id',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Meta query required for counting submissions
                'meta_value' => $post_id,
                'fields' => 'ids',
                'no_found_rows' => false, // We need found_posts
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'posts_per_page' => 1, // Minimize data retrieval
            ]);
            
            $count = $query->found_posts;
            
            $url = admin_url('edit.php?post_type=fr_submission&fr_form_id=' . $post_id);
            echo '<a href="' . esc_url($url) . '" class="fr-submission-count">' . esc_html($count) . '</a>';
        }
    }
    
    /**
     * Add Row Actions
     */
    public function add_row_actions($actions, $post) {
        if ($post->post_type !== 'fr_form') {
            return $actions;
        }
        
        $url = wp_nonce_url(admin_url('admin.php?action=fr_duplicate_form&post=' . $post->ID), 'fr_duplicate_form_' . $post->ID);
        
        $actions['duplicate'] = '<a href="' . esc_url($url) . '" title="' . esc_attr__('Duplicate this form', 'form-relayer') . '">' . __('Duplicate', 'form-relayer') . '</a>';
        
        return $actions;
    }
    
    /**
     * Handle Duplication
     */
    public function handle_duplication() {
        if (empty($_GET['post'])) {
            wp_die( esc_html__('No post to duplicate has been supplied!', 'form-relayer') );
        }
        
        $post_id = isset($_GET['post']) ? absint($_GET['post']) : '';
        
        check_admin_referer('fr_duplicate_form_' . $post_id);
        
        $post = get_post($post_id);
        
        $current_user = wp_get_current_user();
        $new_post_author = $current_user->ID;
        
        if (isset($post) && $post != null) {
            $args = [
                'comment_status' => $post->comment_status,
                'ping_status'    => $post->ping_status,
                'post_author'    => $new_post_author,
                'post_content'   => $post->post_content,
                'post_excerpt'   => $post->post_excerpt,
                'post_name'      => $post->post_name,
                'post_parent'    => $post->post_parent,
                'post_password'  => $post->post_password,
                'post_status'    => 'draft', // Set to draft
                'post_title'     => $post->post_title . ' (Copy)',
                'post_type'      => $post->post_type,
                'to_ping'        => $post->to_ping,
                'menu_order'     => $post->menu_order
            ];
            
            $new_post_id = wp_insert_post($args);
            
            // Duplicate Meta
            $meta_keys = ['_fr_email', '_fr_success_message', '_fr_button_text', '_fr_primary_color'];
            foreach ($meta_keys as $key) {
                $val = get_post_meta($post_id, $key, true);
                if ($val) {
                    update_post_meta($new_post_id, $key, $val);
                }
            }
            
            wp_safe_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
            exit;
        } else {
            // translators: %d is the post ID that could not be found
            wp_die( esc_html( sprintf( __( 'Post creation failed, could not find original post: %d', 'form-relayer' ), intval( $post_id ) ) ) );
        }
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        // Single unified meta box with tabs
        add_meta_box(
            'fr_form_editor',
            __('Form Builder', 'form-relayer'),
            [$this, 'render_tabbed_editor'],
            'fr_form',
            'normal',
            'high'
        );
        
        // Keep Appearance in sidebar
        add_meta_box(
            'fr_form_styles',
            __('Appearance', 'form-relayer'),
            [$this, 'render_styles_meta_box'],
            'fr_form',
            'side',
            'default'
        );
    }
    
    /**
     * Render tabbed editor interface
     *
     * @param WP_Post $post Current post
     */
    public function render_tabbed_editor($post) {
        wp_nonce_field('fr_save_form', 'fr_form_nonce');
        
        // Get data
        $email = get_post_meta($post->ID, '_fr_email', true) ?: get_option('fr_recipient_email');
        $success_msg = get_post_meta($post->ID, '_fr_success_message', true) ?: get_option('fr_success_message');
        $redirect_url = get_post_meta($post->ID, '_fr_redirect_url', true) ?: '';
        $btn_text = get_post_meta($post->ID, '_fr_button_text', true) ?: __('Send Message', 'form-relayer');
        $fields = get_post_meta($post->ID, '_fr_fields', true);
        
        // Auto-Reply (per-form overrides or inherit from global)
        $ar_override = get_post_meta($post->ID, '_fr_autoreply_override', true);
        $ar_enable = get_post_meta($post->ID, '_fr_autoreply_enable', true);
        $ar_subject = get_post_meta($post->ID, '_fr_autoreply_subject', true);
        $ar_message = get_post_meta($post->ID, '_fr_autoreply_message', true);
        
        // If no override, use global defaults for display
        if (!$ar_override) {
            $ar_enable = get_option('fr_enable_auto_reply', 1);
            $ar_subject = get_option('fr_auto_reply_subject', __('Thank you for contacting us', 'form-relayer'));
            $ar_message = get_option('fr_auto_reply_message', __("Dear {name},\n\nThank you for reaching out to us. We have received your message and will respond within 24 hours.\n\nIf you have any urgent concerns, please don't hesitate to call us directly.\n\nBest regards,\n{site_name} Team", 'form-relayer'));
        }
        
        // Design settings (per-form)
        $design_override = get_post_meta($post->ID, '_fr_design_override', true);
        $primary_color = get_post_meta($post->ID, '_fr_primary_color', true) ?: get_option('fr_primary_color', '#0073aa');
        $btn_style = get_post_meta($post->ID, '_fr_button_style', true) ?: 'filled';
        $form_layout = get_post_meta($post->ID, '_fr_form_layout', true) ?: 'single';
        $wrapper_style = get_post_meta($post->ID, '_fr_wrapper_style', true) ?: 'card';
        $max_width = get_post_meta($post->ID, '_fr_max_width', true) ?: '700';
        
        if (!is_array($fields)) {
            $fields = [
                ['id' => 'f1', 'type' => 'text', 'label' => 'Name', 'required' => 1],
                ['id' => 'f2', 'type' => 'email', 'label' => 'Email', 'required' => 1],
                ['id' => 'f3', 'type' => 'textarea', 'label' => 'Message', 'required' => 1],
            ];
        }
        ?>
        <div class="fr-tabs-wrapper">
            <nav class="fr-tabs-nav">
                <button type="button" class="fr-tab-btn active" data-tab="fields">
                    <span class="dashicons dashicons-forms"></span>
                    <?php esc_html_e('Form Fields', 'form-relayer'); ?>
                </button>
                <button type="button" class="fr-tab-btn" data-tab="settings">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Settings', 'form-relayer'); ?>
                </button>
                <button type="button" class="fr-tab-btn" data-tab="design">
                    <span class="dashicons dashicons-art"></span>
                    <?php esc_html_e('Design', 'form-relayer'); ?>
                </button>
                <button type="button" class="fr-tab-btn" data-tab="autoreply">
                    <span class="dashicons dashicons-email"></span>
                    <?php esc_html_e('Auto-Reply', 'form-relayer'); ?>
                </button>
            </nav>
            
            <!-- Tab: Form Fields -->
            <div class="fr-tab-content active" id="fr-tab-fields">
                <div id="fr-builder">
                    <div id="fr-fields-container">
                        <?php foreach ($fields as $index => $field) : ?>
                            <?php $this->render_field_row($index, $field); ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="fr-builder-actions">
                        <button type="button" class="button button-primary fr-add-field">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('Add Field', 'form-relayer'); ?>
                        </button>
                    </div>
                </div>
                
                <script type="text/template" id="tmpl-fr-field">
                    <?php $this->render_field_row('{{INDEX}}', []); ?>
                </script>
            </div>
            
            <!-- Tab: Settings -->
            <div class="fr-tab-content" id="fr-tab-settings">
                <div class="fr-settings-grid">
                    <div class="fr-setting-group">
                        <label for="fr_email"><?php esc_html_e('Recipient Email', 'form-relayer'); ?></label>
                        <input type="email" name="fr_email" id="fr_email" value="<?php echo esc_attr($email); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Where to send notifications for this form.', 'form-relayer'); ?></p>
                    </div>
                    
                    <div class="fr-setting-group">
                        <label for="fr_button_text"><?php esc_html_e('Button Text', 'form-relayer'); ?></label>
                        <input type="text" name="fr_button_text" id="fr_button_text" value="<?php echo esc_attr($btn_text); ?>" class="regular-text">
                    </div>
                    
                    <div class="fr-setting-group fr-full-width">
                        <label for="fr_success_message"><?php esc_html_e('Success Message', 'form-relayer'); ?></label>
                        <textarea name="fr_success_message" id="fr_success_message" rows="3"><?php echo esc_textarea($success_msg); ?></textarea>
                        <p class="description"><?php esc_html_e('Message shown after successful submission (ignored if redirect is set).', 'form-relayer'); ?></p>
                    </div>
                    
                    <div class="fr-setting-group fr-full-width">
                        <label for="fr_redirect_url"><?php esc_html_e('Success Redirect URL', 'form-relayer'); ?></label>
                        <input type="url" name="fr_redirect_url" id="fr_redirect_url" value="<?php echo esc_url($redirect_url); ?>" class="regular-text" placeholder="https://example.com/thank-you">
                        <p class="description"><?php esc_html_e('Redirect to this URL after submission instead of showing a message. Leave empty to show success message.', 'form-relayer'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Design -->
            <div class="fr-tab-content" id="fr-tab-design">
                <div class="fr-settings-card" style="margin-bottom: 15px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
                    <label>
                        <input type="checkbox" name="fr_design_override" value="1" <?php checked($design_override, 1); ?> id="fr-design-override">
                        <strong><?php esc_html_e('Override global design settings for this form', 'form-relayer'); ?></strong>
                    </label>
                    <p class="description"><?php esc_html_e('When checked, this form uses custom design settings instead of global defaults.', 'form-relayer'); ?></p>
                </div>
                
                <div class="fr-settings-grid" id="fr-design-fields">
                    <div class="fr-setting-group">
                        <label for="fr_primary_color"><?php esc_html_e('Primary Color', 'form-relayer'); ?></label>
                        <input type="color" name="fr_primary_color" id="fr_primary_color" value="<?php echo esc_attr($primary_color); ?>" style="height: 40px; width: 80px;">
                        <p class="description"><?php esc_html_e('Button and accent color.', 'form-relayer'); ?></p>
                    </div>
                    
                    <div class="fr-setting-group">
                        <label for="fr_max_width"><?php esc_html_e('Max Width (px)', 'form-relayer'); ?></label>
                        <input type="number" name="fr_max_width" id="fr_max_width" value="<?php echo esc_attr($max_width); ?>" min="400" max="1200" step="50">
                        <p class="description"><?php esc_html_e('Form container max width.', 'form-relayer'); ?></p>
                    </div>
                    
                    <div class="fr-setting-group">
                        <label for="fr_form_layout"><?php esc_html_e('Form Layout', 'form-relayer'); ?></label>
                        <select name="fr_form_layout" id="fr_form_layout">
                            <option value="single" <?php selected($form_layout, 'single'); ?>><?php esc_html_e('Single Column', 'form-relayer'); ?></option>
                            <option value="two-column" <?php selected($form_layout, 'two-column'); ?>><?php esc_html_e('Two Columns', 'form-relayer'); ?></option>
                            <option value="compact" <?php selected($form_layout, 'compact'); ?>><?php esc_html_e('Compact (Inline)', 'form-relayer'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Grid layout for form fields.', 'form-relayer'); ?></p>
                    </div>
                    
                    <div class="fr-setting-group">
                        <label for="fr_wrapper_style"><?php esc_html_e('Wrapper Style', 'form-relayer'); ?></label>
                        <select name="fr_wrapper_style" id="fr_wrapper_style">
                            <option value="card" <?php selected($wrapper_style, 'card'); ?>><?php esc_html_e('Card (Shadow + Border)', 'form-relayer'); ?></option>
                            <option value="minimal" <?php selected($wrapper_style, 'minimal'); ?>><?php esc_html_e('Minimal (No Border)', 'form-relayer'); ?></option>
                            <option value="bordered" <?php selected($wrapper_style, 'bordered'); ?>><?php esc_html_e('Bordered (No Shadow)', 'form-relayer'); ?></option>
                        </select>
                    </div>
                    
                    <div class="fr-setting-group">
                        <label for="fr_button_style"><?php esc_html_e('Button Style', 'form-relayer'); ?></label>
                        <select name="fr_button_style" id="fr_button_style">
                            <option value="filled" <?php selected($btn_style, 'filled'); ?>><?php esc_html_e('Filled (Solid Color)', 'form-relayer'); ?></option>
                            <option value="outline" <?php selected($btn_style, 'outline'); ?>><?php esc_html_e('Outline (Border Only)', 'form-relayer'); ?></option>
                            <option value="gradient" <?php selected($btn_style, 'gradient'); ?>><?php esc_html_e('Gradient', 'form-relayer'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Auto-Reply -->
            <div class="fr-tab-content" id="fr-tab-autoreply">
                <div class="fr-settings-card" style="margin-bottom: 15px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
                    <label>
                        <input type="checkbox" name="fr_autoreply_override" value="1" <?php checked($ar_override, 1); ?> id="fr-ar-override">
                        <strong><?php esc_html_e('Override global auto-reply settings for this form', 'form-relayer'); ?></strong>
                    </label>
                    <p class="description"><?php esc_html_e('When unchecked, this form uses the global auto-reply settings from Settings page.', 'form-relayer'); ?></p>
                </div>
                
                <div class="fr-settings-grid" id="fr-ar-fields">
                    <div class="fr-setting-group fr-full-width">
                        <label>
                            <input type="checkbox" name="fr_autoreply_enable" value="1" <?php checked($ar_enable, 1); ?>>
                            <?php esc_html_e('Enable auto-reply email to sender', 'form-relayer'); ?>
                        </label>
                    </div>
                    
                    <div class="fr-setting-group fr-full-width">
                        <label for="fr_autoreply_subject"><?php esc_html_e('Subject', 'form-relayer'); ?></label>
                        <input type="text" name="fr_autoreply_subject" id="fr_autoreply_subject" value="<?php echo esc_attr($ar_subject); ?>">
                    </div>
                    
                    <div class="fr-setting-group fr-full-width">
                        <label for="fr_autoreply_message"><?php esc_html_e('Message', 'form-relayer'); ?></label>
                        <?php 
                        wp_editor($ar_message, 'fr_autoreply_message', [
                            'textarea_name' => 'fr_autoreply_message',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                            'teeny' => true,
                        ]);
                        ?>
                        <p class="description">
                            <?php esc_html_e('Placeholders:', 'form-relayer'); ?>
                            <code>{name}</code>, <code>{email}</code>, <code>{site_name}</code>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            // Tab switching
            $('.fr-tab-btn').on('click', function() {
                var tab = $(this).data('tab');
                $('.fr-tab-btn').removeClass('active');
                $(this).addClass('active');
                $('.fr-tab-content').removeClass('active');
                $('#fr-tab-' + tab).addClass('active');
            });
            
            // Field Builder Logic
            var itemCount = <?php echo count($fields); ?>;
            
            $('.fr-add-field').on('click', function() {
                var tmpl = $('#tmpl-fr-field').html();
                tmpl = tmpl.replace(/{{INDEX}}/g, itemCount);
                itemCount++;
                $('#fr-fields-container').append(tmpl);
                $('#fr-fields-container .fr-field-row').last().find('.fr-type-select').trigger('change');
            });
            
            $(document).on('click', '.fr-remove-field', function() {
                if (confirm('<?php esc_attr_e('Are you sure?', 'form-relayer'); ?>')) {
                    $(this).closest('.fr-field-row').remove();
                }
            });
            
            $(document).on('change', '.fr-type-select', function() {
                var type = $(this).val();
                var row = $(this).closest('.fr-field-row');
                if (['select', 'radio', 'checkbox'].includes(type)) {
                    row.addClass('fr-show-options');
                } else {
                    row.removeClass('fr-show-options');
                }
                
                if (type === 'html') {
                    row.addClass('fr-show-content');
                } else {
                    row.removeClass('fr-show-content');
                }
            });
            
            if ($.fn.sortable) {
                $('#fr-fields-container').sortable({
                    handle: '.fr-field-handle',
                    placeholder: 'fr-sortable-placeholder',
                    axis: 'y'
                });
            }
            
            $('.fr-type-select').trigger('change');
        });
        </script>
        <?php
    }
    
    /**
     * Render styles meta box
     */
    public function render_styles_meta_box($post) {
        $color = get_post_meta($post->ID, '_fr_primary_color', true) ?: get_option('fr_primary_color', '#0073aa');
        ?>
        <p>
            <label for="fr_primary_color"><strong><?php esc_html_e('Primary Color', 'form-relayer'); ?></strong></label><br>
            <input type="color" name="fr_primary_color" id="fr_primary_color" value="<?php echo esc_attr($color); ?>">
        </p>
        <?php
    }
    
    /**
     * Render Fields Builder
     */
    public function render_fields_meta_box($post) {
        $fields = get_post_meta($post->ID, '_fr_fields', true);
        if (!is_array($fields)) {
            // Default fields for new form
            $fields = [
                ['id' => 'f1', 'type' => 'text', 'label' => 'Name', 'required' => 1],
                ['id' => 'f2', 'type' => 'email', 'label' => 'Email', 'required' => 1],
                ['id' => 'f3', 'type' => 'textarea', 'label' => 'Message', 'required' => 1],
            ];
        }
        ?>
        <div id="fr-builder">
            <div id="fr-fields-container">
                <?php foreach ($fields as $index => $field) : ?>
                    <?php $this->render_field_row($index, $field); ?>
                <?php endforeach; ?>
            </div>
            
            <div class="fr-builder-actions">
                <button type="button" class="button button-primary fr-add-field"><?php esc_html_e('+ Add Field', 'form-relayer'); ?></button>
            </div>
        </div>
        
        <script type="text/template" id="tmpl-fr-field">
            <?php $this->render_field_row('{{INDEX}}', []); ?>
        </script>
        
        <script>
        jQuery(function($) {
            var itemCount = <?php echo count($fields); ?>;
            
            // Add Field
            $('.fr-add-field').on('click', function() {
                var tmpl = $('#tmpl-fr-field').html();
                tmpl = tmpl.replace(/{{INDEX}}/g, itemCount);
                // Need to generate unique ID? For now relies on index but better usage is timestamp
                // Let's replace ID if template has it
                itemCount++;
                $('#fr-fields-container').append(tmpl);
                
                // Initialize visibility
                $('#fr-fields-container .fr-field-row').last().find('.fr-type-select').trigger('change');
            });
            
            // Remove Field
            $(document).on('click', '.fr-remove-field', function() {
                if (confirm('<?php esc_attr_e('Are you sure?', 'form-relayer'); ?>')) {
                    $(this).closest('.fr-field-row').remove();
                }
            });
            
            // Type Change (Show/Hide Options)
            $(document).on('change', '.fr-type-select', function() {
                var type = $(this).val();
                var row = $(this).closest('.fr-field-row');
                if (['select', 'radio', 'checkbox'].includes(type)) {
                    row.addClass('fr-show-options');
                } else {
                    row.removeClass('fr-show-options');
                }
            });
            
            // Sortable
            if ($.fn.sortable) {
                $('#fr-fields-container').sortable({
                    handle: '.fr-field-handle',
                    placeholder: 'ui-state-highlight',
                    axis: 'y'
                });
            }
            
            // Init
            $('.fr-type-select').trigger('change');
        });
        </script>
        <?php
    }
    
    /**
     * Render single field row
     */
    private function render_field_row($index, $field) {
        $id = $field['id'] ?? 'new_' . uniqid();
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? '';
        $required = isset($field['required']) ? $field['required'] : 0;
        $options = $field['options'] ?? '';
        $name_prefix = "fr_fields[$index]";
        ?>
        <div class="fr-field-row">
            <div class="fr-field-handle"><span class="dashicons dashicons-move"></span></div>
            
            <div class="fr-col">
                <label><?php esc_html_e('Label', 'form-relayer'); ?></label>
                <input type="text" name="<?php echo esc_attr( $name_prefix ); ?>[label]" value="<?php echo esc_attr($label); ?>" placeholder="e.g. Full Name">
                <input type="hidden" name="<?php echo esc_attr( $name_prefix ); ?>[id]" value="<?php echo esc_attr($id); ?>">
            </div>
            
            <div class="fr-col">
                <label><?php esc_html_e('Type', 'form-relayer'); ?></label>
                <?php 
                $is_pro = \FormRelayer::is_pro();
                $pro_lock = $is_pro ? '' : ' 🔒';
                $pro_disabled = $is_pro ? '' : 'disabled';
                ?>
                <select name="<?php echo esc_attr( $name_prefix ); ?>[type]" class="fr-type-select">
                    <optgroup label="<?php esc_attr_e('Free Fields', 'form-relayer'); ?>">
                        <option value="text" <?php selected($type, 'text'); ?>><?php esc_html_e('Text', 'form-relayer'); ?></option>
                        <option value="email" <?php selected($type, 'email'); ?>><?php esc_html_e('Email', 'form-relayer'); ?></option>
                        <option value="tel" <?php selected($type, 'tel'); ?>><?php esc_html_e('Phone', 'form-relayer'); ?></option>
                        <option value="textarea" <?php selected($type, 'textarea'); ?>><?php esc_html_e('Textarea', 'form-relayer'); ?></option>
                        <option value="number" <?php selected($type, 'number'); ?>><?php esc_html_e('Number', 'form-relayer'); ?></option>
                        <option value="date" <?php selected($type, 'date'); ?>><?php esc_html_e('Date', 'form-relayer'); ?></option>
                        <option value="select" <?php selected($type, 'select'); ?>><?php esc_html_e('Dropdown', 'form-relayer'); ?></option>
                        <option value="radio" <?php selected($type, 'radio'); ?>><?php esc_html_e('Radio Buttons', 'form-relayer'); ?></option>
                        <option value="checkbox" <?php selected($type, 'checkbox'); ?>><?php esc_html_e('Checkboxes', 'form-relayer'); ?></option>
                    </optgroup>
                    <optgroup label="<?php esc_attr_e('Layout Elements', 'form-relayer'); ?>">
                        <option value="header" <?php selected($type, 'header'); ?>><?php esc_html_e('Section Header', 'form-relayer'); ?></option>
                        <option value="html" <?php selected($type, 'html'); ?>><?php esc_html_e('HTML / Text Content', 'form-relayer'); ?></option>
                    </optgroup>
                    <optgroup label="<?php esc_attr_e('Pro Fields ⭐', 'form-relayer'); ?>">
                        <option value="url" <?php selected($type, 'url'); ?> <?php echo esc_attr( $pro_disabled ); ?>><?php esc_html_e('URL', 'form-relayer'); echo esc_html( $pro_lock ); ?></option>
                        <option value="time" <?php selected($type, 'time'); ?> <?php echo esc_attr( $pro_disabled ); ?>><?php esc_html_e('Time', 'form-relayer'); echo esc_html( $pro_lock ); ?></option>
                        <option value="datetime-local" <?php selected($type, 'datetime-local'); ?> <?php echo esc_attr( $pro_disabled ); ?>><?php esc_html_e('Date & Time', 'form-relayer'); echo esc_html( $pro_lock ); ?></option>
                        <option value="file" <?php selected($type, 'file'); ?> <?php echo esc_attr( $pro_disabled ); ?>><?php esc_html_e('File Upload', 'form-relayer'); echo esc_html( $pro_lock ); ?></option>
                        <option value="color" <?php selected($type, 'color'); ?> <?php echo esc_attr( $pro_disabled ); ?>><?php esc_html_e('Color Picker', 'form-relayer'); echo esc_html( $pro_lock ); ?></option>
                        <option value="range" <?php selected($type, 'range'); ?> <?php echo esc_attr( $pro_disabled ); ?>><?php esc_html_e('Range Slider', 'form-relayer'); echo esc_html( $pro_lock ); ?></option>
                        <option value="password" <?php selected($type, 'password'); ?> <?php echo esc_attr( $pro_disabled ); ?>><?php esc_html_e('Password', 'form-relayer'); echo esc_html( $pro_lock ); ?></option>
                        <option value="hidden" <?php selected($type, 'hidden'); ?> <?php echo esc_attr( $pro_disabled ); ?>><?php esc_html_e('Hidden', 'form-relayer'); echo esc_html( $pro_lock ); ?></option>
                    </optgroup>
                </select>
                

                <div class="fr-options-wrapper">
                    <label style="margin-top:5px;"><?php esc_html_e('Options (comma separated)', 'form-relayer'); ?></label>
                    <textarea name="<?php echo esc_attr( $name_prefix ); ?>[options]" rows="2" placeholder="Option 1, Option 2, Option 3"><?php echo esc_textarea($options); ?></textarea>
                </div>

                <div class="fr-content-wrapper">
                    <label style="margin-top:5px;"><?php esc_html_e('Content (HTML allowed)', 'form-relayer'); ?></label>
                    <textarea name="<?php echo esc_attr( $name_prefix ); ?>[content]" rows="4" placeholder="<p>Enter content...</p>"><?php echo esc_textarea($field['content'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="fr-col" style="text-align: center;">
                <label><?php esc_html_e('Req?', 'form-relayer'); ?></label>
                <input type="checkbox" name="<?php echo esc_attr( $name_prefix ); ?>[required]" value="1" <?php checked($required, 1); ?>>
            </div>
            
            <div class="fr-remove-field"><span class="dashicons dashicons-trash"></span></div>
        </div>
        <?php
    }
    
    /**
     * Save form settings
     */
    public function save_form($post_id) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check
        if (!isset($_POST['fr_form_nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fr_form_nonce'] ) ), 'fr_save_form')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save fields
        if (isset($_POST['fr_email'])) {
            update_post_meta($post_id, '_fr_email', sanitize_email(wp_unslash($_POST['fr_email'])));
        }
        
        if (isset($_POST['fr_success_message'])) {
            update_post_meta($post_id, '_fr_success_message', sanitize_text_field(wp_unslash($_POST['fr_success_message'])));
        }
        
        if (isset($_POST['fr_redirect_url'])) {
            update_post_meta($post_id, '_fr_redirect_url', esc_url_raw(wp_unslash($_POST['fr_redirect_url'])));
        }
        
        if (isset($_POST['fr_button_text'])) {
            update_post_meta($post_id, '_fr_button_text', sanitize_text_field(wp_unslash($_POST['fr_button_text'])));
        }
        
        if (isset($_POST['fr_primary_color'])) {
            update_post_meta($post_id, '_fr_primary_color', sanitize_hex_color(wp_unslash($_POST['fr_primary_color'])));
        }
        
        // Save button color
        if (isset($_POST['fr_button_color'])) {
            update_post_meta($post_id, '_fr_button_color', sanitize_hex_color(wp_unslash($_POST['fr_button_color'])));
        }
        
        // Save Form Builder Fields - JSON format (from modern builder)
        if (isset($_POST['fr_fields_json']) && !empty($_POST['fr_fields_json'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and sanitized in loop
            $json_fields = json_decode(wp_unslash($_POST['fr_fields_json']), true);
            if (is_array($json_fields)) {
                $fields = [];
                foreach ($json_fields as $field) {
                    if (empty($field['type'])) continue;
                    
                    $fields[] = [
                        'id'          => sanitize_key($field['id'] ?? uniqid()),
                        'type'        => sanitize_text_field($field['type']),
                        'label'       => sanitize_text_field($field['label'] ?? ''),
                        'required'    => !empty($field['required']) ? 1 : 0,
                        'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                        'options'     => sanitize_text_field($field['options'] ?? ''),
                        'css_class'   => sanitize_text_field($field['css_class'] ?? ''),
                    ];
                }
                update_post_meta($post_id, '_fr_fields', $fields);
            }
        }
        // Save Form Builder Fields - Array format (from legacy editor)
        elseif (isset($_POST['fr_fields']) && is_array($_POST['fr_fields'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Fields sanitized in loop
            $post_fields = wp_unslash($_POST['fr_fields']);
            $fields = [];
            foreach ($post_fields as $field) {
                if (empty($field['label']) && $field['type'] !== 'html') continue; // Skip empty, but allow HTML blocks to have empty label
                
                $fields[] = [
                    'id'       => sanitize_text_field($field['id'] ?? uniqid()),
                    'type'     => sanitize_text_field($field['type']),
                    'label'    => sanitize_text_field($field['label']),
                    'required' => isset($field['required']) ? 1 : 0,
                    'options'  => sanitize_text_field($field['options'] ?? ''),
                    'content'  => isset($field['content']) ? wp_kses_post($field['content']) : '',
                ];
            }
            update_post_meta($post_id, '_fr_fields', $fields);
        }
        
        // Save Auto-Reply Settings
        update_post_meta($post_id, '_fr_autoreply_override', isset($_POST['fr_autoreply_override']) ? 1 : 0);
        
        if (isset($_POST['fr_autoreply_override'])) {
            update_post_meta($post_id, '_fr_autoreply_enable', isset($_POST['fr_autoreply_enable']) ? 1 : 0);
            update_post_meta($post_id, '_fr_autoreply_subject', sanitize_text_field(wp_unslash($_POST['fr_autoreply_subject'] ?? '')));
            update_post_meta($post_id, '_fr_autoreply_message', wp_kses_post(wp_unslash($_POST['fr_autoreply_message'] ?? '')));
        }
        
        // Save Design Settings
        update_post_meta($post_id, '_fr_design_override', isset($_POST['fr_design_override']) ? 1 : 0);
        
        if (isset($_POST['fr_design_override'])) {
            update_post_meta($post_id, '_fr_primary_color', sanitize_hex_color(wp_unslash($_POST['fr_primary_color'] ?? '#0073aa')));
            update_post_meta($post_id, '_fr_max_width', absint(wp_unslash($_POST['fr_max_width'] ?? 700)));
            update_post_meta($post_id, '_fr_form_layout', sanitize_text_field(wp_unslash($_POST['fr_form_layout'] ?? 'single')));
            update_post_meta($post_id, '_fr_wrapper_style', sanitize_text_field(wp_unslash($_POST['fr_wrapper_style'] ?? 'card')));
            update_post_meta($post_id, '_fr_button_style', sanitize_text_field(wp_unslash($_POST['fr_button_style'] ?? 'filled')));
        }
    }
    

}
