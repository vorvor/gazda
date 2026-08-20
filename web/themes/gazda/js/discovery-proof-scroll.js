(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.discoveryProofScroll = {
    attach(context) {
      once('discovery-proof-scroll', '.discovery-proof', context).forEach((proof) => {
        const $proof = $(proof);
        const $hero = $proof.prev('.discovery-hero');
        const $window = $(window);
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const duration = reducedMotion ? 0 : 360;
        let triggerPoint = 0;
        let tucked = false;
        let frameRequested = false;

        if (!$hero.length) {
          return;
        }

        const measure = () => {
          triggerPoint = $hero.offset().top + $hero.outerHeight() - (window.innerHeight * 0.45);
        };

        const tuck = () => {
          tucked = true;
          $proof
            .attr('aria-hidden', 'true')
            .addClass('is-tucking')
            .stop(true, true)
            .slideUp(duration, () => {
              $proof.removeClass('is-tucking').addClass('is-tucked');
            });
        };

        const reveal = () => {
          tucked = false;
          $proof
            .removeAttr('aria-hidden')
            .removeClass('is-tucked')
            .addClass('is-revealing')
            .stop(true, true)
            .slideDown(duration, () => {
              $proof
                .removeClass('is-revealing')
                .css('display', '');
            });
        };

        const update = () => {
          frameRequested = false;

          if (document.body.classList.contains('searching')) {
            return;
          }

          const scrollTop = $window.scrollTop();
          if (!tucked && scrollTop > triggerPoint + 24) {
            tuck();
          }
          else if (tucked && scrollTop < triggerPoint - 24) {
            reveal();
          }
        };

        const requestUpdate = () => {
          if (!frameRequested) {
            frameRequested = true;
            window.requestAnimationFrame(update);
          }
        };

        measure();
        requestUpdate();

        $window.on('scroll.discoveryProof', requestUpdate);
        $window.on('resize.discoveryProof', () => {
          measure();
          requestUpdate();
        });
      });
    }
  };

})(jQuery, Drupal, once);
