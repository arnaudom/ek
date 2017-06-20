jQuery(document).ready(function(){


jQuery( ".pdf" ).click(function() {
  
 
   var path = 'name_card_pdf/' + this.id;
    window.open(path,'Pdf', 'width=700, height=380');
  
  });
 
});