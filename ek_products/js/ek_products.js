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

    (function (Drupal, once) {
    Drupal.behaviors.ekProductsCardToggle = {
        attach: function (context) {
        once('ek-products-toggle', '.toggle-heading', context).forEach(function (el) {
            el.addEventListener('click', function () {
            const targetSelector = el.getAttribute('data-target');
            if (!targetSelector) return;

            const target = document.querySelector(targetSelector);
            if (!target) return;

            target.classList.toggle('active');
            el.classList.toggle('active');
            });
        });
        }
    };
    })(Drupal, once);


