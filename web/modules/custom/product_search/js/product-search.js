(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.productSearch = {
    attach(context) {
      once('product-search', '.product-search-widget', context).forEach((widget) => {
        const inputs = Array.from(widget.querySelectorAll('.product-search-input'));
        const results = widget.querySelector('.product-search-results');
        const submits = widget.querySelectorAll('.product-search-submit');
        const suggestions = widget.querySelectorAll('[data-search-suggestion]');

        if (!inputs.length || !results) {
          return;
        }

        const defaultResultsHtml = results.innerHTML;
        let timer = null;
        let controller = null;
        let csrfTokenPromise = null;

        const getCsrfToken = () => {
          if (!csrfTokenPromise) {
            csrfTokenPromise = fetch(Drupal.url('search-product/token'), {
              credentials: 'same-origin'
            })
              .then((response) => {
                if (!response.ok) {
                  throw new Error(`HTTP ${response.status}`);
                }
                return response.text();
              })
              .catch((error) => {
                csrfTokenPromise = null;
                throw error;
              });
          }

          return csrfTokenPromise;
        };

        const fetchSearch = (keyword, requestController, retried = false) => getCsrfToken()
          .then((csrfToken) => {
            if (requestController.signal.aborted) {
              throw new DOMException('Aborted', 'AbortError');
            }

            return fetch(Drupal.url('search-product/ajax'), {
              method: 'POST',
              headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: new URLSearchParams({ q: keyword }),
              credentials: 'same-origin',
              signal: requestController.signal
            });
          })
          .then((response) => {
            if (response.status === 403 && !retried && !requestController.signal.aborted) {
              csrfTokenPromise = null;
              return fetchSearch(keyword, requestController, true);
            }

            if (!response.ok) {
              throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
          });

        const runSearch = (sourceInput) => {
          const keyword = sourceInput.value.trim();

          inputs.forEach((input) => {
            if (input !== sourceInput) {
              input.value = sourceInput.value;
            }
          });

          if (keyword) {
            document.body.classList.add('searching');
            document.body.classList.toggle('searching-primary', sourceInput.id !== 'product-search-input-secondary');
          } else {
            document.body.classList.remove('searching');
            document.body.classList.remove('searching-primary');
          }

          if (controller) {
            controller.abort();
          }

          // When the field is empty, restore the initially rendered View with
          // all products instead of showing a help message.
          if (!keyword) {
            controller = null;
            results.innerHTML = defaultResultsHtml;
            results.setAttribute('aria-busy', 'false');
            Drupal.attachBehaviors(results);
            return;
          }

          results.setAttribute('aria-busy', 'true');
          results.innerHTML = '<p class="product-search-loading">' + Drupal.t('Searching...') + '</p>';

          controller = new AbortController();
          const requestController = controller;

          fetchSearch(keyword, requestController)
            .then((data) => {
              if (controller !== requestController) {
                return;
              }

              results.innerHTML = data.html || '<p class="product-search-no-results">' + Drupal.t('No products found.') + '</p>';
              Drupal.attachBehaviors(results);
            })
            .catch((error) => {
              if (error.name === 'AbortError' || controller !== requestController) {
                return;
              }
              results.innerHTML = '<p class="product-search-error">' + Drupal.t('Search failed. Please try again.') + '</p>';
            })
            .finally(() => {
              if (controller === requestController) {
                results.setAttribute('aria-busy', 'false');
              }
            });
        };

        inputs.forEach((input) => {
          input.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => runSearch(input), 250);
          });

          input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
              event.preventDefault();
              window.clearTimeout(timer);
              runSearch(input);
            }
          });
        });

        submits.forEach((submit) => {
          submit.addEventListener('click', () => {
            const input = submit.closest('.product-search-form')?.querySelector('.product-search-input');
            if (input) {
              window.clearTimeout(timer);
              runSearch(input);
            }
          });
        });

        suggestions.forEach((suggestion) => {
          suggestion.addEventListener('click', () => {
            const input = inputs[0];
            input.value = suggestion.dataset.searchSuggestion || '';
            input.focus();
            runSearch(input);
          });
        });
      });
    }
  };

})(Drupal, once);
