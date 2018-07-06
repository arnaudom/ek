(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.memos = {
        attach: function (context, settings) {

            //upload management
            
                $('#upbuttonid').hide();
                $('form', context).on('change', 'input.form-file', function () {
                    $('.messages--error').remove();
                    $('#upbuttonid').mousedown();
                    
                });
            
            
            //init attachment display
            attachments(settings.id, settings.serial);
            
            jQuery('.amount').on('change', function () {

                var i = jQuery('#itemsCount').val();
                var grandTotal = 0;
                jQuery('#grandTotal').val(0);

                for (var n = 1; n <= i; n++) {
                    var amount = jQuery('#amount' + n).val().replace(/[^0-9-.]/g, '');
                    var v = parseFloat(amount);
                    if (!jQuery("#del" + n).is(':checked')) {
                        jQuery('#amount' + n).val(v.toFixed(2))
                        grandTotal = grandTotal + v;
                    }

                }

                jQuery('#grandTotal').val(grandTotal.toFixed(2));
                convert();
            });

            function convert() {
                var total = jQuery('#grandTotal').val().replace(/[^0-9-.]/g, '');
                var currency = jQuery('#edit-currency').val();
                var convert = '';
                
                if(settings.currencies[currency] != 1 && total > 0) {
                    var convert = (total/settings.currencies[currency]).toFixed(2) + ' ' + settings.baseCurrency;
                }
                jQuery('#convertedValue').html(convert);
            }

            function attachments(id,serial) {

                jQuery.ajax({
                    dataType: "json",
                    url: drupalSettings.path.baseUrl + "finance/ajax/memofiles",
                    data: {id: id, serial: serial },
                        success: function (data) { 
                            jQuery('#attachments').html(data.list);

                            jQuery('.delButton').on('click', function () {

                                var id = this.id;
                                jQuery.ajax({
                                    dataType: "json",
                                    url: drupalSettings.path.baseUrl + "finance/ajax/memofilesdelete",
                                    data: {id: id },
                                        success: function (data) { 
                                            if(data.response) {
                                                jQuery('#row-' + id).remove();
                                            }
                                        }
                                    });

                            });
                        }
                    });
            }


        } //attach
    }; //bahaviors
})(jQuery, Drupal, drupalSettings);

