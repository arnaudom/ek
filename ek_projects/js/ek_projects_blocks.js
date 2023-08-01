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

        jQuery(".dashcolumn").sortable({
            connectWith: ".dashcolumn",
            handle: ".widget-title",
            cancel: ".widget-toggle",
            placeholder: "widget widget-placeholder",
            cursor: "move"
        });

        jQuery(".widget-toggle").on( "click", function() {
            var icon = $(this);
            icon.toggleClass( "ui-icon-minusthick ui-icon-plusthick" );
            icon.closest( ".widget" ).find( ".widget-content" ).toggle();
        });
        
        jQuery(".widget-content" ).resizable({
            handles: 's'
        });
        
        
    });

})(jQuery, Drupal, drupalSettings);
