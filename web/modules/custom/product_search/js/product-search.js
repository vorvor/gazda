(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.productSearch = {
    attach(context) {
      once('product-search', '.product-search-widget', context).forEach((widget) => {
        const input = widget.querySelector('.product-search-input');
        const results = widget.querySelector('.product-search-results');
        const submit = widget.querySelector('.product-search-submit');
        const suggestions = widget.querySelectorAll('[data-search-suggestion]');

        if (!input || !results) {
          return;
        }

        const defaultResultsHtml = results.innerHTML;
        let timer = null;
        let controller = null;

        const runSearch = () => {
          const keyword = input.value.trim();

          if (keyword) {
            document.body.classList.add('searching');
          } else {
            document.body.classList.remove('searching');
          }

          if (controller) {
            controller.abort();
          }

          // When the field is empty, restore the initially rendered View with
          // all products instead of showing a help message.
          if (!keyword) {
            results.innerHTML = defaultResultsHtml;
            results.setAttribute('aria-busy', 'false');
            Drupal.attachBehaviors(results);
            return;
          }

          results.setAttribute('aria-busy', 'true');
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
              Drupal.attachBehaviors(results);
            })
            .catch((error) => {
              if (error.name === 'AbortError') {
                return;
              }
              results.innerHTML = '<p class="product-search-error">' + Drupal.t('Search failed. Please try again.') + '</p>';
            })
            .finally(() => {
              results.setAttribute('aria-busy', 'false');
            });
        };

        input.addEventListener('input', () => {
          window.clearTimeout(timer);
          timer = window.setTimeout(runSearch, 250);
        });

        input.addEventListener('keydown', (event) => {
          if (event.key === 'Enter') {
            event.preventDefault();
            window.clearTimeout(timer);
            runSearch();
          }
        });

        submit?.addEventListener('click', runSearch);

        suggestions.forEach((suggestion) => {
          suggestion.addEventListener('click', () => {
            input.value = suggestion.dataset.searchSuggestion || '';
            input.focus();
            runSearch();
          });
        });
      });
    }
  };

})(Drupal, once);
