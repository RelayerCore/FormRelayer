<?php

declare(strict_types=1);

namespace FormRelayer\Forms;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;
use FormRelayer\Security\Nonce;
use FormRelayer\Security\Sanitizer;
use FormRelayer\Email\Mailer;

/**
 * Form Submission Handler
 *
 * Handles all form submissions with proper security
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Handler
{
    private static ?Handler $instance = null;

    private function __construct()
    {
        add_action('wp_ajax_fr_submit_form', [$this, 'handleSubmission']);
        add_action('wp_ajax_nopriv_fr_submit_form', [$this, 'handleSubmission']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('formrelayer/v1', '/forms/(?P<id>\d+)/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'handleRestSubmission'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'id' => [
                    'validate_callback' => fn($param) => is_numeric($param),
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Handle AJAX form submission
     */
    public function handleSubmission(): void
    {
        // Verify nonce
        Nonce::verifyAjax('form_submit');

        // Rate limiting
        if (!$this->checkRateLimit()) {
            wp_send_json_error([
                'message' => __('Too many submissions. Please wait a moment.', 'form-relayer'),
                'code' => 'rate_limited',
            ], 429);
        }

        // Honeypot check
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified earlier in handleSubmission
        if (!$this->checkHoneypot($_POST)) {
            wp_send_json_error([
                'message' => __('Spam detected.', 'form-relayer'),
                'code' => 'spam_detected',
            ], 403);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified earlier, sanitized by method
        $formId = Sanitizer::int(wp_unslash($_POST['form_id'] ?? 0));
        
        if (!$formId) {
            wp_send_json_error([
                'message' => __('Invalid form.', 'form-relayer'),
                'code' => 'invalid_form',
            ], 400);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified earlier in handleSubmission
        $result = $this->processSubmission($formId, $_POST);

        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['data'], $result['status'] ?? 400);
        }
    }

    /**
     * Handle REST API form submission
     */
    public function handleRestSubmission(\WP_REST_Request $request): \WP_REST_Response
    {
        // Rate limiting
        if (!$this->checkRateLimit()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Too many submissions. Please wait a moment.', 'form-relayer'),
                'code' => 'rate_limited',
            ], 429);
        }

        $formId = (int) $request->get_param('id');
        $data = $request->get_json_params();

        $result = $this->processSubmission($formId, $data);

        return new \WP_REST_Response(
            $result['success'] ? $result['data'] : ['success' => false, ...$result['data']],
            $result['success'] ? 200 : ($result['status'] ?? 400)
        );
    }

    /**
     * Process form submission
     *
     * @param int $formId
     * @param array<string, mixed> $data
     * @return array{success: bool, data: array, status?: int}
     */
    private function processSubmission(int $formId, array $data): array
    {
        // Get form
        $form = get_post($formId);
        
        if (!$form || $form->post_type !== PostType::POST_TYPE) {
            return [
                'success' => false,
                'data' => ['message' => __('Form not found.', 'form-relayer'), 'code' => 'not_found'],
                'status' => 404,
            ];
        }

        // Get form fields - handle both legacy array and new JSON string format
        $fieldsRaw = get_post_meta($formId, '_fr_fields', true);
        
        if (is_array($fieldsRaw)) {
            $fields = $fieldsRaw;
        } elseif (is_string($fieldsRaw) && !empty($fieldsRaw)) {
            $fields = json_decode($fieldsRaw, true);
            $fields = is_array($fields) ? $fields : [];
        } else {
            $fields = [];
        }

        // Build field type mapping
        $fieldTypes = [];
        foreach ($fields as $field) {
            $fieldTypes[$field['id']] = $field['type'] ?? 'text';
        }

        // Sanitize submission data
        $sanitizedData = Sanitizer::formSubmission($data, $fieldTypes);

        // Validate fields (Format & Required)
        $validationErrors = Validator::validate($sanitizedData, $fields);
        
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'data' => [
                    'message' => __('Please fill in all required fields.', 'form-relayer'),
                    'code' => 'validation_failed',
                    'errors' => $validationErrors,
                ],
                'status' => 422,
            ];
        }

        // GDPR consent validation
        if (get_option('fr_gdpr_enabled', 0) && get_option('fr_gdpr_required', 1)) {
            if (empty($data['fr_gdpr_consent'])) {
                return [
                    'success' => false,
                    'data' => [
                        'message' => __('You must accept the privacy policy to submit this form.', 'form-relayer'),
                        'code' => 'gdpr_required',
                    ],
                    'status' => 422,
                ];
            }
        }

        // reCAPTCHA verification
        if (get_option('fr_recaptcha_enabled', 0)) {
            $recaptchaResult = $this->verifyRecaptcha($data['fr_recaptcha_token'] ?? '');
            if (!$recaptchaResult['success']) {
                return [
                    'success' => false,
                    'data' => [
                        'message' => $recaptchaResult['message'],
                        'code' => 'recaptcha_failed',
                    ],
                    'status' => 403,
                ];
            }
        }

        // Create submission
        $submissionId = $this->createSubmission($formId, $sanitizedData, $fields);

        if (!$submissionId) {
            return [
                'success' => false,
                'data' => ['message' => __('Failed to save submission.', 'form-relayer'), 'code' => 'save_failed'],
                'status' => 500,
            ];
        }

        // Send notification email
        $this->sendNotificationEmail($formId, $submissionId, $sanitizedData, $fields);

        // Send auto-reply if enabled
        $this->sendAutoReply($formId, $submissionId, $sanitizedData, $fields);

        // Allow hooks
        do_action('formrelayer_submission_complete', $submissionId, $formId, $sanitizedData);

        // Get success message
        $successMessage = get_post_meta($formId, '_fr_success_message', true) 
            ?: get_option('fr_success_message', __('Thank you for your submission!', 'form-relayer'));

        $redirectUrl = get_post_meta($formId, '_fr_redirect_url', true);

        return [
            'success' => true,
            'data' => [
                'message' => $successMessage,
                'submission_id' => $submissionId,
            'redirect_url' => $redirectUrl ?: null,
            ],
        ];
    }



    /**
     * Create submission post
     */
    private function createSubmission(int $formId, array $data, array $fields): int|false
    {
        // Build title from first text field or email
        $title = $this->buildSubmissionTitle($data, $fields);

        $submissionId = wp_insert_post([
            'post_type' => PostType::SUBMISSION_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_parent' => $formId,
        ]);

        if (is_wp_error($submissionId)) {
            return false;
        }

        // Store submission data
        update_post_meta($submissionId, '_fr_form_id', $formId);
        update_post_meta($submissionId, '_fr_submission_data', $data);
        update_post_meta($submissionId, '_fr_submitted_at', current_time('mysql'));
        update_post_meta($submissionId, '_fr_ip_address', $this->getIpAddress());
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by method
        update_post_meta($submissionId, '_fr_user_agent', Sanitizer::text(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')));

        // Store individual fields for easy querying
        foreach ($data as $key => $value) {
            update_post_meta($submissionId, '_fr_field_' . $key, $value);
        }

        return $submissionId;
    }

    /**
     * Build submission title
     */
    private function buildSubmissionTitle(array $data, array $fields): string
    {
        // Try email first
        foreach ($fields as $field) {
            if ($field['type'] === 'email' && !empty($data[$field['id']])) {
                return $data[$field['id']];
            }
        }

        // Then name
        foreach ($fields as $field) {
            if (in_array($field['type'], ['text', 'name']) && !empty($data[$field['id']])) {
                return $data[$field['id']];
            }
        }

        return __('Submission', 'form-relayer') . ' #' . time();
    }

    /**
     * Send notification email
     */
    private function sendNotificationEmail(int $formId, int $submissionId, array $data, array $fields): void
    {
        $email = get_post_meta($formId, '_fr_email', true) ?: get_option('fr_recipient_email');
        
        if (!$email) {
            return;
        }

        $formTitle = get_the_title($formId);
        // translators: %s is the form title
        $subject = sprintf(__('New submission from %s', 'form-relayer'), $formTitle);
        
        $body = $this->buildEmailBody($data, $fields);

        Mailer::getInstance()->send($email, $subject, $body, $submissionId, $formId);
    }

    /**
     * Send auto-reply email
     */
    private function sendAutoReply(int $formId, int $submissionId, array $data, array $fields): void
    {
        $enabled = get_post_meta($formId, '_fr_auto_reply_enabled', true) ?: get_option('fr_enable_auto_reply');
        
        if (!$enabled) {
            return;
        }

        // Find email field
        $recipientEmail = null;
        foreach ($fields as $field) {
            if ($field['type'] === 'email' && !empty($data[$field['id']])) {
                $recipientEmail = $data[$field['id']];
                break;
            }
        }

        if (!$recipientEmail) {
            return;
        }

        $subject = get_post_meta($formId, '_fr_auto_reply_subject', true) 
            ?: get_option('fr_auto_reply_subject', __('Thank you for contacting us', 'form-relayer'));
        
        $message = get_post_meta($formId, '_fr_auto_reply_message', true) 
            ?: get_option('fr_auto_reply_message', '');

        // Replace placeholders
        $message = $this->replacePlaceholders($message, $data, $fields);

        Mailer::getInstance()->sendAutoReply($recipientEmail, $subject, $message, $submissionId, $formId);
    }

    /**
     * Build email body from submission data
     */
    private function buildEmailBody(array $data, array $fields): string
    {
        $body = '<table style="width:100%;border-collapse:collapse;">';

        foreach ($fields as $field) {
            $fieldId = $field['id'] ?? '';
            $label = $field['label'] ?? $fieldId;
            $value = $data[$fieldId] ?? '';

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $body .= sprintf(
                '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">%s</td><td style="padding:8px;border:1px solid #ddd;">%s</td></tr>',
                esc_html($label),
                nl2br(esc_html($value))
            );
        }

        $body .= '</table>';

        return $body;
    }

    /**
     * Replace placeholders in message
     */
    private function replacePlaceholders(string $message, array $data, array $fields): string
    {
        // Replace field placeholders
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        // Replace common placeholders
        $message = str_replace('{site_name}', get_bloginfo('name'), $message);
        $message = str_replace('{site_url}', home_url(), $message);
        $message = str_replace('{date}', current_time('F j, Y'), $message);

        return $message;
    }

    /**
     * Check rate limit using settings
     */
    private function checkRateLimit(): bool
    {
        $ip = $this->getIpAddress();
        $key = 'fr_rate_' . md5($ip);
        $limit = (int) get_option('fr_rate_limit', 5);
        $window = (int) get_option('fr_rate_window', 3600); // Default: 1 hour

        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, $window);
        return true;
    }

    /**
     * Check honeypot field
     */
    private function checkHoneypot(array $data): bool
    {
        if (!get_option('fr_honeypot_enabled', 1)) {
            return true; // Honeypot disabled
        }

        // If honeypot field is filled, it's a bot
        if (!empty($data['fr_website_url'])) {
            return false;
        }

        return true;
    }

    /**
     * Verify reCAPTCHA v3 token
     *
     * @return array{success: bool, message: string, score?: float}
     */
    private function verifyRecaptcha(string $token): array
    {
        if (empty($token)) {
            return [
                'success' => false,
                'message' => __('reCAPTCHA verification required.', 'form-relayer'),
            ];
        }

        $secretKey = get_option('fr_recaptcha_secret_key', '');
        
        if (empty($secretKey)) {
            return ['success' => true, 'message' => 'No secret key configured']; // Skip if not configured
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $this->getIpAddress(),
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            // On API failure, allow submission (fail open)
            return ['success' => true, 'message' => 'API error, allowing submission'];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['success'])) {
            return [
                'success' => false,
                'message' => __('reCAPTCHA verification failed. Please try again.', 'form-relayer'),
            ];
        }

        $threshold = (float) get_option('fr_recaptcha_threshold', 0.5);
        $score = $body['score'] ?? 0;

        if ($score < $threshold) {
            return [
                'success' => false,
                'message' => __('Spam detected. Please try again.', 'form-relayer'),
                'score' => $score,
            ];
        }

        return [
            'success' => true,
            'message' => 'OK',
            'score' => $score,
        ];
    }

    /**
     * Get client IP address
     */
    private function getIpAddress(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                // Handle comma-separated IPs (X-Forwarded-For)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
