<?php
/**
 * Form Templates
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Templates Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Form_Templates {
    
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
        add_action('admin_menu', [$this, 'add_templates_submenu'], 15);
        add_action('wp_ajax_fr_apply_template', [$this, 'ajax_apply_template']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Add templates submenu
     */
    public function add_templates_submenu() {
        add_submenu_page(
            'edit.php?post_type=fr_form',
            __('Form Templates', 'form-relayer'),
            __('Templates', 'form-relayer'),
            'edit_posts',
            'fr-templates',
            [$this, 'render_templates_page']
        );
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Hook handler
        if (!isset($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== 'fr-templates') {
            return;
        }
        
        wp_enqueue_style('fr-templates', FR_PLUGIN_URL . 'assets/css/admin.css', [], FR_VERSION);
    }
    
    /**
     * Render templates page
     */
    public function render_templates_page() {
        $templates = $this->get_templates();
        ?>
        <div class="wrap fr-templates-wrap">
            <h1>
                <span class="dashicons dashicons-forms" style="margin-right: 8px;"></span>
                <?php esc_html_e('Form Templates', 'form-relayer'); ?>
            </h1>
            <p class="description" style="margin-bottom: 25px;">
                <?php esc_html_e('Choose a template to create a new form. You can customize it after creation.', 'form-relayer'); ?>
            </p>
            
            <div class="fr-templates-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php foreach ($templates as $id => $template) : 
                    $is_pro = !empty($template['pro']);
                    $has_pro = defined('FR_PRO_VERSION');
                    $locked = $is_pro && !$has_pro;
                ?>
                <div class="fr-template-card<?php echo $locked ? ' fr-locked' : ''; ?>" style="background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 25px; transition: box-shadow 0.2s ease, transform 0.2s ease;<?php echo $locked ? ' opacity: 0.85;' : ''; ?>">
                    <?php if ($is_pro) : ?>
                    <span style="position: absolute; top: 10px; right: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 3px; text-transform: uppercase;">PRO</span>
                    <?php endif; ?>
                    <div class="fr-template-icon" style="font-size: 48px; margin-bottom: 15px;">
                        <?php echo esc_html($template['icon']); ?>
                    </div>
                    <h3 style="margin: 0 0 10px; font-size: 18px;"><?php echo esc_html($template['name']); ?></h3>
                    <p style="color: #646970; margin: 0 0 15px; min-height: 40px;"><?php echo esc_html($template['description']); ?></p>
                    <div class="fr-template-fields" style="margin-bottom: 15px;">
                        <strong style="font-size: 12px; color: #1d2327;"><?php esc_html_e('Fields:', 'form-relayer'); ?></strong>
                        <span style="font-size: 12px; color: #646970;">
                            <?php echo esc_html(implode(', ', array_column($template['fields'], 'label'))); ?>
                        </span>
                    </div>
                    <?php if ($locked) : ?>
                    <a href="https://formrelayer.com/pro" target="_blank" class="button" style="width: 100%; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none;">
                        <?php esc_html_e('Upgrade to Pro', 'form-relayer'); ?>
                    </a>
                    <?php else : ?>
                    <button type="button" 
                            class="button button-primary fr-use-template" 
                            data-template="<?php echo esc_attr($id); ?>"
                            style="width: 100%;">
                        <?php esc_html_e('Use This Template', 'form-relayer'); ?>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <!-- Blank Form Card -->
                <div class="fr-template-card" style="background: #f6f7f7; border: 2px dashed #c3c4c7; border-radius: 8px; padding: 25px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 200px;">
                    <div class="fr-template-icon" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;">➕</div>
                    <h3 style="margin: 0 0 10px; font-size: 18px;"><?php esc_html_e('Blank Form', 'form-relayer'); ?></h3>
                    <p style="color: #646970; margin: 0 0 15px; text-align: center;"><?php esc_html_e('Start fresh with an empty form.', 'form-relayer'); ?></p>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=fr_form')); ?>" class="button">
                        <?php esc_html_e('Create Blank Form', 'form-relayer'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(function($) {
            $('.fr-use-template').on('click', function() {
                var $btn = $(this);
                var templateId = $btn.data('template');
                
                $btn.prop('disabled', true).text('<?php echo esc_js(__('Creating...', 'form-relayer')); ?>');
                
                $.post(ajaxurl, {
                    action: 'fr_apply_template',
                    template: templateId,
                    nonce: '<?php echo esc_attr( wp_create_nonce('fr_templates') ); ?>'
                }, function(response) {
                    if (response.success && response.data.edit_url) {
                        window.location.href = response.data.edit_url;
                    } else {
                        alert(response.data.message || 'Error creating form');
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Use This Template', 'form-relayer')); ?>');
                    }
                });
            });
            
            // Card hover effects
            $('.fr-template-card').hover(
                function() { $(this).css({'box-shadow': '0 4px 12px rgba(0,0,0,0.1)', 'transform': 'translateY(-2px)'}); },
                function() { $(this).css({'box-shadow': 'none', 'transform': 'none'}); }
            );
        });
        </script>
        <?php
    }
    
    /**
     * Get available templates
     *
     * @return array
     */
    public function get_templates() {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        return apply_filters('fr_form_templates', [
            'contact' => [
                'name'        => __('Contact Form', 'form-relayer'),
                'description' => __('Simple contact form with name, email, and message fields.', 'form-relayer'),
                'icon'        => '📧',
                'fields'      => [
                    ['id' => 'name', 'type' => 'text', 'label' => __('Full Name', 'form-relayer'), 'required' => 1],
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email Address', 'form-relayer'), 'required' => 1],
                    ['id' => 'phone', 'type' => 'phone', 'label' => __('Phone Number', 'form-relayer'), 'required' => 0],
                    ['id' => 'message', 'type' => 'textarea', 'label' => __('Your Message', 'form-relayer'), 'required' => 1],
                ],
                'settings'    => [
                    'button_text'     => __('Send Message', 'form-relayer'),
                    'success_message' => __('Thank you for reaching out! We\'ll get back to you within 24 hours.', 'form-relayer'),
                ],
            ],
            
            'quote' => [
                'name'        => __('Quote Request', 'form-relayer'),
                'description' => __('Request a quote with service selection and budget fields.', 'form-relayer'),
                'icon'        => '💰',
                'fields'      => [
                    ['id' => 'name', 'type' => 'text', 'label' => __('Your Name', 'form-relayer'), 'required' => 1],
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email Address', 'form-relayer'), 'required' => 1],
                    ['id' => 'company', 'type' => 'text', 'label' => __('Company Name', 'form-relayer'), 'required' => 0],
                    ['id' => 'service', 'type' => 'select', 'label' => __('Service Interested In', 'form-relayer'), 'required' => 1, 
                     'options' => __('Web Design, Development, Marketing, Consulting, Other', 'form-relayer')],
                    ['id' => 'budget', 'type' => 'select', 'label' => __('Budget Range', 'form-relayer'), 'required' => 0,
                     'options' => __('Under $1000, $1000-$5000, $5000-$10000, $10000+', 'form-relayer')],
                    ['id' => 'details', 'type' => 'textarea', 'label' => __('Project Details', 'form-relayer'), 'required' => 1],
                ],
                'settings'    => [
                    'button_text'     => __('Request Quote', 'form-relayer'),
                    'success_message' => __('Thanks for your quote request! We\'ll review your requirements and send a custom proposal within 48 hours.', 'form-relayer'),
                ],
            ],
            
            'newsletter' => [
                'name'        => __('Newsletter Signup', 'form-relayer'),
                'description' => __('Simple email signup form for newsletter subscriptions.', 'form-relayer'),
                'icon'        => '📰',
                'fields'      => [
                    ['id' => 'name', 'type' => 'text', 'label' => __('First Name', 'form-relayer'), 'required' => 0],
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email Address', 'form-relayer'), 'required' => 1],
                ],
                'settings'    => [
                    'button_text'     => __('Subscribe', 'form-relayer'),
                    'success_message' => __('You\'re subscribed! Check your inbox for a confirmation email.', 'form-relayer'),
                ],
            ],
            
            'booking' => [
                'name'        => __('Appointment Booking', 'form-relayer'),
                'description' => __('Request an appointment or consultation with date/time.', 'form-relayer'),
                'icon'        => '📅',
                'fields'      => [
                    ['id' => 'name', 'type' => 'text', 'label' => __('Full Name', 'form-relayer'), 'required' => 1],
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email Address', 'form-relayer'), 'required' => 1],
                    ['id' => 'phone', 'type' => 'phone', 'label' => __('Phone Number', 'form-relayer'), 'required' => 1],
                    ['id' => 'date', 'type' => 'date', 'label' => __('Preferred Date', 'form-relayer'), 'required' => 1],
                    ['id' => 'time', 'type' => 'select', 'label' => __('Preferred Time', 'form-relayer'), 'required' => 1,
                     'options' => __('9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 2:00 PM, 3:00 PM, 4:00 PM', 'form-relayer')],
                    ['id' => 'notes', 'type' => 'textarea', 'label' => __('Additional Notes', 'form-relayer'), 'required' => 0],
                ],
                'settings'    => [
                    'button_text'     => __('Request Appointment', 'form-relayer'),
                    'success_message' => __('Appointment request received! We\'ll confirm your booking via email shortly.', 'form-relayer'),
                ],
            ],
            
            'feedback' => [
                'name'        => __('Feedback Form', 'form-relayer'),
                'description' => __('Collect customer feedback with rating and comments.', 'form-relayer'),
                'icon'        => '⭐',
                'fields'      => [
                    ['id' => 'name', 'type' => 'text', 'label' => __('Your Name', 'form-relayer'), 'required' => 0],
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email (optional)', 'form-relayer'), 'required' => 0],
                    ['id' => 'rating', 'type' => 'select', 'label' => __('How would you rate your experience?', 'form-relayer'), 'required' => 1,
                     'options' => __('⭐ Poor, ⭐⭐ Fair, ⭐⭐⭐ Good, ⭐⭐⭐⭐ Very Good, ⭐⭐⭐⭐⭐ Excellent', 'form-relayer')],
                    ['id' => 'feedback', 'type' => 'textarea', 'label' => __('Your Feedback', 'form-relayer'), 'required' => 1],
                    ['id' => 'recommend', 'type' => 'radio', 'label' => __('Would you recommend us?', 'form-relayer'), 'required' => 1,
                     'options' => __('Yes, No, Maybe', 'form-relayer')],
                ],
                'settings'    => [
                    'button_text'     => __('Submit Feedback', 'form-relayer'),
                    'success_message' => __('Thank you for your feedback! It helps us improve.', 'form-relayer'),
                ],
            ],
        ]);
    }
    
    /**
     * AJAX: Apply template
     */
    public function ajax_apply_template() {
        check_ajax_referer('fr_templates', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'form-relayer')]);
        }
        
        
        $template_id = sanitize_text_field(wp_unslash($_POST['template'] ?? ''));
        $templates = $this->get_templates();
        
        if (!isset($templates[$template_id])) {
            wp_send_json_error(['message' => __('Template not found.', 'form-relayer')]);
        }
        
        $template = $templates[$template_id];
        
        // Create new form
        $post_id = wp_insert_post([
            'post_title'  => $template['name'],
            'post_type'   => 'fr_form',
            'post_status' => 'publish',
        ]);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => $post_id->get_error_message()]);
        }
        
        // Save fields
        update_post_meta($post_id, '_fr_fields', $template['fields']);
        
        // Save settings
        if (!empty($template['settings']['button_text'])) {
            update_post_meta($post_id, '_fr_button_text', $template['settings']['button_text']);
        }
        if (!empty($template['settings']['success_message'])) {
            update_post_meta($post_id, '_fr_success_message', $template['settings']['success_message']);
        }
        
        // Set default email
        update_post_meta($post_id, '_fr_email', get_option('admin_email'));
        
        // Set form type for special templates (registration, login)
        if (!empty($template['form_type'])) {
            update_post_meta($post_id, '_fr_form_type', sanitize_text_field($template['form_type']));
        }
        
        wp_send_json_success([
            'message'  => __('Form created successfully!', 'form-relayer'),
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
        ]);
    }
}
