(function ($, Drupal, drupalSettings) {


  Drupal.behaviors.ek_hr_forms = {
    attach: function (context, settings) {

      jQuery('.deleteButton').click(function() {
      
      if(confirm("Confirm")) {
          
        if (settings.hr == 'form') var path = 'delete-form';
        if (settings.hr == 'payslip') var path = 'delete-payslip';
        jQuery.ajax({
          dataType: "json",
          url: path + "/" + this.id ,
          success: function (data) { 
          
              jQuery('#r-' + data.id).remove();

          }
          });      
        }
      });   
         

    
    
    } //attach
    

  }; //bahaviors

})(jQuery, Drupal, drupalSettings);