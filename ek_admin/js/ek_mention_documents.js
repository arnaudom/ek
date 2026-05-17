(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.ek_mention_documents = {
        attach: function (context, settings) {

            /*
             * Document mention autocomplete for # prefix
             * 
             */

            // Helper to get caret position in textarea.
            function getCaretPosition(elem) {
                var iCaretPos = 0;
                if (document.selection) {
                    elem.focus();
                    var oSel = document.selection.createRange();
                    oSel.moveStart('character', -elem.value.length);
                    iCaretPos = oSel.text.length;
                } else if (elem.selectionStart || elem.selectionStart === 0) {
                    iCaretPos = elem.selectionStart;
                }
                return iCaretPos;
            }

            // Helper to set caret position in textarea.
            function setCaretPosition(elem, caretPos) {
                if (elem != null) {
                    if (elem.createTextRange) {
                        var range = elem.createTextRange();
                        range.move('character', caretPos);
                        range.select();
                    } else {
                        if (elem.selectionStart) {
                            elem.focus();
                            elem.setSelectionRange(caretPos, caretPos);
                        } else {
                            elem.focus();
                        }
                    }
                }
            }

            // Attach autocomplete to message textareas.
            $(once('msg-doc-autocomplete', '#edit-message-value, [data-drupal-selector="edit-message-value"]', context))
                .each(function () {

                $(this).autocomplete({
                    source: function (request, response) {
                        var term = request.term;
                        var pos = getCaretPosition(this.element.get(0));
                        var substr = term.substring(0, pos);
                        var lastIndex = substr.lastIndexOf('#');
                        if (lastIndex >= 0) {
                            var document = substr.substr(lastIndex + 1);
                            if (document.length && (/^\w+$/g).test(document)) {
                                jQuery.getJSON(drupalSettings.path.baseUrl + 'ek_admin/documents-autocomplete', {
                                    term: document
                                }, response);
                            }
                        }
                        response({});
                    },
                    focus: function () {
                        return false;
                    },
                    select: function (event, ui) {
                        var pos = getCaretPosition(this);
                        var substr = this.value.substring(0, pos);
                        var lastIndex = substr.lastIndexOf('#');
                        if (lastIndex >= 0) {
                            var prependStr = this.value.substring(0, lastIndex);
                            this.value = prependStr + '#' + ui.item.value + this.value.substr(pos) + ' ';
                            setCaretPosition(this, prependStr.length + ui.item.value.length + 2);
                        }
                        return false;
                    }
                }).data("ui-autocomplete")._renderItem = function (ul, item) {
                    var extension = item.label.split('.').pop().toLowerCase();
                    var iconClass = 'icon_doc_list ' + extension + '_doc_list';
                    return $("<li>")
                            .data("ui-autocomplete-item", item)
                            .append("<span class='smallico " + iconClass + "'></span><a>" + item.label + "</a>")
                            .appendTo(ul);
                };
            });

        }
    };

})(jQuery, Drupal, drupalSettings);