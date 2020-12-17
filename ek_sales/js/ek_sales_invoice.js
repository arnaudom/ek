(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.invoice = {
        attach: function (context, settings) {
            $('.amount').on('change', function(){
                var i = $('#itemsCount').val();
                var itemsTotal = 0;
                var taxValue = 0;
                var taxable = 0;
                var totalWithTax = 0;
                
                for (var n = 1; n <= i;  n++) {
                    if (!$("#del-" + n).is(':checked')  
                        && $("#value" + n).val() != 'footer'
                        && !isNaN($("#value" + n).val())) {
                    
                      $('#itemsTotal' + n).val(0); 
                      var q = parseFloat($('#quantity' + n).val()) ;
                      var v = parseFloat($('#value' + n).val()) ;
                      var lineTotal = q * v ;
                      itemsTotal =  itemsTotal + lineTotal;
                      if ($("#optax" + n).is(':checked')) {
                        taxable = taxable + lineTotal;
                      }
                      $('#total' + n).val(lineTotal.toFixed(2)); 
                      if(q == 0 && v == 0) {
                          // insert paragraph 
                          $("[data-drupal-selector=edit-itemtable-" + n + "]").addClass('rowheader');
                      } else {
                          $("[data-drupal-selector=edit-itemtable-" + n + "]").removeClass('rowheader');
                      }
                    } else {
                      $('#total' + n).val(0); 
                    }
                }
                
                $('#itemsTotal').val(itemsTotal.toFixed(2));
                
                //tax line
                var tax = parseFloat($('#taxvalue').val());
                if(tax > 0) {
                    var taxValue = taxable * tax /100;
                    $('#taxValue').val(taxValue.toFixed(2));
                    totalWithTax = totalWithTax + taxValue;
                    
                } else {
                    $('#taxValue').val('-');
                }
                
                totalWithTax = totalWithTax + itemsTotal;
                $('#totalWithTax').val(totalWithTax.toFixed(2));
                convert();
            });
            
            $('.rowdelete').on('click', function(){
                var id = this.id.split('-');
                if ($("#del-" + id[1]).is(':checked')) { 
                   $("[data-drupal-selector=edit-itemtable-" + id[1] + "]").addClass('delete');
                } else {
                   $("[data-drupal-selector=edit-itemtable-" + id[1] + "]").removeClass('delete');
                }
            });
            
            function convert() {
                var total = $('#itemsTotal').val().replace(/[^0-9-.]/g, '');
                var currency = $('#edit-currency').val();
                var convert = '';
                
                if(settings.currencies[currency] != 1 && total > 0) {
                    var convert = (total/settings.currencies[currency]).toFixed(2) + ' ' + settings.baseCurrency;
                }
                $('#convertedValue').html(convert);
            }

        } //attach
    }; //bahaviors
})(jQuery, Drupal, drupalSettings);