(function ($, Drupal, drupalSettings) {

  jQuery(function() {
  jQuery('#purchases').click(function() {
      jQuery(".data0").toggle("fast");
  });      
  jQuery('#expenses').click(function() {
      jQuery(".data1").toggle("fast");
  });
  jQuery('#income').click(function() {
      jQuery(".data2").toggle("fast");
  });  
  
  });

})(jQuery, Drupal, drupalSettings);