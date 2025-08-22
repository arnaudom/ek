
(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_hr_tip = {
        attach: function (context, settings) {
            $(once('ek-ab-tip', '.tip', context)).each(function () {
                    var $this = $(this);
                    var elementId = $this.attr('id');

                    $this.tooltip({
                    classes: {
                        'ui-tooltip': 'hr-tooltip'
                    },
                    // Positioning to match original intent
                    position: {
                        my: 'right bottom',
                        at: 'left top'
                    },
                    content: function (callback) {
                        callback('Loading...');
                        $.ajax({
                        url: drupalSettings.path.baseUrl + 'human-resources/e/autocomplete',
                        type: 'GET',
                        data: { q: elementId, option: 'image' },
                        success: function (data) {
                            // Process the data
                            var content = data[0] && data[0]['picture'] && data[0]['name']
                            ? data[0]['picture'] + data[0]['name']
                            : 'Error: No data returned';
                            callback(content);
                        },
                        error: function (xhr, status, error) {
                            callback('Error: ' + status + ' - ' + error);
                        }
                        });
                    },
                    // Open event to ensure proper rendering
                    open: function (event, ui) {
                        ui.tooltip.css({
                        'max-width': '400px',
                        'z-index': 1000
                        });
                    }
                });
            });
        
        }
    }

})(jQuery, Drupal, drupalSettings);


