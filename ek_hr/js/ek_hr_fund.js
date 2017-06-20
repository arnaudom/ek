(function ($, Drupal, drupalSettings) {

  jQuery(function() {

  
   jQuery(".editinline").on("change",function(){
       var table = jQuery('#table').val();
       var reference = this.id;
       var value = this.value;
       jQuery.ajax({
                     type: "POST",
                     url: 'parameters-fund/edit',
                     data:{ 'table' : table, 'reference' : reference, 'value' : value},
                     async: false,
                     success: function (data) { 
                         if(data.data) {
                            for(i=0;i<3;i++) {
                               jQuery("#"+reference).fadeTo('fast', 0.5).fadeTo('fast', 1.0);
                            }
                         }
                     }
        });

   });  
  
  
  
  });
  


})(jQuery, Drupal, drupalSettings);

