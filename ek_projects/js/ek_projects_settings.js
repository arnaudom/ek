(function ($, Drupal, drupalSettings) {


  Drupal.behaviors.serialset = {
    attach: function (context, settings) {
        
        var element = ['', 'MYCO-', 'TYPE-', 'CID-', 'MM_YY-', 'ABC-', '123']
        
        jQuery('#edit-first').change(function() {
            var insert = element[this.value];
            $('#e1').html(insert);
        });
        
        jQuery('#edit-second').change(function() {
            var insert = element[this.value];
            $('#e2').html(insert);
        });	    
        jQuery('#edit-third').change(function() {
            var insert = element[this.value];
            $('#e3').html(insert);
        });
        jQuery('#edit-fourth').change(function() {
            var insert = element[this.value];
            $('#e4').html(insert);
        });
        jQuery('#edit-fifth').change(function() {
            var insert = element[this.value];
            $('#e5').html(insert);
        }); 
        
        jQuery('#edit-last').change(function() {
            var insert = element[this.value];
            $('#e6').html(insert);
        });        
    } //attach
    

  }; //bahaviors


})(jQuery, Drupal, drupalSettings);