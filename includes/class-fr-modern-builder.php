<?php
/**
 * Modern Form Builder
 *
 * 2-panel form builder: Canvas | Settings
 * - Keep WP admin bar and sidebar
 * - No field palette (use element picker)
 * - Inline element insertion
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modern Form Builder Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Modern_Builder {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('add_meta_boxes', [$this, 'replace_meta_boxes'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('admin_body_class', [$this, 'add_body_class']);
    }
    
    public function add_body_class($classes) {
        global $post_type, $pagenow;
        
        if (in_array($pagenow, ['post.php', 'post-new.php']) && $post_type === 'fr_form') {
            $classes .= ' fr-builder-page';
        }
        
        return $classes;
    }
    
    public function replace_meta_boxes() {
        global $post_type;
        
        if ($post_type !== 'fr_form') return;
        
        remove_meta_box('fr_form_editor', 'fr_form', 'normal');
        remove_meta_box('fr_styles', 'fr_form', 'side');
        
        add_meta_box(
            'fr_modern_builder',
            __('Form Builder', 'form-relayer'),
            [$this, 'render_builder'],
            'fr_form',
            'normal',
            'high'
        );
    }
    
    public function enqueue_assets($hook) {
        global $post_type;
        
        if (!in_array($hook, ['post.php', 'post-new.php']) || $post_type !== 'fr_form') {
            return;
        }
        
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_enqueue_style(
            'fr-builder',
            FR_PLUGIN_URL . 'assets/css/builder.css',
            [],
            FR_VERSION
        );
        
        wp_enqueue_script(
            'fr-builder',
            FR_PLUGIN_URL . 'assets/js/builder.js',
            ['jquery', 'jquery-ui-sortable'],
            FR_VERSION,
            true
        );
        
        wp_localize_script('fr-builder', 'frBuilder', [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'previewNonce' => wp_create_nonce('fr_form_preview'),
            'i18n'         => [
                'basicFields'      => __('Basic Fields', 'form-relayer'),
                'choiceFields'     => __('Choice Fields', 'form-relayer'),
                'otherFields'      => __('Other', 'form-relayer'),
                'emptyTitle'       => __('Start Building Your Form', 'form-relayer'),
                'emptyHint'        => __('Click below to add your first field', 'form-relayer'),
                'addFirstField'    => __('Add First Field', 'form-relayer'),
                'addElement'       => __('Add Element', 'form-relayer'),
                'searchFields'     => __('Search fields...', 'form-relayer'),
                'fieldSettings'    => __('Field Settings', 'form-relayer'),
                'formSettings'     => __('Form Settings', 'form-relayer'),
                'label'            => __('Label', 'form-relayer'),
                'fieldId'          => __('Field ID', 'form-relayer'),
                'fieldIdHint'      => __('Used in form data (no spaces)', 'form-relayer'),
                'placeholder'      => __('Placeholder', 'form-relayer'),
                'options'          => __('Options', 'form-relayer'),
                'optionsHint'      => __('Enter each option on a new line or comma-separated', 'form-relayer'),
                'required'         => __('Required field', 'form-relayer'),
                'cssClass'         => __('CSS Class', 'form-relayer'),
                'deleteField'      => __('Delete Field', 'form-relayer'),
                'confirmDelete'    => __('Delete this field?', 'form-relayer'),
                'select'           => __('Select...', 'form-relayer'),
                'notificationEmail'=> __('Notification Email', 'form-relayer'),
                'buttonText'       => __('Submit Button Text', 'form-relayer'),
                'successMessage'   => __('Success Message', 'form-relayer'),
                'redirectUrl'      => __('Redirect URL', 'form-relayer'),
                'redirectHint'     => __('Redirect after submission (optional)', 'form-relayer'),
                'primaryColor'     => __('Primary Color', 'form-relayer'),
                'buttonColor'      => __('Button Color', 'form-relayer'),
                'enableAutoReply'  => __('Enable Auto-Reply', 'form-relayer'),
                'autoReplySubject' => __('Auto-Reply Subject', 'form-relayer'),
                'autoReplyMessage' => __('Auto-Reply Message', 'form-relayer'),
                'autoReplyHint'    => __('Sent to submitter. Use {field_id} for placeholders.', 'form-relayer'),
            ],
        ]);
        
        // Hide some WP clutter but keep admin bar and sidebar
        wp_add_inline_style('fr-builder', '
            .fr-builder-page #screen-meta,
            .fr-builder-page #screen-meta-links,
            .fr-builder-page #postdivrich,
            .fr-builder-page #postimagediv,
            .fr-builder-page #tagsdiv-post_tag,
            .fr-builder-page .postbox-header,
            .fr-builder-page #side-sortables,
            .fr-builder-page #postbox-container-1,
            .fr-builder-page #titlediv,
            .fr-builder-page .wrap > h1,
            .fr-builder-page .page-title-action { display: none !important; }
            .fr-builder-page #fr_modern_builder { border: none; box-shadow: none; background: none; margin: 0; }
            .fr-builder-page #fr_modern_builder .inside { padding: 0; margin: 0; }
            .fr-builder-page #post-body.columns-2 { margin-right: 0; }
            .fr-builder-page #post-body-content { margin-bottom: 0; }
            .fr-builder-page .wrap { padding-top: 0; }
        ');
    }
    
    public function render_builder($post) {
        $fields = get_post_meta($post->ID, '_fr_fields', true) ?: [];
        $button_text = get_post_meta($post->ID, '_fr_button_text', true) ?: __('Submit', 'form-relayer');
        $success_msg = get_post_meta($post->ID, '_fr_success_message', true) ?: __('Thank you for your submission!', 'form-relayer');
        $email = get_post_meta($post->ID, '_fr_email', true) ?: get_option('admin_email');
        $redirect_url = get_post_meta($post->ID, '_fr_redirect_url', true) ?: '';
        $primary_color = get_post_meta($post->ID, '_fr_primary_color', true) ?: '#6366f1';
        $button_color = get_post_meta($post->ID, '_fr_button_color', true) ?: '#6366f1';
        
        // Auto-Reply settings
        $auto_reply_enabled = get_post_meta($post->ID, '_fr_auto_reply_enabled', true) ?: '';
        $auto_reply_subject = get_post_meta($post->ID, '_fr_auto_reply_subject', true) ?: '';
        $auto_reply_message = get_post_meta($post->ID, '_fr_auto_reply_message', true) ?: '';
        
        wp_nonce_field('fr_save_form', 'fr_form_nonce');
        ?>
        
        <!-- Hidden inputs for form data -->
        <input type="hidden" id="fr-form-id" value="<?php echo esc_attr($post->ID); ?>">
        <input type="hidden" id="fr-fields-data" name="fr_fields_json" value="<?php echo esc_attr(json_encode($fields)); ?>">
        <input type="hidden" name="fr_email" value="<?php echo esc_attr($email); ?>">
        <input type="hidden" name="fr_button_text" value="<?php echo esc_attr($button_text); ?>">
        <input type="hidden" name="fr_success_message" value="<?php echo esc_attr($success_msg); ?>">
        <input type="hidden" name="fr_redirect_url" value="<?php echo esc_attr($redirect_url); ?>">
        <input type="hidden" name="fr_primary_color" value="<?php echo esc_attr($primary_color); ?>">
        <input type="hidden" name="fr_button_color" value="<?php echo esc_attr($button_color); ?>">
        <input type="hidden" name="fr_auto_reply_enabled" value="<?php echo esc_attr($auto_reply_enabled); ?>">
        <input type="hidden" name="fr_auto_reply_subject" value="<?php echo esc_attr($auto_reply_subject); ?>">
        <input type="hidden" name="fr_auto_reply_message" value="<?php echo esc_attr($auto_reply_message); ?>">
        
        <div class="fr-builder">
            
            <!-- Header -->
            <header class="fr-builder-header">
                <div class="fr-builder-brand">
                    <div class="fr-builder-logo">
                        <span class="dashicons dashicons-forms"></span>
                    </div>
                    <input type="text" 
                           name="post_title" 
                           class="fr-builder-title-input" 
                           value="<?php echo esc_attr($post->post_title ?: __('Untitled Form', 'form-relayer')); ?>"
                           placeholder="<?php esc_attr_e('Form name...', 'form-relayer'); ?>">
                </div>
                
                <div class="fr-builder-actions">
                    <button type="button" id="fr-btn-preview" class="fr-btn fr-btn-ghost fr-btn-icon" title="<?php esc_attr_e('Preview', 'form-relayer'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                    <button type="submit" name="publish" class="fr-btn fr-btn-primary">
                        <?php esc_html_e('Save Form', 'form-relayer'); ?>
                    </button>
                    <button type="button" id="fr-btn-settings" class="fr-btn fr-btn-ghost fr-btn-icon active" title="<?php esc_attr_e('Form Settings', 'form-relayer'); ?>">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </button>
                </div>
            </header>
            
            <!-- Canvas -->
            <main class="fr-form-canvas">
                <div class="fr-canvas-wrapper">
                    <div class="fr-canvas-fields">
                        <!-- Populated by JavaScript -->
                    </div>
                    
                    <div class="fr-canvas-submit" style="<?php echo empty($fields) ? 'display:none;' : ''; ?>">
                        <button type="button" style="background: <?php echo esc_attr($button_color); ?>;">
                            <?php echo esc_html($button_text); ?>
                        </button>
                    </div>
                </div>
            </main>
            
            <!-- Settings Panel -->
            <aside class="fr-settings-panel">
                <div class="fr-settings-header">
                    <h3><?php esc_html_e('Form Settings', 'form-relayer'); ?></h3>
                </div>
                <div class="fr-settings-body" data-mode="form">
                    <!-- Populated by JavaScript -->
                </div>
            </aside>
            
        </div>
        
        <!-- Preview Modal -->
        <div id="fr-preview-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 100000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 12px; width: 90%; max-width: 800px; height: 80vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
                    <h3 style="margin: 0; font-size: 16px;"><?php esc_html_e('Form Preview', 'form-relayer'); ?></h3>
                    <button type="button" onclick="jQuery('#fr-preview-modal').fadeOut(200);" style="background: none; border: none; cursor: pointer; font-size: 20px; color: #64748b;">&times;</button>
                </div>
                <div style="flex: 1; background: #f1f5f9; padding: 20px; overflow: auto; display: flex; justify-content: center;">
                    <iframe id="fr-preview-frame" style="width: 100%; max-width: 600px; height: 100%; border: none; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"></iframe>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            // Sync hidden inputs when form settings change
            $(document).on('change input', '.fr-settings-body[data-mode="form"] input, .fr-settings-body[data-mode="form"] textarea', function() {
                var name = $(this).attr('name');
                if (name) {
                    $('input[name="' + name + '"][type="hidden"]').val($(this).val());
                }
            });
        });
        </script>
        
        <?php
    }
}
