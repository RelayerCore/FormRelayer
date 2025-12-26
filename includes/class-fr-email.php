<?php
/**
 * Email Handler
 *
 * @package FormRelayer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Class
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Legacy class name
class FR_Email {
    
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
     * Send admin notification email
     *
     * @param int    $post_id   Submission ID
     * @param array  $data      Form data
     * @param string $recipient Optional recipient email
     */
    public function send_admin_notification($data, $recipient = null) {
        $to = $recipient ?: get_option('fr_recipient_email', get_option('admin_email'));
        
        // Allow multiple recipients
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        $to = apply_filters('fr_admin_email_recipients', $to, $data);
        
        $site_name = get_bloginfo('name');
        $subject = sprintf(
            /* translators: 1: Site name, 2: Form subject */
            __('[%1$s] Contact: %2$s', 'form-relayer'),
            $site_name,
            $data['subject']
        );
        
        $body = $this->build_admin_email($data);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('Reply-To: %s <%s>', $data['name'], $data['email']),
        ];
        
        // Allow customization
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        $headers = apply_filters('fr_admin_email_headers', $headers, $data);
        
        return wp_mail($to, $subject, $body, $headers);
    }
    
    /**
     * Send auto-reply email
     *
     * @param string $to_email Recipient email
     * @param string $name     Recipient name
     * @param string $subject  Original subject
     * @param int    $form_id  Form ID for per-form settings
     * @return bool
     */
    public function send_auto_reply($to_email, $name, $subject, $form_id = 0) {
        // Check per-form auto-reply settings first (hybrid approach)
        $use_override = $form_id ? get_post_meta($form_id, '_fr_autoreply_override', true) : false;
        
        if ($use_override) {
            // Per-form settings
            $enable = get_post_meta($form_id, '_fr_autoreply_enable', true);
            if (!$enable) {
                return false; // Auto-reply disabled for this form
            }
            $reply_subject = get_post_meta($form_id, '_fr_autoreply_subject', true) ?: __('Thank you for contacting us', 'form-relayer');
            $reply_message = get_post_meta($form_id, '_fr_autoreply_message', true) ?: $this->get_default_auto_reply();
        } else {
            // Global settings
            $enable = get_option('fr_enable_auto_reply', 1);
            if (!$enable) {
                return false; // Auto-reply disabled globally
            }
            $reply_subject = get_option('fr_auto_reply_subject', __('Thank you for contacting us', 'form-relayer'));
            $reply_message = get_option('fr_auto_reply_message', $this->get_default_auto_reply());
        }
        
        // Replace placeholders
        $placeholders = [
            '{name}'      => $name,
            '{email}'     => $to_email,
            '{subject}'   => $subject,
            '{site_name}' => get_bloginfo('name'),
            '{site_url}'  => home_url(),
        ];
        
        $reply_message = str_replace(array_keys($placeholders), array_values($placeholders), $reply_message);
        $reply_subject = str_replace(array_keys($placeholders), array_values($placeholders), $reply_subject);
        
        // Apply auto paragraphs
        $reply_message = wpautop($reply_message);
        
        $body = $this->build_auto_reply_email($name, $reply_message);
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        $headers = apply_filters('fr_auto_reply_headers', $headers, $to_email, $name);
        
        return wp_mail($to_email, $reply_subject, $body, $headers);
    }
    
    /**
     * Build HTML email for admin notification
     *
     * @param array $data Form data
     * @return string
     */
    private function build_admin_email($data) {
        $primary_color = get_option('fr_primary_color', '#0073aa');
        $secondary_color = get_option('fr_secondary_color', '#005177');
        
        $phone_display = !empty($data['phone']) ? esc_html($data['phone']) : __('Not provided', 'form-relayer');
        $location_display = !empty($data['location']) ? esc_html($data['location']) : __('Not specified', 'form-relayer');
        $date = current_time('F j, Y \a\t g:i a');
        $site_name = get_bloginfo('name');
        
        // Allow Pro email templates to override
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        $template = apply_filters('fr_admin_email_template', '', $data);
        if (!empty($template)) {
            return $template;
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            <div style="max-width: 600px; margin: 0 auto; background: #f5f5f5; padding: 20px;">
                <div style="background: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?>, <?php echo esc_attr($secondary_color); ?>); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                    <h1 style="color: #fff; margin: 0; font-size: 24px;"><?php esc_html_e('New Contact Form Submission', 'form-relayer'); ?></h1>
                </div>
                
                <div style="padding: 30px; background: #fff; border: 1px solid #e5e5e5; border-top: none;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <?php foreach ($data as $key => $value) : ?>
                            <?php 
                            // Skip system keys
                            if (strpos($key, '__') === 0 || in_array($key, ['subject', 'ip', 'user_agent', 'timestamp', '_recipient'])) continue;
                            
                            // Get Label
                            $label = isset($data['__label_' . $key]) ? $data['__label_' . $key] : ucfirst($key);
                            ?>
                            <tr>
                                <td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #666; width: 140px; vertical-align: top;">
                                    <strong><?php echo esc_html($label); ?></strong>
                                </td>
                                <td style="padding: 12px 0; border-bottom: 1px solid #eee;">
                                    <?php echo nl2br(esc_html($value)); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <div style="margin-top: 25px; text-align: center;">
                        <a href="mailto:<?php echo esc_attr($data['email']); ?>" 
                           style="display: inline-block; padding: 12px 30px; background: <?php echo esc_attr($primary_color); ?>; color: #fff; text-decoration: none; border-radius: 5px; font-weight: 600;">
                            <?php esc_html_e('Reply to Message', 'form-relayer'); ?>
                        </a>
                    </div>
                </div>
                
                <div style="padding: 20px; text-align: center; font-size: 12px; color: #999; border-radius: 0 0 8px 8px;">
                    <?php
                    printf(
                        /* translators: %s: Date and time */
                        esc_html__('Submitted on %s', 'form-relayer'),
                        esc_html($date)
                    );
                    ?>
                    <br>
                    <span style="color: #ccc;"><?php echo esc_html($site_name); ?></span>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Build HTML email for auto-reply
     *
     * @param string $name    Recipient name
     * @param string $message Email message
     * @return string
     */
    private function build_auto_reply_email($name, $message) {
        $primary_color = get_option('fr_primary_color', '#0073aa');
        $secondary_color = get_option('fr_secondary_color', '#005177');
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $year = gmdate('Y');
        
        // Allow Pro email templates to override
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook
        $template = apply_filters('fr_auto_reply_email_template', '', $name, $message);
        if (!empty($template)) {
            return $template;
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            <div style="max-width: 600px; margin: 0 auto; background: #f5f5f5; padding: 20px;">
                <div style="background: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?>, <?php echo esc_attr($secondary_color); ?>); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                    <h1 style="color: #fff; margin: 0; font-size: 24px;"><?php echo esc_html($site_name); ?></h1>
                </div>
                
                <div style="padding: 35px; background: #fff; border: 1px solid #e5e5e5; border-top: none; line-height: 1.7; color: #444;">
                    <?php echo wp_kses_post($message); ?>
                </div>
                
                <div style="padding: 20px; text-align: center; font-size: 12px; color: #999; border-radius: 0 0 8px 8px;">
                    © <?php echo esc_html($year); ?> <a href="<?php echo esc_url($site_url); ?>" style="color: #999; text-decoration: none;"><?php echo esc_html($site_name); ?></a>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get default auto-reply message
     *
     * @return string
     */
    private function get_default_auto_reply() {
        return __("Dear {name},\n\nThank you for reaching out to us. We have received your message and will respond within 24 hours.\n\nBest regards,\n{site_name} Team", 'form-relayer');
    }
}
