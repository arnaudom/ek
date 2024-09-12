(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.checkboxSum = {
      attach: function (context, settings) {
        $('.select-checkbox', context).on('change', function() {
          var total = 0;
          $('.select-checkbox:checked').each(function() {
            total += parseFloat($(this).data('value')) || 0;
          });
          
          // Round to two decimal places
          total = Math.round(total * 100) / 100;
          var formattedTotal = formatNumber(total);
          // Update the total display
          $('#checkbox-sum-total').text(formattedTotal);
          
          // Trigger a custom event with the new total
          $(document).trigger('checkboxSumUpdated', [total]);
        });
      }
    };
})(jQuery, Drupal, drupalSettings);

function formatNumber(num) {
    return num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
  }