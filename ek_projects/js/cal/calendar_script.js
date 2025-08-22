(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_calendar = {
        attach: function (context, settings) {
            $('.calendar-off-canvas').removeAttr('id');
            if (settings.type == 'block') {
                display_calendar_block(settings.calendarLang);
            }

            $("#filtercalendar")
                .bind("change", function (event) {
                    $('.calendar-off-canvas').removeAttr('id');
                    $('#loading').show();
                    var option = jQuery(this).val();
                    $('#calendar-warning').hide();
                    display_calendar(option, settings.calendarLang);
                });
        } //attach
    }; //behaviors

    // Global calendar instances
    let blockCalendar = null;
    let mainCalendar = null;

    function display_calendar_block(calendarLang) {
        const calendarEl = document.getElementById('calendar_block');
        
        if (blockCalendar) {
            blockCalendar.destroy();
        }
        
        blockCalendar = new FullCalendar.Calendar(calendarEl, {
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: '' // No view switcher for block
            },
            locale: calendarLang,
            aspectRatio: 1,
            eventTimeFormat: {
                hour: 'numeric',
                minute: '2-digit',
                hour12: false
            },
            // Note: 'agenda' was a v3 concept, removed in v5
        });
        
        blockCalendar.render();
    }

    function display_calendar(e, calendarLang) {
        const calendarEl = document.getElementById('calendar');
        
        if (mainCalendar) {
            mainCalendar.destroy();
        }
        
        mainCalendar = new FullCalendar.Calendar(calendarEl, {
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: calendarLang,
            eventSources: [{
                url: drupalSettings.path.baseUrl + "projects/calendar/view/" + e,
                success: function(data) {
                    //console.log("Events received:", data); // Check if IDs and dates are correct
                    
                },
                failure: function() {
                    $('#calendar-warning').show();
                }
            }],
            aspectRatio: 1,
            eventTimeFormat: {
                hour: 'numeric',
                minute: '2-digit',
                hour12: false
            },
            loading: function (bool) {
                $('#loading').toggle(bool);
            },

            eventDidMount: function (info) {
                var $element = $(info.el);
                // Set custom data attribute
                $element.attr('data-custom-id', info.event.id);
                // Remove any existing title attribute that might interfere
                $element.removeAttr('title');
                // Add a temporary title to help jQuery UI Tooltip initialize
                $element.attr('title', 'Loading...');
                
                // Initialize tooltip with extensive debugging
                try {
                    $element.tooltip({
                        content: function() {
                            var content = info.event.extendedProps.description || info.event.description || 'No description available';
                            return content;
                        },
                        position: {
                            my: 'bottom left',
                            at: 'top left',
                            collision: 'flip'
                        },
                        classes: {
                            'ui-tooltip': 'cal-tooltip'
                        },
                        // Force tooltip to show on hover
                        show: {
                            delay: 0,
                            effect: 'fadeIn',
                            duration: 150
                        },
                        hide: {
                            delay: 100,
                            effect: 'fadeOut',
                            duration: 150
                        },
                        // Debug events
                        create: function(event, ui) {
                            
                        },
                        open: function(event, ui) {                            
                            // Force visibility with important styles
                            ui.tooltip.css({
                                'pointer-events': 'none' // Prevent tooltip from interfering with mouse events
                            });
                        },
                        close: function(event, ui) {
                        }
                    });
                    
                    // Verify tooltip instance was created
                    //var instance = $element.tooltip('instance');                    
                    // Check if events are properly bound
                    //var events = $._data($element[0], 'events');
                    
                    // Manual event binding as backup (FullCalendar might interfere with automatic binding)
                    //$element.off('mouseenter.fc-tooltip mouseleave.fc-tooltip');
                    
                    $element.on('mouseenter.fc-tooltip', function(e) {
                        console.log('🖱️ Manual mouseenter for:', info.event.id);
                        e.stopPropagation(); // Prevent FullCalendar from handling this
                        
                        var tooltipInstance = $(this).tooltip('instance');
                        if (tooltipInstance && !tooltipInstance.tooltip) {
                            console.log('Opening tooltip manually...');
                            tooltipInstance.open(e);
                        }
                    });
                    
                    $element.on('mouseleave.fc-tooltip', function(e) {
                        var tooltipInstance = $(this).tooltip('instance');
                        if (tooltipInstance && tooltipInstance.tooltip) {
                            tooltipInstance.close(e);
                        }
                    });
                    
                    // Alternative approach: Use native tooltip events
                    $element.on('focusin.fc-tooltip', function(e) {
                        console.log('🔍 Focus event for:', info.event.id);
                        $(this).tooltip('open');
                    });
                    
                    $element.on('focusout.fc-tooltip', function(e) {
                        $(this).tooltip('close');
                    });
                    
                    // Test tooltip after element is fully rendered
                    /*setTimeout(function() {
                        console.log('🧪 Testing tooltip for:', info.event.id);
                        if ($element.tooltip('instance')) {
                            console.log('Attempting programmatic tooltip open...');
                            $element.tooltip('open');
                            
                            setTimeout(function() {
                                $element.tooltip('close');
                                console.log('Programmatic test completed');
                            }, 1500);
                        }
                    }, 500);*/
                    
                } catch (error) {
                    console.error('Error initializing tooltip:', error);
                }
            },
            eventMouseEnter: function(info) {
                // eventMouseover is now eventMouseEnter
                // Additional hover logic can go here if needed
                
            }
        });
        
        mainCalendar.render();
    }
})(jQuery, Drupal, drupalSettings);






