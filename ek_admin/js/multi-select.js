(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.ek_ad_documents = {
    attach: function (context, settings) {
        $('.form-select-multiple').multiSelect({
            selectableHeader: settings.left,
            selectionHeader: settings.right,
        });
    }//attach
  };

})(jQuery, Drupal, drupalSettings);   