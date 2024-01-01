(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_ad_tag = {
        attach: function (context, settings) {

            if (drupalSettings.auto_complete) {
                $('.form-select-tag').tagEditor({ 
                    autocomplete: {
                                source : drupalSettings.path.baseUrl + drupalSettings.auto_complete, 
                                minLength: 2,
                        }
                    }
                );

            } else {
                $(once('bind-tag-event', '.form-select-tag', context)).tagEditor();
            }
        }
    };
})(jQuery, Drupal, drupalSettings);   