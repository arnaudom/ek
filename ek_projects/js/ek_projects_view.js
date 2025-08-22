(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.viewproject = {
        attach: function (context, settings) {
            if (!window.updaterInitialized && context === document) {
                // Only initialize the updater once
                window.updaterInitialized = true;

                // Intelligent periodic updater with activity-based adjustment
                function initializeIntelligentUpdater(projectId) {
                    const config = {
                        minPeriod: 3000,        // Minimum time between updates (3 seconds)
                        maxPeriod: 5 * 60000,   // Maximum time between updates (5 minutes)
                        initialPeriod: 3000,    // Start with frequent updates
                        userActiveDecay: 1.0,   // No decay when user is active
                        userInactiveDecay: 1.3, // Slower updates when user is inactive
                        inactivityThreshold: 60000, // Consider user inactive after 1 minute
                        manualMode: false       // Flag to track manual mode
                    };
                    
                    let currentPeriod = config.initialPeriod;
                    let lastUserActivity = Date.now();
                    let lastUpdateTime = 0;
                    let hasChanges = false;
                    let updater = null;
                    
                    // Track user activity
                    function updateUserActivity() {
                        lastUserActivity = Date.now();
                        // If we were in slow update mode, switch back to fast updates
                        if (currentPeriod > config.initialPeriod && !config.manualMode) {
                            currentPeriod = config.initialPeriod;
                            resetUpdater();
                        }
                    }
                    
                    // Listen for user interactions that indicate activity
                    ['click', 'keypress', 'scroll', 'mousemove'].forEach(eventType => {
                        document.addEventListener(eventType, updateUserActivity, { passive: true });
                    });
                    
                    function resetUpdater() {
                        if (updater) {
                            clearTimeout(updater);
                            updater = null;
                        }
                        // Only schedule next update if not in manual mode
                        if (!config.manualMode) {
                            updater = setTimeout(performUpdate, currentPeriod);
                        }
                    }
                    
                    function performUpdate() {
                        const isUserActive = (Date.now() - lastUserActivity) < config.inactivityThreshold;
                        
                        // Show subtle loading indicator
                        showUpdateIndicator(true);
                        
                        $.ajax({
                            url: drupalSettings.path.baseUrl + 'ek_project/tracker',
                            data: { id: projectId, last_update: lastUpdateTime },
                            dataType: 'json',
                            success: function(response) {
                            
                                if (response.hasChanges != false && lastUpdateTime < response.hasChanges) {
                                    lastUpdateTime = response.hasChanges;
                                    // Process updates
                                    update_users_activity(response); 
                                    // Reset to faster updates when changes are detected
                                    if (!config.manualMode) {
                                        currentPeriod = config.initialPeriod;
                                    }
                                } else {
                                    // Apply appropriate decay based on user activity (only if not in manual mode)
                                    if (!config.manualMode) {
                                        const decayFactor = isUserActive ? 
                                            config.userActiveDecay : config.userInactiveDecay;
                                        
                                        currentPeriod = Math.min(
                                            currentPeriod * decayFactor, 
                                            config.maxPeriod
                                        );
                                    }
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error("Update failed:", error);
                                // On error, back off more aggressively (only if not in manual mode)
                                if (!config.manualMode) {
                                    currentPeriod = Math.min(currentPeriod * 2, config.maxPeriod);
                                }
                            },
                            complete: function() {
                                showUpdateIndicator(false);
                                // Only reset updater if not in manual mode
                                if (!config.manualMode) {
                                    resetUpdater();
                                }
                            }
                        });
                    }
                    
                    // Start the updater
                    resetUpdater();
                    
                    return {
                        pause: function() {
                            if (updater) {
                                clearTimeout(updater);
                                updater = null;
                            }
                        },
                        resume: function() {
                            if (!updater && !config.manualMode) {
                                currentPeriod = config.initialPeriod;
                                resetUpdater();
                            }
                        },
                        forceUpdate: function() {
                            if (updater) {
                                clearTimeout(updater);
                                updater = null;
                            }
                            performUpdate();
                            // No resetUpdater() call here - it's handled in the complete callback
                            // of performUpdate() and will only restart auto-updates if not in manual mode
                        },
                        updateConfig: function(newConfig) {
                            // Detect manual mode explicitly
                            if (newConfig.min === 0) {
                                config.manualMode = true;
                                console.log("Manual mode activated:", config.manualMode);
                            } else {
                                config.manualMode = false;
                                console.log("Auto mode activated:", config.manualMode);
                            }
                            
                            // Update other configuration values
                            if (newConfig.min !== undefined) config.minPeriod = newConfig.min;
                            if (newConfig.max !== undefined) config.maxPeriod = newConfig.max;
                            if (newConfig.initial !== undefined) {
                                config.initialPeriod = newConfig.initial;
                                if (!config.manualMode) {
                                    currentPeriod = newConfig.initial; // Reset current period to new initial value
                                }
                            }
                            
                            // Optional parameters
                            if (newConfig.activeDecay !== undefined) config.userActiveDecay = newConfig.activeDecay;
                            if (newConfig.inactiveDecay !== undefined) config.userInactiveDecay = newConfig.inactiveDecay;
                            if (newConfig.inactivityThreshold !== undefined) config.inactivityThreshold = newConfig.inactivityThreshold;
                            
                            // Handle updater state based on manual mode
                            if (config.manualMode) {
                                // In manual mode, stop any scheduled updates
                                if (updater) {
                                    clearTimeout(updater);
                                    updater = null;
                                }
                            } else {
                                // In auto mode, restart the updater
                                resetUpdater();
                            }
                        },
                        // Add a method to check current mode (useful for debugging)
                        getMode: function() {
                            return config.manualMode ? "manual" : "auto";
                        }
                    };
                }

                // Initialize the updater when document is ready
                $(function() {
                    if (typeof drupalSettings.ek_projects !== 'undefined' && !window.projectUpdater) {
                        window.projectUpdater = initializeIntelligentUpdater(drupalSettings.ek_projects.id);
                    }
                });


            }

            // activate tabs
            const tabs = document.querySelectorAll(".tab");
            const contents = document.querySelectorAll(".tab-content");

            tabs.forEach(tab => {
                tab.addEventListener("click", function () {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove("active"));
                    contents.forEach(c => c.classList.remove("active"));

                    // Add active class to clicked tab and corresponding content
                    tab.classList.add("active");
                    const contentId = "content-" + tab.id.split("-")[1];
                    document.getElementById(contentId).classList.add("active");
                });
            });

            // open tab with url fragment
            const urlParams = new URLSearchParams(window.location.search);
            const hash = window.location.hash.substring(1);             
            // Check for hash first, then query parameter
            const targetTab = hash || urlParams.get('tab');
            
            if (targetTab) {
                const tabElement = document.getElementById(targetTab);
                if (tabElement) {
                    tabElement.click();
                    // Smooth scroll to tab if needed
                    tabElement.scrollIntoView({ behavior: 'smooth' });
                }
            }
                        
            // update fill progress
            $('.progress-fill').css('width', drupalSettings.ek_projects.fillRatio + '%');
            $('.progress-text').html(drupalSettings.ek_projects.fillRatio);
            if(drupalSettings.ek_projects.fillRatio < 30) {
                $('.progress-fill').css('background-color','#ff0000');
            } else if (drupalSettings.ek_projects.fillRatio < 70) {
                $('.progress-fill').css('background-color','#FFC900');
            }

            $(once('timeline', '.timeline-section', context)).each(function () {
                // Get timeline points
                const points = {
                    proposal: document.querySelector('.timeline-point.proposal'),
                    validation: document.querySelector('.timeline-point.validation'),
                    start: document.querySelector('.timeline-point.start'),
                    timelineNow: document.querySelector('.timeline-point.timelineNow'),
                    deadline: document.querySelector('.timeline-point.deadline')
                };
            
                // Function to update point color
                function updatePointColor(pointClass, color) { 
                    document.querySelector(`${pointClass} .point`).style.backgroundColor = color;
                }
            
                // Check if dates are set
                const hasDates = 
                    drupalSettings.ek_projects.start_date != "0" && 
                    drupalSettings.ek_projects.start_date != "" &&
                    drupalSettings.ek_projects.deadline != "0" &&
                    drupalSettings.ek_projects.deadline != "";
      
                // Update colors based on status
                if (drupalSettings.ek_projects.submission != "0" && drupalSettings.ek_projects.submission != "") {
                    updatePointColor('.proposal', '#00b515'); 
                }
                if (drupalSettings.ek_projects.validation != "0" && drupalSettings.ek_projects.validation !== "") {
                    updatePointColor('.validation', '#00b515');
                }
                if (drupalSettings.ek_projects.start_date != "0" && drupalSettings.ek_projects.start_date != "") {
                    updatePointColor('.start', '#00b515');
                }
                updatePointColor('.timelineNow', '#ffe166'); // Current always highlighted
                if (drupalSettings.ek_projects.deadline != "0" && drupalSettings.ek_projects.deadline != "") {
                    updatePointColor('.deadline', '#0088cc');
                }
            
                // If dates are set, reposition points proportionally
                if (hasDates) {
                    // Calculate total duration in milliseconds
                    const startDate = new Date(drupalSettings.ek_projects.start_date);
                    const deadlineDate = new Date(drupalSettings.ek_projects.deadline);
                    const totalDuration = deadlineDate - startDate;
                    
                    // Current date position (if provided, otherwise use today's date)
                    const currentDate = (drupalSettings.ek_projects.current !== "0" && drupalSettings.ek_projects.current !== "") 
                    ? new Date(drupalSettings.ek_projects.current)
                    : new Date();
                        
                    const timeFromStart = currentDate - startDate;
            
                    // Calculate positions (5-unit scale mapped to 100% width)
                    const startPos = 20;  // Start at 0%
                    const deadlinePos = 90;  // Deadline at 100%
                    const currentPos = (timeFromStart / totalDuration) * 90;
            
                    // Apply positions
                    if (points.start) points.start.style.left = `${startPos}%`;
                    if (points.timelineNow) points.timelineNow.style.left = `${Math.min(Math.max(currentPos, 0), 90)}%`; // Clamp between 0-100
                    if (points.deadline) points.deadline.style.left = `${deadlinePos}%`;      
                    // Keep proposal and validation at their initial positions
                    if (points.proposal) points.proposal.style.left = '1%';
                    if (points.validation) points.validation.style.left = '10%';
                }
                // If no dates are set, default positions from CSS are used
            });


            // Create and inject UI elements for update controls and indicators
            function initializeUpdateUI() {
                // Set up event handlers for the UI controls
                $('#update-frequency').on('change', function() {
                    const value = $(this).val();
                    const config = {
                        realtime: { min: 2000, max: 10000, initial: 2000 },
                        normal: { min: 6000, max: 60000, initial: 6000 },
                        minimal: { min: 60000, max: 600000, initial: 60000 },
                        manual: { min: 0, max: 0, initial: 0 }
                    };

                    updateProjectConfig(config[value]);
                    
                    // Update UI for manual mode
                    if (value === 'manual') {
                        $('#manual-update').prop('disabled', false).removeClass('disabled');
                    } else {
                        $('#manual-update').prop('disabled', true).addClass('disabled');
                    }
                });
                
                $('#manual-update').on('click', function() {
                    if (window.projectUpdater) {
                        window.projectUpdater.forceUpdate();
                    }
                });
                
                $('#enable-notifications').on('change', function() {
                    drupalSettings.ek_projects.soundNotifications = $(this).is(':checked');
                });
            }

            // Show/hide update indicator
            function showUpdateIndicator(isUpdating) {
                const $indicator = $('#update-status-indicator');
                
                if (isUpdating) {
                    $indicator.addClass('visible');
                } else {
                    $indicator.removeClass('visible');
                }
            }

            // Configure the updater based on user preferences
            function updateProjectConfig(config) {
                if (!window.projectUpdater) return;
                
                // Update the configuration with explicit manual mode handling
                window.projectUpdater.updateConfig(config);
            }

            // Highlight changed elements with a subtle animation instead of flashing
            function highlightChangedElement(elementId) { 
                const $element = $('#' + elementId);
                if ($element.length) {
                    // Remove any existing highlight animation
                    $element.removeClass('highlight-update');
                    // Trigger a reflow to restart the animation
                    void $element[0].offsetWidth;
                    // Add the highlight class
                    $element.addClass('highlight-update');
                }
            }

            // Sound notification system with user preference
            function playNotificationSound() {
                // Check user preferences before playing
                if (drupalSettings.ek_projects.soundNotifications !== false) {
                    // Create audio context only when needed
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!window.notificationAudioContext && AudioContext) {
                        window.notificationAudioContext = new AudioContext();
                    }
                    
                    // Simple beep using Web Audio API
                    if (window.notificationAudioContext) {
                        const oscillator = window.notificationAudioContext.createOscillator();
                        const gainNode = window.notificationAudioContext.createGain();
                        
                        oscillator.type = 'sine';
                        oscillator.frequency.value = 3000; 
                        gainNode.gain.value = 0.03; // Quiet notification
                        
                        oscillator.connect(gainNode);
                        gainNode.connect(window.notificationAudioContext.destination);
                        
                        oscillator.start();
                        oscillator.stop(window.notificationAudioContext.currentTime + 0.15);
                    }
                }
            }

            // Update the existing update_users_activity function
            function update_users_activity(activity) {
                $(".tracklist ul").html(activity.data);
                
                if (activity.hasChanges) {
                    //if (update_count > 0) {
                        playNotificationSound();
                    //}
                    //update_count++;
                    last_update = activity.hasChanges;
                    update_fields(drupalSettings.ek_projects.id);
                    update_documents(drupalSettings.ek_projects.id);
                    adddragdrop();
                    
                    // Use subtle highlighting instead of flashing
                    if (activity.field) {
                        highlightChangedElement(activity.field);
                    }
                    highlightChangedElement("tracklist");
                }
            }

            // Initialize everything
            $(function() {
                initializeUpdateUI();
                // Set default for sound notifications
                drupalSettings.ek_projects.soundNotifications = true;
            });

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
                                $('#status').removeClass().addClass("status p_" + remoteData.data[key]);
                            } else {
                                $("#" + key).html(remoteData.data[key]);
                                
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
                $("#s3,#ps3,#s5,#ps5,#tab-s3,#tab-s5").droppable({
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
                            }
                });

                $(".move").draggable({
                    cursor: "move",
                    cursorAt: {left: 0, top: 0},
                    //revert:true,
                    handle: "a",
                    helper: "clone",
                    stop: function (event, ui) {
                        
                    }
                });
            }

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

             /* 
            * toggle the edition mode for all fields
            */
            $(function () {
                $(once('bind-click-event', '#edit_mode', context)).on('click', function (event) {
                    $('#edit_mode').toggleClass("edit _edit");
                    $(".field_edit").toggle("fast");
                    $('section').toggleClass("editBackground");
                    if ($('#edit_mode').hasClass('edit')) {
                        window.projectUpdater.pause();
                    }
                    if ($('#edit_mode').hasClass('_edit')) { 
                        window.projectUpdater.resume();
                    }
                });
            });

            /* tracking data control
            */
            $(function () {
                $(once('activity-click-event','#activityList', context)).on('click', function (event) {
                    $(".tracklist").toggle();
                    $(".update-controls").toggle();
                    $("#activityList i").toggleClass('fa-power-off fa-circle-o');
                    if ($('#activityList i').hasClass('fa-power-off')) window.projectUpdater.pause();
                    if ($('#activityList i').hasClass('fa-circle-o')) window.projectUpdater.resume();
                });

            });

            $(function () {
                $(once('list-click-event','#aListExpand', context)).on('click', function (event) {
                    if ($('.tracklist').css('max-height') != 'none') {
                        $('.tracklist').css('max-height','none');
                        $("#aListExpand").html('▲');
                    } else {
                        $('.tracklist').css('max-height','10em');
                        $("#aListExpand").html('▼');
                    }
                });

            });

            /* 
            * toggle the notify me value
            */
            $(function () {
                $(once('join-click-event','#edit_notify', context)).on('click', function (event) {
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


            /* activate button
            * Open or close all sections
            */
            $(function () {
                $(once('expand-click-event','#expand', context)).on('click', function (event) {
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
                $(once('hide-click-event','.hideFile', context)).on('click', function (event) {
                    if ($('.hideFile').hasClass('show-ico')) {
                        $('.hide').hide('fast');
                    } else if ($('.hideFile').hasClass('hide-ico')) {
                        $('.hide').show('fast');
                    }
                    $('.hideFile').toggleClass('show-ico hide-ico');

                });
            });

            /* 
            * linked project content
            */
            $(function () {
                $(once('link-click-event', '#link-title', context)).on('click', function (event) {
                    $('#link-content').toggle('fast');
                });
            });
            
            /*
            * postit
            */
            $('.projectpostit').blur(function () {
                var text = $(this).html(); 
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

            /*
            * copy project url
            */
            $(".clipboard_name").click(function () {
                var text = window.location.protocol + '//' + window.location.host + '/user/login?destination=/projects/project/' + drupalSettings.ek_projects.id;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        for (let i = 0; i < 2; i++) {
                            $y('#copy' + id).fadeTo('fast', 0).fadeTo('fast', 1);
                        }
                    }, function(err) {
                        fallbackCopyTextToClipboard(text, id);
                    });
                } else {
                    fallbackCopyTextToClipboard(text,'.clipboard_name');
                }
            });

            function fallbackCopyTextToClipboard(text, id) {
                var $body = document.getElementsByTagName('body')[0];
                var $tempInput = document.createElement('textarea');
                $body.appendChild($tempInput);
                $tempInput.value = text;
                $tempInput.select();
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText($tempInput.value).then(function() {
                        // Success
                    }, function(err) {
                        console.error('Clipboard write failed: ', err);
                    });
                } else {
                    document.execCommand('copy');
                }
                $body.removeChild($tempInput);
                for (let i = 0; i < 2; i++) {
                    $(id).fadeTo('fast', 0).fadeTo('fast', 1.0);
                }
            }


        }};
})(jQuery, Drupal, drupalSettings);


(function ($, Drupal) {
    // Store updater state when opening a modal
    $(document).on('dialog:beforecreate', function (e, dialog, $element, settings) {
      if (window.projectUpdater) {
        // Store current updater state
        window.updaterPausedByModal = true;
        window.projectUpdater.pause();
      }
    });
  
    // Restore updater state when closing a modal
    $(document).on('dialog:afterclose', function (e, dialog, $element) {
      if (window.projectUpdater && window.updaterPausedByModal) {
        window.projectUpdater.resume();
        window.updaterPausedByModal = false;
      }
    });

  })(jQuery, Drupal);