(function ($, Drupal, drupalSettings) {


  Drupal.behaviors.charts = {
    attach: function (context, settings) {
        
        var k;
        for ( k in settings.charts)   { 
            
            var c = settings.charts[k];
            //prevent drupal.behavior to call script twice with once
            jQuery('#'+c.element).once().each(function() {
                
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

                if (c.type == 'Donut') {
                  jQuery('#id-'+c.element).css('height' , 'auto');
                   Morris.Donut({
                    element: c.element,
                    data: c.data,
                    xkey: c.xkey,
                    ykeys: c.ykeys,
                    labels: c.labels,
                    resize: 'true'
                  });  
                } 
            });
        }
        
        
    } //attach
    

  }; //bahaviors


})(jQuery, Drupal, drupalSettings);