/**
 * @file email_octopus.js
 *
 * Provides a Email Octopus Functionality.
 */
(function ($, Drupal) {
  Drupal.behaviors.email_octopus = {
    attach(context, settings) {
      $('.subscribe-button').click(function () {
        $('#dest-wrapper').show();
        $('.newsletter-sub').hide();
      });
      $('.back-subs-btn').click(function () {
        $('.newsletter-sub').show();
        $('#dest-wrapper').hide();
      });
    },
  };
})(jQuery, Drupal);
