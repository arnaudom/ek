(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.salesdatacharts = {
        attach: function (context, settings) {
            if (settings.salesdatacharts) {
                var k;
                for (k in settings.salesdatacharts) {
                    var c = settings.salesdatacharts[k];
                    
                    // Prevent drupal.behavior from calling script twice
                    $(once('ek-sales-data', '#' + c.element, context)).each(function () {
                    //jQuery('#' + c.element, context).once('sales-data-chart').each(function () {
                        
                        if (c.type == 'Donut') {
                            jQuery('#' + c.id).css('height', 'auto');
                            Morris.Donut({
                                element: c.element,
                                data: c.data,
                                resize: c.resize || true
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
                                hideHover: c.hideHover || 'auto',
                                resize: c.resize || true
                            });
                        }
                        
                        if (c.type == 'Line') {
                            jQuery('#' + c.id).css('height', 'auto');
                            Morris.Line({
                                element: c.element,
                                data: c.data,
                                xkey: c.xkey,
                                ykeys: c.ykeys,
                                labels: c.labels,
                                hideHover: c.hideHover || 'auto',
                                resize: c.resize || true
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
                                hideHover: c.hideHover || 'auto',
                                resize: c.resize || true
                            });
                        }
                    });
                }
            }
        } // attach
    }; // behaviors
})(jQuery, Drupal, drupalSettings);

