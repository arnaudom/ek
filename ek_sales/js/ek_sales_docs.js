(function ($, Drupal, drupalSettings) {


    Drupal.behaviors.ek_sales = {
        attach: function (context, settings) {
            load_sales_docs();

            function load_sales_docs() {
                jQuery.ajax({
                    dataType: "json",
                    url: drupalSettings.path.baseUrl + 'ek_sales/load_documents',
                    data: {abid: settings.abid},
                    success: function (remoteData) {
                        jQuery('.loading').remove();
                        if(remoteData.data){
                          jQuery('#nodoc').remove();
                          jQuery('#sales_docs').html(remoteData.data);
                          addajax();
                          adddragdrop();  
                        }
                        
                    }
                });

            }


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

            /*
             * Drag & drop
             */
            function adddragdrop() {

                jQuery(".move").draggable({
                    cursor: "move",
                    cursorAt: {left: 0, top: 0},
                    //revert:true,
                    handle: ".handle-ico",
                    helper: "clone",
                    stop: function (event, ui) {
                        if (status == 1) {
                            load_sales_docs();
                        }
                    }
                });

                jQuery(".drop-folder").droppable({
                    activeClass: "ui-state-default",
                    hoverClass: "tr-drop",
                    accept: ":not(.ui-sortable-helper), .move",
                    activeClass: "",
                            drop: function (event, ui) {
                                jQuery.ajax({
                                    type: "POST",
                                    url: drupalSettings.path.baseUrl + "ek_sales/dragdrop",
                                    data: {from: (ui.draggable).attr("id"), to: this.id},
                                    async: false
                                });
                                status = 1;

                            }
                });
            }




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



