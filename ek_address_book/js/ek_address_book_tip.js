(function ($, Drupal) {
  Drupal.behaviors.ekabTip = {
    // Cache object to store tooltip content
    tooltipCache: {},
    // Track ongoing AJAX requests to prevent duplicates
    activeRequests: {},
    
    attach: function (context) {
      var self = this;
      
      // Process .tip elements once using Drupal.once
      $(once('ek-ab-tip', '.tip', context)).each(function () {
        var $this = $(this);
        var elementId = $this.attr('id');        
        // Ensure element has required attributes for tooltip to work
        if (!elementId) {
          console.warn('Element missing ID attribute:', this);
          return;
        }

        // Ensure element has a title attribute or add one
        if (!$this.attr('title') && !$this.data('original-title')) {
          $this.attr('title', 'Logo');
        }

        // Initialize jQuery UI Tooltip
        $this.tooltip({
          // Track property to ensure tooltip opens
          track: false,
          // Positioning to mimic qTip2's my: 'bottom right', at 'top left'
          position: {
            my: 'left center',
            at: 'right',
            collision: 'flip' // Add collision detection
          },
          // Custom classes for styling
          classes: {
            'ui-tooltip': 'ab-tooltip'
          },
          // Show configuration - important for hover behavior
          show: {
            delay: 100,
            effect: 'fadeIn',
            duration: 200
          },
          hide: {
            delay: 50,
            effect: 'fadeOut',
            duration: 100
          },
          // Content function to load AJAX data with caching
          content: function (callback) {            
            // Check if content is already cached
            if (self.tooltipCache[elementId]) {
              callback(self.tooltipCache[elementId]);
              return;
            }
            
            // Check if there's already an active request for this element
            if (self.activeRequests[elementId]) {
              self.activeRequests[elementId].done(function(cachedContent) {
                callback(cachedContent);
              });
              return;
            }
            
            // Store reference to the tooltip element
            var tooltipElement = this;
            
            // Initial loading text
            callback('Loading...');

            // Create and store the AJAX request
            self.activeRequests[elementId] = $.ajax({
              url: '/look_up_contact_ajax/tip',
              type: 'GET',
              data: { q: elementId },
              dataType: 'json', 
              success: function(data) {                
                var content;
                if (data && data['card']) {
                  content = data['card'];
                } else {
                  content = 'Error: No data returned';
                }
                // Cache the content
                self.tooltipCache[elementId] = content;
                // Update tooltip content
                callback(content);
              },
              error: function(xhr, status, error) {
                console.error('AJAX error for:', elementId, status, error, xhr.responseText);
                var errorContent = 'Error: ' + status + ' - ' + error;
                // Cache error content (optional - you might not want to cache errors)
                // self.tooltipCache[elementId] = errorContent;
                callback(errorContent);
              },
              complete: function() {
                // Remove from active requests when done
                delete self.activeRequests[elementId];
              }
            });
          },
          // Open event to ensure proper rendering
          open: function (event, ui) {
            // Optional: Add custom behavior on tooltip open
            ui.tooltip.css({
              'z-index': 1000
            });
          },
          // Close event for debugging
          close: function (event, ui) {
          }
        });
        
       
      });
    }
  };
})(jQuery, Drupal);




/*(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.ek_ab_tip = {
        attach: function (context, settings) {
            $('.tip').each(function () {
                var $this = $(this);
                $this.qtip({
                    style: { 
                        classes: 'qtip-bootstrap' 
                    },
                    position: {
                        my: 'bottom right',
                        at: 'top left'
                    },
                    content: {
                        text: 'Loading...',
                        ajax: {
                            url: '/look_up_contact_ajax/tip',
                            type: 'GET',
                            data: { q: $this.attr('id'), option: 'image' },
                            success: function (data, status) {
                                // Explicitly get the qTip API
                                var api = $this.qtip('api');
                                // Set the content
                                api.set('content.text', data['card'] || 'Error: No data returned');
                            }
                        }
                    }
                });
            });
        }
    };
})(jQuery, Drupal, drupalSettings);*/
