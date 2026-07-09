<?php

namespace Drupal\multi_node_add\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeTypeInterface;

/**
 * Form to launch Multi Node Add process.
 */
class MultiNodeAddForm extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'multi-node-add';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current state of the form.
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   Selected node type.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeTypeInterface $node_type = NULL) {
    $form['#attached']['drupalSettings']['multiNodeAdd'] = [
      'callback' => Url::fromRoute('multi_node_add.frame', [
        'node_type' => $node_type->id(),
        'fields' => '#fields',
      ])->toString(),
    ];

    $form['#attached']['library'][] = 'multi_node_add/multi_node_add';

    $prefilled = FALSE;
    $query = \Drupal::request()->query;
    if ($query->has('fields') && $query->has('num')) {
      $fields = array_filter(explode(',', (string) $query->get('fields')), static function ($field) {
        return preg_match('/^[a-z0-9_]+$/', $field);
      });
      $num = (string) $query->get('num');

      if (!empty($fields) && preg_match('/^[1-9][0-9]*$/', $num)) {
        $form['#attached']['drupalSettings']['multiNodeAddPreload'] = [
          'fields' => array_values($fields),
          'num' => $num,
        ];
        $prefilled = TRUE;
      }
    }

    if (!$prefilled) {
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $node_type->id());
      $req_val = [];
      $field_req = [];
      $field_names = [];

      foreach ($fields as $field_name => $entry) {
        if (!$entry->isReadOnly() && !$entry->isComputed()) {
          if ($entry->isRequired()) {
            $field_req[$field_name] = $entry->getLabel();
            $req_val[$field_name] = $field_name;
          }
          else {
            $field_names[$field_name] = $entry->getLabel();
          }
        }
      }

      $form['hint']['#markup'] = '<div class="warning messages js">' . $this->t('Multi Node Add requires Javascript to provide the needed functionality') . '</div>';
      $form['info']['#markup'] = '<p>' . $this->t('Current content-type: %type', ['%type' => $node_type->label()]) . '</p>';
      if (!empty($field_req)) {
        $form['fields_req'] = [
          '#type' => 'checkboxes',
          '#options' => $field_req,
          '#default_value' => $req_val,
          '#title' => $this->t('Mandatory fields'),
          '#attributes' => ['class' => ['multi-node-add']],
          '#disabled' => TRUE,
        ];
      }
      if (!empty($field_names)) {
        $form['fields_to_utilize'] = [
          '#type' => 'checkboxes',
          '#options' => $field_names,
          '#title' => $this->t('Fields to manage'),
          '#attributes' => ['class' => ['multi-node-add']],
          '#description' => $this->t('Choose those fields that you would like to edit on the new nodes'),
        ];
      }

      // If there are no available fields, we should not offer a form.
      if (empty($field_names) && empty($field_req)) {
        $this->messenger()->addWarning($this->t('Unable to generate multiple nodes for this content type (failed to detect usable fields).'));
        return $form;
      }

      $form['number'] = [
        '#type' => 'textfield',
        '#default_value' => 2,
        '#size' => 2,
        '#required' => TRUE,
        '#title' => $this->t('Number of rows'),
      ];
      $form['show'] = [
        '#type' => 'button',
        '#value' => $this->t('Show'),
      ];
      $form['shortcut'] = [
        '#type' => 'button',
        '#value' => $this->t('Get shortcut URL'),
      ];
    }

    $common_attr = [
      '#attributes' => [
        'class' => [
          'second-step',
        ],
      ],
    ];
    $form['addmore'] = [
      '#type' => 'button',
      '#value' => $this->t('Add 2 more nodes'),
    ] + $common_attr;
    $form['create'] = [
      '#type' => 'button',
      '#value' => $this->t('Create all nodes'),
    ] + $common_attr;
    $form['prepopulate'] = [
      '#type' => 'button',
      '#value' => $this->t('Prepopulate based on first form'),
    ] + $common_attr;
    $form['placeholder']['#markup'] = '<div id="multi_node_add_frames"></div>';

    return $form;
  }

  /**
   * Form submission handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This form never gets submitted, really.
    // Everything is handled by Javascript.
  }

}
