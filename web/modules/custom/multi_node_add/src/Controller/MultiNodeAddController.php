<?php

namespace Drupal\multi_node_add\Controller;

use Drupal\Core\Language\LanguageInterface;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeTypeInterface;

/**
 * Controller for Multi Node Add.
 */
class MultiNodeAddController extends NodeController {

  /**
   * Provides links to specific bundle multi node add forms.
   */
  public function overview() {
    $content = [];

    // Only use node types the user has access to.
    foreach ($this->entityTypeManager()->getStorage('node_type')->loadMultiple() as $type) {
      if ($this->entityTypeManager()->getAccessControlHandler('node')->createAccess($type->id())) {
        $content[$type->id()] = $type;
      }
    }

    // Bypass the node/add listing if only one content type is available.
    if (count($content) == 1) {
      $type = array_shift($content);
      return $this->redirect('multi_node_add.add', ['node_type' => $type->id()]);
    }

    return [
      '#theme' => 'multi_node_add_list',
      '#content' => $content,
    ];
  }

  /**
   * Content of the iframe with the modified node form.
   */
  public function formPage(NodeTypeInterface $node_type = NULL) {
    $account = $this->currentUser();
    $langcode = $this->languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    $node = $this->entityTypeManager()->getStorage('node')->create([
      'uid' => $account->id(),
      'type' => $node_type->id(),
      'langcode' => $langcode,
    ]);

    return $this->entityFormBuilder()->getForm($node, 'default', ['multi_node_add_hijacked' => TRUE]);
  }

  /**
   * Status page after the node creation.
   */
  public function statusPage(NodeTypeInterface $node_type = NULL, $nid = NULL) {
    $node = $nid ? $this->entityTypeManager()->getStorage('node')->load($nid) : NULL;

    if (!$node) {
      return $this->renderBarePage([
        '#markup' => $this->t('The node could not be loaded.'),
      ]);
    }

    return $this->renderBarePage([
      '#type' => 'container',
      '#attributes' => ['class' => ['multi-node-add-status']],
      'message' => [
        '#markup' => $this->t('The node is created. Title: @title, node id: ', ['@title' => $node->label()]),
      ],
      'link' => [
        '#type' => 'link',
        '#title' => $node->id(),
        '#url' => $node->toUrl(),
        '#attributes' => [
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
        ],
      ],
    ]);
  }

  /**
   * Adds the module library to a small render array for the iFrame.
   */
  private function renderBarePage(array $build) {
    $build['#attached']['library'][] = 'multi_node_add/multi_node_add';
    return $build;
  }

}
