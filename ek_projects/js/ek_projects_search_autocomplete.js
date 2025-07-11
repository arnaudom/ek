(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.ek_proejects_search_autocomplete = {
    attach: function (context, settings) {
      // Client-side cache to store search results
      const searchCache = new Map();

      // Custom debounce function
      function debounce(func, wait) {
        let timeout;
        return function (...args) {
          clearTimeout(timeout);
          timeout = setTimeout(() => func.apply(this, args), wait);
        };
      }

      // Debounced search function
      const performSearch = debounce(function (term) {
        // Minimum term length check
        if (term.length < 3) {
          $('#list_search_result').html(''); // Clear results if term is too short
          return;
        }

        // Check cache
        if (searchCache.has(term)) {
          displayResults(searchCache.get(term));
          return;
        }

        // AJAX request
        $.ajax({
          dataType: 'json',
          url: drupalSettings.path.baseUrl + 'look_up_main_projects/doc/0',
          data: { q: term },
          success: function (data) {
            // Store result in cache
            searchCache.set(term, data);
            displayResults(data);
          },
        });
      }, 300); // 300ms debounce delay

      // Display search results
      function displayResults(data) {
        let content = '';
        let i = 0;
        for (; data[i];) {
          content +=
            "<li id='" +
            data[i]['id'] +
            "'>" +
            "<div class='file'>" +
            data[i]['filename'] +
            '</div> - <div class="info">' +
            data[i]['pcode'] +
            '</div>' +
            '</li>';
          i++;
        }
        $('#list_search_result').html(content);
      }

      // Attach keyup event listener with debouncing
      $(once('ek-p-doc', '#document-search-field', context)).keyup(function () {
        const term = $(this).val().trim();
        performSearch(term);
      });
    },
  };
})(jQuery, Drupal, drupalSettings);
