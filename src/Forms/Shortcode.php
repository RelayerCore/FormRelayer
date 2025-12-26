<?php

declare(strict_types=1);

namespace FormRelayer\Forms;

use FormRelayer\Core\Plugin;
use FormRelayer\Core\PostType;
use FormRelayer\Security\Nonce;

/**
 * Form Shortcode Handler
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Shortcode
{
    private static ?Shortcode $instance = null;

    public const TAG = 'form_relayer';

    private function __construct()
    {
        add_shortcode(self::TAG, [$this, 'render']);
        add_shortcode('formrelayer', [$this, 'render']); // Legacy alias (no underscore)
        add_shortcode('fr_form', [$this, 'render']); // Legacy alias
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Render the form shortcode
     *
     * @param array|string $atts
     * @return string
     */
    public function render(array|string $atts = []): string
    {
        $atts = shortcode_atts([
            'id' => 0,
            'title' => '',
            'class' => '',
        ], $atts, self::TAG);

        $formId = absint($atts['id']);

        if (!$formId) {
            return $this->renderError(__('Form ID is required.', 'form-relayer'));
        }

        $form = get_post($formId);

        if (!$form || $form->post_type !== PostType::POST_TYPE) {
            return $this->renderError(__('Form not found.', 'form-relayer'));
        }

        // Enqueue assets
        wp_enqueue_style('formrelayer-frontend');
        wp_enqueue_script('formrelayer-frontend');
        
        // Enqueue conditionals script
        wp_enqueue_script(
            'formrelayer-conditionals',
            Plugin::url('assets/js/frontend-conditionals.js'),
            ['jquery'],
            Plugin::VERSION,
            true
        );

        // Get form settings
        $fields = $this->getFormFields($formId);
        $settings = $this->getFormSettings($formId);
        
        // Collect conditions for frontend
        $conditions = [];
        foreach ($fields as $field) {
            if (!empty($field['condition']) && !empty($field['condition']['enabled'])) {
                $conditions[] = [
                    'targetField' => $field['id'] ?? '',
                    'enabled' => true,
                    'action' => $field['condition']['action'] ?? 'show',
                    'field' => $field['condition']['field'] ?? '',
                    'operator' => $field['condition']['operator'] ?? 'equals',
                    'value' => $field['condition']['value'] ?? '',
                ];
            }
        }
        
        // Output conditions as inline script if any exist
        if (!empty($conditions)) {
            wp_add_inline_script(
                'formrelayer-conditionals',
                'window.frConditions_' . $formId . ' = ' . wp_json_encode($conditions) . ';',
                'before'
            );
        }

        // Build form HTML
        return $this->buildFormHtml($formId, $form, $fields, $settings, $atts);
    }

    /**
     * Get form fields
     *
     * @return array<array>
     */
    private function getFormFields(int $formId): array
    {
        $fieldsRaw = get_post_meta($formId, '_fr_fields', true);
        
        // Handle legacy array format
        if (is_array($fieldsRaw)) {
            return $fieldsRaw;
        }
        
        // Handle new JSON string format
        if (is_string($fieldsRaw) && !empty($fieldsRaw)) {
            $fields = json_decode($fieldsRaw, true);
            return is_array($fields) ? $fields : [];
        }
        
        return [];
    }

    /**
     * Get form settings
     *
     * @return array<string, mixed>
     */
    private function getFormSettings(int $formId): array
    {
        return [
            'button_text' => get_post_meta($formId, '_fr_button_text', true) ?: __('Submit', 'form-relayer'),
            'success_message' => get_post_meta($formId, '_fr_success_message', true) ?: '',
            'redirect_url' => get_post_meta($formId, '_fr_redirect_url', true) ?: '',
            'primary_color' => get_post_meta($formId, '_fr_primary_color', true) ?: '#6366f1',
            'button_color' => get_post_meta($formId, '_fr_button_color', true) ?: '#6366f1',
        ];
    }

    /**
     * Build form HTML
     */
    public function buildFormHtml(
        int $formId,
        \WP_Post $form,
        array $fields,
        array $settings,
        array $atts
    ): string {
        $cssClass = 'fr-form';
        if (!empty($atts['class'])) {
            $cssClass .= ' ' . esc_attr($atts['class']);
        }

        $cssVars = sprintf(
            '--fr-primary: %s; --fr-button: %s;',
            esc_attr($settings['primary_color']),
            esc_attr($settings['button_color'])
        );

        ob_start();
        ?>
        <div class="fr-form-wrapper" data-form-id="<?php echo esc_attr($formId); ?>">
            <div class="fr-message" style="display:none;"></div>
            <form class="<?php echo esc_attr($cssClass); ?>" 
                  method="post" 
                  style="<?php echo esc_attr($cssVars); ?>"
                  data-form-id="<?php echo esc_attr($formId); ?>">
                
                <?php if (!empty($atts['title'])): ?>
                    <h3 class="fr-form-title"><?php echo esc_html($atts['title']); ?></h3>
                <?php endif; ?>

                <input type="hidden" name="action" value="fr_submit_form">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($formId); ?>">
                <?php 
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nonce field contains safe HTML
                echo Nonce::field('form_submit', false); 
                ?>

                <?php // Honeypot field - hidden from real users ?>
                <?php if (get_option('fr_honeypot_enabled', 1)): ?>
                    <div class="fr-hp-wrap" style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;" aria-hidden="true">
                        <label for="fr_website_url_<?php echo esc_attr($formId); ?>">Website</label>
                        <input type="text" 
                               name="fr_website_url" 
                               id="fr_website_url_<?php echo esc_attr($formId); ?>" 
                               value="" 
                               tabindex="-1" 
                               autocomplete="off">
                    </div>
                <?php endif; ?>

                <div class="fr-fields-container">
                    <?php foreach ($fields as $field): ?>
                        <?php 
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderField returns escaped content
                        echo $this->renderField($field); 
                        ?>
                    <?php endforeach; ?>
                </div>

                <?php // GDPR Consent Checkbox ?>
                <?php if (get_option('fr_gdpr_enabled', 0)): ?>
                    <?php 
                    $gdprText = get_option('fr_gdpr_text', __('I agree to the storage and processing of my data by this website.', 'form-relayer'));
                    $gdprRequired = get_option('fr_gdpr_required', 1);
                    ?>
                    <div class="fr-field fr-field-gdpr">
                        <label class="fr-checkbox-label fr-gdpr-label">
                            <input type="checkbox" 
                                   name="fr_gdpr_consent" 
                                   value="1" 
                                   class="fr-checkbox fr-gdpr-checkbox"
                                   <?php echo $gdprRequired ? 'required' : ''; ?>>
                            <span class="fr-gdpr-text"><?php echo wp_kses_post($gdprText); ?></span>
                            <?php if ($gdprRequired): ?>
                                <span class="fr-required">*</span>
                            <?php endif; ?>
                        </label>
                    </div>
                <?php endif; ?>

                <div class="fr-form-submit">
                    <button type="submit" class="fr-submit-btn">
                        <span class="fr-submit-text"><?php echo esc_html($settings['button_text']); ?></span>
                        <span class="fr-submit-loading" style="display:none;">
                            <svg class="fr-spinner" viewBox="0 0 24 24" width="18" height="18">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-dashoffset="10"/>
                            </svg>
                        </span>
                    </button>
                </div>

                <div class="fr-form-messages" style="display:none;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Render individual field
     */
    private function renderField(array $field): string
    {
        $id = esc_attr($field['id'] ?? 'field_' . wp_rand());
        $name = $id;
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $required = !empty($field['required']);
        $options = $field['options'] ?? '';
        $cssClass = $field['css_class'] ?? '';
        $width = $field['width'] ?? '100';

        $fieldClass = 'fr-field fr-field-' . esc_attr($type);
        $fieldClass .= ' fr-field-width-' . esc_attr($width);
        if (!empty($cssClass)) {
            $fieldClass .= ' ' . esc_attr($cssClass);
        }
        
        // Check for conditional logic - hide initially if condition action is 'show'
        $condition = $field['condition'] ?? null;
        $hiddenStyle = '';
        if ($condition && !empty($condition['enabled']) && $condition['action'] === 'show') {
            $fieldClass .= ' fr-hidden';
            $hiddenStyle = ' style="display:none;"';
        }

        $requiredAttr = $required ? 'required' : '';
        $requiredMark = $required ? '<span class="fr-required">*</span>' : '';

        ob_start();
        ?>
        <div class="<?php echo esc_attr($fieldClass); ?>" data-field-id="<?php echo esc_attr($id); ?>"<?php echo esc_attr( $hiddenStyle ); ?>>
            <?php if ($label && $type !== 'hidden'): ?>
                <label for="<?php echo esc_attr( $id ); ?>" class="fr-label">
                    <?php echo esc_html($label); ?><?php echo wp_kses_post( $requiredMark ); ?>
                </label>
            <?php endif; ?>

            <?php
            // All render methods return pre-escaped HTML strings
            switch ($type) {
                case 'textarea':
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $this->renderTextarea($id, $name, $placeholder, $requiredAttr);
                    break;
                case 'select':
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $this->renderSelect($id, $name, $options, $requiredAttr);
                    break;
                case 'radio':
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $this->renderRadio($id, $name, $options, $requiredAttr);
                    break;
                case 'checkbox':
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $this->renderCheckbox($id, $name, $options, $label);
                    break;
                case 'hidden':
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $this->renderHidden($id, $name, $field['value'] ?? '');
                    break;
                case 'header':
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $this->renderHeader($label);
                    break;
                case 'html':
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $this->renderHtmlContent($field['content'] ?? $label);
                    break;
                default:
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo $this->renderInput($id, $name, $type, $placeholder, $requiredAttr);
            }
            ?>
        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Render text/email/etc input
     */
    private function renderInput(string $id, string $name, string $type, string $placeholder, string $required): string
    {
        $inputType = match ($type) {
            'email' => 'email',
            'phone' => 'tel',
            'number' => 'number',
            'url' => 'url',
            'date' => 'date',
            'time' => 'time',
            default => 'text',
        };

        return sprintf(
            '<input type="%s" id="%s" name="%s" placeholder="%s" class="fr-input" %s>',
            esc_attr($inputType),
            esc_attr($id),
            esc_attr($name),
            esc_attr($placeholder),
            esc_attr( $required )
        );
    }

    /**
     * Render textarea
     */
    private function renderTextarea(string $id, string $name, string $placeholder, string $required): string
    {
        return sprintf(
            '<textarea id="%s" name="%s" placeholder="%s" class="fr-textarea fr-input" rows="4" %s></textarea>',
            esc_attr($id),
            esc_attr($name),
            esc_attr($placeholder),
            esc_attr( $required )
        );
    }

    /**
     * Render select dropdown
     */
    private function renderSelect(string $id, string $name, string $options, string $required): string
    {
        // Support both comma and newline separated options
        $optionsArray = array_filter(array_map('trim', preg_split('/[\n\r,]+/', $options)));
        
        $html = sprintf(
            '<select id="%s" name="%s" class="fr-select fr-input" %s>',
            esc_attr($id),
            esc_attr($name),
            esc_attr( $required )
        );
        $html .= '<option value="">' . esc_html__('Select an option', 'form-relayer') . '</option>';
        
        foreach ($optionsArray as $option) {
            $html .= sprintf('<option value="%s">%s</option>', esc_attr($option), esc_html($option));
        }
        
        $html .= '</select>';
        
        return $html;
    }

    /**
     * Render radio buttons
     */
    private function renderRadio(string $id, string $name, string $options, string $required): string
    {
        // Support both comma and newline separated options
        $optionsArray = array_filter(array_map('trim', preg_split('/[\n\r,]+/', $options)));
        
        $html = '<div class="fr-radio-group">';
        
        foreach ($optionsArray as $i => $option) {
            $optionId = $id . '_' . $i;
            $html .= sprintf(
                '<label class="fr-radio-label"><input type="radio" id="%s" name="%s" value="%s" class="fr-radio" %s> %s</label>',
                esc_attr($optionId),
                esc_attr($name),
                esc_attr($option),
                $i === 0 && $required ? 'required' : '',
                esc_html($option)
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render checkboxes
     */
    private function renderCheckbox(string $id, string $name, string $options, string $label): string
    {
        // Single checkbox
        if (empty($options)) {
            return sprintf(
                '<label class="fr-checkbox-label"><input type="checkbox" id="%s" name="%s" value="1" class="fr-checkbox"> %s</label>',
                esc_attr($id),
                esc_attr($name),
                esc_html($label)
            );
        }

        // Multiple checkboxes
        // Support both comma and newline separated options
        $optionsArray = array_filter(array_map('trim', preg_split('/[\n\r,]+/', $options)));
        
        $html = '<div class="fr-checkbox-group">';
        
        foreach ($optionsArray as $i => $option) {
            $optionId = $id . '_' . $i;
            $html .= sprintf(
                '<label class="fr-checkbox-label"><input type="checkbox" id="%s" name="%s[]" value="%s" class="fr-checkbox"> %s</label>',
                esc_attr($optionId),
                esc_attr($name),
                esc_attr($option),
                esc_html($option)
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render hidden field
     */
    private function renderHidden(string $id, string $name, string $value): string
    {
        return sprintf(
            '<input type="hidden" id="%s" name="%s" value="%s">',
            esc_attr($id),
            esc_attr($name),
            esc_attr($value)
        );
    }

    /**
     * Render header/section title
     */
    private function renderHeader(string $label): string
    {
        return sprintf(
            '<h3 class="fr-section-title">%s</h3>',
            esc_html($label)
        );
    }

    /**
     * Render HTML/text content
     */
    private function renderHtmlContent(string $content): string
    {
        return sprintf(
            '<div class="fr-html-content">%s</div>',
            wp_kses_post($content)
        );
    }

    /**
     * Render error message (for admin only)
     */
    private function renderError(string $message): string
    {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return sprintf(
            '<div class="fr-error" style="padding:12px;background:#fee;border:1px solid #c00;border-radius:4px;color:#c00;">%s</div>',
            esc_html($message)
        );
    }
}
