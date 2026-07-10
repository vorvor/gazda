<?php

namespace Drupal\seo_analyzer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Implements a KeywordForm form.
 */
class KeywordForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'seo_analyzer_keyword_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['keyword'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keyword or keyphrase'),
      '#description' => $this->t('The page content will be checked against this keyword to see how well it does for SEO.'),
      '#default_value' => 'keyword',
    ];
    if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
      $form['keyword']['#default_value'] = $_GET['keyword'];
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check SEO for keyword'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $keyword = $form_state->getValue('keyword');
    $url = Url::fromRoute('<current>');
    $url->setOptions(array('query' => ['keyword' => $keyword]));
    $form_state->setRedirectUrl($url);
  }

}
