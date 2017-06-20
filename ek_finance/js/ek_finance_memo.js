jQuery(document).ready(function(){
    jQuery('.amount').on('change', function(){

       var i = jQuery('#itemsCount').val();
       var gtotal = 0; 
       jQuery('#grandtotal').val(0);
        
        for (var n = 1; n <= i;  n++) {
          var amount = jQuery('#amount'+n).val().replace(/[^0-9-.]/g, '');
          var v = parseFloat(amount) ;
          if (!jQuery("#del"+n).is(':checked')   ) {
          jQuery('#amount'+n).val(v.toFixed(2))
            gtotal = gtotal+v;
          }
         
        }       
      
        jQuery('#grandtotal').val(gtotal.toFixed(2));
        
    });

    
});