(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_documents = {
        attach: function (context, settings) {

            if (settings.ek_documents == 'myDocs') {
                load_my_docs();
            } else if (settings.ek_documents == 'sharedDocs') {
                load_shared_docs();
            } else {
                load_common_docs();
            }

            jQuery('#gridview').click(function () {
                jQuery.cookie('list-type', 1, {expires: 1});

                if (settings.ek_documents == 'myDocs') {
                    load_my_docs();
                } else {
                    load_shared_docs();
                }

            });

            jQuery('#listview').click(function () {
                jQuery.cookie('list-type', 0, {expires: 1});
                if (settings.ek_documents == 'myDocs') {
                    load_my_docs();
                } else {
                    load_shared_docs();
                }

            });


        }//attach
    };

})(jQuery, Drupal, drupalSettings);


jQuery('#expand').click(function () {

    if (jQuery('#expand').hasClass('open-ico'))
        jQuery('.inner').hide('fast');
    if (jQuery('#expand').hasClass('close-ico'))
        jQuery('.inner').show('fast');
    jQuery('#expand').toggleClass('close-ico open-ico');

});


function load_my_docs() {
    jQuery.ajax({
        dataType: "json",
        url: "documents/load",
        data: {get: 'myDocs', sort: 'all'},
        success: function (data) {
            jQuery('.loading').remove();
            jQuery('#myDocs').html(data.list);
            addajax();
            adddragdrop();
        }
    });

}

function load_shared_docs() {

    jQuery.PeriodicalUpdater('documents/load', {
        //url: url,         // URL of ajax request
        cache: false, // By default, don't allow caching
        method: 'POST', // method; get or post
        data: {get: 'sharedDocs', sort: 'all'},
        minTimeout: 1000, // starting value for the timeout in milliseconds
        maxTimeout: 64000, // maximum length of time between requests
        multiplier: 2, // if set to 2, timerInterval will double each time the response hasn't changed (up to maxTimeout)
        maxCalls: 100, // maximum number of calls. 0 = no limit.
        maxCallsCallback: null, // The callback to execute when we reach our max number of calls
        autoStop: 0, // automatically stop requests after this many returns of the same data. 0 = disabled
        autoStopCallback: null, // The callback to execute when we autoStop
        cookie: false, // whether (and how) to store a cookie
        runatonce: false, // Whether to fire initially or wait
        verbose: 0        // The level to be logging at: 0 = none; 1 = some; 2 = all
    }, function (remoteData, success, xhr, handle) {
        ;
        // Process the new data (only called when there was a change)
        jQuery("#sharedDocs").html(remoteData.list);
        addajax();
        adddragdrop();

    });


}

function load_common_docs() {

    jQuery.PeriodicalUpdater('documents/load', {
        //url: url,         // URL of ajax request
        cache: false, // By default, don't allow caching
        method: 'POST', // method; get or post
        data: {get: 'commonDocs', sort: 'all'},
        minTimeout: 1000, // starting value for the timeout in milliseconds
        maxTimeout: 64000, // maximum length of time between requests
        multiplier: 2, // if set to 2, timerInterval will double each time the response hasn't changed (up to maxTimeout)
        maxCalls: 100, // maximum number of calls. 0 = no limit.
        maxCallsCallback: null, // The callback to execute when we reach our max number of calls
        autoStop: 0, // automatically stop requests after this many returns of the same data. 0 = disabled
        autoStopCallback: null, // The callback to execute when we autoStop
        cookie: false, // whether (and how) to store a cookie
        runatonce: false, // Whether to fire initially or wait
        verbose: 0        // The level to be logging at: 0 = none; 1 = some; 2 = all
    }, function (remoteData, success, xhr, handle) {
        ;
        // Process the new data (only called when there was a change)
        jQuery("#commonDocs").html(remoteData.list);
        addajax();
        adddragdrop();

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
 * drag drop
 */

function adddragdrop() {

    /**/
    jQuery("#doc-list ul").droppable({
        activeClass: "ui-state-default",
        hoverClass: "ui-state-hover",
        accept: ":not(.ui-sortable-helper)",
        activeClass: "",
                drop: function (event, ui) {
                    jQuery.ajax({
                        type: "POST",
                        url: "documents/dragdrop",
                        data: {from: (ui.draggable).attr("id"), to: this.id},
                        async: false
                    });
                    status = 1;

                }
    })

    jQuery(".drop-folder").droppable({
        activeClass: "ui-state-default",
        hoverClass: "ui-state-hover",
        accept: ":not(.ui-sortable-helper)",
        activeClass: "",
                drop: function (event, ui) {
                    jQuery.ajax({
                        type: "POST",
                        url: "documents/dragdrop",
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
        handle: ".handle-ico",
        helper: "clone",
        stop: function (event, ui) {
            if (status == 1) {
                load_my_docs();
            }
        }
    });

}