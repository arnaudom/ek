(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.ekOperationPerformance = {
    attach: function (context, settings) {
      //$('#op-project-table .op-select-checkbox', context).once('op-checkbox').on('change', function () {
      $(once('select-click-event', '#op-project-table .op-select-checkbox', context)).on('change', function (event) {
        var totalRevenue = 0;
        var totalCost = 0;

        $('#op-project-table .op-select-checkbox:checked').each(function () {
          totalRevenue += parseFloat($(this).data('revenue')) || 0;
          totalCost += parseFloat($(this).data('cost')) || 0;
        });

        $('#op-selected-revenue').text(totalRevenue.toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }));
        $('#op-selected-cost').text(totalCost.toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }));
      });
    }
  };

})(jQuery, Drupal);