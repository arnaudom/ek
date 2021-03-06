Drupal.behaviors.ek_abook_auto_users = {
    attach: function (context, settings) {

        jQuery(function () {
            function split(val) {
                return val.split(/,\s*/
                        );
            }

            function extractLast(term) {
                return split(term).pop();
            }
            jQuery("#edit-email")
                    // don't navigate away from the field on tab when selecting an item
                    .bind("keydown", function (event) {
                        if (event.keyCode === jQuery.ui.keyCode.TAB &&
                                jQuery(this).data("ui-autocomplete").menu.active) {
                            event.preventDefault();
                        }
                    })
                    .autocomplete({
                        source: function (request, response) {
                            jQuery.getJSON("../../look_up_email_ajax/user", {
                                term: extractLast(request.term)
                            }, response);
                        },
                        search: function () {
                            // custom minLength
                            var term = extractLast(this.value);
                            if (term.length < 2) {
                                return false;
                            }
                        },
                        focus: function () {
                            // prevent value inserted on focus
                            return false;
                        },
                        select: function (event, ui) {
                            var terms = split(this.value);
                            // remove the current input
                            terms.pop();
                            // add the selected item
                            terms.push(ui.item.value);
                            // add placeholder to get the comma-and-space at the end - used for multi select
                            terms.push("");
                            this.value = terms.join(", ");

                            return false;
                        }
                    });
        });

    } //attach
}; //bahaviors