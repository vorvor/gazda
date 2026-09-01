<?php

declare(strict_types=1);

namespace Drupal\cultural_program_import\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Renders an original source image URL without downloading the image.
 */
#[FieldFormatter(
  id: 'cultural_program_remote_image',
  label: new TranslatableMarkup('Original remote program image'),
  field_types: ['link'],
)]
final class RemoteProgramImageFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['link_to_content' => FALSE] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements['link_to_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link image to content'),
      '#default_value' => $this->getSetting('link_to_content'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->getSetting('link_to_content')
        ? $this->t('Links to the content page')
        : $this->t('Displays the remote image without a link'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $entity = $items->getEntity();

    foreach ($items as $delta => $item) {
      $uri = trim((string) $item->uri);
      $scheme = mb_strtolower((string) parse_url($uri, PHP_URL_SCHEME));
      if (!UrlHelper::isValid($uri, TRUE) || !in_array($scheme, ['http', 'https'], TRUE)) {
        continue;
      }

      $image = [
        '#theme' => 'image',
        '#uri' => $uri,
        '#alt' => $entity->label(),
        '#attributes' => [
          'loading' => 'lazy',
          'decoding' => 'async',
          'referrerpolicy' => 'no-referrer-when-downgrade',
        ],
      ];
      if ($this->getSetting('link_to_content') && !$entity->isNew()) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $image,
          '#url' => $entity->toUrl(),
          '#attributes' => [
            'class' => ['cultural-program-remote-image'],
            'aria-label' => $this->t('@title program részletei', ['@title' => $entity->label()]),
          ],
        ];
      }
      else {
        $elements[$delta] = $image;
      }
    }

    return $elements;
  }

}
