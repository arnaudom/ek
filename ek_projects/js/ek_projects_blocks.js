(function ($, Drupal, drupalSettings) {

    /* toggle the notify me value
     * 
     */
    jQuery(function () {
        jQuery('.follow').click(function () {
            var pid = this.id;
            jQuery.ajax({
                type: "POST",
                url: drupalSettings.path.baseUrl + 'ek_project/edit_notify',
                data: {id: pid},
                async: false,
                success: function (data) {
                    if (data.action == 1) {
                        jQuery('#' + pid).toggleClass("square check-square");
                    } else {
                        jQuery('#' + pid).toggleClass("check-square square");
                    }

                }
            });
        });
        
        
    });

})(jQuery, Drupal, drupalSettings);
