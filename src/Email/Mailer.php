<?php

declare(strict_types=1);

namespace FormRelayer\Email;

use FormRelayer\Core\Plugin;

/**
 * Email Mailer
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class Mailer
{
    private static ?Mailer $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Send notification email
     */
    public function send(
        string $to,
        string $subject,
        string $body,
        ?int $submissionId = null,
        ?int $formId = null
    ): bool {
        // Allow Pro to intercept
        $useNative = apply_filters('formrelayer_use_native_email', false);
        
        if ($useNative) {
            $result = apply_filters('formrelayer_send_email', false, $to, $subject, $body, $submissionId, $formId);
            
            if ($result !== false) {
                return (bool) $result;
            }
        }

        // Default to wp_mail
        return $this->sendViaWpMail($to, $subject, $body, $formId);
    }

    /**
     * Send auto-reply email
     */
    public function sendAutoReply(
        string $to,
        string $subject,
        string $body,
        ?int $submissionId = null,
        ?int $formId = null
    ): bool {
        // Allow Pro to intercept
        $useNative = apply_filters('formrelayer_use_native_email', false);
        
        if ($useNative) {
            $result = apply_filters('formrelayer_send_auto_reply', false, $to, $subject, $body, $submissionId);
            
            if ($result !== false) {
                return (bool) $result;
            }
        }

        return $this->sendViaWpMail($to, $subject, $body);
    }

    /**
     * Send via WordPress mail
     */
    private function sendViaWpMail(string $to, string $subject, string $body, ?int $formId = null): bool
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $fromEmail = get_option('fr_from_email', get_option('admin_email'));
        $fromName = get_option('fr_from_name', get_bloginfo('name'));

        if ($fromEmail) {
            $headers[] = sprintf('From: %s <%s>', $fromName, $fromEmail);
        }

        // Wrap body in basic HTML template
        $htmlBody = $this->wrapInTemplate($body, $subject, $formId);

        $sent = wp_mail($to, $subject, $htmlBody, $headers);

        // Log the attempt
        $this->logEmail($to, $subject, $sent);

        return $sent;
    }

    /**
     * Wrap email body in HTML template
     */
    private function wrapInTemplate(string $body, string $subject, ?int $formId = null): string
    {
        $template = $formId ? get_post_meta($formId, '_fr_email_template', true) : 'default';
        $siteName = get_bloginfo('name');
        $logoUrl = get_option('fr_email_logo');
        
        // Custom HTML template (Pro)
        if ($template === 'custom' && $formId) {
            $customHtml = get_post_meta($formId, '_fr_custom_email_html', true);
            if (!empty($customHtml)) {
                return str_replace(['{all_fields}', '{subject}', '{site_name}'], [$body, $subject, $siteName], $customHtml);
            }
        }
        
        // Plain text template
        if ($template === 'plain') {
            return sprintf(
                '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:20px;"><h2>%s</h2>%s</body></html>',
                esc_html($subject),
                $body
            );
        }
        
        // Modern template (Pro)
        if ($template === 'modern') {
            $headerContent = $logoUrl 
                ? sprintf('<img src="%s" alt="%s" style="max-height:60px;display:inline-block;border:0;">', esc_url($logoUrl), esc_attr($siteName))
                : sprintf('<h1 style="margin:0;color:#fff;font-size:28px;font-weight:300;">%s</h1>', esc_html($siteName));
            
            // phpcs:ignore Squiz.PHP.Heredoc.NotAllowed -- Heredoc improves email template readability
            return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0f4f8;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);padding:48px 24px;">
        <tr>
            <td align="center">
                {$headerContent}
                <h2 style="margin:16px 0 0;color:rgba(255,255,255,0.9);font-size:16px;font-weight:400;">{$subject}</h2>
            </td>
        </tr>
    </table>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:0 24px 48px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.1);margin-top:-32px;">
                    <tr>
                        <td style="padding:40px;">
                            {$body}
                        </td>
                    </tr>
                </table>
                <p style="color:#6b7280;font-size:12px;margin-top:24px;">Sent from {$siteName}</p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
        }
        
        // Corporate template (Pro)
        if ($template === 'corporate') {
            $headerContent = $logoUrl 
                ? sprintf('<img src="%s" alt="%s" style="max-height:40px;display:inline-block;border:0;">', esc_url($logoUrl), esc_attr($siteName))
                : sprintf('<span style="font-size:18px;font-weight:600;color:#1e40af;">%s</span>', esc_html($siteName));
            
            // phpcs:ignore Squiz.PHP.Heredoc.NotAllowed -- Heredoc improves email template readability
            return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;font-family:Georgia,serif;background:#f8fafc;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px;">
        <tr>
            <td align="center">
                <table width="640" cellpadding="0" cellspacing="0" style="background:#fff;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="padding:24px 32px;border-bottom:3px solid #1e40af;">
                            {$headerContent}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;line-height:1.8;color:#1e293b;">
                            <h2 style="margin:0 0 24px;color:#1e40af;font-size:20px;font-weight:600;">{$subject}</h2>
                            {$body}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px;background:#f1f5f9;font-size:11px;color:#64748b;border-top:1px solid #e2e8f0;">
                            This message was sent from {$siteName}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
        }
        
        // Dark template (Pro)
        if ($template === 'dark') {
            $headerContent = $logoUrl 
                ? sprintf('<img src="%s" alt="%s" style="max-height:50px;display:inline-block;border:0;">', esc_url($logoUrl), esc_attr($siteName))
                : sprintf('<h1 style="margin:0;color:#f9fafb;font-size:22px;font-weight:600;">%s</h1>', esc_html($siteName));
            
            // phpcs:ignore Squiz.PHP.Heredoc.NotAllowed -- Heredoc improves email template readability
            return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a;padding:32px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#1e293b;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px;border-bottom:1px solid #334155;">
                            {$headerContent}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h2 style="margin:0 0 20px;color:#f1f5f9;font-size:18px;">{$subject}</h2>
                            <div style="color:#cbd5e1;line-height:1.7;">
                                {$body}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background:#0f172a;text-align:center;font-size:12px;color:#64748b;">
                            Sent from {$siteName}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
        }

        // Default branded template
        $primaryColor = get_option('fr_primary_color', '#6366f1');
        
        $headerContent = $logoUrl 
            ? sprintf('<img src="%s" alt="%s" style="max-height:50px;display:inline-block;border:0;">', esc_url($logoUrl), esc_attr($siteName))
            : sprintf('<h1 style="margin:0;color:#fff;font-size:20px;">%s</h1>', esc_html($siteName));

        // phpcs:ignore Squiz.PHP.Heredoc.NotAllowed -- Heredoc improves email template readability
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:24px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background:{$primaryColor};padding:24px;text-align:center;">
                            {$headerContent}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            {$body}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px;background:#f9f9f9;text-align:center;font-size:12px;color:#666;">
                            This email was sent from {$siteName}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Log email attempt
     */
    private function logEmail(string $to, string $subject, bool $success): void
    {
        if (!get_option('fr_enable_email_logging', false)) {
            return;
        }

        $log = get_option('fr_email_log', []);
        
        if (!is_array($log)) {
            $log = [];
        }

        $log[] = [
            'to' => $to,
            'subject' => $subject,
            'success' => $success,
            'time' => current_time('mysql'),
        ];

        // Keep only last 100 entries
        $log = array_slice($log, -100);

        update_option('fr_email_log', $log);
    }

    /**
     * Test email configuration
     */
    public function sendTest(string $to): array
    {
        // translators: %s is the site name
        $subject = sprintf(__('Test email from %s', 'form-relayer'), get_bloginfo('name'));
        $body = sprintf(
            '<p>%s</p><p>%s</p>',
            __('This is a test email from FormRelayer.', 'form-relayer'),
            __('If you received this, your email configuration is working correctly.', 'form-relayer')
        );

        $success = $this->send($to, $subject, $body);

        return [
            'success' => $success,
            'message' => $success
                ? __('Test email sent successfully!', 'form-relayer')
                : __('Failed to send test email. Please check your configuration.', 'form-relayer'),
        ];
    }
}
