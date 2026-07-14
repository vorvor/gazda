<?php

namespace Drupal\advanced_block_visibility\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Mobile device' condition for block visibility.
 *
 * @Condition(
 *   id = "mobile_device",
 *   label = @Translation("Mobile device"),
 * )
 */
class MobileDevice extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_merge(parent::defaultConfiguration(), [
      'display_mobile' => TRUE,
      'display_tablet' => TRUE,
      'display_desktop' => TRUE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['display_mobile'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display on mobile devices'),
      '#description' => $this->t('If checked, the block will be displayed on mobile phones.'),
      '#default_value' => $this->configuration['display_mobile'],
    ];

    $form['display_tablet'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display on tablets'),
      '#description' => $this->t('If checked, the block will be displayed on tablet devices.'),
      '#default_value' => $this->configuration['display_tablet'],
    ];

    $form['display_desktop'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display on desktops'),
      '#description' => $this->t('If checked, the block will be displayed on desktop devices.'),
      '#default_value' => $this->configuration['display_desktop'],
    ];

    $form['description'] = [
      '#markup' => $this->t('Select which device types the block should be displayed on. Uncheck to hide on that device type.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['display_mobile'] = (bool) $form_state->getValue('display_mobile');
    $this->configuration['display_tablet'] = (bool) $form_state->getValue('display_tablet');
    $this->configuration['display_desktop'] = (bool) $form_state->getValue('display_desktop');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $detect = new \Detection\MobileDetect();

    // If no options are enabled, the block is hidden (return FALSE).
    if (!$this->configuration['display_mobile'] && !$this->configuration['display_tablet'] && !$this->configuration['display_desktop']) {
      return FALSE;
    }

    $is_mobile = $detect->isMobile() && !$detect->isTablet();
    $is_tablet = $detect->isTablet();
    $is_desktop = !$detect->isMobile();

    // Return TRUE if the device matches an enabled display option.
    return ($this->configuration['display_mobile'] && $is_mobile) ||
           ($this->configuration['display_tablet'] && $is_tablet) ||
           ($this->configuration['display_desktop'] && $is_desktop);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $display_types = [];
    if ($this->configuration['display_mobile']) {
      $display_types[] = $this->t('mobile devices');
    }
    if ($this->configuration['display_tablet']) {
      $display_types[] = $this->t('tablets');
    }
    if ($this->configuration['display_desktop']) {
      $display_types[] = $this->t('desktops');
    }
    if (empty($display_types)) {
      return $this->t('Block is hidden on all devices');
    }
    return $this->t('Block is displayed on @devices', ['@devices' => implode(', ', $display_types)]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['device_type'];
  }

}
