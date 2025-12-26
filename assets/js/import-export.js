/**
 * FormRelayer Import/Export JavaScript
 *
 * @package FormRelayer
 * @since 2.1.0
 */

(function ($) {
    'use strict';

    var ImportExport = {
        selectedFile: null,

        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;

            // Export form selection
            $('#fr-export-form-select').on('change', function () {
                $('#fr-export-single').prop('disabled', !$(this).val());
            });

            // Export single form
            $('#fr-export-single').on('click', function () {
                var formId = $('#fr-export-form-select').val();
                if (formId) {
                    self.exportForm(formId);
                }
            });

            // Export all forms
            $('#fr-export-all').on('click', function () {
                self.exportAllForms();
            });

            // File input change
            $('#fr-import-file').on('change', function (e) {
                if (this.files && this.files[0]) {
                    self.handleFileSelect(this.files[0]);
                }
            });

            // Drag and drop
            var dropzone = $('#fr-import-dropzone');

            dropzone.on('dragover dragenter', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });

            dropzone.on('dragleave drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });

            dropzone.on('drop', function (e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files && files[0]) {
                    self.handleFileSelect(files[0]);
                }
            });

            // Remove file
            $('.fr-ie-remove-file').on('click', function () {
                self.clearFileSelection();
            });

            // Import button
            $('#fr-import-btn').on('click', function () {
                if (self.selectedFile) {
                    self.importForm();
                }
            });
        },

        handleFileSelect: function (file) {
            var self = this;

            // Validate file type
            if (!file.name.endsWith('.json')) {
                alert(frImportExport.i18n.invalidFile);
                return;
            }

            this.selectedFile = file;

            // Update UI
            $('.fr-ie-upload-inner').hide();
            $('.fr-ie-file-info').show();
            $('.fr-ie-filename').text(file.name);
            $('#fr-import-btn').prop('disabled', false);
        },

        clearFileSelection: function () {
            this.selectedFile = null;
            $('#fr-import-file').val('');
            $('.fr-ie-upload-inner').show();
            $('.fr-ie-file-info').hide();
            $('#fr-import-btn').prop('disabled', true);
            $('#fr-import-results').hide().empty();
        },

        exportForm: function (formId) {
            var self = this;
            var $btn = $('#fr-export-single');

            $btn.prop('disabled', true).find('.dashicons').addClass('dashicons-update').removeClass('dashicons-download');

            $.ajax({
                url: frImportExport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fr_export_form',
                    nonce: frImportExport.nonce,
                    form_id: formId
                },
                success: function (response) {
                    if (response.success) {
                        self.downloadJson(response.data.data, response.data.filename);
                    } else {
                        alert(response.data.message || frImportExport.i18n.error);
                    }
                },
                error: function () {
                    alert(frImportExport.i18n.error);
                },
                complete: function () {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-download');
                }
            });
        },

        exportAllForms: function () {
            var self = this;
            var $btn = $('#fr-export-all');

            $btn.prop('disabled', true).find('.dashicons').addClass('dashicons-update').removeClass('dashicons-database-export');

            $.ajax({
                url: frImportExport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fr_export_all_forms',
                    nonce: frImportExport.nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.downloadJson(response.data.data, response.data.filename);
                    } else {
                        alert(response.data.message || frImportExport.i18n.error);
                    }
                },
                error: function () {
                    alert(frImportExport.i18n.error);
                },
                complete: function () {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-database-export');
                }
            });
        },

        importForm: function () {
            var self = this;
            var $btn = $('#fr-import-btn');
            var $results = $('#fr-import-results');

            if (!this.selectedFile) {
                alert(frImportExport.i18n.selectFile);
                return;
            }

            // Read file content
            var reader = new FileReader();

            reader.onload = function (e) {
                var jsonData = e.target.result;

                $btn.prop('disabled', true).find('.dashicons').addClass('dashicons-update').removeClass('dashicons-upload');
                $results.hide().empty();

                $.ajax({
                    url: frImportExport.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'fr_import_form',
                        nonce: frImportExport.nonce,
                        json_data: jsonData
                    },
                    success: function (response) {
                        if (response.success) {
                            self.showImportResults(response.data);
                            self.clearFileSelection();
                        } else {
                            $results.html(
                                '<div class="fr-ie-notice fr-ie-notice-error">' +
                                '<span class="dashicons dashicons-warning"></span>' +
                                (response.data.message || frImportExport.i18n.error) +
                                '</div>'
                            ).show();
                        }
                    },
                    error: function () {
                        $results.html(
                            '<div class="fr-ie-notice fr-ie-notice-error">' +
                            '<span class="dashicons dashicons-warning"></span>' +
                            frImportExport.i18n.error +
                            '</div>'
                        ).show();
                    },
                    complete: function () {
                        $btn.prop('disabled', true).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-upload');
                    }
                });
            };

            reader.onerror = function () {
                alert(frImportExport.i18n.error);
            };

            reader.readAsText(this.selectedFile);
        },

        showImportResults: function (data) {
            var $results = $('#fr-import-results');
            var html = '';

            html += '<div class="fr-ie-notice fr-ie-notice-success">';
            html += '<span class="dashicons dashicons-yes-alt"></span>';
            html += data.message;
            html += '</div>';

            if (data.imported && data.imported.length) {
                html += '<h4>Imported Forms:</h4>';
                html += '<ul>';
                data.imported.forEach(function (form) {
                    html += '<li>';
                    html += '<span class="dashicons dashicons-yes"></span>';
                    html += '<a href="' + form.edit_url + '" target="_blank">' + form.title + '</a>';
                    html += ' (ID: ' + form.id + ')';
                    html += '</li>';
                });
                html += '</ul>';
            }

            if (data.errors && data.errors.length) {
                html += '<h4 style="color: #d63638;">Errors:</h4>';
                html += '<ul>';
                data.errors.forEach(function (error) {
                    html += '<li style="color: #d63638;">';
                    html += '<span class="dashicons dashicons-warning" style="color: #d63638;"></span>';
                    html += error;
                    html += '</li>';
                });
                html += '</ul>';
            }

            $results.html(html).show();
        },

        downloadJson: function (data, filename) {
            var jsonStr = JSON.stringify(data, null, 2);
            var blob = new Blob([jsonStr], { type: 'application/json' });
            var url = URL.createObjectURL(blob);

            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    };

    $(document).ready(function () {
        ImportExport.init();
    });

})(jQuery);
