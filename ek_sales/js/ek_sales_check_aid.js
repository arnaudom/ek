jQuery(document).ready(function(){


jQuery( "#edit-currency" ).change(function() {
  
 
   var term = jQuery('#edit-currency').val();
   var sn = jQuery.ajax({
                    dataType: "json",
                    url: "../../../check_aid_ajax",
                    data: {term:term},
                    success: function (data) { 
                       
                        if(data.aid == 1) {
                          jQuery('#alert').html(data.alert)
                          jQuery('#alert').addClass('messages messages--warning');
                        } else {
                          jQuery('#alert').removeClass('messages messages--warning');
                          jQuery('#alert').html('')
                        }
                        
                    }
                    });

  
   
   
    
  
  });
 
});