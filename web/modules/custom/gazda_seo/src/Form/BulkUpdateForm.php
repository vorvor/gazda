<?php

namespace Drupal\gazda_seo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Gazda SEO bulk update form.
 */
class BulkUpdateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gazda_seo_bulk_update_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $days = getExtraDays('2027');
    print_r($days);

    $form['description'] = [
      '#markup' => '<p>' . $this->t('Click the button below to update all nodes and fill empty image alt and title fields with the node title.') . '</p>',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Bulk Update'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    gazda_seo_update_image_alt_title();
  }

}
