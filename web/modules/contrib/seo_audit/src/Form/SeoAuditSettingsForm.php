<?php

namespace Drupal\seo_audit\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\EmailValidatorInterface;

/**
 * Form for configuring seo crawler settings.
 */
class SeoAuditSettingsForm extends ConfigFormBase {

  /**
   * The email validator.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected EmailValidatorInterface $emailValidator;

  /**
   * Constructs a new SeoAuditSettingsForm.
   *
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator.
   */
  public function __construct(EmailValidatorInterface $email_validator) {
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('email.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['seo_audit.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'seo_audit_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('seo_audit.settings');

    $form['crawl_settings'] = [
      '#type' => 'fieldset',
      '#title' => '<h6>' . $this->t('Crawl Settings') . '</h6>',
      '#description' => $this->t('Configure how the SEO audit crawler behaves.'),
    ];

    $form['crawl_settings']['crawl_concurrency'] = [
      '#type' => 'number',
      '#title' => $this->t('Concurrency'),
      '#description' => $this->t('Set the number of concurrent requests. Useful for speeding up the crawl, but keep it low to avoid overloading the server.'),
      '#default_value' => $config->get('crawl_concurrency') ?? 1,
      '#min' => 1,
      '#max' => 3,
    ];

    $form['crawl_settings']['crawl_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Crawl limit'),
      '#description' => $this->t('Maximum number of pages to crawl per run. Use -1 for unlimited.'),
      '#default_value' => $config->get('crawl_limit') ?? 100,
      '#min' => -1,
    ];

    $form['crawl_settings']['crawl_depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Crawl depth'),
      '#description' => $this->t('Maximum depth (number of link levels) to follow from the starting page. Helps avoid deep, irrelevant pages.'),
      '#default_value' => $config->get('crawl_depth') ?? 5,
      '#min' => 1,
      '#max' => 10,
    ];

    $form['seo_checks'] = [
      '#type' => 'fieldset',
      '#title' => '<h6>' . $this->t('SEO Checks to Include') . '</h6>',
      '#description' => $this->t('Select the elements you want to include in the SEO audit report.'),
    ];

    $form['seo_checks']['check_h1'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check for H1 tag'),
      '#default_value' => $config->get('check_h1') ?? TRUE,
      '#description' => $this->t('Each page should have one unique H1 tag to clearly indicate its main topic. Important for content structure and keyword targeting.'),
    ];

    $form['seo_checks']['check_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check for Title tag'),
      '#default_value' => $config->get('check_title') ?? TRUE,
      '#description' => $this->t('The title tag is a major SEO ranking factor. It appears in search engine results and should accurately describe the page content.'),
    ];

    $form['seo_checks']['check_meta_description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check for Meta Description'),
      '#default_value' => $config->get('check_meta_description') ?? TRUE,
      '#description' => $this->t('Meta descriptions influence click-through rates in search results. They summarize the page content.'),
    ];

    $form['seo_checks']['check_meta_robots'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check for Meta Robots tag'),
      '#default_value' => $config->get('check_meta_robots') ?? FALSE,
      '#description' => $this->t('Meta robots tags control whether a page should be indexed or followed by search engines. Critical for managing SEO visibility.'),
    ];

    $form['seo_checks']['check_img_alt'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check for Image Alt attributes'),
      '#default_value' => $config->get('check_img_alt') ?? FALSE,
      '#description' => $this->t('Alt attributes improve image SEO and accessibility. They describe images to search engines and screen readers.'),
    ];

    $form['seo_checks']['check_broken_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check for broken links'),
      '#default_value' => $config->get('check_broken_links') ?? FALSE,
      '#description' => $this->t('Identifies anchor links that return 404 or are unreachable. Important for user experience and crawlability.'),
    ];

    $form['seo_checks']['visual_breadcrumb'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check visual breadcrumb'),
      '#default_value' => $this->config('seo_audit.settings')->get('visual_breadcrumb'),
      '#description' => $this->t('Enable checking for the presence of visual breadcrumb markup.'),
    ];

    $form['seo_checks']['jsonld_breadcrumb'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check JSON-LD breadcrumb'),
      '#default_value' => $this->config('seo_audit.settings')->get('jsonld_breadcrumb'),
      '#description' => $this->t('Enable checking for the presence of JSON-LD breadcrumb structured data.'),
    ];

    $form['crawl_notify'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['crawl-notify-wrapper']],

      'crawl_notify_enabled' => [
        '#type' => 'checkbox',
        '#title' => '<h6>' . $this->t('Notify on Crawl Completion') . '</h6>',
        '#description' => $this->t('Send an email to specified addresses when a crawl is completed.'),
        '#default_value' => $config->get('crawl_notify_enabled') ?? FALSE,
      ],

      'crawl_notify_emails' => [
        '#type' => 'textfield',
        '#title' => $this->t('Notification Emails'),
        '#description' => $this->t('Enter comma-separated email addresses to notify when the crawl is complete.'),
        '#default_value' => $config->get('crawl_notify_emails') ?? '',
        '#placeholder' => 'email1@example.com, email2@example.com',
        '#states' => [
          'visible' => [
            ':input[name="crawl_notify_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $emails = array_map('trim', explode(',', $form_state->getValue('crawl_notify_emails')));
    foreach ($emails as $email) {
      if (!empty($email) && !$this->emailValidator->isValid($email)) {
        $form_state->setErrorByName('crawl_notify_emails', $this->t('The email address %email is not valid.', ['%email' => $email]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('seo_audit.settings')
      ->set('crawl_concurrency', $form_state->getValue('crawl_concurrency'))
      ->set('crawl_limit', $form_state->getValue('crawl_limit'))
      ->set('crawl_depth', $form_state->getValue('crawl_depth'))
      ->set('check_h1', $form_state->getValue('check_h1'))
      ->set('check_title', $form_state->getValue('check_title'))
      ->set('check_meta_description', $form_state->getValue('check_meta_description'))
      ->set('check_meta_robots', $form_state->getValue('check_meta_robots'))
      ->set('check_img_alt', $form_state->getValue('check_img_alt'))
      ->set('check_broken_links', $form_state->getValue('check_broken_links'))
      ->set('visual_breadcrumb', $form_state->getValue('visual_breadcrumb'))
      ->set('jsonld_breadcrumb', $form_state->getValue('jsonld_breadcrumb'))
      ->set('crawl_notify_enabled', $form_state->getValue('crawl_notify_enabled'))
      ->set('crawl_notify_emails', $form_state->getValue('crawl_notify_emails'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
