(function ($, Drupal, drupalSettings,cookies) {

    Drupal.behaviors.ek_reconciliation = {
        attach: function (context, settings) {
            var ref = jQuery('#edit-account').val();
            var store = 'statement' + ref;

            if (cookies.get(store)) {
                $('#statement').val(cookies.get(store));
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
                var formattedbalance = formatNumber(balance);
                var difference = parseFloat(Math.abs(balance)) - parseFloat(statement);
                var difference = (Math.round(difference * 100) / 100).toFixed(settings.rounding);
                var credit = (Math.round(credit * 100) / 100).toFixed(settings.rounding);
                var debit = (Math.round(debit * 100) / 100).toFixed(settings.rounding);
                var formattedCredit = formatNumber(credit);
                var formattedDebit = formatNumber(debit);
                var sum_credit = (Math.round(sum_credit * 100) / 100).toFixed(settings.rounding);
                var sum_debit = (Math.round(sum_debit * 100) / 100).toFixed(settings.rounding);
                var formattedsum_credit = formatNumber(sum_credit);
                var formattedsum_debit = formatNumber(sum_debit);

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
                jQuery("#credits").val(formattedCredit);
                jQuery("#debits").val(formattedDebit);
                if (balance < 0) {
                    var solde = " (dt)";
                } else {
                    var solde = " (ct)";
                }
                jQuery("#balance").html(formattedbalance);
                jQuery("#ab").html(solde);

                jQuery("#sum_credit").html(formattedsum_credit);
                jQuery("#sum_debit").html(formattedsum_debit);

            });

            jQuery('#statement').change(function () {
                var value = parseFloat(jQuery('#statement').val());
                var ref = jQuery('#edit-account').val();
                var store = 'statement' + ref;
                cookies.set(store, value, {expires: 1});
            });

            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
        }
    }
})(jQuery, Drupal, drupalSettings,window.Cookies);


