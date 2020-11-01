(function ($, Drupal, drupalSettings) {


  Drupal.behaviors.ek_abook_autocomplete = {
    attach: function (context, settings) {

      jQuery('#abook-search-form').keyup(function() {
      
        var term = jQuery('#abook-search-form').val();

        jQuery.ajax({
          dataType: "json",
          url: drupalSettings.path.baseUrl + "look_up_contact_ajax" ,
          data: { option: "image", term: term },
          success: function (data) { 
              var content = '';
              var i = 0;
              for(;data[i];) {
                  
                  var editUrl = "<a class='abook_image-link' href='" + drupalSettings.path.baseUrl 
                          + "address_book/" + data[i]['id'] + "'>" + data[i]['picture'] + "</a>";                  
                  content += "<p>" + editUrl + "  " + data[i]['name'] + " (" + data[i]['type'] +  ")</p>";
                  i++;
              }
              
              jQuery('#abook-search-result').html(content);

          }
          });      

      });   
  
    } //attach
    

  }; //bahaviors

})(jQuery, Drupal, drupalSettings);
