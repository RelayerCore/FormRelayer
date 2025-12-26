/**
 * FormRelayer Frontend JavaScript
 *
 * @package FormRelayer
 */

(function ($) {
    'use strict';

    /**
     * FormRelayer Form Handler
     */
    const FormRelayerForm = {
        /**
         * Initialize
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind form events
         */
        bindEvents: function () {
            $(document).on('submit', '.fr-form', this.handleSubmit.bind(this));
            $(document).on('input', '.fr-form .fr-input', this.handleInput.bind(this));
        },

        /**
         * Handle form submission
         *
         * @param {Event} e Submit event
         */
        handleSubmit: function (e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $wrapper = $form.closest('.fr-form-wrapper');
            const $btn = $form.find('.fr-submit-btn');
            const $message = $wrapper.find('.fr-message');

            // Validate
            if (!this.validateForm($form)) {
                return;
            }

            // Show loading state
            this.setLoading($btn, true);
            $message.hide().removeClass('fr-success fr-error');

            // Prepare data
            const formData = new FormData($form[0]);
            formData.append('action', 'fr_submit_form');
            formData.append('nonce', formRelayer.nonce);

            // reCAPTCHA v3
            if (formRelayer.recaptchaSiteKey && typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function () {
                    grecaptcha.execute(formRelayer.recaptchaSiteKey, { action: 'submit' }).then(function (token) {
                        formData.append('fr_recaptcha_token', token); // Ensure it's in FormData
                        FormRelayerForm.submitAjax($form, formData, $btn, $message);
                    });
                });
            } else {
                FormRelayerForm.submitAjax($form, formData, $btn, $message);
            }
        },

        /**
         * Submit AJAX request
         */
        submitAjax: function ($form, formData, $btn, $message) {
            // Allow extensions to add data
            $(document).trigger('fr:before_submit', [formData, $form]);

            // Submit
            $.ajax({
                url: formRelayer.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // Check for redirect URL
                        if (response.data.redirect_url) {
                            // Trigger success event before redirect
                            $(document).trigger('fr:submit_success', [response, $form]);
                            // Redirect to the specified URL
                            window.location.href = response.data.redirect_url;
                            return;
                        }
                        
                        $message
                            .removeClass('fr-error')
                            .addClass('fr-success')
                            .html(response.data.message)
                            .fadeIn();

                        $form[0].reset();

                        // Scroll to message
                        $('html, body').animate({
                            scrollTop: $wrapper.offset().top - 50
                        }, 300);

                        // Trigger success event
                        $(document).trigger('fr:submit_success', [response, $form]);
                    } else {
                        $message
                            .removeClass('fr-success')
                            .addClass('fr-error')
                            .html(response.data.message || formRelayer.i18n.error)
                            .fadeIn();

                        // Trigger error event
                        $(document).trigger('fr:submit_error', [response, $form]);
                    }
                },
                error: function (xhr, status, error) {
                    let errorMessage = formRelayer.i18n.error;

                    if (status === 'timeout') {
                        errorMessage = formRelayer.i18n.networkError;
                    }

                    $message
                        .removeClass('fr-success')
                        .addClass('fr-error')
                        .html(errorMessage)
                        .fadeIn();

                    // Trigger error event
                    $(document).trigger('fr:submit_error', [{ error: error }, $form]);
                },
                complete: function () {
                    FormRelayerForm.setLoading($btn, false);
                }
            });
        },

        /**
         * Handle input changes (live validation)
         *
         * @param {Event} e Input event
         */
        handleInput: function (e) {
            const $input = $(e.currentTarget);

            // Remove validation classes on input
            $input.removeClass('fr-invalid fr-valid');
        },

        /**
         * Validate form
         *
         * @param {jQuery} $form Form element
         * @return {boolean}
         */
        validateForm: function ($form) {
            let isValid = true;

            $form.find('[required]').each(function () {
                const $input = $(this);
                const value = $input.val().trim();

                if (!value) {
                    $input.addClass('fr-invalid');
                    isValid = false;
                } else {
                    $input.removeClass('fr-invalid');
                }
            });

            // Email validation
            const $email = $form.find('[type="email"]');
            if ($email.length && $email.val()) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test($email.val())) {
                    $email.addClass('fr-invalid');
                    isValid = false;
                }
            }

            return isValid;
        },

        /**
         * Set loading state
         *
         * @param {jQuery} $btn Button element
         * @param {boolean} loading Is loading
         */
        setLoading: function ($btn, loading) {
            if (loading) {
                $btn.prop('disabled', true);
                $btn.find('.fr-btn-text').hide();
                $btn.find('.fr-btn-loading').show();
            } else {
                $btn.prop('disabled', false);
                $btn.find('.fr-btn-text').show();
                $btn.find('.fr-btn-loading').hide();
            }
        }
    };

    // Initialize on DOM ready
    $(document).ready(function () {
        FormRelayerForm.init();
    });

})(jQuery);
