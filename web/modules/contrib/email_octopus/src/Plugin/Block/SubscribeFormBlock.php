<?php

namespace Drupal\email_octopus\Plugin\Block;

/**
 * @file
 * Contains \Drupal\email_octopus\Plugin\Block\EmailOctopusFormBlock.
 */

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Renderer;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\Error;

/**
 * URl for API list.
 */
define("URL", "https://emailoctopus.com/api/1.5/lists?api_key=");

/**
 * Provides a 'Email Octopus Subscribe Form' block.
 *
 * @Block(
 *   id = "email_octopus_subscribe_form_block",
 *   admin_label = @Translation("Email Octopus Subscribe Form"),
 *   category = @Translation("Custom Block")
 * )
 */
class SubscribeFormBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Contains the configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configfactory;

  /**
   * The logger channel factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Renderer $renderer, ClientInterface $http_client, ConfigFactoryInterface $config_factory, LoggerChannelFactory $loggerFactory, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->httpClient = $http_client;
    $this->configfactory = $config_factory;
    $this->loggerFactory = $loggerFactory;
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form['api_list'] = [
      '#type' => 'select',
      '#title' => $this->t('Lists'),
      '#options' => $this->apiList(),
      '#default_value' => $config['api_list'] ?? '',
      '#required' => TRUE,
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Block Title'),
      '#description' => $this->t('Heading for block'),
      '#default_value' => $config['title'] ?? '',
      '#required' => TRUE,
    ];
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Block Body'),
      '#description' => $this->t('Content'),
      '#default_value' => $config['body'] ?? '',
      '#required' => TRUE,
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Thankyou Message'),
      '#description' => $this->t('Message for user on successful registration.'),
      '#default_value' => $config['message'] ?? '',
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('api_list', $form_state->getValue('api_list'));
    $this->setConfigurationValue('title', $form_state->getValue('title'));
    $this->setConfigurationValue('body', $form_state->getValue('body'));
    $this->setConfigurationValue('message', $form_state->getValue('message'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $apilist = $config['api_list'];
    $title = $config['title'];
    $body = $config['body'];
    $message = $config['message'];
    $build = [];
    $form = $this->formBuilder->getForm('\Drupal\email_octopus\Form\OctopusSubscribeForm', $apilist, $title, $body, $message);
    $build['#markup'] = $this->renderer->render($form);
    $build['#cache'] = ['max-age' => 0];
    return $build;
  }

  /**
   * API List.
   *
   * @return array
   *   Return options array.
   */
  public function apiList() {
    $client = $this->httpClient;
    $key = $this->configfactory->getEditable('octopus.adminsettings')->get('api_key');
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
        $this->loggerFactory->get('Email Octopus')->warning('No data from API');
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

}
