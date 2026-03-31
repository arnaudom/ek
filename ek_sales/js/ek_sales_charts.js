(function ($, Drupal, drupalSettings) {


    Drupal.behaviors.salescharts = {
        attach: function (context, settings) {
            if(settings.salescharts){
                var k;
                    for (k in settings.salescharts) {

                        var c = settings.salescharts[k];
                        //prevent drupal.behavior to call script twice with once
                        jQuery('#' + c.element).each(function () {

                            if (c.type == 'Line') {
                                jQuery('#' + c.id).css('height', 'auto');
                                Morris.Line({
                                    element: c.element,
                                    data: c.data,
                                    xkey: c.xkey,
                                    ykeys: c.ykeys,
                                    labels: c.labels,
                                    hideHover: 'auto'
                                });
                            }
                            if (c.type == 'Area') {
                                jQuery('#' + c.id).css('height', 'auto');
                                Morris.Area({
                                    element: c.element,
                                    data: c.data,
                                    xkey: c.xkey,
                                    ykeys: c.ykeys,
                                    labels: c.labels,
                                    hideHover: 'auto'
                                });
                            }

                            if (c.type == 'Bar') {
                                jQuery('#' + c.id).css('height', 'auto');
                                Morris.Bar({
                                    element: c.element,
                                    data: c.data,
                                    xkey: c.xkey,
                                    ykeys: c.ykeys,
                                    labels: c.labels,
                                    hideHover: 'auto',
									stacked: c.stacked,
									resize: c.resize || true
                                });

                            }
                        });
                    }
             // Initialize chart visibility on first load
                once('ek-sales-init', '#sales-chart-select', context).forEach(function (element) {
                    // Hide all charts
                    jQuery('.area-saleschart').hide();
                    // Show the first chart (most recent year - option 3)
                    jQuery('#area-saleschart3').show();
                    // Set select to default value
                    jQuery('#sales-chart-select').val(3);
                });
            
                // Handle chart switching
                once('ek-sales-change', '#sales-chart-select', context).forEach(function (element) {
                    jQuery(element).on('change', function () {
                    var n = jQuery(this).val();
                    for (var i = 0; i < 4; i++) {
                        if (i == n) {
                        jQuery('#area-saleschart' + i).fadeIn(1000);
                        } else {
                        jQuery('#area-saleschart' + i).fadeOut(500);
                        }
                    }
                    });
                });
            }

        } //attach
    }; //bahaviors



})(jQuery, Drupal, drupalSettings);

