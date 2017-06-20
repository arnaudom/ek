(function ($, Drupal, drupalSettings) {


  Drupal.behaviors.ek_sales = {
    attach: function (context, settings) {

      jQuery('#sales_docs').html('<IMG src="../../modules/ek_sales/css/images/loading.gif">');
      jQuery.ajax({
        dataType: "json",
        url: "../../ek_sales/load_documents",
        data: {abid: settings.abid },
        success: function (data) { 
            jQuery('#sales_docs').html(data.list);
            addajax();
        }
        });
    
    
    } //attach
    

  }; //bahaviors


})(jQuery, Drupal, drupalSettings);

function addajax() {
        // Bind Ajax behaviors to all items showing the class.
        jQuery('.use-ajax:not(.ajax-processed)').addClass('ajax-processed').each(function ()    {
        
          var element_settings = {};
          // Clicked links look better with the throbber than the progress bar.
          element_settings.progress = { 'type': 'throbber' };
     
          // For anchor tags, these will go to the target of the anchor rather
          // than the usual location.
          if (jQuery(this).attr('href')) {
            element_settings.url = jQuery(this).attr('href');
            element_settings.event = 'click';
          }
          var base = jQuery(this).attr('id');
          element_settings.base = base;
          element_settings.element = this;
          
          Drupal.ajax[base] = new Drupal.ajax(element_settings);
        });    
      }