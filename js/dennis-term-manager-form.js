jQuery(document).ready(function () {

  var error_types = ["warning", "error"];

  jQuery(error_types).each(function( index, type ) {
    var selector = ".messages." + type;

    var count = jQuery(selector + " ul").children().length;

    if (count > 1) {
      var element = jQuery(selector);

      var button = jQuery("<button/>", {
        text: "+",
        click: function () {
          if (jQuery(this).text() == "+") {
            jQuery(this).text("-");
            element.slideDown();
          }
          else {
            jQuery(this).text("+");
            element.slideUp();
          }
        }
      });

      var message = jQuery("<div/>", {
        title: "Expand/Collapse",
        text: "There were " + count + " " + type + " message(s). ",
        class: type
      }).append(button);

      element.hide().before(message);
    }
  });
});
