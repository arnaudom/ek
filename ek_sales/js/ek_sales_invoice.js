jQuery(document).ready(function(){
    jQuery('.amount').on('change', function(){

        var i = jQuery('#itemsCount').val();
       
        var gtotal = 0;
        var taxtotal = 0;
        for (var n = 1; n <= i;  n++) {
            if (!jQuery("#del"+n).is(':checked')   ) {
              jQuery('#total'+i).val(0); 

              var q = parseFloat(jQuery('#quantity'+n).val()) ;
              var v = parseFloat(jQuery('#value'+n).val()) ;
              var lineTotal = q*v ;
              gtotal = gtotal+lineTotal;
              if ( jQuery("#optax"+n).is(':checked')   ) {
                taxtotal = taxtotal+lineTotal;
              }
              jQuery('#total'+n).val(lineTotal.toFixed(2)); 
              jQuery('#row'+n).addClass('current');
              jQuery('#row'+n).removeClass('delete');
            } else {
              jQuery('#total'+n).val(0);  
              jQuery('#row'+n).addClass('delete');
              jQuery('#row'+n).removeClass('current');
            }
        }
        
        if(!isNaN( jQuery('#incoterm').val() )) {
        //convert quotation to invoice with incoterm
          var inco = parseFloat( jQuery('#incoterm').val() );
          var term = gtotal*inco/100;

          if ( jQuery("#optax_incoterm").is(':checked')   ) {
            taxtotal = taxtotal+term;
          }

          gtotal = gtotal + term;
          
          jQuery('#total_incoterm').val( term.toFixed(2) )

        }
        
        jQuery('#grandtotal').val(gtotal.toFixed(2));
        
        var tax = jQuery('#taxvalue').val();
        
        if(tax > 0) {
        var taxvalue = taxtotal*tax/100
        jQuery('#taxamount').val(taxvalue.toFixed(2));
        var due = (gtotal+taxvalue);
        jQuery('#totaltax').val(due.toFixed(2));
        
        } else {
        jQuery('#taxamount').val('-');
        jQuery('#totaltax').val(gtotal.toFixed(2));
        }
    });

    
});