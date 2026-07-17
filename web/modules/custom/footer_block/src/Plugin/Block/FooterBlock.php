<?php

namespace Drupal\footer_block\Plugin\Block;

/**
 * Provides a 'Footer' Block.
 *
 * @Block(
 *   id = "footer_block",
 *   admin_label = @Translation("Footer Block"),
 *   category = @Translation("Custom")
 * )
 */
class FooterBlock extends \Drupal\Core\Block\BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'footer_block',
      '#content' => $this->t('This is the footer block content.'),
    ];
  }

}
