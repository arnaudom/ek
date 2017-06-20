(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.ek_documents_share = {
    attach: function (context, settings) {
        $('#form-multiselect').multiSelect({
            selectableHeader: settings.left,
            selectionHeader: settings.right,
        });
    }//attach
  };

})(jQuery, Drupal, drupalSettings);   