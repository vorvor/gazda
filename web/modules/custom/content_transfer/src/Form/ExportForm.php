<?php

declare(strict_types=1);

namespace Drupal\content_transfer\Form;

use Drupal\content_transfer\ContentExporter;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Builds the portable content export form.
 */
final class ExportForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    protected ContentExporter $exporter,
    protected FileSystemInterface $fileSystem,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('content_transfer.exporter'),
      $container->get('file_system'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'content_transfer_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Válaszd ki az exportálandó termékeket. A csomag tartalmazza az összes mezőértéket, a közvetlenül hivatkozott média entitásokat és a kapcsolódó fájlokat.') . '</p>',
    ];

    $storage = $this->entityTypeManager->getStorage('node');
    $nodeIds = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'product')
      ->sort('changed', 'DESC')
      ->sort('nid', 'DESC')
      ->execute();
    $nodes = $storage->loadMultiple($nodeIds);

    $shopIds = [];
    foreach ($nodes as $node) {
      if ($node->hasField('field_shop')) {
        $shopIds = array_merge(
          $shopIds,
          array_column($node->get('field_shop')->getValue(), 'target_id'),
        );
      }
    }
    $shops = $storage->loadMultiple(array_unique($shopIds));

    $options = [];
    foreach ($nodeIds as $nodeId) {
      if (isset($nodes[$nodeId])) {
        $shopLabels = [];
        $shopReferences = $nodes[$nodeId]->hasField('field_shop')
          ? $nodes[$nodeId]->get('field_shop')->getValue()
          : [];
        foreach ($shopReferences as $reference) {
          $shopId = $reference['target_id'] ?? NULL;
          if ($shopId !== NULL && isset($shops[$shopId])) {
            $shopLabels[] = $shops[$shopId]->label();
          }
        }
        $options[$nodeId] = $this->t('@title — Üzlet: @shop (ID: @id)', [
          '@title' => $nodes[$nodeId]->label(),
          '@shop' => $shopLabels === [] ? $this->t('nincs megadva') : implode(', ', $shopLabels),
          '@id' => $nodeId,
        ]);
      }
    }

    $form['nodes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Termékek'),
      '#options' => $options,
      '#required' => TRUE,
      '#description' => $this->t('A legutóbb módosított termékek szerepelnek elöl.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('ZIP csomag letöltése'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $nodeIds = array_values(array_filter(
      $form_state->getValue('nodes'),
      static fn ($value): bool => (int) $value > 0,
    ));
    $path = $this->fileSystem->tempnam($this->fileSystem->getTempDirectory(), 'content-transfer-');
    if ($path === FALSE) {
      throw new \RuntimeException('Unable to create a temporary export file.');
    }

    try {
      $this->exporter->exportNodes($nodeIds, $path);
      $filename = 'content-transfer-' . gmdate('Ymd-His') . '.zip';
      $response = new BinaryFileResponse($path);
      $response->headers->set('Content-Type', 'application/zip');
      $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename));
      $response->deleteFileAfterSend(TRUE);
      $form_state->setResponse($response);
    }
    catch (\Throwable $exception) {
      if (is_file($path)) {
        unlink($path);
      }
      $this->messenger()->addError($this->t('Az export nem sikerült: @message', ['@message' => $exception->getMessage()]));
    }
  }

}
