(function ($, Drupal, drupalSettings) {

    jQuery(function () {
       
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