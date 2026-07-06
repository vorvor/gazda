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
      '#theme' => 'product_search',
      '#placeholder' => $this->t('Search product'),
      '#marketingblock' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['marketing-block'],
          'aria-live' => 'polite',
        ],
        'view' => [
          '#markup' => '<div id="inside-block">
            <div><img src="/themes/gazda/images/header-inside-block.png" alt="Inside Block"></div>
            <div id="header-texts">
              <div>Személyes kiszolgálás</div>
              <div>Spórolj időt</div>
              <div>Ötleteket adunk</div>
            </div>
          </div>',
        ],
      ],
      '#results' => [
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
    return views_embed_view('products', 'block_1');
  }

}
