(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.productSearch = {
    attach(context) {
      once('product-search', '.product-search-widget', context).forEach((widget) => {
        console.log('heyyyyy!');
        const input = widget.querySelector('.product-search-input');
        const results = widget.querySelector('.product-search-results');

        if (!input || !results) {
          console.log(input);
          return;
        }

        const defaultResultsHtml = results.innerHTML;
        let timer = null;
        let controller = null;

        const runSearch = () => {
          const keyword = input.value.trim();

          if (controller) {
            controller.abort();
          }

          // When the field is empty, restore the initially rendered View with
          // all products instead of showing a help message.
          if (!keyword) {
            results.innerHTML = defaultResultsHtml;
            return;
          }

          results.innerHTML = '<p class="product-search-loading">' + Drupal.t('Searching...') + '</p>';

          controller = new AbortController();

          fetch(`${Drupal.url('search-product/ajax')}?q=${encodeURIComponent(keyword)}`, {
            method: 'GET',
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            signal: controller.signal
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
              }
              return response.json();
            })
            .then((data) => {
              results.innerHTML = data.html || '<p class="product-search-no-results">' + Drupal.t('No products found.') + '</p>';
            })
            .catch((error) => {
              if (error.name === 'AbortError') {
                return;
              }
              results.innerHTML = '<p class="product-search-error">' + Drupal.t('Search failed. Please try again.') + '</p>';
            });
        };

        input.addEventListener('keyup', () => {
          console.log('hey!');
          // Still runs from keyup, but debounced to avoid a database query for
          // every very fast keystroke. Set this to 0 if you truly want every keyup.
          window.clearTimeout(timer);
          timer = window.setTimeout(runSearch, 250);
        });
      });
    }
  };

})(Drupal, once);
