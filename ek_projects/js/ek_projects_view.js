(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.viewproject = {
        attach: function (context, settings) {
        /* 
        * toggle the notify me value
        */
        $(function () {
            $('#edit_notify').click(function () {
                jQuery.ajax({
                    type: "POST",
                    url: drupalSettings.path.baseUrl + 'ek_project/edit_notify',
                    data: {id: drupalSettings.ek_projects.id},
                    async: false,
                    success: function (data) {
                        if (data.action == 1) {
                            $('#edit_notify').toggleClass("_follow follow");
                            $('#edit_notify_i').toggleClass("square check-square");
                        } else {
                            $('#edit_notify').toggleClass("follow _follow");
                            $('#edit_notify_i').toggleClass("check-square square");
                        }

                    }
                });
            });

        });


        /* 
        * toggle the edition mode for all fields
        */
        $(function () {
            $('#edit_mode').click(function () {
                $('#edit_mode').toggleClass("edit _edit");
                $(".field_edit").toggle("fast");
                $('section').toggleClass("editBackground");
                update_fields(drupalSettings.ek_projects.id);
                update_documents(drupalSettings.ek_projects.id);

            });

        });

        /* activate button
        * Open or close all sections
        */
        $(function () {
            $('#expand').click(function () {
                update_fields(drupalSettings.ek_projects.id);
                update_documents(drupalSettings.ek_projects.id);
                if ($('#expand').hasClass('open-ico'))
                    $('.pro-panel-body').hide('fast');
                if ($('#expand').hasClass('close-ico'))
                    $('.pro-panel-body').show('fast');
                $('#expand').toggleClass('close-ico open-ico');

            });
        });

        /* 
        * delete files toggle
        */
        $(function () {
            $('.hideFile').click(function () {
                if ($('.hideFile').hasClass('show-ico'))
                    $('.hide').hide('fast');
                if ($('.hideFile').hasClass('hide-ico'))
                    $('.hide').show('fast');
                $('.hideFile').toggleClass('show-ico hide-ico');

            });
        });
        
        /*
        * postit
        */
        $('.projectpostit').blur(function () {
                var text = $(this).html(); console.log(text);
                jQuery.ajax({
                    type: "POST",
                    url: drupalSettings.path.baseUrl + 'projects/project/' + drupalSettings.ek_projects.id + '/edit',
                    data: {f: 'postit', d: drupalSettings.ek_projects.id, string: text},
                    async: false,
                    success: function (data) {
                        if (data.action == 1) {
                            
                        } else {
                            
                        }
                    }
                });
            });
        
        /* add ajax call to links updated 
        * after ajax call
        * check core/misc/ajax.js L. 53
        */
        function addajax() {
            // Bind Ajax behaviors to all items showing the class.
            $(once('ajax', '.use-ajax', context)).each(function () {
            //$('.use-ajax').once('ajax').each(function () {
                var element_settings = {};
                // Clicked links look better with the throbber than the progress bar.
                element_settings.progress = {type: 'throbber'};

                // For anchor tags, these will go to the target of the anchor rather
                // than the usual location.
                var href = $(this).attr('href');
                if (href) {
                    element_settings.url = href;
                    element_settings.event = 'click';
                }
                element_settings.dialogType = $(this).data('dialog-type');
                element_settings.dialog = $(this).data('dialog-options');
                element_settings.dialogRenderer = $(this).data('dialog-renderer');
                element_settings.base = $(this).attr('id');
                element_settings.element = this;
                Drupal.ajax(element_settings);
            });
        }

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
                        if (key == 'status_container') {
                            $('#status_container').removeClass().addClass("p_" + remoteData.data[key]);
                        } else {
                            $("#" + key).html(remoteData.data[key]);

                            //tbeep.play();
                            for (i = 0; i < 3; i++) {
                                $("#" + key).fadeTo('slow', 0.5).fadeTo('slow', 1.0);
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
                    $("#project_com_docs").html(remoteData.data);
                    addajax();
                }
            });

            jQuery.ajax({
                type: "GET",
                url: drupalSettings.path.baseUrl + 'ek_project/periodicalupdater',
                data: {query: 'fi_docs', id: drupalSettings.ek_projects.id},
                async: false,
                success: function (remoteData) {
                    $("#project_fi_docs").html(remoteData.data);
                    addajax();
                }
            });

            adddragdrop();
        }

        /*
        * drag drop
        */

        function adddragdrop() {

            /**/
            $("#s3,#ps3,#s5,#ps5").droppable({
                activeClass: "ui-state-default",
                hoverClass: "panel-drop",
                accept: ":not(.ui-sortable-helper), .move",
                activeClass: "",
                        drop: function (event, ui) {
                            jQuery.ajax({
                                type: "POST",
                                url: drupalSettings.path.baseUrl + "projects/dragdrop",
                                data: {move: 'folder',from: (ui.draggable).attr("id"), to: this.id},
                                async: false
                            });
                            status = 1;

                        }
            })

            $(".sub-folder").droppable({
                activeClass: "ui-state-default",
                hoverClass: "tr-drop",
                accept: ":not(.ui-sortable-helper), .move",
                activeClass: "",
                        drop: function (event, ui) {
                            jQuery.ajax({
                                type: "POST",
                                url: drupalSettings.path.baseUrl + "projects/dragdrop",
                                data: {move: 'subfolder',from: (ui.draggable).attr("id"), to: this.id},
                                async: false
                            });
                            status = 1;

                        }
            });


            $(".move").draggable({
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

        /*
        * Start a periodical update of all fields
        * @returns {undefined}
        */
        var last_update = 0;
        var update_count = 0;
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
            
            $(".tracklist ul").html(activity.data);
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
                        $("#" + activity.field).fadeTo('slow', 0.5).fadeTo('slow', 1.0);
                        $(".tracklist").fadeTo('slow', 0.5).fadeTo('slow', 1.0);
                    }
                    
            }
            
        }


        /* tracking data control
        */
        $(function () {
            $('#activityList').click(function () {
                //if ($('#rtc-status').hasClass('available'))
                $(".tracklist").toggle();
                $("#activityList i").toggleClass('fa-power-off fa-circle-o');
                if ($('#activityList i').hasClass('fa-power-off')) P1.cancel();
                if ($('#activityList i').hasClass('fa-circle-o')) P1.reset();
            });

        });
    }};
})(jQuery, Drupal, drupalSettings);