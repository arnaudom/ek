(function ($, Drupal, drupalSettings) {

            Drupal.behaviors.journal = {
                attach: function (context, settings) {
                    jQuery('.print', context).on('click', function () {

                        var printContents = document.getElementById("p" + this.id).innerHTML;
                            $('<iframe>', {
                                name: 'jiframe',
                                class: 'printFrame'
                            }).appendTo('body').contents().find('body').append(printContents);
                            window.frames['jiframe'].focus();
                            window.frames['jiframe'].print();
                            setTimeout(function(){ $(".printFrame").remove(); }, 1000);
                    });
                }
            };
        })(jQuery, Drupal, drupalSettings);