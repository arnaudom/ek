jQuery(document).ready(function(){       
       
  jQuery('.calculate').on('click, change', function(){
    
    var sum_cash = 0;
    var sum_close = 0;        
      for (i = 1; i <= jQuery( ".calculate" ).length; i++) { 
                if(jQuery('#box'+i).prop('checked')) {
                
                  var value = parseFloat(jQuery('#box'+i).val());
                  sum_cash = sum_cash + value
                  var value = parseFloat(jQuery('#box2'+i).val());
                  sum_close = sum_close + value
                }

      }

        var balance = (Math.round(sum_cash*100)/100).toFixed(2);
        var close = (Math.round(sum_close*100)/100).toFixed(2);
        jQuery("#grandtotal").html(addCommas(balance));
        jQuery("#grandtotal2").html(addCommas(close));
        
        var expenses = parseInt(jQuery('#valueexpenses').val());
        if(jQuery('#amortization').prop('checked')) {
            var amortization = parseInt(jQuery('#valueamortization').val());
            expenses = expenses - amortization;
        }
        
        var ratio1 = addCommas((Math.round(sum_cash/expenses)).toFixed(2));
        var ratio2 = addCommas((Math.round(sum_close/expenses)).toFixed(2));
        jQuery("#ratio1").html(ratio1);
        jQuery("#ratio2").html(ratio2);

  });
  
  function addCommas(nStr){
	nStr += '';
	x = nStr.split('.');
	x1 = x[0];
	x2 = x.length > 1 ? '.' + x[1] : '';
	var rgx = /(\d+)(\d{3})/;
	while (rgx.test(x1)) {
		x1 = x1.replace(rgx, '$1' + ',' + '$2');
	}
	return x1 + x2;
    }
});


