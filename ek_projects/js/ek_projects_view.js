(function ($, Drupal, drupalSettings) {

    /* toggle the notify me value
     * 
     */
    jQuery(function () {
        jQuery('#edit_notify').click(function () {
            jQuery.ajax({
                type: "POST",
                url: drupalSettings.path.baseUrl + 'ek_project/edit_notify',
                data: {id: drupalSettings.ek_projects.id},
                async: false,
                success: function (data) {
                    if (data.action == 1) {
                        jQuery('#edit_notify').toggleClass("_follow follow");
                        jQuery('#edit_notify_i').toggleClass("square check-square");
                    } else {
                        jQuery('#edit_notify').toggleClass("follow _follow");
                        jQuery('#edit_notify_i').toggleClass("check-square square");
                    }

                }
            });
        });
        
    });


    /* toggle the edition mode for all fields
     * 
     */
    jQuery(function () {
        jQuery('#edit_mode').click(function () {
            jQuery('#edit_mode').toggleClass("edit _edit");
            jQuery(".field_edit").toggle("fast");
            jQuery('section').toggleClass("editBackground");
            update_fields(drupalSettings.ek_projects.id);
            update_documents(drupalSettings.ek_projects.id);

        });

    });

    /* activate button
     * Open or close all sections
     */
    jQuery(function () {
        jQuery('#expand').click(function () {
            update_fields(drupalSettings.ek_projects.id);
            update_documents(drupalSettings.ek_projects.id);
            if (jQuery('#expand').hasClass('open-ico'))
                jQuery('.panel-body').hide('fast');
            if (jQuery('#expand').hasClass('close-ico'))
                jQuery('.panel-body').show('fast');
            jQuery('#expand').toggleClass('close-ico open-ico');

        });
    });

    /* delete files toggle
     * 
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





/* Call to update all fields
 * used when clicking on #edit_mode or periodical updater when enabled
 */
function update_fields(pid) {

    jQuery.ajax({
        type: "GET",
        url: drupalSettings.path.baseUrl + 'ek_project/periodicalupdater',
        data: {query: 'fields', id: pid},
        async: false,
        success: function (remoteData) {
            for (key in remoteData.data) {
                if(key == 'status_container') {
                    jQuery('#status_container').removeClass().addClass(remoteData.data[key]);
                } else {
                jQuery("#" + key).html(remoteData.data[key]);
            
                //tbeep.play();
                    for (i = 0; i < 3; i++) {
                        jQuery("#" + key).fadeTo('slow', 0.5).fadeTo('slow', 1.0);
                    }
                }
            }
        }
    });
}

/* Call to update all document lists
 * used when clicking on #edit_mode or periodical updater when enabled
 */
function update_documents(pid) {

    jQuery.ajax({
        type: "GET",
        url: drupalSettings.path.baseUrl + 'ek_project/periodicalupdater',
        data: {query: 'com_docs', id: pid},
        async: false,
        success: function (remoteData) {
            jQuery("#project_com_docs").html(remoteData.data);
            addajax();
        }
    });

    jQuery.ajax({
        type: "GET",
        url: drupalSettings.path.baseUrl + 'ek_project/periodicalupdater',
        data: {query: 'fi_docs', id: drupalSettings.ek_projects.id},
        async: false,
        success: function (remoteData) {
            jQuery("#project_fi_docs").html(remoteData.data);
            addajax();
        }
    });
}

/* add ajax call to links updated 
 * after ajax call
 * check core/misc/ajax.js L. 53
 */
function addajax() {

    // Bind Ajax behaviors to all items showing the class.

    jQuery('.use-ajax').once('ajax').each(function () {
        var element_settings = {};
        // Clicked links look better with the throbber than the progress bar.
        element_settings.progress = {type: 'throbber'};

        // For anchor tags, these will go to the target of the anchor rather
        // than the usual location.
        var href = jQuery(this).attr('href');
        if (href) {
            element_settings.url = href;
            element_settings.event = 'click';
        }
        element_settings.dialogType = jQuery(this).data('dialog-type');
        element_settings.dialog = jQuery(this).data('dialog-options');
        element_settings.base = jQuery(this).attr('id');
        element_settings.element = this;
        Drupal.ajax(element_settings);
    });


}

/*
 * drag drop
 */

function adddragdrop() {

    /**/
    jQuery("#s3,#ps3,#s5,#ps5").droppable({
        activeClass: "ui-state-default",
        hoverClass: "ui-state-hover",
        accept: ":not(.ui-sortable-helper), .move",
        activeClass: "",
                drop: function (event, ui) {
                    jQuery.ajax({
                        type: "POST",
                        url: drupalSettings.path.baseUrl + "projects/dragdrop",
                        data: {from: (ui.draggable).attr("id"), to: this.id},
                        async: false
                    });
                    status = 1;

                }
    })


    jQuery(".move").draggable({
        cursor: "move",
        cursorAt: {left: 0, top: 0},
        //revert:true,
        handle: "a",
        helper: "clone",
        stop: function (event, ui) {
            if (status == 1) {
               
            }
        }
    });

}