
(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_hr_tip = {
        attach: function (context, settings) {

            $('.tip').each(function(){
                $(this).qtip({
                    style: { 
                        classes: 'qtip-bootstrap' 
                    },
                    position: {
                            my: 'bottom right', // Position my 
                            at: 'top left', // at the bottom 
                        },
                    content: {
                        text: 'Loading...',
                        ajax: {
                            url: '/human-resources/e/autocomplete',
                            type: 'GET',
                            data: {q: $(this).attr('id'), option:'image'},
                            success: function (data, status) {
                                // Process the data
                                var tx = data[0]['picture'] + data[0]['name'];
                                // Set the content manually (required!)
                                this.set('content.text', tx);
                            }
                        }
                    }
                })
            });
        }
    }

})(jQuery, Drupal, drupalSettings);


