(function ($, Drupal, drupalSettings) {

  jQuery(function() {
  jQuery('#expenses').click(function() {
      jQuery(".data1").toggle("fast");
  });
  jQuery('#income').click(function() {
      jQuery(".data2").toggle("fast");
  });  
  
   jQuery(".editinline").on("change",function(){
       var reference = this.id;
       var value = this.value;
       jQuery.ajax({
                     type: "POST",
                     url: 'budgeting/update',
                     data:{ 'reference' : reference, 'value' : value},
                     async: false,
                     success: function (data) { 
                         for(i=0;i<3;i++) {
                            jQuery("#"+reference).fadeTo('fast', 0.5).fadeTo('fast', 1.0);
                           }
                     }
        });
        
        sumrow(reference);
   });  
  
  
  
  });
  


})(jQuery, Drupal, drupalSettings);

function sumrow(reference) {
  
  var ref = reference.split('-');

  //input total by row
  var row = ref[0] + '-total';
  var cell = ref[0] + '-' + ref[1] + '-' + ref[2] + '-'; 
  var total = 0;
  
   for ( i=1; i < 13; i++ ) {
      
      var amount =  parseFloat(jQuery("#"+ cell + i).val());
      total = total + amount;

   }
   jQuery("#"+ row).html(total.toFixed(2));
   
   //input sub total by month
   var total = 0;
   var classe = ref[0].slice(0, -3);
   var col = classe + '-' + ref[3] + '-total';
   
   var c = classe + '00';

   for ( i=1; i < 10; i++ ) {
      var cell = c + i + '-' + ref[1] + '-' + ref[2] + '-' + ref[3]; 
      var amount =  parseFloat(jQuery("#"+ cell).val());
      if(!isNaN(amount)) total = total + amount;   
   
   }
   
   var c = classe + '0';
   for ( i=10; i < 100; i++ ) {
      var cell = c + i + '-' + ref[1] + '-' + ref[2] + '-' + ref[3]; 
      var amount =  parseFloat(jQuery("#"+ cell).val());
      if(!isNaN(amount)) total = total + amount;   
   
   }
   
   var c = classe ;
   for ( i=100; i < 1000; i++ ) {
      var cell = c + i + '-' + ref[1] + '-' + ref[2] + '-' + ref[3]; 
      var amount =  parseFloat(jQuery("#"+ cell).val());
      if(!isNaN(amount)) total = total + amount;   
   
   }
   
   jQuery("#"+ col).html(total.toFixed(2));
   
   //input sub total row
    var row = classe + '-subtotal';
    var total = 0;
    
     for ( i=1; i < 13; i++ ) {
        var cell = classe + '-' + i + '-total'; 
        var amount =  parseFloat(jQuery("#"+ cell).html());
        if(!isNaN(amount)) total = total + amount;

     }
     jQuery("#"+ row).html(total.toFixed(2));
     
    //input grand total by month / expenses
       var totale = 0;
       var col = '6-' + ref[3] + '-grandtotal';
       for ( i=1; i < 10; i++ ) {
          var cell = '6' + i + '-' + ref[3] + '-total'; 
          var amount =  parseFloat(jQuery("#"+ cell).html());
          if(!isNaN(amount)) totale = totale + amount;   
       
       }   
       for ( i=1; i < 10; i++ ) {
          var cell = '5' + i + '-' + ref[3] + '-total'; 
          var amount =  parseFloat(jQuery("#"+ cell).html());
          if(!isNaN(amount)) totale = totale + amount;   
       
       }  
       jQuery("#"+ col).html(totale.toFixed(2));

   //input expense grand total row   
    var total = 0;
     for ( i=1; i < 13; i++ ) {
        var cell = '6-' + i + '-grandtotal'; 
        var amount =  parseFloat(jQuery("#"+ cell).html());
        if(!isNaN(amount)) total = total + amount;

     }
      jQuery("#sumexpense").html(total.toFixed(2));
    
    
    //input grand total by month / income
       var totali = 0;
       var col = '4-' + ref[3] + '-grandtotal';
       for ( i=1; i < 10; i++ ) {
          var cell = '4' + i + '-' + ref[3] + '-total'; 
          var amount =  parseFloat(jQuery("#"+ cell).html());
          if(!isNaN(amount)) totali = totali + amount;   
       
       }   
 
       jQuery("#"+ col).html(totali.toFixed(2)); 

   //input income grand total row   
    var total = 0;
     for ( i=1; i < 13; i++ ) {
        var cell = '4-' + i + '-grandtotal'; 
        var amount =  parseFloat(jQuery("#"+ cell).html());
        if(!isNaN(amount)) total = total + amount;

     }
      jQuery("#sumincome").html(total.toFixed(2));
      
      
    //input balance by month
       var balance = totali - totale;
       jQuery("#balance-" + ref[3]).html(balance.toFixed(2));
       
       if(balance < 0 ) {
           jQuery("#balance-" +ref[3]).addClass('red')
       } else {
           jQuery("#balance-" +ref[3]).removeClass('red')
       }
       
   //input balance total row   
    var total = 0;
    
     for ( i=1; i < 13; i++ ) {
        var cell = 'balance-' + i ; 
        var amount =  parseFloat(jQuery("#"+ cell).html());
        if(!isNaN(amount)) total = total + amount;

     }
     jQuery("#balancetotal").html(total.toFixed(2));
      if(total < 0 ) {
           jQuery("#balancetotal").addClass('red')
     } else {
           jQuery("#balancetotal").removeClass('red')
     }
}