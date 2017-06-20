jQuery(document).ready(function() {
    jQuery("#help_content").hide(); 
});

jQuery("#help").on("click",function(){

    jQuery("#help_content").toggle();


}); 