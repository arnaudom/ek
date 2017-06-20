jQuery(
  function() {
    var link = jQuery('#view').attr('src');
    jQuery('.view_popup').click(function(event) {
           event.preventDefault();
           event.stopPropagation();
           window.open(link, '_blank');
       });	
    jQuery('.view_popup').css( 'cursor', 'pointer' );
  });

