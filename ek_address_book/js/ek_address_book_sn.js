
jQuery(document).ready(function () {
    jQuery("#edit-name").change(function () {
        var term = jQuery('#edit-name').val();
        var sn = jQuery.ajax({
            dataType: "json",
            url: "built_sn_ajax",
            data: {term: term},
            success: function (data) {
                jQuery('#short_name').val(data.sn);

                if (data.name >= 1) {
                    jQuery('#alert').html(data.alert)
                    jQuery('#alert').addClass('messages messages--warning');
                } else {
                    jQuery('#alert').removeClass('messages messages--warning');
                    jQuery('#alert').html('')
                }
            }
        });
    });

});