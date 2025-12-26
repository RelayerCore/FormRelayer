<?php
/**
 * Form Preview
 *
 * Provides live preview of forms in the editor.
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Preview Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Form_Preview {
    
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
        // AJAX handler for preview
        add_action('wp_ajax_fr_form_preview', [$this, 'ajax_render_preview']);
        
        // Enqueue preview assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Enqueue preview assets
     */
    public function enqueue_assets($hook) {
        global $post_type;
        
        if (!in_array($hook, ['post.php', 'post-new.php']) || $post_type !== 'fr_form') {
            return;
        }
        
        // Add inline styles for preview modal
        wp_add_inline_style('fr-admin', $this->get_modal_css());
    }
    
    /**
     * Add preview button after title
     */
    public function add_preview_button($post) {
        if ($post->post_type !== 'fr_form') {
            return;
        }
        ?>
        <div class="fr-preview-button-wrap" style="margin: 15px 0;">
            <button type="button" id="fr-preview-form" class="button button-secondary button-large">
                <span class="dashicons dashicons-visibility" style="margin-top: 4px;"></span>
                <?php esc_html_e('Preview Form', 'form-relayer'); ?>
            </button>
            <span class="fr-preview-hint" style="margin-left: 10px; color: #666; font-size: 13px;">
                <?php esc_html_e('Click to see how your form looks on the frontend', 'form-relayer'); ?>
            </span>
        </div>
        
        <script>
        jQuery(function($) {
            $('#fr-preview-form').on('click', function() {
                var $btn = $(this);
                var originalText = $btn.html();
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php echo esc_js(__('Loading...', 'form-relayer')); ?>');
                
                // Collect current form data
                var formData = {
                    action: 'fr_form_preview',
                    nonce: '<?php echo esc_attr( wp_create_nonce('fr_form_preview') ); ?>',
                    post_id: <?php echo intval( $post->ID ); ?>,
                    fields: []
                };
                
                // Get field data from the current editor state
                $('#fr-form-fields .fr-field-item').each(function() {
                    var $field = $(this);
                    var fieldData = {
                        type: $field.find('[name*="[type]"]').val(),
                        label: $field.find('[name*="[label]"]').val(),
                        id: $field.find('[name*="[id]"]').val(),
                        required: $field.find('[name*="[required]"]').is(':checked') ? 1 : 0,
                        placeholder: $field.find('[name*="[placeholder]"]').val() || '',
                        options: $field.find('[name*="[options]"]').val() || ''
                    };
                    formData.fields.push(fieldData);
                });
                
                // Get button text
                formData.button_text = $('[name="fr_button_text"]').val() || '<?php echo esc_js(__('Submit', 'form-relayer')); ?>';
                
                $.post(ajaxurl, formData, function(response) {
                    $btn.prop('disabled', false).html(originalText);
                    
                    if (response.success) {
                        // Show modal with preview
                        $('#fr-preview-modal').fadeIn(200);
                        $('#fr-preview-frame').attr('srcdoc', response.data.html);
                    } else {
                        alert(response.data.message || '<?php echo esc_js(__('Error generating preview', 'form-relayer')); ?>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).html(originalText);
                    alert('<?php echo esc_js(__('Error generating preview', 'form-relayer')); ?>');
                });
            });
            
            // Close modal
            $(document).on('click', '#fr-preview-modal .fr-modal-close, #fr-preview-modal .fr-modal-overlay', function() {
                $('#fr-preview-modal').fadeOut(200);
            });
            
            // Escape key closes modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#fr-preview-modal').is(':visible')) {
                    $('#fr-preview-modal').fadeOut(200);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render preview modal HTML
     */
    public function render_preview_modal() {
        global $post_type;
        
        if ($post_type !== 'fr_form') {
            return;
        }
        ?>
        <div id="fr-preview-modal" class="fr-modal" style="display: none;">
            <div class="fr-modal-overlay"></div>
            <div class="fr-modal-container">
                <div class="fr-modal-header">
                    <h2>
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('Form Preview', 'form-relayer'); ?>
                    </h2>
                    <button type="button" class="fr-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="fr-modal-body">
                    <div class="fr-preview-device-toggle">
                        <button type="button" class="fr-device-btn active" data-device="desktop" title="<?php esc_attr_e('Desktop', 'form-relayer'); ?>">
                            <span class="dashicons dashicons-desktop"></span>
                        </button>
                        <button type="button" class="fr-device-btn" data-device="tablet" title="<?php esc_attr_e('Tablet', 'form-relayer'); ?>">
                            <span class="dashicons dashicons-tablet"></span>
                        </button>
                        <button type="button" class="fr-device-btn" data-device="mobile" title="<?php esc_attr_e('Mobile', 'form-relayer'); ?>">
                            <span class="dashicons dashicons-smartphone"></span>
                        </button>
                    </div>
                    <div class="fr-preview-frame-container">
                        <iframe id="fr-preview-frame" class="fr-preview-frame"></iframe>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            // Device toggle
            $('.fr-device-btn').on('click', function() {
                var device = $(this).data('device');
                var $frame = $('#fr-preview-frame');
                
                $('.fr-device-btn').removeClass('active');
                $(this).addClass('active');
                
                $frame.removeClass('fr-device-desktop fr-device-tablet fr-device-mobile');
                $frame.addClass('fr-device-' + device);
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Render form preview
     */
    public function ajax_render_preview() {
        check_ajax_referer('fr_form_preview', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'form-relayer')]);
        }
        
        $post_id = absint($_POST['post_id'] ?? 0);
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array processed in loop below
        $fields = isset($_POST['fields']) ? wp_unslash($_POST['fields']) : [];
        $button_text = sanitize_text_field(wp_unslash($_POST['button_text'] ?? __('Submit', 'form-relayer')));
        
        // Sanitize fields
        $sanitized_fields = [];
        foreach ($fields as $field) {
            $sanitized_fields[] = [
                'type'        => sanitize_text_field($field['type'] ?? 'text'),
                'label'       => sanitize_text_field($field['label'] ?? ''),
                'id'          => sanitize_key($field['id'] ?? ''),
                'required'    => absint($field['required'] ?? 0),
                'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                'options'     => sanitize_text_field($field['options'] ?? ''),
            ];
        }
        
        // Render the form HTML
        $form_html = $this->render_form_preview($post_id, $sanitized_fields, $button_text);
        
        wp_send_json_success(['html' => $form_html]);
    }
    
    /**
     * Render form preview HTML
     *
     * @param int    $post_id     Form post ID
     * @param array  $fields      Form fields
     * @param string $button_text Submit button text
     * @return string
     */
    private function render_form_preview($post_id, $fields, $button_text) {
        // Get form colors
        $primary_color = get_post_meta($post_id, '_fr_primary_color', true) ?: '#0073aa';
        $button_color = get_post_meta($post_id, '_fr_button_color', true) ?: '#0073aa';
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                * { box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    font-size: 16px;
                    line-height: 1.6;
                    color: #333;
                    background: #f5f5f5;
                    padding: 30px;
                    margin: 0;
                }
                .fr-form {
                    max-width: 600px;
                    margin: 0 auto;
                    background: #fff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .fr-form-group {
                    margin-bottom: 20px;
                }
                .fr-label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 6px;
                    color: #333;
                }
                .fr-required {
                    color: #dc3545;
                    margin-left: 3px;
                }
                .fr-input,
                .fr-textarea,
                .fr-select {
                    width: 100%;
                    padding: 12px 15px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    font-size: 15px;
                    transition: border-color 0.2s, box-shadow 0.2s;
                }
                .fr-input:focus,
                .fr-textarea:focus,
                .fr-select:focus {
                    outline: none;
                    border-color: <?php echo esc_attr($primary_color); ?>;
                    box-shadow: 0 0 0 3px <?php echo esc_attr($primary_color); ?>22;
                }
                .fr-textarea {
                    min-height: 120px;
                    resize: vertical;
                }
                .fr-checkbox-group,
                .fr-radio-group {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }
                .fr-checkbox-label,
                .fr-radio-label {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    cursor: pointer;
                }
                .fr-submit {
                    display: inline-block;
                    background: <?php echo esc_attr($button_color); ?>;
                    color: #fff;
                    border: none;
                    padding: 14px 30px;
                    font-size: 16px;
                    font-weight: 600;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: opacity 0.2s, transform 0.2s;
                    width: 100%;
                }
                .fr-submit:hover {
                    opacity: 0.9;
                    transform: translateY(-1px);
                }
                .fr-preview-notice {
                    background: #fff3cd;
                    border: 1px solid #ffeeba;
                    color: #856404;
                    padding: 10px 15px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                    font-size: 13px;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="fr-preview-notice">
                <?php esc_html_e('This is a preview. Form submissions are disabled.', 'form-relayer'); ?>
            </div>
            
            <form class="fr-form" onsubmit="return false;">
                <?php foreach ($fields as $field) : 
                    if (empty($field['type'])) continue;
                    $required = $field['required'] ? ' required' : '';
                    $req_mark = $field['required'] ? '<span class="fr-required">*</span>' : '';
                    $field_id = 'fr_' . ($field['id'] ?: uniqid());
                ?>
                <div class="fr-form-group field-<?php echo esc_attr($field['type']); ?>">
                    <?php if (!in_array($field['type'], ['hidden'])) : ?>
                    <label class="fr-label" for="<?php echo esc_attr($field_id); ?>">
                        <?php echo esc_html($field['label']); ?><?php echo wp_kses_post( $req_mark ); ?>
                    </label>
                    <?php endif; ?>
                    
                    <?php switch ($field['type']) :
                        case 'textarea': ?>
                            <textarea class="fr-textarea" id="<?php echo esc_attr($field_id); ?>" placeholder="<?php echo esc_attr($field['placeholder']); ?>"<?php echo esc_attr( $required ); ?>></textarea>
                            <?php break;
                        
                        case 'select': ?>
                            <select class="fr-select" id="<?php echo esc_attr($field_id); ?>"<?php echo esc_attr( $required ); ?>>
                                <option value=""><?php esc_html_e('Select...', 'form-relayer'); ?></option>
                                <?php foreach (explode(',', $field['options']) as $option) : ?>
                                <option value="<?php echo esc_attr(trim($option)); ?>"><?php echo esc_html(trim($option)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php break;
                        
                        case 'radio': ?>
                            <div class="fr-radio-group">
                                <?php foreach (explode(',', $field['options']) as $i => $option) : ?>
                                <label class="fr-radio-label">
                                    <input type="radio" name="<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr(trim($option)); ?>"<?php echo $i === 0 && $field['required'] ? ' required' : ''; ?>>
                                    <?php echo esc_html(trim($option)); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php break;
                        
                        case 'checkbox':
                            if (!empty($field['options'])) : ?>
                            <div class="fr-checkbox-group">
                                <?php foreach (explode(',', $field['options']) as $option) : ?>
                                <label class="fr-checkbox-label">
                                    <input type="checkbox" name="<?php echo esc_attr($field_id); ?>[]" value="<?php echo esc_attr(trim($option)); ?>">
                                    <?php echo esc_html(trim($option)); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php else : ?>
                            <label class="fr-checkbox-label">
                                <input type="checkbox" id="<?php echo esc_attr($field_id); ?>"<?php echo esc_attr( $required ); ?>>
                                <?php echo esc_html($field['label']); ?>
                            </label>
                            <?php endif;
                            break;
                        
                        case 'password': ?>
                            <input type="password" class="fr-input" id="<?php echo esc_attr($field_id); ?>" placeholder="<?php echo esc_attr($field['placeholder']); ?>"<?php echo esc_attr( $required ); ?>>
                            <?php break;
                        
                        case 'file': ?>
                            <input type="file" class="fr-input" id="<?php echo esc_attr($field_id); ?>"<?php echo esc_attr( $required ); ?>>
                            <?php break;
                        
                        case 'hidden':
                            // Don't render hidden fields in preview
                            break;
                        
                        default: ?>
                            <input type="<?php echo esc_attr($field['type']); ?>" class="fr-input" id="<?php echo esc_attr($field_id); ?>" placeholder="<?php echo esc_attr($field['placeholder']); ?>"<?php echo esc_attr( $required ); ?>>
                    <?php endswitch; ?>
                </div>
                <?php endforeach; ?>
                
                <div class="fr-form-group">
                    <button type="submit" class="fr-submit"><?php echo esc_html($button_text); ?></button>
                </div>
            </form>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get modal CSS
     */
    private function get_modal_css() {
        return '
        /* Preview Modal */
        .fr-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 100000;
        }
        .fr-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
        }
        .fr-modal-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 12px;
            width: 90%;
            max-width: 900px;
            height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .fr-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        .fr-modal-header h2 {
            margin: 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .fr-modal-close {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            color: #666;
            font-size: 20px;
        }
        .fr-modal-close:hover {
            color: #dc3545;
        }
        .fr-modal-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        /* Device Toggle */
        .fr-preview-device-toggle {
            display: flex;
            justify-content: center;
            gap: 5px;
            padding: 10px;
            background: #f5f5f5;
            border-bottom: 1px solid #ddd;
        }
        .fr-device-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background: #fff;
            border-radius: 5px;
            cursor: pointer;
            color: #666;
            transition: all 0.2s;
        }
        .fr-device-btn:hover {
            background: #f0f0f0;
        }
        .fr-device-btn.active {
            background: #0073aa;
            border-color: #0073aa;
            color: #fff;
        }
        
        /* Preview Frame */
        .fr-preview-frame-container {
            flex: 1;
            background: #e5e5e5;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            overflow: auto;
        }
        .fr-preview-frame {
            background: #fff;
            border: none;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            transition: width 0.3s ease;
        }
        .fr-preview-frame.fr-device-desktop,
        .fr-preview-frame:not([class*="fr-device"]) {
            width: 100%;
            height: 100%;
        }
        .fr-preview-frame.fr-device-tablet {
            width: 768px;
            height: 100%;
            border-radius: 8px;
        }
        .fr-preview-frame.fr-device-mobile {
            width: 375px;
            height: 100%;
            border-radius: 8px;
        }
        
        /* Spin animation */
        .dashicons.spin {
            animation: fr-spin 1s linear infinite;
        }
        @keyframes fr-spin {
            100% { transform: rotate(360deg); }
        }
        ';
    }
}
