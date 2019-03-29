(function ($, Drupal, drupalSettings) {


    Drupal.behaviors.ek_sales = {
        attach: function (context, settings) {
            jQuery.ajax({
                dataType: "json",
                url: drupalSettings.path.baseUrl + 'ek_sales/load_documents',
                data: {abid: settings.abid},
                async: false,
                success: function (remoteData) {
                    jQuery('.loading').remove();
                    jQuery('#sales_docs').html(remoteData.data);
                    addajax();
                }
            });


        } //attach


    }; //bahaviors

    /* 
     * deleted files toggle
     */
    jQuery(function () {
        jQuery('.hideFile').click(function () {
            if (jQuery('.hideFile').hasClass('show-ico'))
                jQuery('.hide').hide('fast');
            if (jQuery('.hideFile').hasClass('hide-ico'))
                jQuery('.hide').show('fast');
            jQuery('.hideFile').toggleClass('show-ico hide-ico');

        });
    });
})(jQuery, Drupal, drupalSettings);

function addajax() {
    // Bind Ajax behaviors to all items showing the class.
    jQuery('.use-ajax:not(.ajax-processed)').addClass('ajax-processed').each(function () {

        var element_settings = {};
        // Clicked links look better with the throbber than the progress bar.
        element_settings.progress = {'type': 'throbber'};

        // For anchor tags, these will go to the target of the anchor rather
        // than the usual location.
        if (jQuery(this).attr('href')) {
            element_settings.url = jQuery(this).attr('href');
            element_settings.event = 'click';
        }
        var base = jQuery(this).attr('id');
        element_settings.base = base;
        element_settings.element = this;

        Drupal.ajax[base] = new Drupal.ajax(element_settings);
    });
}