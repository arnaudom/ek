(function ($, Drupal, drupalSettings) {
    
    jQuery(".toggle_exchange").on("click",function(){
        
        jQuery('.journal-exchange').toggle();
        if(jQuery(".toggle_exchange").html() == '[+]') {
            jQuery(".toggle_exchange").html('[-]');
        } else {
            jQuery(".toggle_exchange").html('[+]');
        }
    }); 

    
})(jQuery, Drupal, drupalSettings);
