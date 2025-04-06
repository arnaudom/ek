(function ($, Drupal, drupalSettings) {


  Drupal.behaviors.ek_abook_autocomplete = {
    attach: function (context, settings) {

      $('#abook-search-form').keyup(function() {
        triggerSearch();
      });

      $('#filter_client, #filter_supplier').click(function() {
        triggerSearch();
      });

      if ($('#abook-search-form').val() !== null && $('#abook-search-form').val() !== '') {
        triggerSearch();
      }

      function triggerSearch() {
        var term = $('#abook-search-form').val();
        var client = $('#filter_client').prop('checked');
        var supplier = $('#filter_supplier').prop('checked');
        jQuery.ajax({
          dataType: "json",
          url: drupalSettings.path.baseUrl + "look_up_contact_ajax" ,
          data: { option: "image", term: term , client: client, supplier: supplier},
          success: function (data) { 
              var content = '';
              var i = 0;
              for(;data[i];) {
                  
                  var editUrl = "<a class='abook_image-link' href='" + drupalSettings.path.baseUrl 
                          + "address_book/" + data[i]['id'] + "'>" + data[i]['picture'] + "</a>";                  
                  content += "<p>" + editUrl + "  " + data[i]['name'] + " (" + data[i]['type'] +  ")</p>";
                  i++;
              }
              
              $('#abook-search-result').html(content);

          }
          });      

      };   
  
    } //attach
    

  }; //bahaviors

})(jQuery, Drupal, drupalSettings);
