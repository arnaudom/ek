(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.logistics = {
        attach: function (context, settings) {
            
        jQuery('.amount').on('change', function(){
            
            var i = jQuery('#itemsCount').val();console.log(i);

            var itemsTotal = 0;
            for (var n = 1; n <= i;  n++) {

                if (!jQuery("#del"+n).is(':checked') 
                        &&  jQuery("#value"+n).val() != 'footer'
                        && !isNaN(jQuery("#value"+n).val()) ) {
                   //Delivery form
                   jQuery('#total'+n).val(0); 
                    var q = parseFloat(jQuery('#quantity'+n).val()) ;
                    var v = parseFloat(jQuery('#value'+n).val()) ;
                    var lineTotal = q*v ;
                  
                
                  if(isNaN(lineTotal) ) {
                    jQuery('#total'+n).val('-');
                  } else {
                    
                    itemsTotal = itemsTotal + lineTotal;
                    jQuery('#total'+n).val(lineTotal.toFixed(2));
                  }          
                } else if(!jQuery("#del"+n).is(':checked') 
                        &&  jQuery("#value"+n).val() != 'footer') {
                    //receiving form
                    itemsTotal = itemsTotal + parseFloat(jQuery('#quantity'+n).val());
                }
            }
            
            jQuery('#itemsTotal').val(itemsTotal.toFixed(2));
            

        });
        
        } //attach
    }; //bahaviors
})(jQuery, Drupal, drupalSettings);