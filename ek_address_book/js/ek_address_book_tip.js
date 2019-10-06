
(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_ab_tip = {
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
                            url: '/look_up_contact_ajax/tip',
                            type: 'GET',
                            data: {q: $(this).attr('id'), option:'image'},
                            success: function (data, status) {
                                // Process the data
                                // Set the content manually (required!)
                                this.set('content.text', data['card']);
                            }
                        }
                    }
                })
            });
        }
    }

})(jQuery, Drupal, drupalSettings);


