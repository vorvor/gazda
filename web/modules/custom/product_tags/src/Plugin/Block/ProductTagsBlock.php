<?php

namespace Drupal\product_tags\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a 'Product Tags' block.
 *
 * @Block(
 *   id = "product_tags_block",
 *   admin_label = @Translation("Product Tags"),
 *   category = @Translation("Custom")
 * )
 */
class ProductTagsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ProductTagsBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof \Drupal\node\NodeInterface || $node->bundle() !== 'shop') {
      return [];
    }

    $tags = $this->getShopProductTags($node->id());

    if (empty($tags)) {
      return [];
    }

    return [
      '#theme' => 'product_tags_list',
      '#tags' => $tags,
      '#shop' => $node,
      '#cache' => [
        'tags' => ['node_list:product', 'node:' . $node->id()],
      ],
    ];
  }

  /**
   * Collects all unique tags from products relating to a specific shop.
   *
   * @param int $shop_id
   *   The shop node ID.
   *
   * @return array
   *   An array of tag names.
   */
  protected function getShopProductTags($shop_id) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'product')
      ->condition('field_shop', $shop_id)
      ->condition('status', 1)
      ->accessCheck(TRUE);

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    $tags = [];
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    foreach ($nodes as $node) {
      if ($node->hasField('field_tags')) {
        foreach ($node->get('field_tags')->referencedEntities() as $term) {
          $tags[$term->id()] = $term->label();
        }
      }
    }

    // Return unique tags, sorted alphabetically.
    asort($tags);
    return $tags;
  }

}
