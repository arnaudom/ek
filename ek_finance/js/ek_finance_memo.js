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
                var gtotal = 0;
                jQuery('#grandtotal').val(0);

                for (var n = 1; n <= i; n++) {
                    var amount = jQuery('#amount' + n).val().replace(/[^0-9-.]/g, '');
                    var v = parseFloat(amount);
                    if (!jQuery("#del" + n).is(':checked')) {
                        jQuery('#amount' + n).val(v.toFixed(2))
                        gtotal = gtotal + v;
                    }

                }

                jQuery('#grandtotal').val(gtotal.toFixed(2));

            });


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

