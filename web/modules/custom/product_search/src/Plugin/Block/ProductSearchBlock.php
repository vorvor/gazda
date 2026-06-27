<?php

namespace Drupal\product_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an AJAX product search block.
 *
 * @Block(
 *   id = "product_search_block",
 *   admin_label = @Translation("Product search"),
 *   category = @Translation("Custom")
 * )
 */
final class ProductSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $default_view = $this->buildProductsView();

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['product-search-widget', 'product-search-block'],
      ],
      'search' => [
        '#type' => 'textfield',
        '#attributes' => [
          'class' => ['product-search-input'],
          'autocomplete' => 'off',
          'placeholder' => $this->t('Search product'),
        ],
      ],
      'results' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['product-search-results'],
          'aria-live' => 'polite',
        ],
        'view' => $default_view ?: [
          '#markup' => '<p class="product-search-error">' . $this->t('The products view could not be rendered.') . '</p>',
        ],
      ],
      '#attached' => [
        'library' => ['product_search/search'],
      ],
    ];
  }

  /**
   * Builds the default products View with all products.
   *
   * The block intentionally renders the same View display as the
   * /search-product page. The AJAX endpoint is shared with the page.
   */
  private function buildProductsView(): mixed {
    return views_embed_view('products', 'page_1');
  }

}
