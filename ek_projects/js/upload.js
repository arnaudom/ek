(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.ekProjectsUpload = {
    attach: function (context, settings) {
        const $dropZone =  $(once('file-drop-zone', '#file-drop-zone', context));
        //const $dropZone = $('#file-drop-zone', context).once('file-drop-zone');
        if ($dropZone.length === 0) return;

        const $fileInput = $('input[type="file"][name="files[upload_doc]"]', context);
        const $previewContainer = $('#file-preview-container', context);
        const $imagePreview = $('#image-preview', context);
        const $fileInfo = $('#file-info', context);
        const $fileName = $('#file-name', context);
        const $fileSize = $('#file-size', context);
        const $removeBtn = $('#remove-file-btn', context);
        const $uploadBtn = $('#upbuttonid', context);
        const $managedFileWrapper = $('.form-managed-file', context);
        const allowedExtensions = settings.ek_projects?.extensions || [];

        let isUploading = false;
        let currentFile = null;

        console.log('Upload form initialized');

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            $dropZone.on(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            });
        });

        // Highlight drop zone when item is dragged over
        ['dragenter', 'dragover'].forEach(eventName => {
            $dropZone.on(eventName, function() {
            $dropZone.addClass('drag-active');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            $dropZone.on(eventName, function() {
            $dropZone.removeClass('drag-active');
            });
        });

        // Handle dropped files
        $dropZone.on('drop', function(e) {
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
            const file = files[0];
            
            if (validateFile(file)) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                $fileInput[0].files = dataTransfer.files;
                
                currentFile = file;
                $fileInput.trigger('change');
                
                // Show preview immediately
                showPreview(file);
            } else {
                showError('Invalid file type. Allowed: ' + allowedExtensions.join(', '));
            }
            }
        });

        // Click on drop zone to trigger file input
        $dropZone.on('click', function(e) {
            if (!$(e.target).closest('.file-preview-container').length && 
                !$(e.target).closest('.remove-file-btn').length) {
            $fileInput.trigger('click');
            }
        });

        // Handle file input change
        $fileInput.on('change', function() {
            const file = this.files[0];
            if (file && validateFile(file)) {
            currentFile = file;
            showPreview(file);
            } else if (!file) {
            // File was cleared
            currentFile = null;
            clearPreview();
            }
        });

        // Custom Remove button click handler
        $removeBtn.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Custom remove button clicked');
            removeDrupalFile();
        });

        // Watch for Drupal's managed file AJAX completion
        $(document).ajaxComplete(function(event, xhr, settings) {
            // File upload completed
            if (settings.extraData && settings.extraData._triggering_element_name === 'files[upload_doc]') {
            setTimeout(function() {
                const $drupalRemoveBtn = $managedFileWrapper.find('input[type="submit"][value="Remove"]');
                if ($drupalRemoveBtn.length > 0) {
                console.log('File uploaded to Drupal, enabling upload button');
                $uploadBtn.prop('disabled', false);
                
                // Ensure preview is still visible
                if (currentFile) {
                    showPreview(currentFile);
                }
                }
            }, 100);
            }

            // File removal completed by Drupal
            if (settings.extraData && settings.extraData._triggering_element_name === 'upload_doc_remove_button') {
            console.log('Drupal file removed via AJAX');
            setTimeout(function() {
                currentFile = null;
                clearPreview();
                $uploadBtn.prop('disabled', true);
                $fileInput.val('');
            }, 100);
            }

            // Main upload button response
            if (isUploading && settings.extraData && settings.extraData._triggering_element_name === 'op') {
            try {
                let responseHtml = '';
                
                if (xhr.responseJSON) {
                const response = xhr.responseJSON;
                if (Array.isArray(response)) {
                    response.forEach(function(item) {
                    if (item.command === 'insert' && item.data) {
                        responseHtml += item.data;
                    }
                    });
                }
                } else if (xhr.responseText) {
                responseHtml = xhr.responseText;
                }

                if (responseHtml.includes('upload-success') || responseHtml.includes('File uploaded')) {
                console.log('Upload successful, clearing form');
                setTimeout(function() {
                    clearEntireForm();
                    isUploading = false;
                    
                    setTimeout(function() {
                    $('#doc_upload_message', context).fadeOut(function() {
                        $(this).html('').show();
                    });
                    }, 5000);
                }, 500);
                } else if (responseHtml.includes('upload-error') || responseHtml.includes('Error uploading')) {
                isUploading = false;
                }
            } catch (e) {
                console.error('Error processing upload response:', e);
                isUploading = false;
            }
            }
        });

        // Listen for Drupal's Remove button clicks (using mousedown which Drupal uses)
        $(document).on('mousedown', '.form-managed-file input[type="submit"][value="Remove"]', function() {
            console.log('Drupal remove button clicked');
            // The ajaxComplete handler above will handle the actual clearing
        });

        // Main upload button handler
        $uploadBtn.on('mousedown', function() {
            if (!isUploading) {
            console.log('Upload button clicked');
            isUploading = true;
            }
        });

        // Validate file extension
        function validateFile(file) {
            if (allowedExtensions.length === 0) return true;
            
            const fileName = file.name.toLowerCase();
            const extension = fileName.split('.').pop();
            return allowedExtensions.includes(extension);
        }

        // Show preview
        function showPreview(file) {
            console.log('Showing preview for:', file.name);
            const fileType = file.type;
            const isImage = fileType.startsWith('image/');

            $dropZone.find('.drop-zone-message').hide();
            $previewContainer.show();
            $removeBtn.show(); // Explicitly show the button

            if (isImage) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $imagePreview.attr('src', e.target.result).show();
                $fileInfo.hide();
                console.log('Image preview shown');
            };
            reader.readAsDataURL(file);
            } else {
            $imagePreview.hide();
            $fileInfo.show();
            $fileName.text(file.name);
            $fileSize.text(formatFileSize(file.size));
            console.log('File info shown');
            }
        }

        // Clear preview only
        function clearPreview() {
            console.log('Clearing preview');
            $previewContainer.hide();
            $removeBtn.hide();
            $imagePreview.attr('src', '').hide();
            $fileInfo.hide();
            $dropZone.find('.drop-zone-message').show();
        }

        // Remove file from Drupal's managed file field
        function removeDrupalFile() {
            const $drupalRemoveBtn = $managedFileWrapper.find('input[type="submit"][value="Remove"]');
            
            if ($drupalRemoveBtn.length > 0) {
            console.log('Triggering Drupal remove button');
            $drupalRemoveBtn.trigger('mousedown');
            // The ajaxComplete will handle clearing the preview
            } else {
            console.log('No Drupal file, just clearing input');
            $fileInput.val('');
            currentFile = null;
            clearPreview();
            $uploadBtn.prop('disabled', true);
            }
        }

        // Clear entire form after successful upload
        function clearEntireForm() {
            console.log('Clearing entire form');
            
            // Clear text fields
            $('#edit-sub-folder', context).val('');
            $('#edit-comment', context).val('');
            
            // Clear current file reference
            currentFile = null;
            
            // Clear the file input
            $fileInput.val('');
            
            // Clear preview
            clearPreview();
            
            // Remove Drupal's managed file if it exists
            const $drupalRemoveBtn = $managedFileWrapper.find('input[type="submit"][value="Remove"]');
            if ($drupalRemoveBtn.length > 0) {
            console.log('Clearing Drupal managed file');
            $drupalRemoveBtn.trigger('mousedown');
            }
            
            // Disable upload button
            $uploadBtn.prop('disabled', true);
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Show error message
        function showError(message) {
            const $messageDiv = $('#doc_upload_message', context);
            $messageDiv.html('<div class="red upload-error">' + message + '</div>');
            setTimeout(function() {
            $messageDiv.fadeOut(function() {
                $(this).html('').show();
            });
            }, 5000);
        }

        // Initial state
        $uploadBtn.prop('disabled', true);
        $removeBtn.hide();
        
        // Check if there's already a file
        setTimeout(function() {
            const $existingFile = $managedFileWrapper.find('.file a');
            if ($existingFile.length > 0) {
            $uploadBtn.prop('disabled', false);
            }
        }, 100);
        }
    };

})(jQuery, Drupal, drupalSettings);