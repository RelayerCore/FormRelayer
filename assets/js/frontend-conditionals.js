/**
 * FormRelayer Conditional Logic - Frontend Handler
 * 
 * Applies show/hide conditional logic on frontend forms
 * 
 * @package FormRelayer
 * @since 2.2.0
 */

(function ($) {
    'use strict';

    var FRConditionals = {
        /**
         * Initialize conditional logic for all forms
         */
        init: function () {
            var self = this;

            // Process each form that has conditional data
            $('.fr-form').each(function () {
                self.processForm($(this));
            });
        },

        /**
         * Process a single form for conditional logic
         */
        processForm: function ($form) {
            var self = this;
            var conditions = $form.data('conditions');

            // If no conditions data attribute, try to find it from script tag
            if (!conditions) {
                var formId = $form.attr('id');
                if (formId && window['frConditions_' + formId.replace('fr-form-', '')]) {
                    conditions = window['frConditions_' + formId.replace('fr-form-', '')];
                }
            }

            if (!conditions || !conditions.length) {
                return;
            }

            // Bind change events to trigger fields
            conditions.forEach(function (cond) {
                if (!cond.enabled || !cond.field) return;

                var $triggerField = $form.find('[name="' + cond.field + '"], [name="' + cond.field + '[]"]');

                if ($triggerField.length) {
                    // Bind change event
                    $triggerField.on('change input', function () {
                        self.evaluateCondition($form, cond);
                    });

                    // Initial evaluation
                    self.evaluateCondition($form, cond);
                }
            });
        },

        /**
         * Evaluate a single condition
         */
        evaluateCondition: function ($form, cond) {
            var $triggerField = $form.find('[name="' + cond.field + '"], [name="' + cond.field + '[]"]');
            var $targetField = $form.find('[data-field-id="' + cond.targetField + '"]');

            if (!$targetField.length) {
                // Try finding by field wrapper with matching name input
                $targetField = $form.find('[name="' + cond.targetField + '"]').closest('.fr-field');
            }

            if (!$triggerField.length || !$targetField.length) {
                return;
            }

            var triggerValue = this.getFieldValue($triggerField);
            var conditionMet = this.checkCondition(triggerValue, cond.operator, cond.value);

            // Apply show/hide based on action and condition result
            if (cond.action === 'show') {
                if (conditionMet) {
                    $targetField.slideDown(200).removeClass('fr-hidden');
                } else {
                    $targetField.slideUp(200).addClass('fr-hidden');
                }
            } else if (cond.action === 'hide') {
                if (conditionMet) {
                    $targetField.slideUp(200).addClass('fr-hidden');
                } else {
                    $targetField.slideDown(200).removeClass('fr-hidden');
                }
            }
        },

        /**
         * Get value from a field (handles various input types)
         */
        getFieldValue: function ($field) {
            var type = $field.attr('type');
            var tagName = $field.prop('tagName').toLowerCase();

            if (tagName === 'select') {
                return $field.val() || '';
            }

            if (type === 'checkbox') {
                // For checkboxes, get all checked values
                var values = [];
                $field.filter(':checked').each(function () {
                    values.push($(this).val());
                });
                return values.join(',');
            }

            if (type === 'radio') {
                return $field.filter(':checked').val() || '';
            }

            return $field.val() || '';
        },

        /**
         * Check if condition is met
         */
        checkCondition: function (fieldValue, operator, condValue) {
            fieldValue = String(fieldValue).toLowerCase().trim();
            condValue = String(condValue || '').toLowerCase().trim();

            switch (operator) {
                case 'equals':
                    return fieldValue === condValue;

                case 'not_equals':
                    return fieldValue !== condValue;

                case 'contains':
                    return fieldValue.indexOf(condValue) !== -1;

                case 'not_empty':
                    return fieldValue !== '';

                case 'empty':
                    return fieldValue === '';

                default:
                    return false;
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        FRConditionals.init();
    });

    // Expose for programmatic access
    window.FRConditionals = FRConditionals;

})(jQuery);
