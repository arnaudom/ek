(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.purchase = {
        attach: function (context, settings) {
            jQuery('.amount').on('change', function(){

                var i = jQuery('#itemsCount').val();
                var itemsTotal = 0;
                var taxValue = 0;
                var taxable = 0;
                var totalWithTax = 0;
                
                for (var n = 1; n <= i;  n++) {
                    if (!jQuery("#del" + n).is(':checked') 
                        && jQuery("#value" + n).val() != 'footer'
                        && !isNaN(jQuery("#value" + n).val())) {
                    
                      jQuery('#itemsTotal' + n).val(0); 
                      var q = parseFloat(jQuery('#quantity' + n).val()) ;
                      var v = parseFloat(jQuery('#value' + n).val()) ;
                      var lineTotal = q * v ;
                      itemsTotal =  itemsTotal + lineTotal;
                      if (jQuery("#optax" + n).is(':checked')) {
                        taxable = taxable + lineTotal;
                      }
                      jQuery('#total' + n).val(lineTotal.toFixed(2)); 
                    } else {
                      jQuery('#total' + n).val(0); 
                    }
                }
                
                jQuery('#itemsTotal').val(itemsTotal.toFixed(2));
                
                //tax line
                var tax = parseFloat(jQuery('#taxvalue').val());
                if(tax > 0) {
                    var taxValue = taxable * tax /100;
                    jQuery('#taxValue').val(taxValue.toFixed(2));
                    totalWithTax = totalWithTax + taxValue;
                    
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