(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_home = {
        attach: function (context, settings) {

                $('.list_ico').click(function () {
                    var q = $(this).parent().attr('id');
                    jQuery.ajax({
                        url: drupalSettings.path.baseUrl + "ek_admin/feature-reset" ,
                        data: { q: q },
                        success: function (data) { 
                            if (data.action == 1) {
                                jQuery('#' + q + " span").toggleClass("star_ico check_icon");
                            } 
                        }
                    });
                    
                });

        }
    };
})(jQuery, Drupal, drupalSettings);  
