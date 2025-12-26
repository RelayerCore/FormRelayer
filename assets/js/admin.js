jQuery(document).ready(function ($) {
    // Initialize Color Picker
    if ($.fn.wpColorPicker) {
        $('.fr-color-picker').wpColorPicker();
    }

    // Simple toast notification for admin pages (matches builder style)
    function showAdminToast(message, type) {
        type = type || 'success';
        var bgColor = type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#1d2327';
        var icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';

        var $toast = $('<div class="fr-admin-toast">' +
            '<span style="margin-right:8px;font-weight:bold;">' + icon + '</span>' +
            message +
            '</div>');
        $toast.css({
            position: 'fixed',
            bottom: '20px',
            right: '20px',
            background: bgColor,
            color: '#fff',
            padding: '12px 20px',
            borderRadius: '8px',
            fontSize: '14px',
            fontWeight: '500',
            zIndex: 999999,
            boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
            display: 'flex',
            alignItems: 'center',
            opacity: 0,
            transform: 'translateY(20px)'
        });
        $('body').append($toast);

        // Animate in
        $toast.animate({ opacity: 1, bottom: '24px' }, 200);

        setTimeout(function () {
            $toast.animate({ opacity: 0, bottom: '40px' }, 200, function () {
                $(this).remove();
            });
        }, 2000);
    }

    // Copy shortcode button on forms list
    $(document).on('click', '.fr-copy-shortcode-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var shortcode = $btn.data('shortcode');

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shortcode).then(function () {
                $btn.addClass('button-primary');
                $btn.find('.dashicons').removeClass('dashicons-admin-page').addClass('dashicons-yes');
                showAdminToast('Shortcode copied!');
                setTimeout(function () {
                    $btn.removeClass('button-primary');
                    $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-admin-page');
                }, 2000);
            });
        } else {
            // Fallback for older browsers
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(shortcode).select();
            document.execCommand('copy');
            $temp.remove();
            $btn.addClass('button-primary');
            $btn.find('.dashicons').removeClass('dashicons-admin-page').addClass('dashicons-yes');
            showAdminToast('Shortcode copied!');
            setTimeout(function () {
                $btn.removeClass('button-primary');
                $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-admin-page');
            }, 2000);
        }
    });

    // Logo Upload
    var file_frame;
    $(document).on('click', '.fr-upload-logo-btn', function (e) {
        e.preventDefault();

        // If the media frame already exists, reopen it.
        if (file_frame) {
            file_frame.open();
            return;
        }

        // Create the media frame.
        file_frame = wp.media.frames.file_frame = wp.media({
            title: 'Select Email Logo',
            button: {
                text: 'Use this logo'
            },
            multiple: false
        });

        // When an image is selected, run a callback.
        file_frame.on('select', function () {
            var attachment = file_frame.state().get('selection').first().toJSON();
            $('#fr_email_logo').val(attachment.url);

            // Update preview
            var $preview = $('.fr-logo-preview');
            $preview.html('<img src="' + attachment.url + '" style="max-height:50px;border:1px solid #ddd;padding:4px;border-radius:4px;background:#fff;"> <button type="button" class="button-link fr-remove-logo-btn" style="color:#b32d2e;">Remove</button>');
        });

        // Finally, open the modal
        file_frame.open();
    });

    // Remove Logo
    $(document).on('click', '.fr-remove-logo-btn', function () {
        $('#fr_email_logo').val('');
        $('.fr-logo-preview').empty();
    });
});
