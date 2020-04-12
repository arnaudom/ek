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
        + jQuery('#postcode').html() + ' \n'
        + jQuery('#city').html() + ' \n'
        + jQuery('#country').html() + ' \n'
        + jQuery('#regname').html() + ' \n';
        var $body = document.getElementsByTagName('body')[0];
        var $tempInput = document.createElement('INPUT');
        $body.appendChild($tempInput);
        $tempInput.setAttribute('value', text)
        $tempInput.select();
        document.execCommand('copy');
        $body.removeChild($tempInput);
        for (i = 0; i < 2; i++) {
            jQuery('.copyAdd').fadeTo('fast', 1.0).fadeTo('fast', 0);
            
        }
        

        
    });
    
    jQuery(".clipboard_name").click(function () {
        
        var id = this.id;
        var text = jQuery('#salutation' + id).html() + ' \n' 
        + jQuery('#card' + id).html() + ', \n' 
        + jQuery('#name').html() + ' \n' 
        + jQuery('#address1').html() + ' \n' 
        + jQuery('#address2').html() + ' \n'
        + jQuery('#state').html() + ' \n'
        + jQuery('#postcode').html() + ' \n'
        + jQuery('#city').html() + ' \n'
        + jQuery('#country').html() + ' \n';

        var $body = document.getElementsByTagName('body')[0];
        var $tempInput = document.createElement('INPUT');
        $body.appendChild($tempInput);
        $tempInput.setAttribute('value', text)
        $tempInput.select();
        document.execCommand('copy');
        $body.removeChild($tempInput);
        for (i = 0; i < 2; i++) {
            jQuery('#copy' + this.id).fadeTo('fast', 1.0).fadeTo('fast', 0);
        }
        
        
    });

});