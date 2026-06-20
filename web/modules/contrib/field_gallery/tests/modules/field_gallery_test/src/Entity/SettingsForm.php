<?php

namespace Drupal\field_gallery_test\Entity;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class GrainSettingsForm.
 */
class SettingsForm extends FormBase {

  /**
   * Get From ID.
   */
  public function getFormId() {
    return 'field_gallery_test_entity_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Empty implementation of the abstract submit class.
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['vehicle_settings']['#markup'] = 'Settings form for the entity. Manage field settings here.';
    return $form;
  }

}
