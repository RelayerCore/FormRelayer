<?php
/**
 * Shortcode Handler
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Shortcode {
    
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
        add_shortcode('form_relayer', [$this, 'render']);
        add_shortcode('formrelayer', [$this, 'render']); // Alias
    }
    
    /**
     * Render the contact form
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render($atts) {
        // DEBUG: Shortcode is being called!
        // Uncomment the line below to verify shortcode is registered:
        // return '<p style="color:red;font-weight:bold;">SHORTCODE IS WORKING!</p>';
        
        $atts = shortcode_atts([
            'id'          => 0,
            'location'    => '',
            'button_text' => '',
            'show_phone'  => 'true',
            'show_location' => 'true',
        ], $atts, 'form_relayer');
        
        // Resolve Form ID
        $form_id = absint($atts['id']);
        if (empty($form_id)) {
            // Fallback: Get most recent form
            $recent_forms = get_posts([
                'post_type' => 'fr_form',
                'posts_per_page' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids'
            ]);
            $form_id = !empty($recent_forms) ? $recent_forms[0] : 0;
        }
        
        if (empty($form_id)) {
            return '<p>' . __('Form not found.', 'form-relayer') . '</p>';
        }
        
        // Get Form Settings
        $button_text = get_post_meta($form_id, '_fr_button_text', true);
        if (empty($atts['button_text']) && !empty($button_text)) {
            $atts['button_text'] = $button_text;
        } elseif (empty($atts['button_text'])) {
            $atts['button_text'] = __('Send Message', 'form-relayer');
        }
        
        // Get dynamic locations from settings (Global for now, could be per form later)
        $locations = get_option('fr_form_locations', []);
        
        // Get design settings from Form Meta (or fall back to global)
        $design_override = get_post_meta($form_id, '_fr_design_override', true);
        
        if ($design_override) {
            $primary_color = get_post_meta($form_id, '_fr_primary_color', true) ?: '#0073aa';
            $max_width = get_post_meta($form_id, '_fr_max_width', true) ?: '700';
            $form_layout = get_post_meta($form_id, '_fr_form_layout', true) ?: 'single';
            $wrapper_style = get_post_meta($form_id, '_fr_wrapper_style', true) ?: 'card';
            $button_style = get_post_meta($form_id, '_fr_button_style', true) ?: 'filled';
        } else {
            $primary_color = get_option('fr_primary_color', '#0073aa');
            $max_width = '700';
            $form_layout = 'single';
            $wrapper_style = 'card';
            $button_style = 'filled';
        }
        
        // Build wrapper classes
        $wrapper_classes = ['fr-form-wrapper'];
        $wrapper_classes[] = 'fr-layout-' . $form_layout;
        $wrapper_classes[] = 'fr-style-' . $wrapper_style;
        
        // Build button classes
        $button_classes = ['fr-submit-btn'];
        $button_classes[] = 'fr-btn-' . $button_style;
        
        // Inline styles for customization
        $wrapper_inline_style = sprintf(
            '--fr-primary: %s; --fr-max-width: %spx;',
            esc_attr($primary_color),
            esc_attr($max_width)
        );
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" id="fr-form-<?php echo esc_attr(uniqid()); ?>" style="<?php echo esc_attr($wrapper_inline_style); ?>">
            <div id="fr-message" class="fr-message" style="display: none;" role="alert"></div>
            
            <form class="fr-form fr-layout-<?php echo esc_attr($form_layout); ?>" method="post" data-form-id="<?php echo esc_attr($form_id); ?>">
                <?php wp_nonce_field('fr_form_nonce', 'fr_nonce'); ?>
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
                
                <!-- Honeypot -->
                <div style="position: absolute; left: -9999px;" aria-hidden="true">
                    <input type="text" name="website_url" tabindex="-1" autocomplete="off">
                </div>
                
                <?php if (get_option('fr_recaptcha_site_key')) : ?>
                    <input type="hidden" name="fr_recaptcha_token" class="fr-recaptcha-token">
                <?php endif; ?>
                
                <?php
                // Get Fields
                $fields = get_post_meta($form_id, '_fr_fields', true);
                
                // Backwards Compatibility: If no fields defined, show default
                if (empty($fields) || !is_array($fields)) {
                    $fields = [
                        ['id' => 'name', 'type' => 'text', 'label' => __('Name', 'form-relayer'), 'required' => 1, 'width' => 'half'],
                        ['id' => 'email', 'type' => 'email', 'label' => __('Email Address', 'form-relayer'), 'required' => 1, 'width' => 'half'],
                        ['id' => 'phone', 'type' => 'tel', 'label' => __('Phone Number', 'form-relayer'), 'required' => 0, 'width' => 'half'],
                        ['id' => 'location', 'type' => 'select', 'label' => __('Location', 'form-relayer'), 'required' => 0, 'options' => implode(',', $locations), 'width' => 'half'],
                        ['id' => 'message', 'type' => 'textarea', 'label' => __('Message', 'form-relayer'), 'required' => 1, 'width' => 'full'],
                    ];
                }
                
                echo '<div class="fr-form-fields">';
                
                // Render Fields
                foreach ($fields as $field) {
                    $field_id = esc_attr($field['id'] ?? uniqid('fr_'));
                    $type = esc_attr($field['type'] ?? 'text');
                    $label = esc_html($field['label'] ?? '');
                    $required = !empty($field['required']);
                    $width = esc_attr($field['width'] ?? 'full');
                    $req_attr = $required ? 'required' : '';
                    $req_span = $required ? ' <span class="fr-required">*</span>' : '';
                    $placeholder = esc_attr($field['placeholder'] ?? $label);
                    
                    // Width class for Pro multi-column layouts
                    $width_class = 'fr-field-width-' . $width;
                    
                    echo '<div class="fr-form-group field-' . esc_attr( $type ) . ' ' . esc_attr( $width_class ) . '">';
                    
                    // Skip label for hidden and checkbox (single)
                    if ($type !== 'hidden') {
                        echo '<label class="fr-label" for="fr_' . esc_attr( $field_id ) . '">' . esc_html( $label ) . wp_kses_post( $req_span ) . '</label>';
                    }
                    
                    switch ($type) {
                        case 'textarea':
                            echo '<textarea class="fr-input fr-textarea" name="fr_c_fields[' . esc_attr( $field_id ) . ']" id="fr_' . esc_attr( $field_id ) . '" ' . esc_attr( $req_attr ) . ' rows="4" placeholder="' . esc_attr( $placeholder ) . '"></textarea>';
                            break;
                            
                        case 'select':
                            echo '<select class="fr-input fr-select" name="fr_c_fields[' . esc_attr( $field_id ) . ']" id="fr_' . esc_attr( $field_id ) . '" ' . esc_attr( $req_attr ) . '>';
                            echo '<option value="">' . esc_html__('Select...', 'form-relayer') . '</option>';
                            // Support both comma and newline separated options
                            $options_raw = $field['options'] ?? '';
                            $options = preg_split('/[\n\r,]+/', $options_raw, -1, PREG_SPLIT_NO_EMPTY);
                            foreach ($options as $opt) {
                                $opt = trim($opt);
                                if ($opt) echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                            }
                            echo '</select>';
                            break;
                            
                        case 'radio':
                            $options = preg_split('/[\n\r,]+/', $field['options'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
                            echo '<div class="fr-radio-group">';
                            foreach ($options as $idx => $opt) {
                                $opt = trim($opt);
                                if (!$opt) continue;
                                $rid = 'fr_' . $field_id . '_' . $idx;
                                echo '<label class="fr-radio-label" for="' . esc_attr($rid) . '">';
                                echo '<input type="radio" name="fr_c_fields[' . esc_attr( $field_id ) . ']" id="' . esc_attr($rid) . '" value="' . esc_attr($opt) . '" ' . esc_attr( $req_attr ) . '> ';
                                echo esc_html($opt);
                                echo '</label>';
                            }
                            echo '</div>';
                            break;
                            
                        case 'checkbox':
                             $options = preg_split('/[\n\r,]+/', $field['options'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
                             // If multiple options, treat as array
                             if (count($options) > 1 || !empty($field['options'])) {
                                 echo '<div class="fr-checkbox-group">';
                                 foreach ($options as $idx => $opt) {
                                    $opt = trim($opt);
                                    if (!$opt) continue;
                                    $rid = 'fr_' . $field_id . '_' . $idx;
                                    echo '<label class="fr-checkbox-label" for="' . esc_attr($rid) . '">';
                                    echo '<input type="checkbox" name="fr_c_fields[' . esc_attr( $field_id ) . '][]" id="' . esc_attr($rid) . '" value="' . esc_attr($opt) . '"> ';
                                    echo esc_html($opt);
                                    echo '</label>';
                                 }
                                 echo '</div>';
                             } else {
                                // Single checkbox (e.g. "I agree")
                                echo '<label class="fr-checkbox-label" for="fr_' . esc_attr( $field_id ) . '">';
                                echo '<input type="checkbox" name="fr_c_fields[' . esc_attr( $field_id ) . ']" id="fr_' . esc_attr( $field_id ) . '" value="1" ' . esc_attr( $req_attr ) . '> ';
                                echo esc_html($label);
                                echo '</label>';
                             }
                             break;
                             
                        case 'file':
                            echo '<input class="fr-input fr-file" type="file" name="fr_c_fields[' . esc_attr( $field_id ) . ']" id="fr_' . esc_attr( $field_id ) . '" ' . esc_attr( $req_attr ) . '>';
                            break;
                        
                        case 'hidden':
                            // Hidden fields don't show label
                            echo '<input type="hidden" name="fr_c_fields[' . esc_attr( $field_id ) . ']" id="fr_' . esc_attr( $field_id ) . '" value="' . esc_attr($field['options'] ?? '') . '">';
                            break;
                        
                        case 'color':
                            echo '<input class="fr-input fr-color" type="color" name="fr_c_fields[' . esc_attr( $field_id ) . ']" id="fr_' . esc_attr( $field_id ) . '" value="#6366f1">';
                            break;
                        
                        case 'range':
                            echo '<input class="fr-input fr-range" type="range" name="fr_c_fields[' . esc_attr( $field_id ) . ']" id="fr_' . esc_attr( $field_id ) . '" min="0" max="100" value="50" oninput="this.nextElementSibling.textContent=this.value">';
                            echo '<span class="fr-range-value">50</span>';
                            break;
                            
                        default: // text, email, number, date, time, datetime-local, tel, url, password
                            echo '<input class="fr-input" type="' . esc_attr( $type ) . '" name="fr_c_fields[' . esc_attr( $field_id ) . ']" id="fr_' . esc_attr( $field_id ) . '" placeholder="' . esc_attr( $placeholder ) . '" ' . esc_attr( $req_attr ) . '>';
                    }
                    
                    // Add hidden label input for email processing
                    echo '<input type="hidden" name="fr_c_labels[' . esc_attr( $field_id ) . ']" value="' . esc_attr($label) . '">';
                    
                    echo '</div>';
                }
                
                echo '</div>'; // .fr-form-fields
                
                // reCAPTCHA Token
                if (get_option('fr_recaptcha_site_key')) :
                    echo '<input type="hidden" name="fr_recaptcha_token" class="fr-recaptcha-token">';
                endif;
                ?>
                
                <button type="submit" class="<?php echo esc_attr(implode(' ', $button_classes)); ?>" id="fr-submit-btn">
                    <span class="fr-btn-text"><?php echo esc_html($atts['button_text']); ?></span>
                    <span class="fr-btn-loading" style="display: none;">
                        <span class="fr-btn-loader"></span>
                        <?php esc_html_e('Sending...', 'form-relayer'); ?>
                    </span>
                </button>
                
                <!-- GDPR Consent -->
                <?php if (get_option('fr_enable_gdpr')) : ?>
                    <div class="fr-gdpr-wrap">
                        <label>
                            <input type="checkbox" name="fr_gdpr_consent" required>
                            <?php echo esc_html(get_option('fr_gdpr_message', __('I consent to having this website store my submitted information.', 'form-relayer'))); ?>
                        </label>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
