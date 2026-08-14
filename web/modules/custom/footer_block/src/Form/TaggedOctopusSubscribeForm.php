<?php

declare(strict_types=1);

namespace Drupal\footer_block\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\footer_block\EmailOctopusContactPayload;

/**
 * Adds the setaljbe tag to footer newsletter subscribers.
 */
final class TaggedOctopusSubscribeForm extends \Drupal\email_octopus\Form\OctopusSubscribeForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $listid = NULL,
    $title = NULL,
    $body = NULL,
    $message = NULL,
  ) {
    $form = parent::buildForm(
      $form,
      $form_state,
      $listid,
      $title,
      $body,
      $message,
    );

    $form['content'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['newsletter-sub']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $title,
      ],
      'body' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $body,
      ],
      'subscribe' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Feliratkozom'),
        '#attributes' => [
          'class' => ['button', 'subscribe-button'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe($email, $listid) {
    $api_key = (string) $this->config('octopus.adminsettings')->get('api_key');
    if ($api_key === '') {
      return '0';
    }

    try {
      $this->httpClient->request('POST', 'https://emailoctopus.com/api/1.6/lists/' . $listid . '/contacts', [
        'timeout' => 300,
        'headers' => ['Content-Type' => 'application/json'],
        'json' => EmailOctopusContactPayload::create($api_key, (string) $email),
      ]);
      return '1';
    }
    catch (\Throwable $exception) {
      if (method_exists($exception, 'getResponse') && $exception->getResponse()) {
        $response = json_decode((string) $exception->getResponse()->getBody(), TRUE);
        if (($response['error']['code'] ?? NULL) === 'MEMBER_EXISTS_WITH_EMAIL_ADDRESS') {
          return '2';
        }
      }
      return '0';
    }
  }

}
