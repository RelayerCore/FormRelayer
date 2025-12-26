# FormRelayer

A powerful, lightweight WordPress contact form plugin with admin dashboard, submissions viewer, auto-reply, and more.

![FormRelayer Banner](assets/images/banner.png)

## 🚀 Features

- **Simple Setup** – Add a contact form to any page with a shortcode
- **Submissions Dashboard** – View and manage all form submissions in WordPress admin
- **Email Notifications** – Get notified instantly when someone submits the form
- **Auto-Reply** – Automatically send a confirmation email to users
- **Spam Protection** – Built-in honeypot protection and rate limiting
- **Customizable Colors** – Match your brand with custom primary colors
- **Dynamic Locations** – Add location options for multi-location businesses
- **Responsive Design** – Looks great on all devices
- **Dark Mode Support** – Automatic dark mode for users who prefer it
- **Translation Ready** – Fully translatable to any language

## 📦 Installation

### From WordPress.org (Coming Soon)

1. Go to **Plugins > Add New**
2. Search for "FormRelayer"
3. Click **Install Now** and then **Activate**

### Manual Installation

1. Download the latest release
2. Upload to `/wp-content/plugins/form-relayer/`
3. Activate through the **Plugins** menu in WordPress

## 🎯 Usage

Add the shortcode to any page or post:

```
[form_relayer]
```

### Shortcode Attributes

| Attribute | Default | Description |
|-----------|---------|-------------|
| `location` | `""` | Pre-select a location |
| `button_text` | `"Send Message"` | Custom button text |
| `show_phone` | `"true"` | Show/hide phone field |
| `show_location` | `"true"` | Show/hide location dropdown |

### Examples

```
[form_relayer button_text="Get in Touch"]
[form_relayer location="New York Office" show_phone="false"]
```

## ⚙️ Configuration

1. Go to **FormRelayer > Settings**
2. Configure recipient email, success message, and colors
3. Set up auto-reply email template
4. Add location options if needed

## 📧 Email Templates

### Placeholders

Use these placeholders in your auto-reply message:

| Placeholder | Description |
|-------------|-------------|
| `{name}` | Submitter's name |
| `{email}` | Submitter's email |
| `{subject}` | Form subject |
| `{site_name}` | Your site name |
| `{site_url}` | Your site URL |

## 🔌 Hooks & Filters

### Actions

```php
// After submission is created
do_action('fr_submission_created', $post_id, $data);

// After successful submission
do_action('fr_after_submission', $post_id, $data, $_POST);

// Form rendering hooks
do_action('fr_form_after_message', $atts);
do_action('fr_form_before_submit', $atts);
```

### Filters

```php
// Modify validation errors
add_filter('fr_validation_errors', function($errors, $data) {
    return $errors;
}, 10, 2);

// Modify rate limit
add_filter('fr_rate_limit', function($limit) {
    return 10; // 10 submissions per hour
});

// Modify admin email recipients
add_filter('fr_admin_email_recipients', function($to, $data) {
    return $to;
}, 10, 2);
```

## 💎 Pro Features

Upgrade to [FormRelayer Pro](https://formrelayer.com/pro) for:

- ✅ File Attachments
- ✅ Google reCAPTCHA / hCaptcha
- ✅ Export to CSV/Excel
- ✅ Advanced Email Templates
- ✅ Multiple Forms
- ✅ Conditional Logic
- ✅ Zapier/Webhook Integrations
- ✅ Priority Support

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

Created with ❤️ by [FormRelayer](https://formrelayer.com)

## 📞 Support

- **Documentation**: [formrelayer.com/docs](https://formrelayer.com/docs)
- **Support Forum**: [WordPress.org](https://wordpress.org/support/plugin/form-relayer/)
- **Pro Support**: [formrelayer.com/support](https://formrelayer.com/support)
