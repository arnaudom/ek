/*(function ($, Drupal, drupalSettings) {

    Drupal.behaviors.ek_ad_tag = {
        attach: function (context, settings) {

            if (drupalSettings.auto_complete) {
                $('.form-select-tag').tagEditor({ 
                    autocomplete: {
                                source : drupalSettings.path.baseUrl + drupalSettings.auto_complete, 
                                minLength: 2,
                        }
                    }
                );

            } else {
                $(once('bind-tag-event', '.form-select-tag', context)).tagEditor();
            }
        }
    };
})(jQuery, Drupal, drupalSettings);  */

/*(function (Drupal, drupalSettings) {
  Drupal.behaviors.ek_ad_tag = {
    attach: function (context, settings) {
      const textfields = Drupal.behaviors.ek_ad_tag.once('tag-editor', '.form-select-tag', context);
      textfields.forEach(textfield => {
        // Hide the original textfield
        textfield.style.display = 'none';

        // Create tag editor container
        const tagEditor = document.createElement('div');
        tagEditor.classList.add('tag-editor');

        // Create tags container
        const tagsContainer = document.createElement('div');
        tagsContainer.classList.add('tags-container');

        // Create input field for new tags
        const input = document.createElement('input');
        input.type = 'text';
        input.classList.add('tag-input');

        // Create suggestions container for autocomplete
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.classList.add('suggestions');

        // Append elements to the tag editor
        tagEditor.appendChild(tagsContainer);
        tagEditor.appendChild(input);
        tagEditor.appendChild(suggestionsContainer);
        textfield.parentNode.insertBefore(tagEditor, textfield.nextSibling);

        // Function to add a tag
        function addTag(tag) {
          const tagElement = document.createElement('span');
          tagElement.classList.add('tag');
          const tagText = document.createElement('span');
          tagText.textContent = tag;
          tagElement.appendChild(tagText);
          const removeButton = document.createElement('button');
          removeButton.textContent = 'x';
          removeButton.addEventListener('click', () => {
            tagElement.remove();
            updateTextfield();
          });
          tagElement.appendChild(removeButton);
          tagsContainer.appendChild(tagElement);
        }

        // Function to update the hidden textfield value
        function updateTextfield() {
          const tags = Array.from(tagsContainer.querySelectorAll('.tag span')).map(span => span.textContent);
          textfield.value = tags.join(',');
        }

        // Handle input events for adding tags
        input.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            const tag = input.value.trim();
            if (tag) {
              addTag(tag);
              input.value = '';
              updateTextfield();
            }
          }
        });

        // Populate with existing tags
        const initialTags = textfield.value.split(',').filter(tag => tag.trim());
        initialTags.forEach(addTag);

        // Autocomplete functionality (if enabled)
        if (drupalSettings.auto_complete) {
          const fetchSuggestions = Drupal.behaviors.ek_ad_tag.debounce(() => {
            const query = input.value.trim();
            if (query.length >= 2) {
              fetch(`${drupalSettings.path.baseUrl}${drupalSettings.auto_complete}?term=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                  displaySuggestions(data);
                })
                .catch(() => {
                  suggestionsContainer.style.display = 'none';
                });
            } else {
              suggestionsContainer.innerHTML = '';
              suggestionsContainer.style.display = 'none';
            }
          }, 300);

          input.addEventListener('input', fetchSuggestions);

          function displaySuggestions(suggestions) {
            suggestionsContainer.innerHTML = '';
            if (suggestions.length > 0) {
              suggestions.forEach(suggestion => {
                const suggestionElement = document.createElement('div');
                suggestionElement.textContent = suggestion;
                suggestionElement.addEventListener('click', () => {
                  addTag(suggestion);
                  input.value = '';
                  updateTextfield();
                  suggestionsContainer.style.display = 'none';
                });
                suggestionsContainer.appendChild(suggestionElement);
              });
              suggestionsContainer.style.display = 'block';
            } else {
              suggestionsContainer.style.display = 'none';
            }
          }

          // Hide suggestions when clicking outside
          document.addEventListener('click', (event) => {
            if (!tagEditor.contains(event.target)) {
              suggestionsContainer.style.display = 'none';
            }
          });
        }
      });
    },

    // Debounce function to limit fetch requests
    debounce: function (func, delay) {
      let timeout;
      return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
      };
    },

    // Custom once function (simplified)
    once: function (id, selector, context) {
        // Ensure id is a string and sanitize it for dataset
        const safeId = 'tagEditor' + String(id).replace(/[^a-zA-Z0-9]/g, '_');
        const elements = context.querySelectorAll(selector);
        return Array.from(elements).filter(element => {
            if (!element.dataset[safeId]) {
            element.dataset[safeId] = true;
            return true;
            }
            return false;
        });
    }
  };
})(Drupal, drupalSettings);*/

