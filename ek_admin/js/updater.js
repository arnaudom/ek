(function ($, Drupal, drupalSettings, once) {

  /**
   * Load the company documents list into the #company_docs container.
   *
   * Shared by the page behavior and the dialog:afterclose handler so the list
   * reflects edits (comment / folder) made through the off_canvas form.
   */
  function loadCompanyDocs() {
    if (!jQuery('#company_docs').length) {
      return;
    }
    jQuery.ajax({
      dataType: "json",
      url: drupalSettings.path.baseUrl + "ek_admin/load_documents",
      data: {coid: drupalSettings.coid },
      success: function (data) {
          jQuery('.loading').remove();
          jQuery('#company_docs').html(data.list);
          addajax();
      }
    });
  }

  Drupal.behaviors.ek_admin = {
    attach: function (context, settings) {
      loadCompanyDocs();

      // Collapse / expand a document folder group.
      // Delegated on the container because its content is replaced by the ajax
      // call above; bound with the non deprecated once() API.
      $(once('ek-admin-folder', '#company_docs', context))
          .on('click', '.folder-title', function () {
              $(this).closest('tr.folder').nextUntil('tr.folder').toggle('fast');
              $(this).toggleClass('folder-closed');
          });

    } //attach
    

  }; //bahaviors

  // Refresh the document list when a dialog (off_canvas / modal) is closed so
  // edits made through the document meta form are reflected in the list.
  $(document).on('dialog:afterclose', function () {
    loadCompanyDocs();
  });

    /* delete files toggle
     * 
     */
    jQuery(function () {
        jQuery('.hideFile').click(function () {
            if (jQuery('.hideFile').hasClass('show-ico'))
                jQuery('.hide').hide('fast');
            if (jQuery('.hideFile').hasClass('hide-ico'))
                jQuery('.hide').show('fast');
            jQuery('.hideFile').toggleClass('show-ico hide-ico');

        });
    });
})(jQuery, Drupal, drupalSettings, once);

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
          
          // Read the data-dialog-* attributes so a link can open a modal /
          // off_canvas dialog (same settings used by Drupal.ajax.bindAjaxLinks).
          element_settings.dialogType = jQuery(this).data('dialog-type');
          element_settings.dialogRenderer = jQuery(this).data('dialog-renderer');
          element_settings.dialog = jQuery(this).data('dialog-options');
          
          var base = jQuery(this).attr('id');
          element_settings.base = base;
          element_settings.element = this;
          
          Drupal.ajax[base] = new Drupal.ajax(element_settings);
        });    
      }