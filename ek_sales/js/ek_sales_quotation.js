(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.quotation = {
        attach: function (context, settings) {

            $('.amount').on('change', function () {

                var i = $('#itemsCount').val() * 2 + 2;

                var itemsTotal = 0;
                var incotermValue = 0;
                var taxValue = 0;
                var taxable = 0;
                var totalWithTax = 0;
                for (var n = 1; n <= i; n++) {

                    if (!$("#del-" + n).is(':checked')
                            && $("#value" + n).val() != 'secondRow'
                            && $("#value" + n).val() != 'footer'
                            && !isNaN($("#value" + n).val())) {

                        if ($("#value" + n).val() == 'incoterm') {
                            //custom line in build quotation
                            var lineTotal = parseFloat($('#incotermValue').val());
                        } else {
                            $('#total' + n).val(0);
                            var q = parseFloat($('#quantity' + n).val());
                            var v = parseFloat($('#value' + n).val());
                            var lineTotal = q * v;
                        }

                        if (isNaN(lineTotal)) {
                            $('#total' + n).val('-');
                        } else {
                            if ($("#value" + n).val() != 'incoterm') {
                                //incoterm is not added to items total
                                itemsTotal = itemsTotal + lineTotal;
                            }

                            if ($("#optax" + n).is(':checked')) {
                                //compile amount with applied tax
                                taxable = taxable + lineTotal;
                            }
                            $('#total' + n).val(lineTotal.toFixed(2));
                            if (q == 0 && v == 0) {
                                // insert paragraph 
                                $(".tr" + n).addClass('rowheader');
                                $("._tr" + n).addClass('_rowheader');
                            } else { console.log(n);
                                $(".tr" + n).removeClass('rowheader');
                                $("._tr" + n).removeClass('_rowheader');
                            }
                        }
                    }
                }
                $('#itemsTotal').val(itemsTotal.toFixed(2));

                //Add incotem value to final total
                var term = parseFloat($('#term_rate').val());
                if (term > 0) {
                    incoterm = itemsTotal * term / 100;
                    $('#incotermValue').val(incoterm.toFixed(2));
                    totalWithTax = totalWithTax + incoterm;

                } else {
                    $('#incotermValue').val('-');
                }
                //Add tax value to final total
                var tax = $('#tax_rate').val();
                if (tax > 0) {
                    var taxvalue = taxable * tax / 100
                    $('#taxValue').val(taxvalue.toFixed(2));
                    totalWithTax = totalWithTax + taxvalue;
                } else {
                    $('#taxValue').val('-');

                }
                totalWithTax = totalWithTax + itemsTotal;
                $('#totalWithTax').val(totalWithTax.toFixed(2));
                convert();
            });
            
            $('.rowdelete').on('click', function () {
                var id = this.id.split('-');
                if ($("#del-" + id[1]).is(':checked')) {
                    $(".tr" + id[1]).addClass('delete');
                } else {
                    $(".tr" + id[1]).removeClass('delete');
                }
            });
            function convert() {
                var total = $('#itemsTotal').val().replace(/[^0-9-.]/g, '');
                var currency = $('#edit-currency').val();
                var convert = '';

                if (settings.currencies[currency] != 1 && total > 0) {
                    var convert = (total / settings.currencies[currency]).toFixed(2) + ' ' + settings.baseCurrency;
                }
                $('#convertedValue').html(convert);
            }

        } //attach
    }; //bahaviors
})(jQuery, Drupal, drupalSettings);