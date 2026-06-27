<?php

namespace Drupal\views_fields_combine\Plugin\views\field;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("field_combiner")
 */
class FieldCombiner extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   *
   * @{inheritdoc}
   */
  public function query(): void {
    // Leave empty to avoid a query on this field.
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order): void {
    $combined_fields = array_filter($this->options['combined_fields']);
    if (count($combined_fields) === 0) {
      return;
    }

    $separator = Xss::filter($this->options['separator'], $this->options['separator_allowed_tags']);
    $formula = "CONCAT_WS('" . $separator . "', " . implode(', ', array_keys($combined_fields)) . ')';

    // @phpstan-ignore-next-line
    $this->query->addOrderBy(NULL, $formula, $order);
  }

  /**
   * Define the available options.
   *
   * @return array<string, mixed>
   *   Return the result in array format.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['combined_fields'] = ['default' => []];
    $options['separator'] = ['default' => ''];
    $seperator_allowed_tags = [
      'default' => [
        'a',
        'em',
        'strong',
        'span',
        'br',
      ],
    ];
    // Allow other modules to alter the list of tags.
    $this->moduleHandler->alter('views_fields_combine_seperator_allowed_tags', $seperator_allowed_tags);
    $options['separator_allowed_tags'] = $seperator_allowed_tags;
    $options['hide_empty_combined_fields'] = ['default' => TRUE];
    $options['combined_fields_add_labels'] = ['default' => FALSE];
    return $options;
  }

  /**
   * Provide the options form.
   *
   * @param array<mixed> $form
   *   The form object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $options = $this->getPreviousFieldLabels();

    $form['combined_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Combined fields'),
      '#options' => $options,
      '#default_value' => $this->options['combined_fields'],
      '#prefix' => '<div id="edit-row-options-combined-wrapper"><div>',
      '#suffix' => '</div></div>',
    ];

    $form['separator'] = [
      '#title' => $this->t('Separator'),
      '#type' => 'textfield',
      '#size' => 10,
      '#default_value' => $this->options['separator'] ?? '',
      '#description' => $this->t('The separator may be placed between combined fields to keep them from squishing up next to each other. You may use some basic HTML tags (@tags) in this field.', ['@tags' => implode(', ', $this->options['separator_allowed_tags'])]),
    ];

    $form['hide_empty_combined_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide empty fields'),
      '#default_value' => $this->options['hide_empty_combined_fields'] ?? FALSE,
      '#description' => $this->t('Do not display fields, labels or markup for fields that are empty.'),
    ];

    $form['combined_fields_add_labels'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add field labels'),
      '#default_value' => $this->options['combined_fields_add_labels'] ?? FALSE,
      '#description' => $this->t('Add field labels to output. A colon separates each label from its corresponding value by default. You may change this by translate the string "@combined_field_label:" in context "views_fields_combine".'),
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * Render the result.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $output = [];
    $combined_fields = array_filter($this->options['combined_fields']);
    $fields = $this->displayHandler->getHandlers('field');

    /** @var \Drupal\views\Plugin\views\field\FieldPluginBase $handler */
    foreach (array_intersect_key($fields, $combined_fields) as $handler) {
      if (isset($handler->last_render)) {
        $rendered = $handler->last_render;
        if ($this->options['hide_empty_combined_fields'] && empty($rendered)) {
          continue;
        }
        $field_value = htmlspecialchars_decode($rendered);

        if ($this->options['combined_fields_add_labels']) {
          $t_options = [
            'context' => 'views_fields_combine',
          ];
          $definition = $handler->definition;
          if (isset($definition['title']) && !empty($definition['title'])) {
            $t_arguments = [
              '@combined_field_label' => $definition['title'],
            ];
            $field_value = $this->t('@combined_field_label:', $t_arguments, $t_options) . ' ' . $field_value;
          }
        }

        $output[] = $field_value;
      }
    }
    $separator = Xss::filter($this->options['separator'], $this->options['separator_allowed_tags']);
    return ViewsRenderPipelineMarkup::create(implode($separator, $output));
  }

}
