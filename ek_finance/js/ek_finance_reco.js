(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_reconciliation = {
        attach: function (context, settings) {
console.log(settings.rounding);
            var ref = jQuery('#edit-account').val();
            var store = 'statement' + ref;

            if (jQuery.cookie(store)) {
                jQuery('#statement').val(jQuery.cookie(store));
            }

            jQuery('.calculate').on('click, change', function () {

                var openchart = parseFloat(jQuery("#openchart").val());
                var credit = parseFloat(jQuery("#opencredits").val());
                var debit = parseFloat(jQuery("#opendebits").val());
                var statement = parseFloat(jQuery("#statement").val());
                var openbalance = parseFloat(jQuery("#openbalance").val());

                var sum_debit = 0;
                var sum_credit = 0;

                for (i = 0; i < jQuery(".calculate").length; i++) {
                    if (jQuery('#line-' + i).prop('checked')) {

                        var value = parseFloat(jQuery('#line-' + i).val());

                        if (jQuery('#type-' + i).val() == 'credit') {
                            sum_credit = sum_credit + value;
                            credit = credit + value;
                        }

                        if (jQuery('#type-' + i).val() == 'debit') {
                            sum_debit = sum_debit + value;
                            debit = debit + value;
                        }
                    }

                }

                var balance = (Math.round((openbalance + sum_credit - sum_debit) * 100) / 100).toFixed(2);
                var difference = parseFloat(Math.abs(balance)) - parseFloat(statement);
                var difference = (Math.round(difference * 100) / 100).toFixed(settings.rounding);
                var credit = (Math.round(credit * 100) / 100).toFixed(settings.rounding);
                var debit = (Math.round(debit * 100) / 100).toFixed(settings.rounding);

                sum_credit = (Math.round(sum_credit * 100) / 100).toFixed(settings.rounding);
                sum_debit = (Math.round(sum_debit * 100) / 100).toFixed(settings.rounding);

                jQuery("#difference").val(difference);

                if (difference == 0) {
                    jQuery("#difference").css('background-color', '#00D744');
                } else {
                    jQuery("#difference").css('background-color', '#FF6666');
                }
                /*
                 if (difference > -0.05 && difference < 0.05) {form2.difference.style.backgroundColor = '#8eefa3';jQuery('#button_1').fadeIn('fast');}
                 if ( difference >= 0.05) {form2.difference.style.backgroundColor = '#f6b4b1';jQuery('#button_1').fadeOut('fast');}
                 if ( difference <= -0.05) {form2.difference.style.backgroundColor = '#f6b4b1';jQuery('#button_1').fadeOut('fast');}
                 */
                jQuery("#credits").val(credit);
                jQuery("#debits").val(debit);
                if (balance < 0) {
                    var solde = " (dt)";
                } else {
                    var solde = " (ct)";
                }
                jQuery("#balance").html(balance);
                jQuery("#ab").html(solde);

                jQuery("#sum_credit").html(sum_credit);
                jQuery("#sum_debit").html(sum_debit);

            });


        }
    }
})(jQuery, Drupal, drupalSettings);



jQuery(document).ready(function () {

    jQuery('#statement').change(function () {

        var value = parseFloat(jQuery('#statement').val());
        var ref = jQuery('#edit-account').val();
        var store = 'statement' + ref;
        jQuery.cookie(store, value, {expires: 1});


    });
});


