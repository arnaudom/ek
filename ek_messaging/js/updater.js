(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.ek_messaging = {
    attach: function (context, settings) {

    jQuery('.archiveButton',context).click(function() {
        jQuery.ajax({
            dataType: "json",
            url: "archive",
            data: {id: this.id },
            success: function (data) { 
                jQuery('#line' + data.id).remove();
            }
        });       
    });    

    jQuery('.deleteButton').click(function() {
    var mess = Drupal.t("delete") + "?";
        if (window.confirm(mess)) {
            jQuery.ajax({
                dataType: "json",
                url: "delete",
                data: {id: this.id },
                success: function (data) { 
                    jQuery('#line' + data.id).remove();
                }
            }); 
        }  
    });   
         
    
    jQuery(function() { 
        
        function split( val ) {
            return val.split( /,\s*/ );
        }

        function extractLast( term ) {
          return split( term ).pop();
        }
        jQuery( "#edit-users" )
          // don't navigate away from the field on tab when selecting an item
            .bind( "keydown", function( event ) {
                if ( event.keyCode === jQuery.ui.keyCode.TAB &&
                    jQuery( this ).data( "ui-autocomplete" ).menu.active ) {
                         event.preventDefault();
            }
          })
            .autocomplete({
                source: function( request, response ) {
                    jQuery.getJSON( drupalSettings.path.baseUrl + "ek_admin/user/autocomplete", {
                        q: extractLast( request.term )
                    }, response );
                },
                search: function() {
                // custom minLength
                    var term = extractLast( this.value );
                    if ( term.length < 2 ) {
                        return false;
                    }
                },
                focus: function() {
                    // prevent value inserted on focus
                    return false;
                },
                select: function( event, ui ) {
                    var terms = split( this.value );
                    // remove the current input
                    terms.pop();
                    // add the selected item
                    terms.push( ui.item.value );
                    // add placeholder to get the comma-and-space at the end - used for multi select
                    terms.push( "" );
                    this.value = terms.join( ", " );
                    return false;
                }
            });
    });
    
    
  } 
}; 
})(jQuery, Drupal, drupalSettings);

