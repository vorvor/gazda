<?php

namespace Drupal\product_search\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\product_search\SearchAnalytics;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

  private const MAX_SEARCH_TERM_LENGTH = 255;

  public function __construct(
    private readonly Connection $productSearchDatabase,
    private readonly RendererInterface $productSearchRenderer,
    private readonly EntityTypeManagerInterface $productSearchEntityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly SearchAnalytics $searchAnalytics,
    private readonly CsrfTokenGenerator $csrfTokenGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
      $container->get('product_search.search_analytics'),
      $container->get('csrf_token'),
    );
  }

  /**
   * Search page at /search-product.
   */
  public function page(): array {
    $default_view = $this->buildProductsView();
    $discovery = $this->buildHomepageDiscovery();

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
      '#results' => $default_view ?: [
        '#markup' => '<p class="product-search-error">' . $this->t('The products view could not be rendered.') . '</p>',
      ],
      '#local_offering_count' => $discovery['local_offering_count'],
      '#shop_count' => $discovery['shop_count'],
      '#hero_shop_name' => $discovery['hero_shop_name'],
      '#hero_shop_url' => $discovery['hero_shop_url'],
      '#hero_shop_image_url' => $discovery['hero_shop_image_url'],
      '#hero_shop_image_alt' => $discovery['hero_shop_image_alt'],
      '#hero_shop_summary' => $discovery['hero_shop_summary'],
      '#search_suggestions' => $discovery['search_suggestions'],
      '#attached' => [
        'library' => ['product_search/search'],
      ],
      '#cache' => [
        'max-age' => 0,
        'tags' => [
          'node_list:product',
          'node_list:service',
          'node_list:shop',
          'taxonomy_term_list:tags',
        ],
        'contexts' => ['user.permissions'],
      ],
    ];
  }

  /**
   * Builds live counts and a randomly selected local-shop story.
   */
  private function buildHomepageDiscovery(): array {
    $node_storage = $this->productSearchEntityTypeManager->getStorage('node');
    $term_storage = $this->productSearchEntityTypeManager->getStorage('taxonomy_term');

    $local_offering_count = (int) $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', ['product', 'service'], 'IN')
      ->condition('status', 1)
      ->count()
      ->execute();

    $shop_count = (int) $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'shop')
      ->condition('status', 1)
      ->count()
      ->execute();

    $tag_ids = $term_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'tags')
      ->condition('status', 1)
      ->execute();
    $search_suggestions = array_values(array_map(
      static fn($term): string => $term->label(),
      $term_storage->loadMultiple($tag_ids),
    ));
    shuffle($search_suggestions);
    $search_suggestions = array_slice($search_suggestions, 0, 4);

    $story = [
      'hero_shop_name' => '',
      'hero_shop_url' => '',
      'hero_shop_image_url' => '',
      'hero_shop_image_alt' => '',
      'hero_shop_summary' => '',
    ];

    $shop_ids = $node_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'shop')
      ->condition('status', 1)
      ->execute();

    if ($shop_ids) {
      $shop_ids = array_values($shop_ids);
      $shop_id = $shop_ids[random_int(0, count($shop_ids) - 1)];
      $shop = $node_storage->load($shop_id);

      if ($shop) {
        $image_item = NULL;
        if (!$shop->get('field_images')->isEmpty()) {
          $image_item = $shop->get('field_images')->first();
        }
        elseif (!$shop->get('field_logo')->isEmpty()) {
          $image_item = $shop->get('field_logo')->first();
        }

        $image = $image_item?->entity;
        $description = $shop->get('field_description')->value ?? '';
        $description_lines = array_values(array_filter(array_map(
          'trim',
          preg_split('/\R+/u', strip_tags($description)) ?: [],
        )));

        $story = [
          'hero_shop_name' => $shop->label(),
          'hero_shop_url' => $shop->toUrl()->toString(),
          'hero_shop_image_url' => $image ? $this->fileUrlGenerator->generateString($image->getFileUri()) : '',
          'hero_shop_image_alt' => trim($image_item?->alt ?? '') ?: $shop->label(),
          'hero_shop_summary' => $description_lines[0] ?? 'Helyi üzlet Szentendrén.',
        ];
      }
    }

    return $story + [
      'local_offering_count' => $local_offering_count,
      'shop_count' => $shop_count,
      'search_suggestions' => $search_suggestions,
    ];
  }

  /**
   * Returns a session-bound token for the product-search POST endpoint.
   */
  public function csrfToken(Request $request): Response {
    $session = $request->getSession();
    $session->start();
    $session->set('product_search.csrf_token', TRUE);

    return new Response(
      $this->csrfTokenGenerator->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY),
      200,
      [
        'Content-Type' => 'text/plain',
        'Cache-Control' => 'no-store, private',
      ],
    );
  }

  /**
   * CSRF-protected AJAX endpoint for submitted search keywords.
   */
  public function ajaxSearch(Request $request): JsonResponse {
    if (!$this->csrfTokenGenerator->validate(
      (string) $request->headers->get('X-CSRF-Token', ''),
      CsrfRequestHeaderAccessCheck::TOKEN_KEY,
    )) {
      return new JsonResponse(['message' => 'Invalid CSRF token.'], 403);
    }

    $keyword = mb_substr(
      trim((string) $request->request->get('q', '')),
      0,
      self::MAX_SEARCH_TERM_LENGTH,
    );

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
    $this->searchAnalytics->log($keyword, count($nids), $request->getClientIp());

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
   */
  private function findProductNodeIds(string $keyword): array {
    return array_map(
      'intval',
      $this->buildProductSearchQuery($keyword)->execute()->fetchCol(),
    );
  }

  /**
   * Builds the node-access-aware product lookup query.
   *
   * This intentionally uses Drupal's database API instead of EntityQuery for
   * the entity-reference term-name search. EntityQuery is good for simple field
   * conditions like field_tags.target_id, but it can break when trying to walk
   * from a node field to a referenced taxonomy term name.
   */
  private function buildProductSearchQuery(string $keyword): SelectInterface {
    $like = '%' . $this->productSearchDatabase->escapeLike($keyword) . '%';

    $query = $this->productSearchDatabase->select('node_field_data', 'nfd')
      ->fields('nfd', ['nid'])
      ->condition('nfd.type', ['product', 'service'], 'IN')
      ->condition('nfd.status', 1)
      ->addTag('node_access')
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

    if ($schema->tableExists('node__field_service_description')) {
      $query->leftJoin('node__field_service_description', 'fsd', 'fsd.entity_id = nfd.nid AND fsd.deleted = 0');
      $or->condition('fsd.field_service_description_value', $like, 'LIKE');
    }

    if ($schema->tableExists('node__field_marketing_text')) {
      $query->leftJoin('node__field_marketing_text', 'fmt', 'fmt.entity_id = nfd.nid AND fmt.deleted = 0');
      $or->condition('fmt.field_marketing_text_value', $like, 'LIKE');
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

    return $query;
  }

}
