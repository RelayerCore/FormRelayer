<?php

declare(strict_types=1);

namespace FormRelayer\Security;

/**
 * Input Sanitizer
 *
 * Centralized input sanitization with type-specific methods
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Sanitizer
{
    /**
     * Sanitize text input
     */
    public static function text(mixed $input): string
    {
        if (!is_string($input)) {
            return '';
        }
        return sanitize_text_field($input);
    }

    /**
     * Sanitize textarea input
     */
    public static function textarea(mixed $input): string
    {
        if (!is_string($input)) {
            return '';
        }
        return sanitize_textarea_field($input);
    }

    /**
     * Sanitize email
     */
    public static function email(mixed $input): string
    {
        if (!is_string($input)) {
            return '';
        }
        return sanitize_email($input);
    }

    /**
     * Sanitize URL
     */
    public static function url(mixed $input): string
    {
        if (!is_string($input)) {
            return '';
        }
        return esc_url_raw($input);
    }

    /**
     * Sanitize integer
     */
    public static function int(mixed $input): int
    {
        return absint($input);
    }

    /**
     * Sanitize float
     */
    public static function float(mixed $input): float
    {
        return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Sanitize boolean
     */
    public static function bool(mixed $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Sanitize HTML (allow safe tags)
     */
    public static function html(mixed $input): string
    {
        if (!is_string($input)) {
            return '';
        }
        return wp_kses_post($input);
    }

    /**
     * Sanitize key/slug
     */
    public static function key(mixed $input): string
    {
        if (!is_string($input)) {
            return '';
        }
        return sanitize_key($input);
    }

    /**
     * Sanitize file name
     */
    public static function fileName(mixed $input): string
    {
        if (!is_string($input)) {
            return '';
        }
        return sanitize_file_name($input);
    }

    /**
     * Sanitize array of values
     *
     * @param array<mixed> $input
     * @param callable $sanitizer
     * @return array<mixed>
     */
    public static function array(array $input, callable $sanitizer): array
    {
        return array_map($sanitizer, $input);
    }

    /**
     * Sanitize JSON string
     */
    public static function json(mixed $input): ?array
    {
        if (!is_string($input)) {
            return null;
        }

        $decoded = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    /**
     * Sanitize form field value based on type
     */
    public static function fieldValue(mixed $value, string $fieldType): mixed
    {
        return match ($fieldType) {
            'email' => self::email($value),
            'url' => self::url($value),
            'number' => self::int($value),
            'textarea' => self::textarea($value),
            'checkbox' => self::bool($value),
            'select', 'radio' => self::text($value),
            'hidden' => self::text($value),
            default => self::text($value),
        };
    }

    /**
     * Sanitize entire form submission
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $fieldTypes Field ID => type mapping
     * @return array<string, mixed>
     */
    public static function formSubmission(array $data, array $fieldTypes): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $fieldType = $fieldTypes[$key] ?? 'text';
            $sanitized[self::key($key)] = self::fieldValue($value, $fieldType);
        }

        return $sanitized;
    }

    /**
     * Strip all tags from input
     */
    public static function stripTags(mixed $input): string
    {
        if (!is_string($input)) {
            return '';
        }
        return wp_strip_all_tags($input);
    }

    /**
     * Validate and sanitize phone number
     */
    public static function phone(mixed $input): string
    {
        if (!is_string($input)) {
            return '';
        }
        // Remove everything except digits, plus, hyphens, parentheses, and spaces
        return preg_replace('/[^\d\+\-\(\)\s]/', '', $input) ?? '';
    }
}
