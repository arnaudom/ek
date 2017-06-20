(function ($, Drupal, drupalSettings) {


  Drupal.behaviors.ek_products_autocomplete = {
    attach: function (context, settings) {

      jQuery('#product-search-form').keyup(function() {
      
        var term = jQuery('#product-search-form').val();

        jQuery.ajax({
          dataType: "json",
          url: drupalSettings.path.baseUrl + "look_up_item_ajax/0" ,
          data: { option: "image", q: term },
          success: function (data) { 
              var content = '';
              var i = 0;
              for(;data[i];) {
                  
                  var editUrl = "<a class='product_image-link' href='" + drupalSettings.path.baseUrl 
                          + "item/" + data[i]['id'] + "'>" + data[i]['picture'] + "</a>";                  
                  content += "<p>" + editUrl + "  " + data[i]['name'] + "</p>";
                  i++;
              }
              
              jQuery('#product-search-result').html(content);

          }
          });      

      });   
         

    
    
    } //attach
    

  }; //bahaviors

})(jQuery, Drupal, drupalSettings);
