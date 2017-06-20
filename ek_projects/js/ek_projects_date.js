Drupal.behaviors.ek_projects_date = {
        attach: function (context, settings) {

        jQuery('.form-date').datepicker({dateFormat: 'yy-mm-dd'});
        jQuery('#ui-datepicker-div').css('z-index', 9999); 
    
        } //attach
}; //bahaviors