(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_calendar = {
        attach: function (context, settings) {

            if (settings.type == 'block') {
                display_calendar_block(settings.calendarLang);
            }

            jQuery("#filtercalendar")
                    .bind("change", function (event) {
                        jQuery('#loading').show();
                        var option = jQuery(this).val();
                        jQuery('#calendar-warning').hide();
                        display_calendar(option, settings.calendarLang);
                    });
        } //attach
    }; //bahaviors

    function display_calendar_block(calendarLang) {

        jQuery('#calendar_block').fullCalendar({
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'h'
            },
            lang: calendarLang,
            aspectRatio: 1,
            timeFormat: 'H(:mm)',
            agenda: 'h:mm{ - h:mm}',

        });
    }

    function display_calendar(e, calendarLang) {
        jQuery('#calendar').fullCalendar('destroy');
        jQuery('#calendar').fullCalendar({
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            },
            eventMouseover: true,
            //selectable: true,
            //selectHelper: true,
            //editable: true,
            lang: calendarLang,
            events: {
                url: drupalSettings.path.baseUrl + "projects/calendar/view/" + e,
                error: function () {
                    jQuery('#calendar-warning').show();
                }
            },
            aspectRatio: 1,
            timeFormat: 'H(:mm)',
            agenda: 'h:mm{ - h:mm}',
            loading: function (bool) {
                jQuery('#loading').toggle(bool);
            },
            eventRender: function (event, element) {
                element.qtip({
                    content: event.description,
                    target: 'mouse',
                    style: {
                        classes: 'qtip-bootstrap'
                    },
                    position: {
                        my: 'bottom right', // Position my 
                        at: 'top left', // at the bottom 
                    },
                });
            }
        });
    }
})(jQuery, Drupal, drupalSettings);



