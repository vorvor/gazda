<?php

namespace Drupal\cultural_program_import\Plugin\views\exposed_form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsExposedForm;
use Drupal\views\Plugin\views\exposed_form\Basic;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides date controls and a current-date default for cultural programs.
 */
#[ViewsExposedForm(
  id: 'cultural_program_date',
  title: new TranslatableMarkup('Cultural program date filters'),
  help: new TranslatableMarkup('Date pickers with the current date as the default start date')
)]
final class CulturalProgramDate extends Basic implements ContainerFactoryPluginInterface {

  /**
   * Constructs a CulturalProgramDate exposed form plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly TimeInterface $time,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function renderExposedForm($block = FALSE) {
    $input = $this->view->getExposedInput();
    if (!array_key_exists('field_program_start_value', $input)) {
      $timezone = $this->configFactory->get('system.date')->get('timezone.default') ?: NULL;
      $input['field_program_start_value'] = $this->dateFormatter->format(
        $this->time->getCurrentTime(),
        'custom',
        'Y-m-d',
        $timezone,
      );
      $this->view->setExposedInput($input);
    }

    return parent::renderExposedForm($block);
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(&$form, FormStateInterface $form_state) {
    parent::exposedFormAlter($form, $form_state);

    foreach (['field_program_start_value', 'field_program_end_value'] as $element_name) {
      if (isset($form[$element_name])) {
        $form[$element_name]['#type'] = 'date';
        unset($form[$element_name]['#placeholder']);
      }
    }
  }

}
