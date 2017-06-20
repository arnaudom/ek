
/*
* calculate refund total
*/

(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.ek_finance = {
    attach: function (context) {
    


        jQuery('.sum').click( function(){
            
            var value = 0; 
            
            for (i = 1; i <= jQuery( ".sum" ).length; i++) { 
                if($('#'+i).prop('checked')) {
                value = value + parseFloat( jQuery('#'+i).val() );
                } 
            }
        
        jQuery('#total').html(value.toFixed(2));
        
        });
  
    }
    
  };

})(jQuery, Drupal, drupalSettings);


