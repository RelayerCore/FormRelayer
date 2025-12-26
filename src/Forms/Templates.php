<?php

declare(strict_types=1);

namespace FormRelayer\Forms;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;

/**
 * Form Templates
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Templates
{
    private static ?Templates $instance = null;

    /** @var array<string, array> */
    private array $templates = [];

    private function __construct()
    {
        $this->registerDefaultTemplates();
        add_action('admin_menu', [$this, 'addMenu'], 15);
        add_action('admin_init', [$this, 'handleTemplateCreation']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add templates submenu
     */
    public function addMenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostType::POST_TYPE,
            __('Form Templates', 'form-relayer'),
            __('Templates', 'form-relayer'),
            'edit_posts',
            'fr-templates',
            [$this, 'renderPage']
        );
    }

    /**
     * Render templates page
     */
    public function renderPage(): void
    {
        $templates = $this->getTemplates();
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
                    $isPro = !empty($template['pro']);
                    $hasPro = Plugin::isPro();
                    $locked = $isPro && !$hasPro;
                ?>
                <div class="fr-template-card<?php echo $locked ? ' fr-locked' : ''; ?>" 
                     style="background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 25px; 
                            transition: box-shadow 0.2s ease, transform 0.2s ease; position: relative;
                            <?php echo $locked ? 'opacity: 0.85;' : ''; ?>">
                    <?php if ($isPro) : ?>
                    <span style="position: absolute; top: 10px; right: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                 color: #fff; font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 3px; text-transform: uppercase;">PRO</span>
                    <?php endif; ?>
                    <div class="fr-template-icon" style="font-size: 48px; margin-bottom: 15px;">
                        <?php echo esc_html($template['icon']); ?>
                    </div>
                    <h3 style="margin: 0 0 10px; font-size: 18px;"><?php echo esc_html($template['name']); ?></h3>
                    <p style="color: #646970; margin: 0 0 15px; min-height: 40px;"><?php echo esc_html($template['description']); ?></p>
                    <div class="fr-template-fields" style="margin-bottom: 15px;">
                        <strong style="font-size: 12px; color: #1d2327;"><?php esc_html_e('Fields:', 'form-relayer'); ?></strong>
                        <span style="font-size: 12px; color: #646970;">
                            <?php 
                            $fieldLabels = array_column($template['fields'], 'label');
                            echo esc_html($fieldLabels ? implode(', ', $fieldLabels) : __('None', 'form-relayer')); 
                            ?>
                        </span>
                    </div>
                    <?php if ($locked) : ?>
                    <a href="https://formrelayer.com/pro" target="_blank" class="button button-secondary" style="width: 100%; text-align: center;">
                        <?php esc_html_e('Upgrade to Pro', 'form-relayer'); ?>
                    </a>
                    <?php else : ?>
                    <a href="<?php echo esc_url($this->getTemplateUrl($id)); ?>" class="button button-primary" style="width: 100%; text-align: center;">
                        <?php esc_html_e('Use This Template', 'form-relayer'); ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <style>
            .fr-template-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
        </style>
        <?php
    }

    /**
     * Register default templates
     */
    private function registerDefaultTemplates(): void
    {
        $this->templates = [
            'contact' => [
                'name' => __('Contact Form', 'form-relayer'),
                'description' => __('Simple contact form with name, email, and message.', 'form-relayer'),
                'icon' => '📧',
                'fields' => [
                    ['id' => 'name', 'type' => 'text', 'label' => __('Your Name', 'form-relayer'), 'required' => 1, 'placeholder' => __('John Doe', 'form-relayer')],
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email Address', 'form-relayer'), 'required' => 1, 'placeholder' => __('john@example.com', 'form-relayer')],
                    ['id' => 'subject', 'type' => 'text', 'label' => __('Subject', 'form-relayer'), 'required' => 0, 'placeholder' => __('How can we help?', 'form-relayer')],
                    ['id' => 'message', 'type' => 'textarea', 'label' => __('Message', 'form-relayer'), 'required' => 1, 'placeholder' => __('Your message...', 'form-relayer')],
                ],
            ],
            'newsletter' => [
                'name' => __('Newsletter Signup', 'form-relayer'),
                'description' => __('Simple email capture for newsletter subscriptions.', 'form-relayer'),
                'icon' => '📰',
                'fields' => [
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email Address', 'form-relayer'), 'required' => 1, 'placeholder' => __('Enter your email', 'form-relayer')],
                ],
                'settings' => [
                    'button_text' => __('Subscribe', 'form-relayer'),
                ],
            ],
            'feedback' => [
                'name' => __('Feedback Form', 'form-relayer'),
                'description' => __('Collect user feedback with rating.', 'form-relayer'),
                'icon' => '⭐',
                'fields' => [
                    ['id' => 'name', 'type' => 'text', 'label' => __('Your Name', 'form-relayer'), 'required' => 0],
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email', 'form-relayer'), 'required' => 0],
                    ['id' => 'rating', 'type' => 'select', 'label' => __('Rating', 'form-relayer'), 'required' => 1, 'options' => "5 - Excellent\n4 - Good\n3 - Average\n2 - Poor\n1 - Very Poor"],
                    ['id' => 'feedback', 'type' => 'textarea', 'label' => __('Your Feedback', 'form-relayer'), 'required' => 1],
                ],
            ],
            'support' => [
                'name' => __('Support Request', 'form-relayer'),
                'description' => __('Technical support ticket form.', 'form-relayer'),
                'icon' => '🎫',
                'fields' => [
                    ['id' => 'name', 'type' => 'text', 'label' => __('Full Name', 'form-relayer'), 'required' => 1],
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email', 'form-relayer'), 'required' => 1],
                    ['id' => 'priority', 'type' => 'select', 'label' => __('Priority', 'form-relayer'), 'required' => 1, 'options' => "Low\nMedium\nHigh\nUrgent"],
                    ['id' => 'category', 'type' => 'select', 'label' => __('Category', 'form-relayer'), 'required' => 1, 'options' => "Technical Issue\nBilling\nFeature Request\nOther"],
                    ['id' => 'subject', 'type' => 'text', 'label' => __('Subject', 'form-relayer'), 'required' => 1],
                    ['id' => 'description', 'type' => 'textarea', 'label' => __('Description', 'form-relayer'), 'required' => 1, 'placeholder' => __('Please describe your issue in detail...', 'form-relayer')],
                ],
            ],
            'appointment' => [
                'name' => __('Appointment Request', 'form-relayer'),
                'description' => __('Schedule appointments or consultations.', 'form-relayer'),
                'icon' => '📅',
                'fields' => [
                    ['id' => 'name', 'type' => 'text', 'label' => __('Full Name', 'form-relayer'), 'required' => 1],
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email', 'form-relayer'), 'required' => 1],
                    ['id' => 'phone', 'type' => 'phone', 'label' => __('Phone Number', 'form-relayer'), 'required' => 1],
                    ['id' => 'date', 'type' => 'date', 'label' => __('Preferred Date', 'form-relayer'), 'required' => 1],
                    ['id' => 'time', 'type' => 'select', 'label' => __('Preferred Time', 'form-relayer'), 'required' => 1, 'options' => "9:00 AM\n10:00 AM\n11:00 AM\n1:00 PM\n2:00 PM\n3:00 PM\n4:00 PM"],
                    ['id' => 'notes', 'type' => 'textarea', 'label' => __('Additional Notes', 'form-relayer'), 'required' => 0],
                ],
            ],
            'healthcare_contact' => [
                'name' => __('Healthcare Contact', 'form-relayer'),
                'description' => __('Professional contact form for medical offices and clinics.', 'form-relayer'),
                'icon' => '🏥',
                'fields' => [
                    ['id' => 'name', 'type' => 'text', 'label' => __('Full Name', 'form-relayer'), 'required' => 1, 'placeholder' => __('John Doe', 'form-relayer')],
                    ['id' => 'email', 'type' => 'email', 'label' => __('Email', 'form-relayer'), 'required' => 1, 'placeholder' => __('john@example.com', 'form-relayer')],
                    ['id' => 'phone', 'type' => 'phone', 'label' => __('Phone Number', 'form-relayer'), 'required' => 0, 'placeholder' => __('(555) 123-4567', 'form-relayer')],
                    ['id' => 'location', 'type' => 'select', 'label' => __('Preferred Location', 'form-relayer'), 'required' => 0, 'options' => "Select a location\nMain Office\nSecond Location\nEither Location"],
                    ['id' => 'subject', 'type' => 'text', 'label' => __('Subject', 'form-relayer'), 'required' => 1, 'placeholder' => __('How can we help?', 'form-relayer')],
                    ['id' => 'message', 'type' => 'textarea', 'label' => __('Message', 'form-relayer'), 'required' => 1, 'placeholder' => __('Please describe your inquiry...', 'form-relayer')],
                ],
                'settings' => [
                    'button_text' => __('Send Message', 'form-relayer'),
                    'success_message' => __("Thank you for your message! We'll get back to you within 24 hours.", 'form-relayer'),
                ],
            ],
            'blank' => [
                'name' => __('Blank Form', 'form-relayer'),
                'description' => __('Start from scratch with an empty form.', 'form-relayer'),
                'icon' => '📄',
                'fields' => [],
            ],
        ];

        // Allow Pro to add templates
        $this->templates = apply_filters('formrelayer_templates', $this->templates);
    }

    /**
     * Get all templates
     *
     * @return array<string, array>
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Get a specific template
     */
    public function getTemplate(string $templateId): ?array
    {
        return $this->templates[$templateId] ?? null;
    }

    /**
     * Create form from template
     */
    public function createFromTemplate(string $templateId, string $title = ''): int|\WP_Error
    {
        $template = $this->getTemplate($templateId);

        if (!$template) {
            return new \WP_Error('invalid_template', __('Template not found.', 'form-relayer'));
        }

        $formTitle = $title ?: $template['name'];

        $formId = wp_insert_post([
            'post_type' => PostType::POST_TYPE,
            'post_title' => $formTitle,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($formId)) {
            return $formId;
        }

        // Save fields
        update_post_meta($formId, '_fr_fields', wp_json_encode($template['fields']));

        // Save settings
        if (!empty($template['settings'])) {
            foreach ($template['settings'] as $key => $value) {
                update_post_meta($formId, '_fr_' . $key, $value);
            }
        }

        return $formId;
    }

    /**
     * Handle template creation from admin
     */
    public function handleTemplateCreation(): void
    {
        if (!isset($_GET['fr_create_from_template'])) {
            return;
        }

        if (!current_user_can('edit_posts')) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'fr_create_template')) {
            return;
        }

        $templateId = sanitize_key($_GET['fr_create_from_template']);
        $formId = $this->createFromTemplate($templateId);

        if (is_wp_error($formId)) {
            wp_die( esc_html( $formId->get_error_message() ) );
        }

        wp_safe_redirect(admin_url('post.php?post=' . $formId . '&action=edit'));
        exit;
    }

    /**
     * Get template creation URL
     */
    public function getTemplateUrl(string $templateId): string
    {
        return wp_nonce_url(
            admin_url('edit.php?post_type=' . PostType::POST_TYPE . '&fr_create_from_template=' . $templateId),
            'fr_create_template'
        );
    }
}
