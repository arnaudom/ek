(function ($, Drupal, drupalSettings) {

            Drupal.behaviors.journal = {
                attach: function (context, settings) {
                    
                    $('.print', context).on('click', function () {
                        var printContents = document.getElementById("p" + this.id).innerHTML;console.
                        // Create an iframe
                        var iframe = $('<iframe>', {
                            name: 'chatframe',
                            class: 'printFrame',
                            style: 'display:none;' // Hide the iframe
                        }).appendTo('body')[0];
            
                        iframe.onload = function() {
                            // Get the iframe document
                            var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            
                            // Set the content type and encoding
                            iframeDoc.open();
                            iframeDoc.write('<!DOCTYPE html>');
                            iframeDoc.write('<html><head><title>Print</title>');
                            iframeDoc.write('<meta charset="utf-8">');
                            iframeDoc.write('</head><body>');
                            iframeDoc.write(printContents);
                            iframeDoc.write('</body></html>');
                            iframeDoc.close();
            
                            // Focus and print the iframe content
                            iframe.contentWindow.focus();
                            iframe.contentWindow.print();
            
                            // Remove the iframe after printing
                            setTimeout(function() {
                                $(iframe).remove();
                            }, 1000);
                        };
                        iframe.src = 'about:blank';
                    });
            
                }
            };
        })(jQuery, Drupal, drupalSettings);