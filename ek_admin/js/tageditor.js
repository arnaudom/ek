(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_ad_tag = {
        attach: function (context, settings) {

            if (drupalSettings.auto_complete) {

                $('.form-select-tag').once().tagEditor({
                    autocomplete: {
                                source : drupalSettings.path.baseUrl + drupalSettings.auto_complete, 
                                minLength: 2,
                            }
                        }
                        );

            } else {
                $('.form-select-tag').once().tagEditor();
            }


        }
    };
})(jQuery, Drupal, drupalSettings);   