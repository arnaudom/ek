
/*
* COMPILE JOURNAL ENTRY  TOTAL DEBIT/CREDIT
*/
jQuery(document).ready(function(){

    jQuery('.amount').blur( function(){

      var i=jQuery("#rows").val();
     
      
      
      var debit = parseFloat( jQuery("#debit1").val().replace(",","") );
      var credit = parseFloat( jQuery("#credit1").val().replace(",","") );

      
      if (i>1) {
        for (var n=2;n<=i;n++) {
        
          if(jQuery("#debit"+n).val() != '') {
          debit = debit+parseFloat( jQuery("#debit"+n).val().replace(",","") );
          }
          if(jQuery("#credit"+n).val() != '') {
          credit = credit+parseFloat( jQuery("#credit"+n).val().replace(",","") );
          }
        }
     }
     
     debit = debit.toFixed(2);
     credit = credit.toFixed(2);     
     var dt = currencyFormat(debit, ',');
     var ct = currencyFormat(credit, ',');
     jQuery("#totald").html(dt);
     jQuery("#totalc").html(ct); 
     
     if (debit != credit) {
      jQuery("#totald").addClass('delete');
      jQuery("#totalc").addClass('delete');
      jQuery("#totald").removeClass('record');
      jQuery("#totalc").removeClass('record');
      
     } else {
      jQuery("#totald").addClass('record');
      jQuery("#totalc").addClass('record');
      jQuery("#totald").removeClass('delete');
      jQuery("#totalc").removeClass('delete');     
     }
     
     
    });


  jQuery('.sum').click( function(){
      
      var c = jQuery( ".sum" ).length;
      console.log(c);
  
  });
    
});

function currencyFormat(amount, delimiter) { 

    //var delimiter = ","; // replace comma if desired
    amount = new String(amount);
    var a = amount.split('.',2)
    var d = a[1];
    var i = parseInt(a[0]);
    if(isNaN(i)) { return ''; }
    var minus = '';
    if(i < 0) { minus = '-'; }
    i = Math.abs(i);
    var n = new String(i);
    var a = [];
    while(n.length > 3)
    {
        var nn = n.substr(n.length-3);
        a.unshift(nn);
        n = n.substr(0,n.length-3);
    }
    if(n.length > 0) { a.unshift(n); }
    n = a.join(delimiter);
    if(d.length < 1) { amount = n; }
    else { amount = n + '.' + d; }
    amount = minus + amount;
    return amount;

}

