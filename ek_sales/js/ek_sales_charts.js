(function ($, Drupal, drupalSettings) {


    Drupal.behaviors.salescharts = {
        attach: function (context, settings) {
            if(settings.salescharts){
                var k;
                    for (k in settings.salescharts) {

                        var c = settings.salescharts[k];
                        //prevent drupal.behavior to call script twice with once
                        jQuery('#' + c.element).once().each(function () {

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
                                    hideHover: 'auto'
                                });

                            }
                        });
                    }
                    jQuery('.area-saleschart').fadeOut();
                    jQuery('#sales-chart-select').change(function () {
                        var n = jQuery('#sales-chart-select').val();
                        for (i = 0; i < 3; i++) {
                            if (i == n) {
                                jQuery('#area-saleschart' + i).fadeIn(1000);
                            } else {
                                jQuery('#area-saleschart' + i).hide();
                            }
                        }
                    }); 
            }

        } //attach
    }; //bahaviors



})(jQuery, Drupal, drupalSettings);

