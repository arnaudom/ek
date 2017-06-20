
  function number_format(fieldId, milSep, decSep, e) {
  
    var sep = 0;
      var key = '';
      var i = j = 0;
      var len = len2 = 0;
      var strCheck = '-0123456789';
      var aux = aux2 = '';
      var whichCode = (window.Event) ? e.which : e.keyCode;

      if (whichCode == 13) return true;  // Enter
      if (whichCode == 8) return true;  // Delete
      key = String.fromCharCode(whichCode);  // Get key value from key code
      if (strCheck.indexOf(key) == -1) return false;  // Not a valid key
      len = fieldId.value.length;
      for(i = 0; i < len; i++)
      if ((fieldId.value.charAt(i) != '0') && (fieldId.value.charAt(i) != decSep)) break;
      aux = '';
      for(; i < len; i++)
      if (strCheck.indexOf(fieldId.value.charAt(i))!=-1) aux += fieldId.value.charAt(i);
      aux += key;
      len = aux.length;
      if (len == 0) fieldId.value = '';
      if (len == 1) fieldId.value = '0'+ decSep + '0' + aux;
      if (len == 2) fieldId.value = '0'+ decSep + aux;
      if (len > 2) {
        aux2 = '';
        for (j = 0, i = len - 3; i >= 0; i--) {
          if (j == 3) {
            aux2 += milSep;
            j = 0;
          }
          aux2 += aux.charAt(i);
          j++;
        }
        fieldId.value = '';
        len2 = aux2.length;
        for (i = len2 - 1; i >= 0; i--)
        fieldId.value += aux2.charAt(i);
        fieldId.value += decSep + aux.substr(len - 2, len);
      }
      return false;  
      
  
  
}