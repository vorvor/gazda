<?php

namespace Drupal\email_octopus\Form;

/**
 * @file
 * Contains \Drupal\email_octopus\Form\OctopusListForm.
 */

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\Error;

/**
 * URl for API list.
 */
define("URL", "https://emailoctopus.com/api/1.5/lists?api_key=");

/**
 * Class Octopus List Form.
 *
 * @package email_octopus
 */
class OctopusListForm extends FormBase {

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
    return 'octopus_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $values = NULL) {
    $form['table_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'table-wrapper'],
    ];
    $form['table_wrapper']['listid'] = [
      '#type' => 'select',
      '#title' => $this->t('Lists'),
      '#options' => $this->apiList(),
      '#required' => TRUE,
    ];
    $form['table_wrapper']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Subscribe/Unsubscribe'),
      '#options' => [
        'subscribed' => $this->t('Subscribed'),
        'unsubscribed' => $this->t('Unsubscribed'),
      ],
    ];
    $form['table_wrapper']['actions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => '::setMessage',
        'wrapper' => 'table-wrapper',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];
    $header = [
      'email' => $this->t('Email'),
      'status' => $this->t('Status'),
    ];
    $form['table_wrapper']['value'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $values,
      '#empty' => $this->t('No list selected'),
    ];
    return $form;
  }

  /**
   * Set Message.
   *
   * @param array $form
   *   Form Array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   *
   * @return object
   *   Returns form wrapper object.
   */
  public function setMessage(array $form, FormStateInterface $form_state) {
    return $form['table_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $header = [
      'email' => $this->t('Email'),
      'status' => $this->t('Status'),
    ];
    $form['table_wrapper']['value'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $this->data($form_state->getValue('listid'), $form_state->getValue('status')),
      '#empty' => $this->t('No users found.'),
    ];
  }

  /**
   * ApiList.
   *
   * @return array
   *   Return option array.
   */
  public function apiList() {
    $client = $this->httpClient;
    $key = $this->config('octopus.adminsettings')->get('api_key');
    if (empty($key)) {
      return [];
    }
    $options = [
      'timeout' => 300,
      'headers' => ['Content-Type' => 'application/json'],
    ];
    try {
      $response = $client->request('GET', URL . $key, $options);
      $json = json_decode($response->getBody()->getContents(), TRUE);
      if (empty($json['data'])) {
        $this->logger('Email Octopus')->warning('No data from API');
        return [];
      }
      else {
        $group = [];
        foreach ($json['data'] as $option) {
          if ($option['id'] != NULL || $option['name'] != NULL) {
            $group[$option['id']] = $option['name'];
          }
        }
        return $group;
      }
    }
    catch (\Exception $e) {
      Error::logException(\Drupal::logger('email_octopus'), $e);
      $this->messenger()->addMessage($e->getMessage(), MessengerInterface::TYPE_ERROR);
      return [];
    }

  }

  /**
   * Data.
   *
   * @param string $listid
   *   List Id.
   * @param string $status
   *   Status.
   *
   * @return string
   *   Return response.
   */
  public function data($listid, $status) {
    $data = [];
    $client = $this->httpClient;
    $key = $this->config('octopus.adminsettings')->get('api_key');
    $options = [
      'timeout' => 300,
      'headers' => ['Content-Type' => 'applifcation/json'],
    ];
    if ($listid != NULL) {
      $contacts = $client->request('GET', 'https://emailoctopus.com/api/1.5/lists/' . $listid . '/contacts/' . $status . '?api_key=' . $key, $options);
      $contactsJson = json_decode($contacts->getBody()->getContents(), TRUE);
      foreach ($contactsJson['data'] as $value) {
        $data[] = [
          'email' => $value['email_address'],
          'status' => $value['status'],
        ];
      }
      return $data;
    }
  }

}
