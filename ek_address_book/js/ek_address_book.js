jQuery(document).ready(function () {


    jQuery(".pdf").click(function () {
        var path = 'name_card_pdf/' + this.id;
        window.open(path, 'Pdf', 'width=700, height=380');

    });

    jQuery(".clipboard_add").click(function () {

        var text = jQuery('#name').html() + ' \n' 
        + jQuery('#address1').html() + ' \n' 
        + jQuery('#address2').html() + ' \n'
        + jQuery('#state').html() + ' \n'
        + jQuery('#postcode').html() + ' '
        + jQuery('#city').html() + ' \n'
        + jQuery('#country').html() + ' \n'
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                for (let i = 0; i < 2; i++) {
                    jQuery('#copy' + id).fadeTo('fast', 1.0).fadeTo('fast', 0);
                }
            }, function(err) {
                console.error('Could not copy text: ', err);
                fallbackCopyTextToClipboard(text, id);
            });
        } else {
            fallbackCopyTextToClipboard(text,'.copyAdd');
        }
    });

    jQuery(".clipboard_name").click(function () {
        var id = this.id;
        var text = jQuery('#salutation' + id).html() + ' ' 
            + jQuery('#card' + id).html() + ', \n' 
            + jQuery('#name').html() + '\n' 
            + jQuery('#address1').html() + '\n' 
            + jQuery('#address2').html() + '\n'
            + jQuery('#state').html() + '\n'
            + jQuery('#postcode').html() + ' '
            + jQuery('#city').html() + '\n'
            + jQuery('#country').html();
    
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                for (let i = 0; i < 2; i++) {
                    jQuery('#copy' + id).fadeTo('fast', 1.0).fadeTo('fast', 0);
                }
            }, function(err) {
                console.error('Could not copy text: ', err);
                fallbackCopyTextToClipboard(text, id);
            });
        } else {
            fallbackCopyTextToClipboard(text,'#copy' + id);
        }
    });
    
    function fallbackCopyTextToClipboard(text, id) {
        var $body = document.getElementsByTagName('body')[0];
        var $tempInput = document.createElement('textarea');
        $body.appendChild($tempInput);
        $tempInput.value = text;
        $tempInput.select();
        document.execCommand('copy');
        $body.removeChild($tempInput);
        for (let i = 0; i < 2; i++) {
            jQuery(id).fadeTo('fast', 1.0).fadeTo('fast', 0);
        }
    }
});