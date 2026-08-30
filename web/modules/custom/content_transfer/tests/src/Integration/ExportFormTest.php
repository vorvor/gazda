<?php

declare(strict_types=1);

use Drupal\content_transfer\Form\ExportForm;
use Drupal\Core\Form\FormState;

function export_form_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
$expectedIds = array_values($storage->getQuery()
  ->accessCheck(TRUE)
  ->condition('type', 'product')
  ->sort('changed', 'DESC')
  ->sort('nid', 'DESC')
  ->execute());
export_form_assert($expectedIds !== [], 'The fixture site must contain products.');

$formObject = ExportForm::create(\Drupal::getContainer());
$form = $formObject->buildForm([], new FormState());

export_form_assert(($form['nodes']['#type'] ?? NULL) === 'checkboxes', 'Products must be displayed as checkboxes.');
$actualIds = array_map('intval', array_keys($form['nodes']['#options'] ?? []));
export_form_assert($actualIds === array_map('intval', $expectedIds), 'Products must be ordered by changed date descending, then node ID descending.');

$nodes = $storage->loadMultiple($actualIds);
$checkedShopLabels = 0;
foreach ($actualIds as $nodeId) {
  export_form_assert(isset($nodes[$nodeId]) && $nodes[$nodeId]->bundle() === 'product', 'The checkbox list must contain only product nodes.');
  foreach ($nodes[$nodeId]->get('field_shop')->referencedEntities() as $shop) {
    export_form_assert(str_contains((string) $form['nodes']['#options'][$nodeId], $shop->label()), 'Each related shop name must be displayed next to its product.');
    $checkedShopLabels++;
  }
}
export_form_assert($checkedShopLabels > 0, 'The fixture products must contain related shops.');

print "PASS: export form lists products by latest change with related shops\n";
