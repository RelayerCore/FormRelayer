<?php
/**
 * Form Handler (AJAX)
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Handler Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Form_Handler {
    
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
        add_action('wp_ajax_fr_submit_form', [$this, 'handle_submission']);
        add_action('wp_ajax_nopriv_fr_submit_form', [$this, 'handle_submission']);
    }
    
    /**
     * Handle form submission
     */
    public function handle_submission() {
        // Verify nonce
        if (!check_ajax_referer('fr_form_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security verification failed. Please refresh the page and try again.', 'form-relayer'),
            ]);
        }
        
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        if (!$form_id || get_post_type($form_id) !== 'fr_form') {
            wp_send_json_error([
                'message' => __('Invalid form.', 'form-relayer'),
            ]);
        }
        
        // Honeypot check
        if (!empty($_POST['website_url'])) {
            wp_send_json_error([
                'message' => __('Spam detected.', 'form-relayer'),
            ]);
        }
        
        // Rate limiting (simple IP-based)
        if ($this->is_rate_limited()) {
            wp_send_json_error([
                'message' => __('Too many submissions. Please try again in a few minutes.', 'form-relayer'),
            ]);
        }
        
        // reCAPTCHA Validation
        if ($this->is_spam($_POST)) {
            wp_send_json_error([
                'message' => __('Spam detected. Please try again.', 'form-relayer'),
            ]);
        }
        
        // Sanitize inputs
        // Dynamic Fields Processing
        // Dynamic Fields Processing
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array processed in loop
        $raw_fields = isset($_POST['fr_c_fields']) ? wp_unslash($_POST['fr_c_fields']) : [];
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array processed in loop
        $field_labels = isset($_POST['fr_c_labels']) ? wp_unslash($_POST['fr_c_labels']) : [];
        $data = [];
        
        // Flatten for data array
        foreach ($raw_fields as $key => $value) {
            $label = $field_labels[$key] ?? $key;
            if (is_array($value)) {
                $value = implode(', ', array_map('sanitize_text_field', $value));
            } else {
                $value = sanitize_text_field($value);
            }
            $data[$key] = $value;
            $data['__label_' . $key] = $label;
        }
        
        // Intelligent mapping to standard keys (Name, Email, Subject) for system use
        // Look for fields with type 'email' or label containing 'Email'
        $data['email'] = '';
        $data['name'] = __('Visitor', 'form-relayer');
        $data['subject'] = __('New Submission', 'form-relayer');
        $data['message'] = '';
        
        // Try to map by field keys if we used standard ones in fallback
        if (isset($data['email'])) $data['email'] = $data['email'];
        if (isset($data['name'])) $data['name'] = $data['name'];
        if (isset($data['message'])) $data['message'] = $data['message'];
        
        // Heuristic mapping if using custom keys
        foreach ($data as $key => $val) {
            $label_lower = strtolower($data['__label_' . $key] ?? '');
            if (empty($data['email']) && (strpos($key, 'email') !== false || strpos($label_lower, 'email') !== false)) {
                $data['email'] = $val;
            }
            if ($data['name'] === 'Visitor' && (strpos($key, 'name') !== false || strpos($label_lower, 'name') !== false)) {
                $data['name'] = $val;
            }
            if (empty($data['message']) && (strpos($key, 'message') !== false || strpos($type ?? '', 'area') !== false)) {
                $data['message'] = $val;
            }
        }
        
        // Fallback for Subject
        // translators: %s is the submitter's name
        $data['subject'] = sprintf(__('Form Submission from %s', 'form-relayer'), $data['name']);

        $errors = [];
        if (empty($data['email']) || !is_email($data['email'])) {
            $errors[] = __('Please provide a valid email address.', 'form-relayer');
        }
        
        // Check required fields based on Form Definition
        $form_def_fields = get_post_meta($form_id, '_fr_fields', true);
        if (is_array($form_def_fields)) {
            foreach ($form_def_fields as $fdef) {
                if (!empty($fdef['required']) && empty($raw_fields[$fdef['id']])) {
                    // translators: %s is the field label
                    $errors[] = sprintf(__('%s is required.', 'form-relayer'), $fdef['label']);
                }
            }
        }

        if (!empty($errors)) {
             wp_send_json_error([
                'message' => implode(' ', $errors),
            ]);
        }
        
        // Save submission
        $submission_data = [
            'post_title'  => $data['subject'],
            'post_type'   => 'fr_submission',
            'post_status' => 'publish',
        ];
        
        $post_id = wp_insert_post($submission_data);
        
        if ($post_id) {
            // Save standard meta
            update_post_meta($post_id, '_fr_name', $data['name']);
            update_post_meta($post_id, '_fr_email', $data['email']);
            update_post_meta($post_id, '_fr_message', $data['message']);
            // Save full payload for display
            update_post_meta($post_id, '_fr_payload', $data);
            
            update_post_meta($post_id, '_fr_status', 'new');
            update_post_meta($post_id, '_parent_form_id', $form_id);
            
            // Send email notification (Use Form Settings)
            $recipient = get_post_meta($form_id, '_fr_email', true);
            FR_Email::get_instance()->send_admin_notification($data, $recipient);
        }
        
        // Send auto-reply (uses per-form settings if override enabled, else global)
        FR_Email::get_instance()->send_auto_reply($data['email'], $data['name'], $data['subject'], $form_id);
        
        // Pro: Handle file uploads
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        do_action('fr_after_submission', $post_id, $data, $_POST);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        do_action('fr_submission_created', $post_id, $data); // Webhook trigger
        
        // Update rate limit
        $this->update_rate_limit();
        
        // Success response
        $success_message = get_post_meta($form_id, '_fr_success_message', true) ?: get_option('fr_success_message', __('Thank you for your message! We\'ll get back to you within 24 hours.', 'form-relayer'));
        $redirect_url = get_post_meta($form_id, '_fr_redirect_url', true);
        
        $response = [
            'message' => $success_message,
        ];
        
        // Add redirect URL if set
        if (!empty($redirect_url)) {
            $response['redirect_url'] = esc_url($redirect_url);
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Validate form data
     *
     * @param array $data Form data
     * @return array Validation errors
     */
    private function validate_data($data) {
        $errors = [];
        
        if (empty($data['name']) || strlen($data['name']) < 2) {
            $errors[] = __('Please enter your name (at least 2 characters).', 'form-relayer');
        }
        
        if (empty($data['email']) || !is_email($data['email'])) {
            $errors[] = __('Please enter a valid email address.', 'form-relayer');
        }
        
        if (empty($data['subject'])) {
            $errors[] = __('Please enter a subject.', 'form-relayer');
        }
        
        if (empty($data['message']) || strlen($data['message']) < 10) {
            $errors[] = __('Message must be at least 10 characters.', 'form-relayer');
        }
        
        // GDPR Validation
        if (get_option('fr_enable_gdpr', 0)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_submission
            if (empty($_POST['fr_gdpr'])) {
                $errors[] = __('Please accept the privacy policy to continue.', 'form-relayer');
            }
        }
        
        // Allow extensions to add validation
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        return apply_filters('fr_validation_errors', $errors, $data);
    }
    
    /**
     * Check if current IP is rate limited
     *
     * @return bool
     */
    private function is_rate_limited() {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $transient_key = 'fr_rate_' . md5($ip);
        $submissions = get_transient($transient_key);
        
        // Allow 5 submissions per hour by default
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        $limit = apply_filters('fr_rate_limit', 5);
        
        if ($submissions && $submissions >= $limit) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Update rate limit counter
     */
    private function update_rate_limit() {
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $transient_key = 'fr_rate_' . md5($ip);
        $submissions = get_transient($transient_key) ?: 0;
        
        // 1 hour expiry
        set_transient($transient_key, $submissions + 1, HOUR_IN_SECONDS);
    }

    /**
     * Check for spam using reCAPTCHA
     *
     * @param array $data Form data
     * @return bool True if spam
     */
    private function is_spam($data) {
        $secret_key = get_option('fr_recaptcha_secret_key');
        
        if (empty($secret_key)) {
            return false;
        }
        
        
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_submission
        $token = isset($_POST['fr_recaptcha_token']) ? sanitize_text_field(wp_unslash($_POST['fr_recaptcha_token'])) : '';
        
        if (empty($token)) {
            return true; // Token required if keys are set
        }
        
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $secret_key,
                'response' => $token,
                'remoteip' => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
            ],
        ]);
        
        if (is_wp_error($response)) {
            return false; // Fail open if Google is down? Or fail closed? true/false
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['success']) && $result['success'] && $result['score'] >= 0.5) {
            return false; // Not spam
        }
        
        return true; // Spam
    }
}
