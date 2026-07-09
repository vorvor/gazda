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
      '#maxlength' => 512,
    ];

    $form['chatgpt_prompt_marketing'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt marketing'),
      '#default_value' => $config->get('chatgpt_prompt_marketing'),
      '#required' => TRUE,
      '#rows' => 100,
    ];

    $form['chatgpt_prompt_desc'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt description'),
      '#default_value' => $config->get('chatgpt_prompt_desc'),
      '#required' => TRUE,
      '#rows' => 100,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $api_key = trim($form_state->getValue('chatgpt_api_key') ?? '');
    $prompt_marketing = trim($form_state->getValue('chatgpt_prompt_marketing') ?? '');
    $prompt_desc = trim($form_state->getValue('chatgpt_prompt_desc') ?? '');
    $this->config('generate_marketing_text.settings')
      ->set('chatgpt_api_key', $api_key)
      ->set('chatgpt_prompt_marketing', $prompt_marketing)
      ->set('chatgpt_prompt_desc', $prompt_desc)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
