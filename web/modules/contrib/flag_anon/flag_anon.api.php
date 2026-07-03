<?php

/**
 * @file
 * Hooks provided by the Flag anonymous module.
 */

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\flag\FlagInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter anonymous message placeholders list.
 *
 * @param array $placeholders
 *   Placeholders list, usage in FormattableMarkup as arguments.
 * @param \Drupal\flag\FlagInterface $flag
 *   The Flag entity.
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The flaggable entity.
 */
function hook_flag_anon_message_placeholders_alter(array &$placeholders, FlagInterface $flag, EntityInterface $entity) {

  // After adding this code you can use @my_routed_placeholder
  // in "Message" field "Anonymous settings" section.
  $placeholders['@my_routed_placeholder'] = Link::createFromRoute(
    t('Routed placeholder'),
    'user.login',
    // Paste route params here.
    [],
    [
      'attributes' => [
        'title' => t('My routed placeholder example'),
        'class' => ['my-routed-placeholder'],
        'data-some-value' => 'Lorem ipsum',
      ],
    ]
  )->toString();

  // After adding this code you can use @my_url_placeholder
  // in "Message" field "Anonymous settings" section.
  $placeholders['@my_url_placeholder'] = Link::fromTextAndUrl(
    t('Url placeholder'),
    Url::fromUserInput(
      '/user',
      [
        'attributes' => [
          'title' => t('My url placeholder example'),
          'class' => ['my-url-placeholder'],
          'data-some-value' => 'Lorem ipsum',
        ],
      ]
    )
  )->toString();

}
