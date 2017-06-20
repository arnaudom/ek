

function open_dialog(id) {
   jQuery( "#"+id).dialog( "open" );
  };
  
jQuery(function() {

  jQuery( ".dialog" ).dialog({
    autoOpen: false,
    show: {
    effect: "blind",
    duration: 1000
    },
    hide: {
    effect: "explode",
    duration: 1000
    }
  });
});
