<?php

namespace Drupal\email_octopus\Form;

/**
 * @file
 * Contains \Drupal\email_octopus\Form\OctopusSubscribeForm.
 */

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Octopus Subscribe Form.
 *
 * @package email_octopus
 */
class OctopusSubscribeForm extends FormBase {

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'octopus_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $listid = NULL, $title = NULL, $body = NULL, $message = NULL) {
    $form['content'] = [
      '#markup' => $this->t('<div class="newsletter-sub"><h3>@title</h3><p>@body</p><span class="button subscribe-button">Subscribe</span></div>', [
        '@title' => $title,
        '@body' => $body,
      ]),
    ];
    $form['dest_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'dest-wrapper'],
    ];
    $form['dest_wrapper']['back_btn'] = [
      '#markup' => '<div class="back-subs-btn">&nbsp;</div>',
    ];
    $form['dest_wrapper']['value'] = [
      '#markup' => ' ',
    ];
    $form['dest_wrapper']['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter Email'),
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['dest_wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#prefix' => '<div class="subs-button">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => '::updateTo',
        'wrapper' => 'dest-wrapper',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];
    $form['listid'] = [
      '#type' => 'hidden',
      '#default_value' => $listid,
    ];
    $form['thankyou_message'] = [
      '#type' => 'hidden',
      '#default_value' => $message,
    ];
    $form['#theme'] = 'octopus_subscribe_form';
    $form['#attached']['library'][] = 'email_octopus/email_octopus';
    return $form;
  }

  /**
   * Update To.
   *
   * @param array $form
   *   Form Array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   *
   * @return object
   *   Returns form wrapper object.
   */
  public function updateTo(array $form, FormStateInterface $form_state) {
    return $form['dest_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('email') == NULL) {
      $form_state->setErrorByName('email', $this->t('Please enter email'));
    }
    elseif (!filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('email', $this->t('Please enter valid email'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getValue('email'))) {
      $status = 0;
      $response = $this->subscribe($form_state->getValue('email'), $form_state->getValue('listid'));
      switch ($response) {
        case '0':
          // No API Key or Internal Error.
          $status = 0;
          break;

        case '1':
          // Success.
          $status = 1;
          break;

        case '2':
          // Already a subscriber.
          $status = 2;
          break;
      }
      if ($status == 0) {
        $this->messenger()->addMessage($this->t('Please try again later'), MessengerInterface::TYPE_ERROR);
      }
      elseif ($status == 2) {
        $this->messenger()->addMessage($this->t('Already a Subscriber'), MessengerInterface::TYPE_ERROR);
      }
      else {
        unset($form['dest_wrapper']['email']);
        unset($form['dest_wrapper']['submit']);
        unset($form['dest_wrapper']['back_btn']);
        $form['dest_wrapper']['value'] = [
          '#markup' => $form_state->getValue('thankyou_message'),
        ];
      }
    }
  }

  /**
   * Subscribe.
   *
   * @param string $email
   *   Email-Id Subscriber.
   * @param string $listid
   *   List Id.
   *
   * @return string
   *   Return response.
   */
  public function subscribe($email, $listid) {
    $data = '';
    $key = $this->config('octopus.adminsettings')->get('api_key');
    $client = $this->httpClient;
    if ($key) {
      $options = [
        'timeout' => 300,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
          "api_key" => $key,
          "email_address" => $email,
          "status" => "SUBSCRIBED",
        ]),
      ];
      try {
        $client->request('POST', 'https://emailoctopus.com/api/1.5/lists/' . $listid . '/contacts', $options);
        $data = '1';
        return $data;
      }
      catch (\Exception $e) {
        $json = $e->getResponse()->getBody()->getContents();
        $responseArray = json_decode($json, TRUE);
        if ($responseArray['error']['code'] == 'MEMBER_EXISTS_WITH_EMAIL_ADDRESS') {
          $data = '2';
          return $data;
        }
        else {
          $data = '0';
          return $data;
        }
      }
    }
    else {
      $data = '0';
      return $data;
    }
  }

}
