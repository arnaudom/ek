(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_sales = {
        attach: function (context, settings) {
            load_sales_docs();

            function load_sales_docs() {
                jQuery.ajax({
                    dataType: "json",
                    url: drupalSettings.path.baseUrl + 'ek_sales/load_documents',
                    data: {abid: settings.abid},
                    success: function (remoteData) {
                        jQuery('.loading').remove();
                        if(remoteData.data){
                          jQuery('#nodoc').remove();
                          jQuery('#sales_docs').html(remoteData.data);
                          addajax();
                          adddragdrop();  
                        }
                        
                    }
                });

            }

            function addajax() {
                // Bind Ajax behaviors to all items showing the class.
                jQuery('.use-ajax:not(.ajax-processed)').addClass('ajax-processed').each(function () {

                    var element_settings = {};
                    // Clicked links look better with the throbber than the progress bar.
                    element_settings.progress = {'type': 'throbber'};

                    // For anchor tags, these will go to the target of the anchor rather
                    // than the usual location.
                    if (jQuery(this).attr('href')) {
                        element_settings.url = jQuery(this).attr('href');
                        element_settings.event = 'click';
                    }
                    var base = jQuery(this).attr('id');
                    element_settings.base = base;
                    element_settings.element = this;

                    Drupal.ajax[base] = new Drupal.ajax(element_settings);
                });
            }

            /*
             * Drag & drop for document folders
             */
            function adddragdrop() {

                jQuery(".move").draggable({
                    cursor: "move",
                    cursorAt: {left: 0, top: 0},
                    //revert:true,
                    handle: ".handle-ico",
                    helper: "clone",
                    stop: function (event, ui) {
                        if (status == 1) {
                            load_sales_docs();
                        }
                    }
                });

                jQuery(".drop-folder").droppable({
                    activeClass: "ui-state-default",
                    hoverClass: "tr-drop",
                    accept: ":not(.ui-sortable-helper), .move",
                    activeClass: "",
                    drop: function (event, ui) {
                        jQuery.ajax({
                            type: "POST",
                            url: drupalSettings.path.baseUrl + "ek_sales/dragdrop",
                            data: {from: (ui.draggable).attr("id"), to: this.id},
                            async: false
                        });
                        status = 1;
                    }
                });
            }

        } //attach
    }; //behaviors

    /* 
     * Toggle deleted files visibility
     */
    jQuery(function () {
        jQuery('.hideFile').click(function () {
            if (jQuery('.hideFile').hasClass('show-ico'))
                jQuery('.hide').hide('fast');
            if (jQuery('.hideFile').hasClass('hide-ico'))
                jQuery('.hide').show('fast');
            jQuery('.hideFile').toggleClass('show-ico hide-ico');

        });
    });

    /**
     * ========================================
     * IMPROVED UPLOAD FORM BEHAVIORS
     * File upload with drag & drop
     * ======================================== 
     */
    Drupal.behaviors.uploadFormDragDrop = {
        attach: function (context, settings) {
            // Get the drop zone if it exists
            var dropZoneSelector = '#upload-drop-zone';
            var $dropZone = jQuery(dropZoneSelector);
            
            if ($dropZone.length === 0) {
                return; // Form not on this page
            }

            var $fileInput = $dropZone.find('#file-input');
            var $fileList = $dropZone.find('#file-list');
            var $dropHintContainer = $dropZone.find('.drop-hint-container');
            var $uploadButton = $dropZone.find('#upload-button');
            var $resetButton = $dropZone.find('.reset-button');

            var selectedFiles = [];

            /**
             * Prevent default drag/drop behaviors
             */
            var dragDropEvents = ['dragenter', 'dragover', 'dragleave', 'drop'];
            dragDropEvents.forEach(function(eventName) {
                $dropZone.on(eventName, preventDefaults);
                jQuery(document).on(eventName, preventDefaults);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            /**
             * Visual feedback for drag over
             */
            $dropHintContainer.on('dragenter dragover', function() {
                $dropHintContainer.addClass('drag-over');
            });

            $dropHintContainer.on('dragleave drop', function() {
                $dropHintContainer.removeClass('drag-over');
            });

            /**
             * Handle dropped files
             */
            $dropHintContainer.on('drop', function(e) {
                var dt = e.originalEvent.dataTransfer;
                var files = dt.files;
                handleFiles(files);
            });

            /**
             * Handle file input change
             */
            $fileInput.on('change', function(e) {
                handleFiles(this.files);
            });

            /**
             * Click drop zone to open file picker
             */
            $dropHintContainer.on('click', function() {
                $fileInput.click();
            });

            /**
             * Process selected files
             */
            function handleFiles(files) {
                selectedFiles = [];
                
                for (var i = 0; i < files.length; i++) {
                    var file = files[i];
                    
                    // Validate file
                    if (isFileAllowed(file)) {
                        selectedFiles.push(file);
                    } else {
                        showError('File type not allowed: ' + file.name);
                    }
                }

                updateFileList();
                updateFormState();
            }

            /**
             * Check if file type is allowed
             */
            function isFileAllowed(file) {
                var allowedExtensions = [
                    'png', 'gif', 'jpg', 'jpeg', 'txt', 'doc', 'docx',
                    'xls', 'xlsx', 'odt', 'ods', 'odp', 'pdf', 'ppt',
                    'pptx', 'sxc', 'rar', 'rtf', 'tiff', 'zip'
                ];

                var fileName = file.name;
                var fileExtension = fileName.substring(fileName.lastIndexOf('.') + 1).toLowerCase();
                return allowedExtensions.indexOf(fileExtension) > -1;
            }

            /**
             * Format file size for display
             */
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                var k = 1024;
                var sizes = ['Bytes', 'KB', 'MB', 'GB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
            }

            /**
             * Get file extension for icon display
             */
            function getFileIcon(fileName) {
                var extension = fileName.substring(fileName.lastIndexOf('.') + 1).toUpperCase();
                return extension.length <= 3 ? extension : '📄';
            }

            /**
             * Update the displayed file list
             */
            function updateFileList() {
                $fileList.html('');

                if (selectedFiles.length === 0) {
                    $fileList.removeClass('has-files');
                    return;
                }

                $fileList.addClass('has-files');

                jQuery.each(selectedFiles, function(index, file) {
                    var $li = jQuery('<li></li>');
                    
                    var html = '<div class="file-item">' +
                        '<div class="file-icon">' + getFileIcon(file.name) + '</div>' +
                        '<div class="file-info">' +
                        '<span class="file-name" title="' + file.name + '">' + file.name + '</span>' +
                        '<span class="file-size">' + formatFileSize(file.size) + '</span>' +
                        '</div>' +
                        '<button type="button" class="file-remove-btn" data-index="' + index + '" title="Remove file">✕</button>' +
                        '</div>';
                    
                    $li.html(html);
                    $fileList.append($li);

                    // Attach remove button handler
                    $li.find('.file-remove-btn').on('click', function(e) {
                        e.preventDefault();
                        var fileIndex = jQuery(this).data('index');
                        removeFile(fileIndex);
                    });
                });
            }

            /**
             * Remove a file from the selection
             */
            function removeFile(index) {
                selectedFiles.splice(index, 1);
                updateFileList();
                updateFormState();
            }

            /**
             * Update the form state based on selected files
             */
            function updateFormState() {
                if (selectedFiles.length > 0) {
                    $uploadButton.prop('disabled', false).removeClass('disabled');
                    $resetButton.show();
                } else {
                    $uploadButton.prop('disabled', true).addClass('disabled');
                    $resetButton.hide();
                }
            }

            /**
             * Show error message
             */
            function showError(message) {
                var $messagesContainer = $dropZone.find('.upload-messages');
                
                var errorHtml = '<div class="messages messages--error">' +
                    '<h2>Upload Error</h2>' +
                    '<ul><li>' + message + '</li></ul>' +
                    '</div>';

                $messagesContainer.html(errorHtml);

                // Auto-remove error after 5 seconds
                setTimeout(function() {
                    $messagesContainer.find('.messages--error').fadeOut(function() {
                        jQuery(this).remove();
                    });
                }, 5000);
            }

            /**
             * Reset button functionality
             */
            if ($resetButton.length > 0) {
                $resetButton.on('click', function(e) {
                    e.preventDefault();
                    selectedFiles = [];
                    $fileInput.val('');
                    updateFileList();
                    updateFormState();
                    $fileList.removeClass('has-files');
                });
            }

            // Initialize form state
            updateFormState();
        }
    }; // uploadFormDragDrop behavior

})(jQuery, Drupal, drupalSettings);