jQuery(document).ready(function(){
    jQuery('.amount').on('change', function(){

       var i = jQuery('#itemsCount').val();
       var gtotal = 0; 
       jQuery('#grandtotal').val(0);
        
        for (var n = 1; n <= i;  n++) {
          var v = parseFloat(jQuery('#amount'+n).val()) ;
            jQuery('#amount'+n).val(v.toFixed(2))
            gtotal = gtotal+v;
         
        }       
      
        jQuery('#grandtotal').val(gtotal.toFixed(2));
        
    });

    
});