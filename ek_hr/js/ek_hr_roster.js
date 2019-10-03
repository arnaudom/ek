(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_hr = {
        attach: function (context, settings) {


            if (settings.roster.cut) {
                if (jQuery.cookie(settings.roster.cut)) {
                    jQuery('#edit-cutoff').val(jQuery.cookie(settings.roster.cut));
                }
                $('#edit-cutoff').change(function () {
                    var value = parseFloat(jQuery('#edit-cutoff').val());
                    jQuery.cookie(settings.roster.cut, value, {expires: 1});
                });
            }

            $('.tip').each(function(){
                $(this).qtip({
                    position: {
                            my: 'bottom right', // Position my top left...
                            at: 'top left', // at the bottom right of...
                            target: this // my target
                        },
                    content: {
                        text: 'Loading...',
                        ajax: {
                            url: '/human-resources/e/autocomplete',
                            type: 'GET',
                            data: {q: $(this).attr('id'), option:'image'},
                            success: function (data, status) {
                                // Process the data
                                var tx = data[0]['picture'] + data[0]['name'];
                                // Set the content manually (required!)
                                this.set('content.text', tx);
                            }
                        }
                    }
                })
            });

            $(".day").on("click", function () {
                jQuery(".time").hide();
                jQuery(".slide").hide();
                jQuery("." + this.id).show();
                jQuery(".calendar-ico").show();
                jQuery(".export-ico").hide();
            });

            $("#calendar").on("click", function () {
                jQuery(".slide").hide();
                jQuery(".time").show();
                jQuery(".calendar-ico").hide();
                jQuery(".export-ico").show();
            });


            for (var key  in settings.roster) {

                var data = settings.roster[key];

                /* */
                jQuery("#" + key).slider({
                    range: true,
                    min: data[1],
                    max: data[2],
                    step: data[3],
                    values: [data[4], data[5]],
                    slide: function (event, ui) {
                        var id = this.id;
                        var from = id.replace('slide', 'from');
                        var to = id.replace('slide', 'to');
                        var roster = id.replace('slide', 'roster');
                        var index = id.replace('slide1-', '');
                        var index = index.replace('slide2-', '');
                        var index = index.replace('slide3-', '');

                        var spread = ui.values[ 1 ] - ui.values[ 0 ];
                        var ui0 = timing(ui.values[ 0 ]);
                        var ui1 = timing(ui.values[ 1 ]);
                        jQuery("#" + from).html(ui0);
                        jQuery("#" + to).html(ui1);
                        var tot = total_time(index)
                        jQuery("#spread-" + index).html(tot);
                    }

                });

            }

        }


    };

})(jQuery, Drupal, drupalSettings);


function timing(x) {
    var y = parseInt(x);
    var z = x - y;
    var t = z / 100 * 60 + y;
    return t.toFixed(2);
}

function base_100(x) {
    var y = parseInt(x);
    var z = x - y;
    var t = z / 60 * 100 + y;
    return t.toFixed(2);

}

function total_time(id) {

    var t0 = base_100(parseFloat(jQuery("#from1-" + id).html()));
    if (isNaN(t0))
        t0 = 0;
    var t1 = base_100(parseFloat(jQuery("#to1-" + id).html()));
    if (isNaN(t1))
        t1 = 0;
    var t2 = base_100(parseFloat(jQuery("#from2-" + id).html()));
    if (isNaN(t2))
        t2 = 0;
    var t3 = base_100(parseFloat(jQuery("#to2-" + id).html()));
    if (isNaN(t3))
        t3 = 0;
    var t4 = base_100(parseFloat(jQuery("#from3-" + id).html()));
    if (isNaN(t4))
        t4 = 0;
    var t5 = base_100(parseFloat(jQuery("#to3-" + id).html()));
    if (isNaN(t5))
        t5 = 0;
    var input = timing(t0) + ',' + timing(t1) + ',' + timing(t2) + ',' + timing(t3) + ',' + timing(t4) + ',' + timing(t5);
    jQuery('[name="roster[roster-' + id + ']"]').val(input);
    var ta = t1 - t0;
    var tb = t3 - t2;
    var tc = t5 - t4;
    var total = ta + tb + tc;
    var y = parseInt(total);
    var z = total - y;
    var total = (z / 100 * 60) + y;

    return total.toFixed(2);

}