(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.quotation = {
        attach: function (context, settings) {
            
        
        jQuery('.amount').on('change', function(){
            
            
            var i = jQuery('#itemsCount').val()*2 + 2;

            var itemsTotal = 0;
            var incotermValue = 0;
            var taxValue = 0;
            var taxable = 0;
            var totalWithTax = 0;
            for (var n = 1; n <= i;  n++) {

                if (!jQuery("#del"+n).is(':checked') 
                        &&  jQuery("#value"+n).val() != 'secondRow' 
                        &&  jQuery("#value"+n).val() != 'footer'
                        && !isNaN(jQuery("#value"+n).val()) ) {

                  
                  if(jQuery("#value"+n).val() == 'incoterm') {
                      //custom line in build quotation
                      var lineTotal = parseFloat(jQuery('#incotermValue').val());
                  } else {
                   jQuery('#total'+n).val(0); 
                    var q = parseFloat(jQuery('#quantity'+n).val()) ;
                    var v = parseFloat(jQuery('#value'+n).val()) ;
                    var lineTotal = q*v ;
                  }
                
                  if(isNaN(lineTotal) ) {
                    jQuery('#total'+n).val('-');
                  } else {
                    if(jQuery("#value"+n).val() != 'incoterm') {
                        //incoterm is not added to items total
                        itemsTotal = itemsTotal + lineTotal;
                    }
                    
                    if (jQuery("#optax"+n).is(':checked')) { 
                        //compile amount with applied tax
                        taxable = taxable + lineTotal;
                    } 
                    jQuery('#total'+n).val(lineTotal.toFixed(2));
                  }          
                } 
            }
            jQuery('#itemsTotal').val(itemsTotal.toFixed(2));
            
            //Add incotem value to final total
            var term = parseFloat(jQuery('#term_rate').val());
            if(term > 0) {
                    incoterm =  itemsTotal * term /100;
                    jQuery('#incotermValue').val(incoterm.toFixed(2));
                    totalWithTax = totalWithTax + incoterm;

            } else {
                    jQuery('#incotermValue').val('-');
            }
            //Add tax value to final total
            var tax = jQuery('#tax_rate').val();
            if(tax > 0) {
                var taxvalue = taxable * tax /100
                jQuery('#taxValue').val(taxvalue.toFixed(2));
                totalWithTax = totalWithTax + taxvalue;
            } else {
                jQuery('#taxValue').val('-');
                
            }
            totalWithTax = totalWithTax + itemsTotal;
            jQuery('#totalWithTax').val(totalWithTax.toFixed(2));
            convert();
        });
        
        function convert() {
            var total = jQuery('#itemsTotal').val().replace(/[^0-9-.]/g, '');
            var currency = jQuery('#edit-currency').val();
            var convert = '';

            if(settings.currencies[currency] != 1 && total > 0) {
                var convert = (total/settings.currencies[currency]).toFixed(2) + ' ' + settings.baseCurrency;
            }
            jQuery('#convertedValue').html(convert);
        }

        } //attach
    }; //bahaviors
})(jQuery, Drupal, drupalSettings);