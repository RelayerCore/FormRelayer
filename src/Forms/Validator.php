<?php

declare(strict_types=1);

namespace FormRelayer\Forms;

use FormRelayer\Core\Plugin;

/**
 * Server-side Form Validator
 *
 * Implements strict format validation for fields.
 *
 * @package FormRelayer
 * @since 2.1.0
 */
class Validator
{
    /**
     * Validate form data against field definitions
     *
     * @param array $data Submission data (key => value)
     * @param array $fields Field definitions
     * @return array Array of error messages (empty if valid)
     */
    public static function validate(array $data, array $fields): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $id = $field['id'];
            $type = $field['type'] ?? 'text';
            $label = $field['label'] ?? __('Field', 'form-relayer');
            $value = $data[$id] ?? '';

            // Skip empty optional fields
            if (empty($value) && empty($field['required'])) {
                continue;
            }

            // Required check
            if (!empty($field['required']) && empty($value)) {
                // translators: %s is the field label
                $errors[$id] = sprintf(__('%s is required.', 'form-relayer'), $label);
                continue;
            }

            // Format validation
            switch ($type) {
                case 'email':
                    if (!is_email($value)) {
                        // translators: %s is the field label
                        $errors[$id] = sprintf(__('%s must be a valid email address.', 'form-relayer'), $label);
                    }
                    break;

                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        // translators: %s is the field label
                        $errors[$id] = sprintf(__('%s must be a valid URL.', 'form-relayer'), $label);
                    }
                    break;
                
                case 'number':
                    if (!is_numeric($value)) {
                        // translators: %s is the field label
                        $errors[$id] = sprintf(__('%s must be a number.', 'form-relayer'), $label);
                    }
                    break;

                case 'phone':
                    // Basic check: must contain at least some digits
                    if (!preg_match('/[0-9]/', $value)) {
                         // translators: %s is the field label
                         $errors[$id] = sprintf(__('%s does not appear to be a valid phone number.', 'form-relayer'), $label);
                    }
                    break;
            }
        }

        return $errors;
    }
}
