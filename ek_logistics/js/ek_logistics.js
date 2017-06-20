jQuery(document).ready(function(){
    jQuery('.amount').on('change, blur', function(){
        var i = jQuery('#itemsCount').val();
        
        var gtotal = 0;
        var taxtotal = 0;
        var incoterm = 0;
        for (var n = 1; n <= i;  n++) {
        jQuery('#total'+i).val(0); 

          var q = parseFloat(jQuery('#quantity'+n).val()) ;
          var v = parseFloat(jQuery('#value'+n).val()) ;
          var lineTotal = q*v ;
          
          /*
          if ( jQuery("#optax"+n).is(':checked')   ) {
            taxtotal = taxtotal+lineTotal;
          }
          */
          if( isNaN(lineTotal) ) {
          jQuery('#total'+n).val('-');
          } else {
            gtotal = gtotal+lineTotal;
            taxtotal = taxtotal+lineTotal;
            jQuery('#total'+n).val(lineTotal.toFixed(2)); 
          }
        
        
        }
        jQuery('#grandtotal').val(gtotal.toFixed(2));
        
        var term = parseFloat(jQuery('#term_rate').val());
        if(term > 0) {
                incoterm =  gtotal*term/100;
                jQuery('#incotermamount').val(incoterm.toFixed(2));
                gtotal = gtotal+incoterm;

        } else {
                jQuery('#incotermamount').val('-');
        }
        
        var tax = parseFloat(jQuery('#tax_rate').val());
        
        if(tax > 0) {
        var taxvalue = gtotal*tax/100
        jQuery('#taxamount').val(taxvalue.toFixed(2));
        var due = (gtotal+taxvalue);
        jQuery('#totaltax').val(due.toFixed(2));
        
        } else {
        jQuery('#taxamount').val('-');
        jQuery('#totaltax').val(gtotal.toFixed(2));
        }
    });

    
});