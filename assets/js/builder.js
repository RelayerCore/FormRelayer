/**
 * FormRelayer 2-Panel Form Builder
 * 
 * Canvas + Settings panel with inline element insertion
 */

(function ($) {
    'use strict';

    var FR_Builder = {
        selectedField: null,
        fields: [],
        formId: 0,
        insertPosition: -1, // -1 = end, 0+ = specific index
        showingFormSettings: false,
        selectedTypes: [], // Multi-select in element picker

        // Field type definitions
        fieldTypes: {
            text: { icon: '📝', label: 'Text Input', desc: 'Single line text', group: 'basic' },
            email: { icon: '✉️', label: 'Email', desc: 'Email address', group: 'basic' },
            phone: { icon: '📞', label: 'Phone', desc: 'Phone number', group: 'basic' },
            name: { icon: '👤', label: 'Full Name', desc: 'First and last name', group: 'basic' },
            textarea: { icon: '📄', label: 'Long Text', desc: 'Multi-line textarea', group: 'basic' },
            number: { icon: '🔢', label: 'Number', desc: 'Numeric input', group: 'basic' },
            url: { icon: '🔗', label: 'Website URL', desc: 'Website address', group: 'basic' },
            select: { icon: '📋', label: 'Dropdown', desc: 'Select from options', group: 'choice' },
            radio: { icon: '⭕', label: 'Radio Buttons', desc: 'Single choice', group: 'choice' },
            checkbox: { icon: '☑️', label: 'Checkboxes', desc: 'Multiple choices', group: 'choice' },
            header: { icon: '🏷️', label: 'Section Title', desc: 'Add a heading', group: 'content' },
            html: { icon: '📰', label: 'Text / HTML', desc: 'Custom text or HTML', group: 'content' },
            date: { icon: '📅', label: 'Date', desc: 'Date picker', group: 'other' },
            time: { icon: '🕐', label: 'Time', desc: 'Time picker', group: 'other' },
            hidden: { icon: '👁️', label: 'Hidden', desc: 'Hidden field', group: 'other' }
        },

        // Default fields for new forms
        defaultFields: [
            { id: 'name', type: 'text', label: 'Full Name', required: 1, placeholder: 'Your full name', width: '50' },
            { id: 'email', type: 'email', label: 'Email Address', required: 1, placeholder: 'your@email.com', width: '50' },
            { id: 'phone', type: 'phone', label: 'Phone Number', required: 0, placeholder: '(555) 123-4567', width: '100' },
            { id: 'message', type: 'textarea', label: 'Message', required: 0, placeholder: 'How can we help you?', width: '100' }
        ],

        // Width options for grid system
        widthOptions: [
            { value: '100', label: 'Full Width (100%)', icon: '█████' },
            { value: '50', label: 'Half (50%)', icon: '██½' },
            { value: '33', label: 'Third (33%)', icon: '██⅓' },
            { value: '66', label: 'Two-Thirds (66%)', icon: '████⅔' },
            { value: '25', label: 'Quarter (25%)', icon: '█¼' },
            { value: '75', label: 'Three-Quarters (75%)', icon: '█████¾' }
        ],

        /**
         * Initialize
         */
        init: function () {
            this.formId = $('#fr-form-id').val() || 0;
            this.loadFields();
            this.bindEvents();
            this.renderCanvas();
            this.showFormSettings(); // Show form settings by default
            this.initSortable();
        },

        /**
         * Load existing fields
         */
        loadFields: function () {
            var data = $('#fr-fields-data').val();
            if (data) {
                try {
                    this.fields = JSON.parse(data);
                } catch (e) {
                    this.fields = [];
                }
            }

            // If no fields, show template picker
            if (this.fields.length === 0) {
                this.showTemplatePicker();
            }
        },

        /**
         * Form templates library
         */
        formTemplates: {
            blank: {
                name: 'Blank Form',
                icon: '📄',
                desc: 'Start from scratch',
                fields: []
            },
            contact: {
                name: 'Contact Form',
                icon: '✉️',
                desc: 'Name, email, message',
                fields: [
                    { id: 'name_' + Date.now(), type: 'text', label: 'Full Name', required: 1, placeholder: 'Your full name', width: '50' },
                    { id: 'email_' + Date.now(), type: 'email', label: 'Email Address', required: 1, placeholder: 'your@email.com', width: '50' },
                    { id: 'phone_' + Date.now(), type: 'phone', label: 'Phone Number', required: 0, placeholder: '(555) 123-4567', width: '100' },
                    { id: 'message_' + Date.now(), type: 'textarea', label: 'Message', required: 1, placeholder: 'How can we help you?', width: '100' }
                ]
            },
            feedback: {
                name: 'Feedback Form',
                icon: '⭐',
                desc: 'Collect customer feedback',
                fields: [
                    { id: 'name_' + Date.now(), type: 'text', label: 'Your Name', required: 0, placeholder: '', width: '50' },
                    { id: 'email_' + Date.now(), type: 'email', label: 'Email', required: 0, placeholder: '', width: '50' },
                    { id: 'rating_' + Date.now(), type: 'select', label: 'How would you rate us?', required: 1, options: '5 - Excellent\n4 - Very Good\n3 - Good\n2 - Fair\n1 - Poor', width: '100' },
                    { id: 'feedback_' + Date.now(), type: 'textarea', label: 'Your Feedback', required: 1, placeholder: 'Tell us what you think...', width: '100' }
                ]
            },
            newsletter: {
                name: 'Newsletter Signup',
                icon: '📰',
                desc: 'Email list subscription',
                fields: [
                    { id: 'email_' + Date.now(), type: 'email', label: 'Email Address', required: 1, placeholder: 'Enter your email', width: '100' }
                ]
            },
            quote: {
                name: 'Quote Request',
                icon: '💼',
                desc: 'Service quote request',
                fields: [
                    { id: 'name_' + Date.now(), type: 'text', label: 'Full Name', required: 1, placeholder: '', width: '50' },
                    { id: 'email_' + Date.now(), type: 'email', label: 'Email', required: 1, placeholder: '', width: '50' },
                    { id: 'phone_' + Date.now(), type: 'phone', label: 'Phone', required: 1, placeholder: '', width: '50' },
                    { id: 'company_' + Date.now(), type: 'text', label: 'Company', required: 0, placeholder: '', width: '50' },
                    { id: 'service_' + Date.now(), type: 'select', label: 'Service Needed', required: 1, options: 'Option 1\nOption 2\nOption 3', width: '100' },
                    { id: 'details_' + Date.now(), type: 'textarea', label: 'Project Details', required: 0, placeholder: 'Tell us about your project...', width: '100' }
                ]
            }
        },

        /**
         * Show template picker modal
         */
        showTemplatePicker: function () {
            var self = this;

            // Remove existing picker
            $('.fr-template-picker-overlay').remove();

            var html = '<div class="fr-template-picker-overlay">';
            html += '<div class="fr-template-picker">';
            html += '<div class="fr-template-picker-header">';
            html += '<h2>🚀 ' + (frBuilder.i18n.chooseTemplate || 'Choose a Template') + '</h2>';
            html += '<p>' + (frBuilder.i18n.templateDesc || 'Start with a pre-built template or create from scratch') + '</p>';
            html += '</div>';
            html += '<div class="fr-template-picker-grid">';

            $.each(this.formTemplates, function (key, template) {
                html += '<div class="fr-template-card" data-template="' + key + '">';
                html += '<div class="fr-template-icon">' + template.icon + '</div>';
                html += '<h3>' + template.name + '</h3>';
                html += '<p>' + template.desc + '</p>';
                html += '</div>';
            });

            html += '</div>';
            html += '</div>';
            html += '</div>';

            $('body').append(html);

            // Bind click events
            $('.fr-template-card').on('click', function () {
                var templateKey = $(this).data('template');
                self.applyTemplate(templateKey);
                $('.fr-template-picker-overlay').fadeOut(200, function () {
                    $(this).remove();
                });
            });
        },

        /**
         * Apply selected template
         */
        applyTemplate: function (templateKey) {
            var template = this.formTemplates[templateKey];
            if (!template) return;

            // Deep clone the fields to avoid reference issues
            this.fields = JSON.parse(JSON.stringify(template.fields));

            // Generate unique IDs for each field
            this.fields.forEach(function (field, index) {
                field.id = field.type + '_' + Date.now() + '_' + index;
            });

            this.saveFields();
            this.renderCanvas();
            this.showFormSettings();
            this.showToast((frBuilder.i18n.templateApplied || 'Template applied!'), 'success');
        },

        /**
         * Save fields
         */
        saveFields: function () {
            $('#fr-fields-data').val(JSON.stringify(this.fields));
        },

        /**
         * Sync settings from modal inputs to hidden inputs before save
         */
        syncSettingsToHiddenInputs: function () {
            // Save TinyMCE content first
            if (typeof tinyMCE !== 'undefined') {
                if (tinyMCE.get('fr_success_message_modal')) {
                    tinyMCE.get('fr_success_message_modal').save();
                }
                if (tinyMCE.get('fr_auto_reply_message_modal')) {
                    tinyMCE.get('fr_auto_reply_message_modal').save();
                }
            }

            // Map modal inputs to hidden inputs
            var mappings = {
                '#fr_email_modal': 'input[name="fr_email"][type="hidden"]',
                '#fr_email_template_modal': 'input[name="fr_email_template"][type="hidden"]',
                '#fr_custom_email_html_modal': 'input[name="fr_custom_email_html"][type="hidden"]',
                '#fr_button_text_modal': 'input[name="fr_button_text"][type="hidden"]',
                '#fr_confirmation_type': 'input[name="fr_confirmation_type"][type="hidden"]',
                '#fr_redirect_url_modal': 'input[name="fr_redirect_url"][type="hidden"]',
                '#fr_primary_color_modal': 'input[name="fr_primary_color"][type="hidden"]',
                '#fr_button_color_modal': 'input[name="fr_button_color"][type="hidden"]',
                '#fr_auto_reply_enabled_modal': 'input[name="fr_auto_reply_enabled"][type="hidden"]',
                '#fr_auto_reply_subject_modal': 'input[name="fr_auto_reply_subject"][type="hidden"]'
            };

            // Ensure hidden inputs exist, create if not
            $.each(mappings, function (modalSelector, hiddenSelector) {
                var $modal = $(modalSelector);
                if ($modal.length) {
                    var name = $modal.attr('name');
                    var value = $modal.is(':checkbox') ? ($modal.is(':checked') ? '1' : '0') : $modal.val();

                    // Update existing hidden input or the first input with that name
                    var $hidden = $('input[name="' + name + '"][type="hidden"]');
                    if ($hidden.length) {
                        $hidden.val(value);
                    }
                }
            });

            // Handle textarea values (success message, auto-reply message)
            var successMsg = $('#fr_success_message_modal').length ? $('#fr_success_message_modal').val() : $('[name="fr_success_message_modal"]').val();
            if (successMsg !== undefined) {
                $('input[name="fr_success_message"][type="hidden"]').val(successMsg);
            }

            var autoReplyMsg = $('#fr_auto_reply_message_modal').length ? $('#fr_auto_reply_message_modal').val() : $('[name="fr_auto_reply_message_modal"]').val();
            if (autoReplyMsg !== undefined) {
                $('input[name="fr_auto_reply_message"][type="hidden"]').val(autoReplyMsg);
            }
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            var self = this;

            // Click field on canvas to select
            $(document).on('click', '.fr-canvas-field', function (e) {
                // Don't select if clicking action buttons
                if ($(e.target).closest('.fr-canvas-field-action').length) return;
                e.stopPropagation();
                var index = $(this).data('index');
                self.selectField(index);
            });

            // Inline settings button
            $(document).on('click', '.fr-action-settings', function (e) {
                e.stopPropagation();
                var index = $(this).data('index');
                self.selectField(index);
            });

            // Inline delete button
            $(document).on('click', '.fr-action-delete', function (e) {
                e.stopPropagation();
                var index = $(this).data('index');
                self.selectedField = index;
                self.deleteSelectedField();
            });

            // Mobile settings toggle
            $(document).on('click', '#fr-mobile-toggle-settings', function (e) {
                e.preventDefault();
                self.showFormSettings();
                $('.fr-settings-panel').addClass('active');
            });

            // Desktop settings toggle - Force form settings view
            $(document).on('click', '#fr-btn-settings', function (e) {
                e.preventDefault();
                self.showFormSettings();
            });

            // Mobile close settings
            $(document).on('click', '.fr-settings-mobile-close', function (e) {
                e.preventDefault();
                $('.fr-settings-panel').removeClass('active');
            });

            // Close settings panel when clicking outside on mobile
            $(document).on('click', '.fr-canvas-wrapper', function () {
                if ($(window).width() <= 1024) {
                    $('.fr-settings-panel').removeClass('active');
                }
            });

            // Inline duplicate button
            $(document).on('click', '.fr-action-duplicate', function (e) {
                e.stopPropagation();
                var index = $(this).data('index');
                self.selectedField = index;
                self.duplicateSelectedField();
            });

            // Click canvas background to deselect
            $(document).on('click', '.fr-form-canvas', function (e) {
                if ($(e.target).closest('.fr-canvas-field').length === 0 &&
                    $(e.target).closest('.fr-add-element-btn').length === 0 &&
                    $(e.target).closest('.fr-insert-btn').length === 0) {
                    self.deselectField();
                    self.showFormSettings();
                }
            });

            // Add element button (main)
            $(document).on('click', '.fr-add-element-btn, .fr-insert-btn', function () {
                var position = $(this).data('position');
                self.insertPosition = (position !== undefined) ? position : -1;
                self.selectedTypes = []; // Reset selection
                self.openElementPicker();
            });

            // Element picker card toggle (multi-select)
            $(document).on('click', '.fr-element-picker-card', function () {
                var type = $(this).data('type');
                $(this).toggleClass('selected');

                // Update selected types array
                if ($(this).hasClass('selected')) {
                    self.selectedTypes.push(type);
                } else {
                    self.selectedTypes = self.selectedTypes.filter(function (t) { return t !== type; });
                }

                self.updatePickerFooter();
            });

            // Add selected fields button
            $(document).on('click', '.fr-element-picker-add', function () {
                if (self.selectedTypes.length > 0) {
                    self.addMultipleFields(self.selectedTypes);
                    self.closeElementPicker();
                }
            });

            // Close button
            $(document).on('click', '.fr-element-picker-close', function () {
                self.closeElementPicker();
            });

            // Element picker search
            $(document).on('input', '.fr-element-picker-search', function () {
                var query = $(this).val().toLowerCase();
                $('.fr-element-picker-card').each(function () {
                    var name = $(this).find('.fr-element-picker-card-label').text().toLowerCase();
                    $(this).toggle(name.includes(query));
                });
            });

            // Close element picker on overlay click
            $(document).on('click', '.fr-overlay', function () {
                self.closeElementPicker();
            });

            // Settings inputs - Field settings
            $(document).on('input change', '.fr-settings-body[data-mode="field"] input, .fr-settings-body[data-mode="field"] textarea, .fr-settings-body[data-mode="field"] select', function () {
                var prop = $(this).data('property');
                var val = $(this).attr('type') === 'checkbox' ? ($(this).is(':checked') ? 1 : 0) : $(this).val();

                if (self.selectedField !== null && prop) {
                    self.fields[self.selectedField][prop] = val;
                    self.saveFields();
                    self.renderCanvas();
                }
            });

            // Conditional Logic - Enable toggle
            $(document).on('change', '[data-condition="enabled"]', function () {
                var enabled = $(this).is(':checked');
                if (enabled) {
                    $('.fr-condition-settings').slideDown(200);
                } else {
                    $('.fr-condition-settings').slideUp(200);
                }

                if (self.selectedField !== null) {
                    if (!self.fields[self.selectedField].condition) {
                        self.fields[self.selectedField].condition = {};
                    }
                    self.fields[self.selectedField].condition.enabled = enabled;
                    self.saveFields();
                }
            });

            // Conditional Logic - Operator change (show/hide value field)
            $(document).on('change', '[data-condition="operator"]', function () {
                var op = $(this).val();
                if (['empty', 'not_empty'].includes(op)) {
                    $('.fr-condition-value').slideUp(200);
                } else {
                    $('.fr-condition-value').slideDown(200);
                }

                if (self.selectedField !== null) {
                    if (!self.fields[self.selectedField].condition) {
                        self.fields[self.selectedField].condition = {};
                    }
                    self.fields[self.selectedField].condition.operator = op;
                    self.saveFields();
                }
            });

            // Conditional Logic - Other inputs (action, field, value)
            $(document).on('input change', '[data-condition="action"], [data-condition="field"], [data-condition="value"]', function () {
                var condKey = $(this).data('condition');
                var val = $(this).val();

                if (self.selectedField !== null && condKey) {
                    if (!self.fields[self.selectedField].condition) {
                        self.fields[self.selectedField].condition = {};
                    }
                    self.fields[self.selectedField].condition[condKey] = val;
                    self.saveFields();
                }
            });

            // Delete field
            $(document).on('click', '.fr-settings-delete', function () {
                if (self.selectedField !== null) {
                    self.deleteSelectedField();
                }
            });

            // Duplicate field
            $(document).on('click', '.fr-settings-duplicate', function () {
                if (self.selectedField !== null) {
                    self.duplicateSelectedField();
                }
            });

            // Keyboard shortcuts - Ctrl+D for duplicate
            $(document).on('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                    e.preventDefault();
                    if (self.selectedField !== null) {
                        self.duplicateSelectedField();
                    }
                }
            });

            // Settings icon - open Form Settings Modal
            $(document).on('click', '#fr-btn-settings, #fr-sidebar-btn-settings', function () {
                self.openFormSettingsModal();
            });

            // Close Form Settings Modal
            $(document).on('click', '.fr-settings-modal-close, .fr-settings-modal-overlay', function () {
                self.closeFormSettingsModal();
            });

            // Confirmation type toggle
            $(document).on('change', '#fr_confirmation_type', function () {
                var type = $(this).val();
                $('.fr-confirmation-message, .fr-confirmation-redirect, .fr-confirmation-page').hide();
                $('.fr-confirmation-' + type).show();
            });

            // Auto-reply "use global" toggle
            $(document).on('change', '#fr_use_global_autoreply', function () {
                if ($(this).is(':checked')) {
                    $('#fr-custom-autoreply').slideUp(200);
                } else {
                    $('#fr-custom-autoreply').slideDown(200);
                }
            });

            // Apply settings button
            $(document).on('click', '#fr-save-settings', function () {
                // Get TinyMCE content if available
                if (typeof tinyMCE !== 'undefined') {
                    if (tinyMCE.get('fr_success_message_modal')) {
                        tinyMCE.get('fr_success_message_modal').save();
                    }
                    if (tinyMCE.get('fr_auto_reply_message_modal')) {
                        tinyMCE.get('fr_auto_reply_message_modal').save();
                    }
                }

                self.closeFormSettingsModal();
                self.showToast('Settings applied!', 'success');
            });

            // Preview button
            $(document).on('click', '#fr-btn-preview', function () {
                self.openPreview();
            });

            // Keyboard shortcuts
            $(document).on('keydown', function (e) {
                // Escape - close modals/deselect
                if (e.key === 'Escape') {
                    self.closeElementPicker();
                    $('#fr-preview-modal').fadeOut(200);
                    if (self.selectedField !== null) {
                        self.deselectField();
                        self.showFormSettings();
                    }
                }

                // Delete/Backspace - delete selected field
                if ((e.key === 'Delete' || e.key === 'Backspace') && self.selectedField !== null) {
                    // Don't delete if typing in input
                    if (!$(e.target).is('input, textarea, select')) {
                        e.preventDefault();
                        self.deleteSelectedField();
                    }
                }

                // Ctrl+S / Cmd+S - Save form
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    self.syncSettingsToHiddenInputs();
                    $('#fr-btn-save').click();
                }
            });

            // Sync settings before form submission
            $('form#post, form.edit-form').on('submit', function () {
                self.syncSettingsToHiddenInputs();
            });

            // Also sync on save button click
            $(document).on('click', '#fr-btn-save', function () {
                self.syncSettingsToHiddenInputs();
            });

            // Copy shortcode button (only bind to button to prevent double triggers)
            $('#fr-copy-shortcode').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var shortcode = $('#fr-shortcode-text').text();
                var $btn = $(this);

                // Copy to clipboard
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(shortcode).then(function () {
                        $btn.addClass('copied');
                        self.showToast('Shortcode copied!', 'success');
                        setTimeout(function () {
                            $btn.removeClass('copied');
                        }, 2000);
                    });
                } else {
                    // Fallback for older browsers
                    var $temp = $('<textarea>');
                    $('body').append($temp);
                    $temp.val(shortcode).select();
                    document.execCommand('copy');
                    $temp.remove();
                    $btn.addClass('copied');
                    self.showToast('Shortcode copied!', 'success');
                    setTimeout(function () {
                        $btn.removeClass('copied');
                    }, 2000);
                }
            });

            // Email template toggle
            $(document).on('change', '#fr_email_template_modal', function () {
                if ($(this).val() === 'custom') {
                    $('#fr-custom-email-container').slideDown(200);
                } else {
                    $('#fr-custom-email-container').slideUp(200);
                }
            });

            // Trigger on modal open
            $(document).on('click', '#fr-btn-settings, #fr-sidebar-btn-settings', function () {
                setTimeout(function () { $('#fr_email_template_modal').trigger('change'); }, 50);
            });
        },

        /**
         * Initialize sortable
         */
        initSortable: function () {
            var self = this;

            if ($('.fr-canvas-fields').length && typeof $.fn.sortable !== 'undefined') {
                $('.fr-canvas-fields').sortable({
                    items: '.fr-canvas-field',
                    handle: '.fr-canvas-field-drag',
                    placeholder: 'fr-field-placeholder',
                    tolerance: 'pointer',
                    start: function (e, ui) {
                        ui.item.addClass('fr-dragging');
                        ui.placeholder.height(ui.item.height());
                    },
                    stop: function (e, ui) {
                        ui.item.removeClass('fr-dragging');

                        var newOrder = [];
                        $('.fr-canvas-field').each(function () {
                            var idx = $(this).data('index');
                            newOrder.push(self.fields[idx]);
                        });
                        self.fields = newOrder;
                        self.selectedField = null;
                        self.saveFields();
                        self.renderCanvas();
                        self.showFormSettings();
                    }
                });
            }
        },

        /**
         * Render form canvas
         */
        renderCanvas: function () {
            var self = this;
            var $container = $('.fr-canvas-fields');

            if (this.fields.length === 0) {
                $container.html(
                    '<div class="fr-canvas-empty">' +
                    '<div class="fr-canvas-empty-illustration">' +
                    '<svg width="120" height="100" viewBox="0 0 120 100" fill="none" xmlns="http://www.w3.org/2000/svg">' +
                    '<rect x="15" y="10" width="90" height="80" rx="8" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="2"/>' +
                    '<rect x="25" y="25" width="70" height="10" rx="4" fill="#94a3b8"/>' +
                    '<rect x="25" y="42" width="70" height="10" rx="4" fill="#94a3b8"/>' +
                    '<rect x="25" y="59" width="45" height="10" rx="4" fill="#94a3b8"/>' +
                    '<circle cx="95" cy="75" r="18" fill="#6366f1"/>' +
                    '<path d="M95 67v16M87 75h16" stroke="white" stroke-width="3" stroke-linecap="round"/>' +
                    '</svg>' +
                    '</div>' +
                    '<h3>' + frBuilder.i18n.emptyTitle + '</h3>' +
                    '<p>' + frBuilder.i18n.emptyHint + '</p>' +
                    '<button type="button" class="fr-add-element-btn fr-add-element-btn-primary" data-position="-1">' +
                    '<span class="dashicons dashicons-plus-alt2"></span> ' +
                    frBuilder.i18n.addFirstField +
                    '</button>' +
                    '<div class="fr-shortcut-hints" style="margin-top:20px;font-size:12px;color:#94a3b8;">' +
                    '<span style="margin-right:12px;"><kbd style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-family:monospace;">Ctrl+S</kbd> Save</span>' +
                    '<span><kbd style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-family:monospace;">Del</kbd> Delete field</span>' +
                    '</div>' +
                    '</div>'
                );
                $('.fr-canvas-submit').hide();
                return;
            }

            var html = '';

            // Insert indicator at top
            html += '<div class="fr-insert-indicator"><div class="fr-insert-line"></div><button type="button" class="fr-insert-btn" data-position="0"><span class="dashicons dashicons-plus" style="font-size:14px;width:14px;height:14px;"></span> Insert</button></div>';

            $.each(this.fields, function (index, field) {
                var isActive = self.selectedField === index ? ' fr-field-active' : '';
                var fieldType = self.fieldTypes[field.type] || { icon: '📝', label: field.type };
                var reqMark = field.required ? '<span class="fr-required">*</span>' : '';
                var width = field.width || '100';
                var widthLabel = self.getWidthLabel(width);

                html += '<div class="fr-canvas-field fr-field-width-' + width + isActive + '" data-index="' + index + '" data-width="' + width + '">';
                html += '<div class="fr-canvas-field-drag">⋮⋮</div>';
                html += '<div class="fr-canvas-field-icon">' + fieldType.icon + '</div>';
                html += '<div class="fr-canvas-field-content">';
                html += '<div class="fr-canvas-field-label">' + (field.label || 'Untitled') + reqMark + '</div>';
                html += '<span class="fr-canvas-field-type">' + fieldType.label + '</span>';
                html += '</div>';
                html += '<div class="fr-canvas-field-width-badge" title="Click to change width">' + widthLabel + '</div>';
                html += '<div class="fr-canvas-field-actions">';
                html += '<button type="button" class="fr-canvas-field-action fr-action-settings" data-index="' + index + '" title="Settings"><span class="dashicons dashicons-admin-generic" style="font-size:16px;width:16px;height:16px;"></span></button>';
                html += '<button type="button" class="fr-canvas-field-action fr-action-duplicate" data-index="' + index + '" title="Duplicate (Ctrl+D)"><span class="dashicons dashicons-admin-page" style="font-size:16px;width:16px;height:16px;"></span></button>';
                html += '<button type="button" class="fr-canvas-field-action fr-action-delete" data-index="' + index + '" title="Delete (Del)"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;"></span></button>';
                html += '</div>';
                html += '<div class="fr-resize-handle" data-index="' + index + '" title="Drag to resize"></div>';
                html += '</div>';

                // Insert indicator after each field
                html += '<div class="fr-insert-indicator"><div class="fr-insert-line"></div><button type="button" class="fr-insert-btn" data-position="' + (index + 1) + '"><span class="dashicons dashicons-plus" style="font-size:14px;width:14px;height:14px;"></span> Insert</button></div>';
            });

            // Add element button at bottom
            html += '<button type="button" class="fr-add-element-btn" data-position="-1">';
            html += '<span class="dashicons dashicons-plus-alt2"></span> ';
            html += frBuilder.i18n.addElement;
            html += '</button>';

            $container.html(html);
            $('.fr-canvas-submit').show();
            this.initSortable();
            this.initResizeHandles();
        },

        /**
         * Get width label for display
         */
        getWidthLabel: function (width) {
            var opt = this.widthOptions.find(function (o) { return o.value === width; });
            return opt ? width + '%' : width + '%';
        },

        /**
         * Initialize resize handles for drag-to-resize
         */
        initResizeHandles: function () {
            var self = this;
            var $container = $('.fr-canvas-fields');
            var containerWidth = $container.width();

            $('.fr-resize-handle').on('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var $field = $(this).closest('.fr-canvas-field');
                var index = $field.data('index');
                var startX = e.pageX;
                var startWidth = $field.outerWidth();

                $('body').addClass('fr-resizing');
                $field.addClass('fr-field-resizing');

                $(document).on('mousemove.frResize', function (e) {
                    var deltaX = e.pageX - startX;
                    var newWidth = startWidth + deltaX;
                    var percentWidth = Math.round((newWidth / containerWidth) * 100);

                    // Snap to predefined widths
                    var snappedWidth = self.snapToWidth(percentWidth);
                    $field.attr('class', $field.attr('class').replace(/fr-field-width-\d+/g, ''));
                    $field.addClass('fr-field-width-' + snappedWidth);
                    $field.find('.fr-canvas-field-width-badge').text(snappedWidth + '%');
                });

                $(document).on('mouseup.frResize', function (e) {
                    $(document).off('.frResize');
                    $('body').removeClass('fr-resizing');
                    $field.removeClass('fr-field-resizing');

                    // Get final width and save
                    var finalWidth = $field.attr('class').match(/fr-field-width-(\d+)/);
                    if (finalWidth && finalWidth[1]) {
                        self.fields[index].width = finalWidth[1];
                        self.saveFields();
                    }
                });
            });
        },

        /**
         * Snap percentage to nearest predefined width
         */
        snapToWidth: function (percent) {
            var widths = [25, 33, 50, 66, 75, 100];
            var closest = widths.reduce(function (prev, curr) {
                return (Math.abs(curr - percent) < Math.abs(prev - percent) ? curr : prev);
            });
            return closest;
        },

        /**
         * Render field preview
         */
        renderFieldPreview: function (field) {
            var placeholder = field.placeholder || '';

            switch (field.type) {
                case 'textarea':
                    return '<textarea placeholder="' + placeholder + '"></textarea>';

                case 'select':
                    var options = (field.options || '').split(',');
                    var optHtml = '<option>' + (frBuilder.i18n.select || 'Select...') + '</option>';
                    $.each(options, function (i, opt) {
                        if (opt.trim()) optHtml += '<option>' + opt.trim() + '</option>';
                    });
                    return '<select>' + optHtml + '</select>';

                case 'radio':
                case 'checkbox':
                    var options = (field.options || 'Option 1, Option 2').split(',');
                    var type = field.type === 'radio' ? 'radio' : 'checkbox';
                    var html = '<div style="display:flex;flex-direction:column;gap:6px;">';
                    $.each(options, function (i, opt) {
                        html += '<label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#64748b;">';
                        html += '<input type="' + type + '" name="preview_' + field.id + '">';
                        html += opt.trim();
                        html += '</label>';
                    });
                    return html + '</div>';

                case 'file':
                    return '<input type="file">';

                case 'hidden':
                    return '<div style="color:#94a3b8;font-size:12px;font-style:italic;">Hidden field (not visible to users)</div>';

                case 'header':
                    var headerText = field.label || 'Section Title';
                    return '<h3 style="margin:0;padding:8px 0;font-size:16px;font-weight:600;color:#1e293b;border-bottom:2px solid #e2e8f0;">' + headerText + '</h3>';

                case 'html':
                    var content = field.content || field.label || '<em>Custom text or HTML here...</em>';
                    return '<div style="padding:8px 12px;background:#f8fafc;border-radius:6px;font-size:13px;color:#475569;border:1px dashed #cbd5e1;">' + content + '</div>';

                default:
                    return '<input type="' + field.type + '" placeholder="' + placeholder + '">';
            }
        },

        /**
         * Render field settings panel
         */
        renderFieldSettings: function () {
            var $body = $('.fr-settings-body');
            $body.attr('data-mode', 'field');

            if (this.selectedField === null) {
                this.showFormSettings();
                return;
            }

            var field = this.fields[this.selectedField];
            var fieldType = this.fieldTypes[field.type] || {};

            var html = '<div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #e2e8f0;">';
            html += '<div style="display:flex;align-items:center;gap:10px;">';
            html += '<span style="font-size:24px;">' + (fieldType.icon || '📝') + '</span>';
            html += '<div><strong style="color:#1e293b;">' + (fieldType.label || field.type) + '</strong></div>';
            html += '</div></div>';

            // Label
            html += '<div class="fr-settings-group">';
            html += '<label>' + frBuilder.i18n.label + '</label>';
            html += '<input type="text" data-property="label" value="' + (field.label || '') + '">';
            html += '</div>';

            // Field ID
            html += '<div class="fr-settings-group">';
            html += '<label>' + frBuilder.i18n.fieldId + '</label>';
            html += '<input type="text" data-property="id" value="' + (field.id || '') + '">';
            html += '<div class="fr-settings-hint">' + frBuilder.i18n.fieldIdHint + '</div>';
            html += '</div>';

            // Placeholder
            if (['text', 'email', 'phone', 'name', 'textarea', 'number', 'url', 'password'].includes(field.type)) {
                html += '<div class="fr-settings-group">';
                html += '<label>' + frBuilder.i18n.placeholder + '</label>';
                html += '<input type="text" data-property="placeholder" value="' + (field.placeholder || '') + '">';
                html += '</div>';
            }

            // Options
            if (['select', 'radio', 'checkbox'].includes(field.type)) {
                html += '<div class="fr-settings-group">';
                html += '<label>' + frBuilder.i18n.options + '</label>';
                html += '<textarea data-property="options" rows="4">' + (field.options || '') + '</textarea>';
                html += '<div class="fr-settings-hint">' + frBuilder.i18n.optionsHint + '</div>';
                html += '</div>';
            }

            // Content (for HTML field type)
            if (field.type === 'html') {
                html += '<div class="fr-settings-group">';
                html += '<label>' + (frBuilder.i18n.content || 'Content') + '</label>';
                html += '<textarea data-property="content" rows="5" placeholder="Enter text or HTML...">' + (field.content || '') + '</textarea>';
                html += '<div class="fr-settings-hint">' + (frBuilder.i18n.contentHint || 'You can use HTML tags for formatting') + '</div>';
                html += '</div>';
            }

            // Required (not for content/hidden fields)
            if (!['hidden', 'header', 'html'].includes(field.type)) {
                html += '<div class="fr-settings-group">';
                html += '<label class="fr-settings-checkbox">';
                html += '<input type="checkbox" data-property="required"' + (field.required ? ' checked' : '') + '>';
                html += '<span>' + frBuilder.i18n.required + '</span>';
                html += '</label>';
                html += '</div>';
            }

            // Width (Grid System)
            html += '<div class="fr-settings-group">';
            html += '<label>' + (frBuilder.i18n.fieldWidth || 'Field Width') + '</label>';
            html += '<select data-property="width">';
            var fieldWidth = field.width || '100';
            var self = this;
            $.each(this.widthOptions, function (i, opt) {
                var selected = opt.value === fieldWidth ? ' selected' : '';
                html += '<option value="' + opt.value + '"' + selected + '>' + opt.label + '</option>';
            });
            html += '</select>';
            html += '<div class="fr-settings-hint">' + (frBuilder.i18n.fieldWidthHint || 'Set field column width for grid layout') + '</div>';
            html += '</div>';

            // === CONDITIONAL LOGIC ===
            html += '<div class="fr-settings-section">';
            html += '<div class="fr-settings-section-title">' + (frBuilder.i18n.conditionalLogic || 'Conditional Logic') + '</div>';

            // Enable toggle
            var hasCondition = field.condition && field.condition.enabled;
            html += '<div class="fr-settings-group">';
            html += '<label class="fr-settings-checkbox">';
            html += '<input type="checkbox" data-condition="enabled"' + (hasCondition ? ' checked' : '') + '>';
            html += '<span>' + (frBuilder.i18n.enableConditionalLogic || 'Enable conditional logic') + '</span>';
            html += '</label>';
            html += '</div>';

            // Condition settings (shown when enabled)
            html += '<div class="fr-condition-settings"' + (hasCondition ? '' : ' style="display:none;"') + '>';

            // Action: Show or Hide
            var condAction = (field.condition && field.condition.action) || 'show';
            html += '<div class="fr-settings-group">';
            html += '<label>' + (frBuilder.i18n.action || 'Action') + '</label>';
            html += '<select data-condition="action">';
            html += '<option value="show"' + (condAction === 'show' ? ' selected' : '') + '>' + (frBuilder.i18n.showThisField || 'Show this field') + '</option>';
            html += '<option value="hide"' + (condAction === 'hide' ? ' selected' : '') + '>' + (frBuilder.i18n.hideThisField || 'Hide this field') + '</option>';
            html += '</select>';
            html += '</div>';

            // Target Field
            var condField = (field.condition && field.condition.field) || '';
            html += '<div class="fr-settings-group">';
            html += '<label>' + (frBuilder.i18n.ifField || 'If field') + '</label>';
            html += '<select data-condition="field">';
            html += '<option value="">' + (frBuilder.i18n.selectField || '-- Select field --') + '</option>';
            // Add other fields as options (excluding self and content types)
            $.each(this.fields, function (i, f) {
                if (f.id !== field.id && !['header', 'html', 'hidden'].includes(f.type)) {
                    var selected = condField === f.id ? ' selected' : '';
                    html += '<option value="' + f.id + '"' + selected + '>' + (f.label || f.id) + '</option>';
                }
            });
            html += '</select>';
            html += '</div>';

            // Condition operator
            var condOp = (field.condition && field.condition.operator) || 'equals';
            html += '<div class="fr-settings-group">';
            html += '<label>' + (frBuilder.i18n.condition || 'Condition') + '</label>';
            html += '<select data-condition="operator">';
            html += '<option value="equals"' + (condOp === 'equals' ? ' selected' : '') + '>' + (frBuilder.i18n.equals || 'Equals') + '</option>';
            html += '<option value="not_equals"' + (condOp === 'not_equals' ? ' selected' : '') + '>' + (frBuilder.i18n.notEquals || 'Does not equal') + '</option>';
            html += '<option value="contains"' + (condOp === 'contains' ? ' selected' : '') + '>' + (frBuilder.i18n.contains || 'Contains') + '</option>';
            html += '<option value="not_empty"' + (condOp === 'not_empty' ? ' selected' : '') + '>' + (frBuilder.i18n.notEmpty || 'Is not empty') + '</option>';
            html += '<option value="empty"' + (condOp === 'empty' ? ' selected' : '') + '>' + (frBuilder.i18n.isEmpty || 'Is empty') + '</option>';
            html += '</select>';
            html += '</div>';

            // Value (not shown for empty/not_empty)
            var condValue = (field.condition && field.condition.value) || '';
            html += '<div class="fr-settings-group fr-condition-value"' + (['empty', 'not_empty'].includes(condOp) ? ' style="display:none;"' : '') + '>';
            html += '<label>' + (frBuilder.i18n.value || 'Value') + '</label>';
            html += '<input type="text" data-condition="value" value="' + condValue + '" placeholder="' + (frBuilder.i18n.enterValue || 'Enter value...') + '">';
            html += '</div>';

            html += '</div>'; // .fr-condition-settings
            html += '</div>'; // .fr-settings-section

            // CSS Class
            html += '<div class="fr-settings-group">';
            html += '<label>' + frBuilder.i18n.cssClass + '</label>';
            html += '<input type="text" data-property="css_class" value="' + (field.css_class || '') + '">';
            html += '</div>';

            // Delete
            html += '<button type="button" class="fr-settings-delete">';
            html += '<span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;margin-right:4px;"></span>';
            html += frBuilder.i18n.deleteField;
            html += '</button>';

            $body.html(html);
            $('.fr-settings-header h3').text(frBuilder.i18n.fieldSettings);
            $('#fr-btn-settings').removeClass('active');
        },

        /**
         * Show form settings panel
         */
        showFormSettings: function () {
            var $body = $('.fr-settings-body');
            $body.attr('data-mode', 'form');
            this.showingFormSettings = true;

            var html = '';
            html += '<div class="fr-settings-placeholder">';
            html += '<div class="fr-placeholder-icon" style="font-size:48px;margin-bottom:16px;opacity:0.5;">👆</div>';
            html += '<h3>' + (frBuilder.i18n.selectField || 'Select a Field') + '</h3>';
            html += '<p style="color:#64748b;margin-bottom:24px;">' + (frBuilder.i18n.selectFieldHint || 'Click on any field in the canvas to edit its properties.') + '</p>';
            html += '<button type="button" class="fr-btn fr-btn-secondary fr-open-form-settings">';
            html += '<span class="dashicons dashicons-admin-generic" style="font-size:14px;width:14px;height:14px;margin-right:4px;"></span> ' + (frBuilder.i18n.formSettings || 'Form Settings');
            html += '</button>';
            html += '</div>';

            $body.html(html);
            $('.fr-settings-header h3').text(frBuilder.i18n.settings || 'Settings');
            $('#fr-btn-settings').removeClass('active');

            // Re-bind click
            $('.fr-open-form-settings').on('click', function () {
                frBuilder.openFormSettingsModal();
            });


            // === GENERAL SETTINGS ===
            html += '<div style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">General</div>';

            // Notification Email
            html += '<div class="fr-settings-group">';
            html += '<label>' + frBuilder.i18n.notificationEmail + '</label>';
            html += '<input type="text" name="fr_email" value="' + ($('[name="fr_email"]').val() || '') + '" placeholder="admin@example.com">';
            html += '<div class="fr-settings-hint">' + (frBuilder.i18n.emailHint || 'Separate multiple email addresses with commas') + '</div>';
            html += '</div>';

            // Submit Button Text
            html += '<div class="fr-settings-group">';
            html += '<label>' + frBuilder.i18n.buttonText + '</label>';
            html += '<input type="text" name="fr_button_text" value="' + ($('[name="fr_button_text"]').val() || 'Submit') + '">';
            html += '</div>';

            // Success Message - use pre-rendered wp_editor
            html += '<div id="fr-success-message-placeholder"></div>';

            // Redirect URL
            html += '<div class="fr-settings-group">';
            html += '<label>' + frBuilder.i18n.redirectUrl + '</label>';
            html += '<input type="url" name="fr_redirect_url" value="' + ($('[name="fr_redirect_url"]').val() || '') + '">';
            html += '<div class="fr-settings-hint">' + frBuilder.i18n.redirectHint + '</div>';
            html += '</div>';

            // === AUTO-REPLY SETTINGS ===
            html += '<div style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin:24px 0 12px;padding-top:16px;border-top:1px solid #e2e8f0;">Auto-Reply</div>';

            // Enable Auto-Reply
            html += '<div class="fr-settings-group">';
            html += '<label class="fr-settings-checkbox">';
            html += '<input type="checkbox" name="fr_auto_reply_enabled"' + ($('[name="fr_auto_reply_enabled"]').val() === '1' ? ' checked' : '') + '>';
            html += '<span>' + frBuilder.i18n.enableAutoReply + '</span>';
            html += '</label>';
            html += '</div>';

            // Auto-Reply Subject
            html += '<div class="fr-settings-group">';
            html += '<label>' + frBuilder.i18n.autoReplySubject + '</label>';
            html += '<input type="text" name="fr_auto_reply_subject" value="' + ($('[name="fr_auto_reply_subject"]').val() || '') + '">';
            html += '</div>';

            // Auto-Reply Message - use pre-rendered wp_editor
            html += '<div id="fr-auto-reply-placeholder"></div>';

            // === DESIGN SETTINGS ===
            html += '<div style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin:24px 0 12px;padding-top:16px;border-top:1px solid #e2e8f0;">Design</div>';

            // Colors
            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
            html += '<div class="fr-settings-group">';
            html += '<label>' + frBuilder.i18n.primaryColor + '</label>';
            html += '<input type="color" name="fr_primary_color" value="' + ($('[name="fr_primary_color"]').val() || '#6366f1') + '">';
            html += '</div>';
            html += '<div class="fr-settings-group">';
            html += '<label>' + frBuilder.i18n.buttonColor + '</label>';
            html += '<input type="color" name="fr_button_color" value="' + ($('[name="fr_button_color"]').val() || '#6366f1') + '">';
            html += '</div>';
            html += '</div>';

            $body.html(html);

            // Move pre-rendered wp_editor containers into placeholders
            var $successEditor = $('#fr-editor-success-message');
            var $autoReplyEditor = $('#fr-editor-auto-reply');

            if ($successEditor.length) {
                $('#fr-success-message-placeholder').replaceWith($successEditor.show());
            }
            if ($autoReplyEditor.length) {
                $('#fr-auto-reply-placeholder').replaceWith($autoReplyEditor.show());
            }

            // Re-initialize TinyMCE if needed (for when editors are moved)
            if (typeof tinyMCE !== 'undefined') {
                setTimeout(function () {
                    // Refresh TinyMCE to ensure it works after being moved
                    if (tinyMCE.get('fr_success_message_editor')) {
                        tinyMCE.get('fr_success_message_editor').show();
                    }
                    if (tinyMCE.get('fr_auto_reply_message_editor')) {
                        tinyMCE.get('fr_auto_reply_message_editor').show();
                    }
                }, 100);
            }

            $('.fr-settings-header h3').text(frBuilder.i18n.formSettings);
            $('#fr-btn-settings').addClass('active');
        },

        /**
         * Open Form Settings Modal
         */
        openFormSettingsModal: function () {
            var $modal = $('#fr-form-settings-modal');
            $modal.fadeIn(200);

            // Initialize confirmation type visibility
            var confirmationType = $('#fr_confirmation_type').val() || 'message';
            $('.fr-confirmation-message, .fr-confirmation-redirect, .fr-confirmation-page').hide();
            $('.fr-confirmation-' + confirmationType).show();

            // Initialize auto-reply visibility
            if ($('#fr_use_global_autoreply').is(':checked')) {
                $('#fr-custom-autoreply').hide();
            } else {
                $('#fr-custom-autoreply').show();
            }

            // Show TinyMCE editors after modal opens
            if (typeof tinyMCE !== 'undefined') {
                setTimeout(function () {
                    if (tinyMCE.get('fr_success_message_modal')) {
                        tinyMCE.get('fr_success_message_modal').show();
                    }
                    if (tinyMCE.get('fr_auto_reply_message_modal')) {
                        tinyMCE.get('fr_auto_reply_message_modal').show();
                    }
                }, 100);
            }
        },

        /**
         * Close Form Settings Modal
         */
        closeFormSettingsModal: function () {
            $('#fr-form-settings-modal').fadeOut(200);
        },

        /**
         * Open element picker
         */
        openElementPicker: function () {
            this.renderElementPicker();
            $('.fr-overlay').addClass('active');
            $('.fr-element-picker').addClass('active');
            $('.fr-element-picker-search').val('').focus();
        },

        /**
         * Close element picker
         */
        closeElementPicker: function () {
            $('.fr-overlay').removeClass('active');
            $('.fr-element-picker').removeClass('active');
        },

        /**
         * Render element picker
         */
        renderElementPicker: function () {
            // Remove existing picker
            $('.fr-element-picker, .fr-overlay').remove();

            var groups = { basic: [], choice: [], content: [], other: [] };
            var self = this;

            $.each(this.fieldTypes, function (type, data) {
                if (groups[data.group]) {
                    groups[data.group].push({ type: type, ...data });
                }
            });

            var groupLabels = {
                basic: frBuilder.i18n.basicFields || 'Basic Fields',
                choice: frBuilder.i18n.choiceFields || 'Choice Fields',
                content: frBuilder.i18n.contentFields || 'Content',
                other: frBuilder.i18n.otherFields || 'Other'
            };

            var html = '<div class="fr-overlay"></div>';
            html += '<div class="fr-element-picker">';

            // Header with search and close
            html += '<div class="fr-element-picker-header">';
            html += '<input type="text" class="fr-element-picker-search" placeholder="' + (frBuilder.i18n.searchFields || 'Search fields...') + '">';
            html += '<button type="button" class="fr-element-picker-close">&times;</button>';
            html += '</div>';

            // Body with card grid
            html += '<div class="fr-element-picker-body">';

            $.each(groups, function (groupId, items) {
                if (items.length === 0) return;

                html += '<div class="fr-element-picker-section">';
                html += '<div class="fr-element-picker-section-title">' + groupLabels[groupId] + '</div>';
                html += '<div class="fr-element-picker-grid">';

                $.each(items, function (i, item) {
                    html += '<div class="fr-element-picker-card" data-type="' + item.type + '">';
                    html += '<div class="fr-element-picker-card-icon">' + item.icon + '</div>';
                    html += '<div class="fr-element-picker-card-label">' + item.label + '</div>';
                    html += '</div>';
                });

                html += '</div></div>';
            });

            html += '</div>';

            // Footer with selection count and add button
            html += '<div class="fr-element-picker-footer">';
            html += '<div class="fr-element-picker-count"><strong>0</strong> fields selected</div>';
            html += '<button type="button" class="fr-element-picker-add" disabled>';
            html += '<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span> Add Fields';
            html += '</button>';
            html += '</div>';

            html += '</div>';

            $('body').append(html);
        },

        /**
         * Update picker footer (selection count)
         */
        updatePickerFooter: function () {
            var count = this.selectedTypes.length;
            $('.fr-element-picker-count').html('<strong>' + count + '</strong> field' + (count !== 1 ? 's' : '') + ' selected');
            $('.fr-element-picker-add').prop('disabled', count === 0);

            if (count > 0) {
                $('.fr-element-picker-add').html('<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span> Add ' + count + ' Field' + (count !== 1 ? 's' : ''));
            } else {
                $('.fr-element-picker-add').html('<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span> Add Fields');
            }
        },

        /**
         * Add multiple fields at once
         */
        addMultipleFields: function (types) {
            var self = this;
            var position = this.insertPosition;

            $.each(types, function (i, type) {
                var fieldType = self.fieldTypes[type] || {};
                var newField = {
                    id: type + '_' + Date.now() + '_' + i,
                    type: type,
                    label: fieldType.label || type,
                    required: 0,
                    placeholder: '',
                    options: '',
                    css_class: ''
                };

                if (position >= 0 && position <= self.fields.length) {
                    self.fields.splice(position + i, 0, newField);
                } else {
                    self.fields.push(newField);
                }
            });

            this.insertPosition = -1;
            this.saveFields();
            this.renderCanvas();
            this.showFormSettings();
            this.showToast(types.length + ' field' + (types.length !== 1 ? 's' : '') + ' added', 'success');
        },

        /**
         * Add field
         */
        addField: function (type) {
            var fieldType = this.fieldTypes[type] || {};
            var newField = {
                id: type + '_' + Date.now(),
                type: type,
                label: fieldType.label || type,
                required: 0,
                placeholder: '',
                options: '',
                css_class: ''
            };

            if (this.insertPosition >= 0 && this.insertPosition <= this.fields.length) {
                this.fields.splice(this.insertPosition, 0, newField);
                this.selectedField = this.insertPosition;
            } else {
                this.fields.push(newField);
                this.selectedField = this.fields.length - 1;
            }

            this.insertPosition = -1;
            this.saveFields();
            this.renderCanvas();
            this.renderFieldSettings();
        },

        /**
         * Select field
         */
        selectField: function (index) {
            this.selectedField = index;
            this.showingFormSettings = false;

            // Auto-open sidebar on mobile
            if ($(window).width() <= 1024) {
                $('.fr-settings-panel').addClass('active');
            }

            this.renderCanvas();
            this.renderFieldSettings();
        },

        /**
         * Deselect field
         */
        deselectField: function () {
            this.selectedField = null;
            this.renderCanvas();
        },

        /**
         * Delete selected field with animation
         */
        deleteSelectedField: function () {
            if (this.selectedField === null) return;

            var self = this;
            var $field = $('.fr-canvas-field[data-index="' + this.selectedField + '"]');
            var fieldLabel = this.fields[this.selectedField].label || 'Field';

            // Animate out
            $field.addClass('fr-removing');

            setTimeout(function () {
                self.fields.splice(self.selectedField, 1);
                self.selectedField = null;
                self.saveFields();
                self.renderCanvas();
                self.showFormSettings();
                self.showToast(fieldLabel + ' deleted', 'info');
            }, 200);
        },

        /**
         * Duplicate selected field
         */
        duplicateSelectedField: function () {
            if (this.selectedField === null) return;

            var field = this.fields[this.selectedField];
            if (!field) return;

            // Deep copy field
            var newField = JSON.parse(JSON.stringify(field));

            // Generate new ID
            newField.id = field.type + '_' + Date.now();

            // Add (Copy) to label
            if (newField.label) {
                newField.label += ' (Copy)';
            }

            // Insert after selected field
            this.fields.splice(this.selectedField + 1, 0, newField);

            // Select the new field
            this.selectedField = this.selectedField + 1;

            this.saveFields();
            this.renderCanvas();
            this.showFieldSettings(newField);
            this.showToast('Field duplicated', 'success');
        },

        /**
         * Open preview
         */
        openPreview: function () {
            var self = this;

            // Show modal immediately with loading state
            $('#fr-preview-modal').fadeIn(200);
            $('#fr-preview-frame').attr('srcdoc', '<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;color:#6b7280;"><div style="text-align:center;"><div style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#6366f1;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 16px;"></div><p>Loading preview...</p></div><style>@keyframes spin{to{transform:rotate(360deg)}}</style></body></html>');

            var formData = {
                action: 'fr_form_preview',
                nonce: frBuilder.previewNonce,
                post_id: this.formId,
                fields: this.fields,
                button_text: $('[name="fr_button_text"]').val() || 'Submit'
            };

            $.post(frBuilder.ajaxUrl, formData, function (response) {
                if (response.success) {
                    $('#fr-preview-frame').attr('srcdoc', response.data.html);
                } else {
                    $('#fr-preview-frame').attr('srcdoc', '<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;color:#ef4444;"><p>' + (response.data.message || 'Error loading preview') + '</p></body></html>');
                    self.showToast(response.data.message || 'Error generating preview', 'error');
                }
            }).fail(function () {
                $('#fr-preview-frame').attr('srcdoc', '<html><body style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;color:#ef4444;"><p>Network error - please try again</p></body></html>');
                self.showToast('Network error - please try again', 'error');
            });
        },

        /**
         * Show toast notification
         */
        showToast: function (message, type) {
            type = type || 'info';

            // Create container if not exists
            if (!$('.fr-toast-container').length) {
                $('body').append('<div class="fr-toast-container"></div>');
            }

            var icons = {
                success: '✓',
                error: '✕',
                info: 'ℹ',
                warning: '⚠'
            };

            var $toast = $('<div class="fr-toast fr-toast-' + type + '">' +
                '<span class="fr-toast-icon">' + icons[type] + '</span>' +
                '<span>' + message + '</span>' +
                '</div>');

            $('.fr-toast-container').append($toast);

            // Auto remove after 3s
            setTimeout(function () {
                $toast.addClass('fr-toast-hiding');
                setTimeout(function () {
                    $toast.remove();
                }, 200);
            }, 3000);
        }
    };

    // Create toast container on init
    $(document).ready(function () {
        if ($('.fr-builder').length) {
            FR_Builder.init();

            // Append toast container
            if (!$('.fr-toast-container').length) {
                $('body').append('<div class="fr-toast-container"></div>');
            }
        }
    });

    window.FR_Builder = FR_Builder;

})(jQuery);
