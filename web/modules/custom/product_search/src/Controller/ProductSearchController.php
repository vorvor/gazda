<?php

namespace Drupal\product_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the AJAX product search page.
 */
final class ProductSearchController extends ControllerBase {

  /**
   * Maximum matching products to pass to the View.
   *
   * Raise this if needed, but keep in mind that long contextual arguments
   * are not ideal. For very large result sets, consider a custom Views filter.
   */
  private const MAX_RESULTS = 100;

  public function __construct(
    private readonly Connection $productSearchDatabase,
    private readonly RendererInterface $productSearchRenderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('renderer'),
    );
  }

  /**
   * Search page at /search-product.
   */
  public function page(): array {
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
          'id' => 'product-search-results',
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
   * AJAX endpoint: /search-product/ajax?q=keyword.
   */
  public function ajaxSearch(Request $request): JsonResponse {
    $keyword = trim((string) $request->query->get('q', ''));

    // Empty keyword means: show the default products View with all products.
    if ($keyword === '') {
      $build = $this->buildProductsView();

      if (!$build) {
        return new JsonResponse([
          'html' => '<p class="product-search-error">The products view could not be rendered.</p>',
          'count' => 0,
        ], 500);
      }

      return new JsonResponse([
        'html' => (string) $this->productSearchRenderer->renderRoot($build),
        'count' => NULL,
      ]);
    }

    $nids = $this->findProductNodeIds($keyword);

    if ($nids === []) {
      return new JsonResponse([
        'html' => '<p class="product-search-no-results">No products found.</p>',
        'count' => 0,
      ]);
    }

    $build = $this->buildProductsView($nids);

    if (!$build) {
      return new JsonResponse([
        'html' => '<p class="product-search-error">The products view could not be rendered.</p>',
        'count' => count($nids),
      ], 500);
    }

    return new JsonResponse([
      'html' => (string) $this->productSearchRenderer->renderRoot($build),
      'count' => count($nids),
    ]);
  }

  /**
   * Builds the products View.
   *
   * When $nids is NULL, the View is rendered without a contextual argument,
   * so it can show all products. Configure the contextual filter's "When the
   * filter value is NOT available" behavior to "Display all results".
   *
   * When $nids is an array, the View receives a single contextual argument like
   * "12+15+28". In Views contextual filters, + means OR/multiple values.
   */
  private function buildProductsView(?array $nids = NULL): mixed {
    if ($nids === NULL) {
      return views_embed_view('products', 'page_1');
    }

    $argument = implode('+', $nids);
    return views_embed_view('products', 'page_1', $argument);
  }

  /**
   * Finds product node IDs by title, description, tag name, or category name.
   *
   * This intentionally uses Drupal's database API instead of EntityQuery for
   * the entity-reference term-name search. EntityQuery is good for simple field
   * conditions like field_tags.target_id, but it can break when trying to walk
   * from a node field to a referenced taxonomy term name.
   */
  private function findProductNodeIds(string $keyword): array {
    $like = '%' . $this->productSearchDatabase->escapeLike($keyword) . '%';

    $query = $this->productSearchDatabase->select('node_field_data', 'nfd')
      ->fields('nfd', ['nid'])
      ->condition('nfd.type', 'product')
      ->condition('nfd.status', 1)
      ->groupBy('nfd.nid')
      ->range(0, self::MAX_RESULTS);

    // MySQL can reject SELECT DISTINCT nid ORDER BY changed because changed is
    // not in the SELECT list. Use GROUP BY nid + MAX(changed) for stable sorting.
    $query->addExpression('MAX(nfd.changed)', 'changed_sort');
    $query->orderBy('changed_sort', 'DESC');

    $schema = $this->productSearchDatabase->schema();

    $or = $query->orConditionGroup()
      ->condition('nfd.title', $like, 'LIKE');

    if ($schema->tableExists('node__field_description')) {
      $query->leftJoin('node__field_description', 'fd', 'fd.entity_id = nfd.nid AND fd.deleted = 0');
      $or->condition('fd.field_description_value', $like, 'LIKE');
    }

    if ($schema->tableExists('node__field_tags')) {
      $query->leftJoin('node__field_tags', 'ft', 'ft.entity_id = nfd.nid AND ft.deleted = 0');
      $query->leftJoin('taxonomy_term_field_data', 'tag_tfd', 'tag_tfd.tid = ft.field_tags_target_id AND tag_tfd.vid = :tag_vid', [':tag_vid' => 'tags']);
      $or->condition('tag_tfd.name', $like, 'LIKE');
    }

    if ($schema->tableExists('node__field_category')) {
      $query->leftJoin('node__field_category', 'fc', 'fc.entity_id = nfd.nid AND fc.deleted = 0');
      $query->leftJoin('taxonomy_term_field_data', 'cat_tfd', 'cat_tfd.tid = fc.field_category_target_id AND cat_tfd.vid = :cat_vid', [':cat_vid' => 'product_category']);
      $or->condition('cat_tfd.name', $like, 'LIKE');
    }

    if ($schema->tableExists('node__field_shop')) {
      $query->leftJoin('node__field_shop', 'fs', 'fs.entity_id = nfd.nid AND fs.deleted = 0');
      $query->leftJoin('node_field_data', 'shop', 'shop.nid = fs.field_shop_target_id');
      $or->condition('shop.title', $like, 'LIKE');
    }

    $query->condition($or);

    return array_map('intval', $query->execute()->fetchCol());
  }

}
