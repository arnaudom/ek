(function ($, Drupal, drupalSettings) {


  Drupal.behaviors.ek_hr_autocomplete = {
    attach: function (context, settings) {

      jQuery('#hr-search-form').keyup(function() {
      
        var term = jQuery('#hr-search-form').val();

        jQuery.ajax({
          dataType: "json",
          url: drupalSettings.path.baseUrl + "human-resources/e/autocomplete" ,
          data: { option: "image", q: term },
          success: function (data) { 
              var content = '';
              var i = 0;
              for(;data[i];) {
                  
                  var editUrl = "<a class='hr_image-link' href='" + drupalSettings.path.baseUrl 
                          + "human-resources/view-employee/" + data[i]['id'] + "'>" + data[i]['picture'] + "</a>";                  
                  content += "<p>" + editUrl + "  " + data[i]['name'] + "</p>";
                  i++;
              }
              
              jQuery('#hr-search-result').html(content);

          }
          });      

      });   
         

    
    
    } //attach
    

  }; //bahaviors

})(jQuery, Drupal, drupalSettings);
