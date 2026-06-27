<?php

/**
 * @file
 * Alter table structure.
 */

/**
 * Implements hook_views_data_alter().
 */
function views_fields_combine_views_data_alter(array &$data) {
  $data['views']['fields_combine'] = [
    'title' => t('Combined fields'),
    'field' => [
      'title' => t('Combined fields'),
      'help' => t('Combine the output of multiple fields.'),
      'id' => 'field_combiner',
    ],
  ];
}
