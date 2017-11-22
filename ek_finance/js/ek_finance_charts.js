(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.charts = {
        attach: function (context, settings) {

            var k;
            for (k in settings.charts) {
                var c = settings.charts[k];
                //prevent drupal.behavior to call script twice with once
                jQuery('#' + c.element).once().each(function () {

                    if (c.type == 'Line') {
                        Morris.Line({
                            element: c.element,
                            data: c.data,
                            xkey: c.xkey,
                            ykeys: c.ykeys,
                            labels: c.labels
                        });

                    }
                    if (c.type == 'Area') {

                        Morris.Area({
                            element: c.element,
                            data: c.data,
                            xkey: c.xkey,
                            ykeys: c.ykeys,
                            labels: c.labels
                        });

                    }
                    if (c.type == 'Bar') {
                        Morris.Bar({
                            element: c.element,
                            data: c.data,
                            xkey: c.xkey,
                            ykeys: c.ykeys,
                            labels: c.labels
                        });
                    }
                });
            }
            jQuery('.area-expenseschart').fadeOut();
            jQuery('#expenses-chart-select').change(function () {
                var n = jQuery('#expenses-chart-select').val();
                
                for (i = 0; i < 3; i++) {
                    if (i == n) {
                        jQuery('#area-expenseschart' + i).fadeIn(1000);
                    } else {
                        jQuery('#area-expenseschart' + i).hide();
                    }
                }
            });

        } //attach


    }; //bahaviors



})(jQuery, Drupal, drupalSettings);



