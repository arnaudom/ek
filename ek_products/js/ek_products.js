    /* activate button
     * Open or close all sections
     */
    jQuery(function () {
        jQuery('#expand').click(function () {
            if (jQuery('#expand').hasClass('open-ico'))
                jQuery('.panel-body').hide('fast');
            if (jQuery('#expand').hasClass('close-ico'))
                jQuery('.panel-body').show('fast');
            jQuery('#expand').toggleClass('close-ico open-ico');

        });
    });


