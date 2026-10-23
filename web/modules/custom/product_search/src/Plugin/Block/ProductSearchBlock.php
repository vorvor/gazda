<?php

namespace Drupal\product_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an AJAX product search block.
 *
 * @Block(
 *   id = "product_search_block",
 *   admin_label = @Translation("Product search"),
 *   category = @Translation("Custom")
 * )
 */
final class ProductSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly RouteMatchInterface $routeMatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('current_route_match'));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return array_merge(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    $shop_id = $node instanceof NodeInterface && $node->bundle() === 'shop' ? (int) $node->id() : NULL;
    $default_view = $shop_id === NULL
      ? $this->buildProductsView()
      : views_embed_view('products', 'block_1', $shop_id);

    return [
      '#theme' => 'product_search',
      '#shop_id' => $shop_id,
      '#cache' => ['contexts' => ['route']],
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
