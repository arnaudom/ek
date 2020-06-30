(function ($, Drupal, drupalSettings) {

    var last_update = 0;
    var update_count = 0;
    /*
     * Start a periodical update of all fields
     * @returns {undefined}
     */
    var P1 = $.periodic({period: 1000, decay: 1.2, max_period: 60000}, function() {
        
     $.ajax({
        url: drupalSettings.path.baseUrl + 'ek_project/tracker',
        data: {id: drupalSettings.ek_projects.id},
        success: function(remoteData) { 
                update_users_activity(remoteData);
        },
        complete: function(xhr, status) {
                P1.ajax_complete(xhr, status);
        },
        dataType: 'json'
      });
    });

    function update_users_activity(activity) {
        
        jQuery(".tracklist ul").html(activity.data);
        if(last_update !== activity.data) {
                if(update_count > 0) {
                    tbeep.play();
                }
                update_count++;
                last_update = activity.data;
                update_fields(drupalSettings.ek_projects.id);
                update_documents(drupalSettings.ek_projects.id);
                adddragdrop();
                for (i = 0; i < 3; i++) {
                    jQuery("#" + activity.field).fadeTo('slow', 0.5).fadeTo('slow', 1.0);
                    jQuery(".tracklist").fadeTo('slow', 0.5).fadeTo('slow', 1.0);
                }
                
        }
        
    }


    /* tracking data control
     */
    jQuery(function () {
        jQuery('#activityList').click(function () {
            //if (jQuery('#rtc-status').hasClass('available'))
            jQuery(".tracklist").toggle();
            jQuery("#activityList i").toggleClass('fa-power-off fa-circle-o');
            if (jQuery('#activityList i').hasClass('fa-power-off')) P1.cancel();
            if (jQuery('#activityList i').hasClass('fa-circle-o')) P1.reset();
        });

    });


})(jQuery, Drupal, drupalSettings);