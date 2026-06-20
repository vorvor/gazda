/**
 * Selectable fields script for fieldGalleryCarouselBS.
 */
(function ($, Drupal, window, document) {

    Drupal.behaviors.basic = {
        attach: function (context, settings) {
            $(document).ready(function () {
                // Activate Carousel
                $("#fieldGalleryCarouselBS").carousel();

            });
        }
    };

}(jQuery, Drupal, this, this.document));
