function reload_images(id) {

jQuery.ajax({
  dataType: "json",
  url: "reload_images_ajax",
  data: {id:id},
  success: function (data) { 
      jQuery('#product_images').html(data.li);
      
  }
  });
}