(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.ek_ad_documents = {
    attach: function (context, settings) {

        $('.form-select-chosen').chosen({ width: '100%'});
        
    }//attach
  };

})(jQuery, Drupal, drupalSettings);   