/**
 * @file ek_sales_aging_charts.js
 *
 * Drupal behavior for the aging analytics dashboard.
 * Reads drupalSettings.agingAnalytics and renders:
 *   - Morris Bar chart  (#aging-bar-chart)
 *   - Morris Donut – invoices  (#aging-donut-inv)
 *   - Morris Donut – purchases (#aging-donut-pur)
 *   - Collapsible detail table (#aging-detail-table-wrap)
 *
 * Depends on: ek_admin/ek_admin_charts (Morris.js + Raphael)
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  // -------------------------------------------------------------------------
  // Color constants (match the CSS classes used in the KPI cards)
  // -------------------------------------------------------------------------
  var COLORS = {
    overdue:    ['#c0392b', '#e74c3c', '#e57e73', '#ed9f99', '#f5c6c3'],
    soon:       ['#d35400', '#e67e22'],
    future:     ['#27ae60', '#2ecc71', '#82e0aa'],
    receivable: '#2980b9',
    payable:    '#c0392b',
    donutInv:   ['#2980b9', '#e67e22', '#27ae60'],
    donutPur:   ['#a93226', '#ca6f1e', '#1e8449'],
  };

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  /**
   * Format a number as currency string.
   * @param {number} value
   * @param {string} currency
   * @return {string}
   */
  function fmt(value, currency) {
    return (currency ? currency + ' ' : '') +
      parseFloat(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
  }

  /**
   * Build the HTML detail table for one dataset (invoices or purchases).
   * @param {Array}  buckets   - tableRows from drupalSettings
   * @param {string} dataKey   - 'invoices' | 'purchases'
   * @param {string} currency
   * @return {string} HTML string
   */
  function buildDetailTable(buckets, dataKey, currency) {
    var html = '';

    $.each(buckets, function (i, bucket) {
      var rows = bucket[dataKey];
      if (!rows || rows.length === 0) {
        return; // skip empty buckets
      }

      var bucketTotal = 0;
      $.each(rows, function (j, r) { bucketTotal += parseFloat(r.baseValue); });

      var typeClass = 'aging-bucket--' + bucket.type;

      html += '<div class="aging-bucket ' + typeClass + '">';
      html += '<div class="aging-bucket__header" data-toggle="aging-rows-' + dataKey + '-' + i + '">';
      html += '<span class="aging-bucket__label">' + bucket.label + '</span>';
      html += '<span class="aging-bucket__count">' + rows.length + ' ' + Drupal.t('item(s)') + '</span>';
      html += '<span class="aging-bucket__total">' + fmt(bucketTotal, currency) + '</span>';
      html += '<span class="aging-bucket__toggle-icon">&#9660;</span>';
      html += '</div>';

      html += '<div class="aging-bucket__rows" id="aging-rows-' + dataKey + '-' + i + '">';
      html += '<table class="aging-table">';
      html += '<thead><tr>';
      html += '<th>' + Drupal.t('Reference') + '</th>';
      html += '<th>' + Drupal.t('Client') + '</th>';
      html += '<th>' + Drupal.t('Due date') + '</th>';
      html += '<th>' + Drupal.t('Days') + '</th>';
      html += '<th class="aging-table__num">' + Drupal.t('Amount') + '</th>';
      html += '<th class="aging-table__num">' + Drupal.t('Tax') + '</th>';
      html += '<th class="aging-table__num">' + Drupal.t('Base value') + '</th>';
      html += '</tr></thead><tbody>';

      $.each(rows, function (j, r) {
        var rowClass = r.age > 90 ? 'aging-row--critical'
                     : r.age > 30 ? 'aging-row--warning'
                     : r.age > 0  ? 'aging-row--overdue'
                     : '';
        var statusSpan = r.status
          ? ' <span class="aging-partial">(' + r.status + ')</span>'
          : '';
        var taxCell = r.tax > 0
          ? fmt(r.tax, r.currency)
          : '—';

        html += '<tr class="' + rowClass + '">';
        html += '<td>' + r.numberLink + statusSpan + '</td>';
        html += '<td>' + r.client + '</td>';
        html += '<td>' + r.due + '</td>';
        html += '<td class="' + (r.age > 0 ? 'aging-days--overdue' : 'aging-days--future') + '">'
              + (r.age > 0 ? '+' + r.age : r.age) + '</td>';
        html += '<td class="aging-table__num">' + fmt(r.remaining, r.currency) + '</td>';
        html += '<td class="aging-table__num">' + taxCell + '</td>';
        html += '<td class="aging-table__num">' + fmt(r.baseValue, currency) + '</td>';
        html += '</tr>';
      });

      html += '</tbody></table>';
      html += '</div>'; // .aging-bucket__rows
      html += '</div>'; // .aging-bucket
    });

    return html || '<p class="aging-empty">' + Drupal.t('No outstanding items.') + '</p>';
  }

  // -------------------------------------------------------------------------
  // Drupal behavior
  // -------------------------------------------------------------------------
  Drupal.behaviors.agingAnalyticsCharts = {
    attach: function (context, settings) {

      // Guard: run only once and only when settings are present
      if (!settings.agingAnalytics) {
        return;
      }

      var cfg      = settings.agingAnalytics;
      var currency = cfg.baseCurrency || '';

      // ---- Bar chart -------------------------------------------------------
      $('#aging-bar-chart', context).each(function () {
        // Morris Bar needs string xkeys; use index as key
        var barData = $.map(cfg.barData, function (d, i) {
          return {
            period:     d.period,
            receivable: d.receivable,
            payable:    d.payable
          };
        });

        Morris.Bar({
          element:     'aging-bar-chart',
          data:        barData,
          xkey:        'period',
          ykeys:       ['receivable', 'payable'],
          labels:      [Drupal.t('Receivable'), Drupal.t('Payable')],
          barColors:   [COLORS.receivable, COLORS.payable],
          hideHover:   'auto',
          xLabelAngle: 40,
          gridTextSize: 11,
          resize:      true
        });
      });

      // ---- Donut — invoices -----------------------------------------------
      $('#aging-donut-inv', context).each(function () {
        var invChart = Morris.Donut({
          element: 'aging-donut-inv',
          data:    cfg.invDonut,
          colors:  COLORS.donutInv,
          resize:  true
        });
        // Force redraw after layout settles so SVG fills its container
        setTimeout(function () { invChart.redraw(); }, 50);
      });

      // ---- Donut — purchases ----------------------------------------------
      $('#aging-donut-pur', context).each(function () {
        var purChart = Morris.Donut({
          element: 'aging-donut-pur',
          data:    cfg.purDonut,
          colors:  COLORS.donutPur,
          resize:  true
        });
        setTimeout(function () { purChart.redraw(); }, 50);
      });

      // ---- Detail table (tab switching) -----------------------------------
      var $wrap   = $('#aging-detail-table-wrap', context);
      var $tabs   = $('.aging-tab', context);
      var active  = 'invoices';

      function renderTable(target) {
        $wrap.html(buildDetailTable(cfg.tableRows, target, currency));

        // Collapsible bucket rows
        $wrap.find('.aging-bucket__header').on('click', function () {
          var targetId = $(this).data('toggle');
          var $rows    = $('#' + targetId);
          var $icon    = $(this).find('.aging-bucket__toggle-icon');
          $rows.toggleClass('aging-bucket__rows--collapsed');
          $icon.html($rows.hasClass('aging-bucket__rows--collapsed') ? '&#9658;' : '&#9660;');
        });
      }

      // Render default tab
      renderTable(active);

      $tabs.on('click', function () {
        var target = $(this).data('target');
        if (target === active) {
          return;
        }
        active = target;
        $tabs.removeClass('aging-tab--active');
        $(this).addClass('aging-tab--active');
        renderTable(target);
      });
    }
  };

}(jQuery, Drupal, drupalSettings));