(function (Drupal, drupalSettings) {
  Drupal.behaviors.ek_ad_tag = {
    attach: function (context, settings) {
      // Handle textfield-based tag editor
      const textfields = Drupal.behaviors.ek_ad_tag.once('tag-editor', '.form-select-tag', context);
      textfields.forEach(textfield => {
        // Hide the original textfield
        textfield.style.display = 'none';

        // Create tag editor container
        const tagEditor = document.createElement('div');
        tagEditor.classList.add('tag-editor');

        // Create tags container
        const tagsContainer = document.createElement('div');
        tagsContainer.classList.add('tags-container');

        // Create input field for new tags
        const input = document.createElement('input');
        input.type = 'text';
        input.classList.add('tag-input');

        // Create suggestions container for autocomplete
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.classList.add('suggestions');

        // Append elements to the tag editor
        tagEditor.appendChild(tagsContainer);
        tagEditor.appendChild(input);
        tagEditor.appendChild(suggestionsContainer);
        textfield.parentNode.insertBefore(tagEditor, textfield.nextSibling);

        // Function to add a tag
        function addTag(tag) {
          const tagElement = document.createElement('span');
          tagElement.classList.add('tag');
          const tagText = document.createElement('span');
          tagText.textContent = tag;
          tagElement.appendChild(tagText);
          const removeButton = document.createElement('button');
          removeButton.textContent = 'x';
          removeButton.addEventListener('click', () => {
            tagElement.remove();
            updateTextfield();
          });
          tagElement.appendChild(removeButton);
          tagsContainer.appendChild(tagElement);
        }

        // Function to update the hidden textfield value
        function updateTextfield() {
          const tags = Array.from(tagsContainer.querySelectorAll('.tag span')).map(span => span.textContent);
          textfield.value = tags.join(',');
        }

        // Handle input events for adding tags
        input.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            const tag = input.value.trim();
            if (tag) {
              addTag(tag);
              input.value = '';
              updateTextfield();
            }
          }
        });

        // Populate with existing tags
        const initialTags = textfield.value.split(',').filter(tag => tag.trim());
        initialTags.forEach(addTag);

        // Autocomplete functionality (if enabled)
        if (drupalSettings.auto_complete) {
          const fetchSuggestions = Drupal.behaviors.ek_ad_tag.debounce(() => {
            const query = input.value.trim();
            if (query.length >= 2) {
              fetch(`${drupalSettings.path.baseUrl}${drupalSettings.auto_complete}?term=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                  displaySuggestions(data);
                })
                .catch(() => {
                  suggestionsContainer.style.display = 'none';
                });
            } else {
              suggestionsContainer.innerHTML = '';
              suggestionsContainer.style.display = 'none';
            }
          }, 300);

          input.addEventListener('input', fetchSuggestions);

          function displaySuggestions(suggestions) {
            suggestionsContainer.innerHTML = '';
            if (suggestions.length > 0) {
              suggestions.forEach(suggestion => {
                const suggestionElement = document.createElement('div');
                suggestionElement.textContent = suggestion;
                suggestionElement.addEventListener('click', () => {
                  addTag(suggestion);
                  input.value = '';
                  updateTextfield();
                  suggestionsContainer.style.display = 'none';
                });
                suggestionsContainer.appendChild(suggestionElement);
              });
              suggestionsContainer.style.display = 'block';
            } else {
              suggestionsContainer.style.display = 'none';
            }
          }

          // Hide suggestions when clicking outside
          document.addEventListener('click', (event) => {
            if (!tagEditor.contains(event.target)) {
              suggestionsContainer.style.display = 'none';
            }
          });
        }
      });

      // Handle select-based tag editor
      const selects = Drupal.behaviors.ek_ad_tag.once('select-tag-editor', '.form-select-chosen', context);
      selects.forEach(select => {
        // Hide the original select
        select.style.display = 'none';

        // Create tag editor container
        const tagEditor = document.createElement('div');
        tagEditor.classList.add('tag-editor');

        // Create tags container
        const tagsContainer = document.createElement('div');
        tagsContainer.classList.add('tags-container');

        // Create input field for selecting options
        const input = document.createElement('input');
        input.type = 'text';
        input.classList.add('tag-input');
        input.placeholder = 'Type to filter options...';

        // Create suggestions container for dropdown
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.classList.add('suggestions');

        // Append elements to the tag editor
        tagEditor.appendChild(tagsContainer);
        tagEditor.appendChild(input);
        tagEditor.appendChild(suggestionsContainer);
        select.parentNode.insertBefore(tagEditor, select.nextSibling);

        // Get available options from the select element
        const options = Array.from(select.options).map(option => ({
          value: option.value,
          text: option.text
        }));

        // Function to add a tag
        function addTag(value, text) {
          const tagElement = document.createElement('span');
          tagElement.classList.add('tag');
          const tagText = document.createElement('span');
          tagText.textContent = text || value;
          tagElement.appendChild(tagText);
          const removeButton = document.createElement('button');
          removeButton.textContent = 'x';
          removeButton.addEventListener('click', () => {
            tagElement.remove();
            updateSelect();
          });
          tagElement.appendChild(removeButton);
          tagsContainer.appendChild(tagElement);
        }

        // Function to update the select element
        function updateSelect() {
          const tags = Array.from(tagsContainer.querySelectorAll('.tag span')).map(span => {
            const text = span.textContent;
            const option = options.find(opt => opt.text === text || opt.value === text);
            return option ? option.value : text;
          });
          Array.from(select.options).forEach(option => {
            option.selected = tags.includes(option.value);
          });
          // Trigger change event to ensure Drupal form updates
          select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Populate with default:selected options
        const selectedValues = Array.from(select.selectedOptions).map(option => ({
          value: option.value,
          text: option.text
        }));
        selectedValues.forEach(option => addTag(option.value, option.text));

        // Handle input for filtering options
        const filterOptions = Drupal.behaviors.ek_ad_tag.debounce(() => {
          const query = input.value.trim().toLowerCase();
          suggestionsContainer.innerHTML = '';
          if (query.length >= 1) {
            const filteredOptions = options.filter(option =>
              option.text.toLowerCase().includes(query) &&
              !Array.from(tagsContainer.querySelectorAll('.tag span')).some(span => span.textContent === option.text)
            );
            filteredOptions.forEach(option => {
              const suggestionElement = document.createElement('div');
              suggestionElement.textContent = option.text;
              suggestionElement.addEventListener('click', () => {
                addTag(option.value, option.text);
                input.value = '';
                updateSelect();
                suggestionsContainer.style.display = 'none';
              });
              suggestionsContainer.appendChild(suggestionElement);
            });
            suggestionsContainer.style.display = filteredOptions.length > 0 ? 'block' : 'none';
          } else {
            suggestionsContainer.style.display = 'none';
          }
        }, 300);

        input.addEventListener('input', filterOptions);

        // Show all available options when input is focused
        input.addEventListener('focus', () => {
          const availableOptions = options.filter(option =>
            !Array.from(tagsContainer.querySelectorAll('.tag span')).some(span => span.textContent === option.text)
          );
          suggestionsContainer.innerHTML = '';
          availableOptions.forEach(option => {
            const suggestionElement = document.createElement('div');
            suggestionElement.textContent = option.text;
            suggestionElement.addEventListener('click', () => {
              addTag(option.value, option.text);
              input.value = '';
              updateSelect();
              suggestionsContainer.style.display = 'none';
            });
            suggestionsContainer.appendChild(suggestionElement);
          });
          suggestionsContainer.style.display = availableOptions.length > 0 ? 'block' : 'none';
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', (event) => {
          if (!tagEditor.contains(event.target)) {
            suggestionsContainer.style.display = 'none';
          }
        });
      });
    },

    // Debounce function to limit fetch requests
    debounce: function (func, delay) {
      let timeout;
      return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
      };
    },

    // Custom once function
    once: function (id, selector, context) {
      const safeId = 'tagEditor' + String(id).replace(/[^a-zA-Z0-9]/g, '_');
      const elements = context.querySelectorAll(selector);
      return Array.from(elements).filter(element => {
        if (!element.dataset[safeId]) {
          element.dataset[safeId] = 'true';
          return true;
        }
        return false;
      });
    }
  };
})(Drupal, drupalSettings);