<?php

namespace Drupal\generate_marketing_text\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Marketing Text Generator settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'generate_marketing_text_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['generate_marketing_text.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('generate_marketing_text.settings');

    $form['chatgpt_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ChatGPT API Key'),
      '#default_value' => $config->get('chatgpt_api_key'),
      '#required' => TRUE,
      '#maxlength' => 256,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $api_key = trim($form_state->getValue('chatgpt_api_key') ?? '');
    $this->config('generate_marketing_text.settings')
      ->set('chatgpt_api_key', $api_key)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
