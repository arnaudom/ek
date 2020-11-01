(function ($, Drupal, drupalSettings) {


  Drupal.behaviors.ek_sales_search_doc = {
    attach: function (context, settings) {

      jQuery('#doc-search-form').keyup(function() {
      
        var term = jQuery('#doc-search-form').val();
        jQuery.ajax({
          dataType: "json",
          url: drupalSettings.path.baseUrl + "ek_sales-search-doc" ,
          data: { q: term },
          success: function (data) { 
              var content = '';
              var i = 0;
              for(;data[i];) {
                  
                  if(data[i]['share'] == 1) {
                     var editUrl = "<a href='" + data[i]['url'] + "'>" + data[i]['filename'] + "</a>";      
                  } else {
                     var editUrl =  "<i>" + data[i]['filename'] + "</i>";
                  }
                  content += "<li><div class='file'>" + editUrl + " - " + data[i]['date'] + " - " + data[i]['size'] + "</div>"                          
                  content += "<div class='info'>" + data[i]['ab'] + "  [" + data[i]['folder'] + "]</div></li>";
                  i++;
              }
              
              jQuery('#doc-search-result').html(content);

          }
          });      

      });   
      
    } //attach

  }; //bahaviors

})(jQuery, Drupal, drupalSettings);


