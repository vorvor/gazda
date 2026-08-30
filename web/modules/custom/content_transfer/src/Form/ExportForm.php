<?php

declare(strict_types=1);

namespace Drupal\content_transfer\Form;

use Drupal\content_transfer\ContentExporter;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
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
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('content_transfer.exporter'),
      $container->get('file_system'),
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
      '#markup' => '<p>' . $this->t('Válaszd ki az exportálandó tartalmakat. A csomag tartalmazza az összes mezőértéket, a közvetlenül hivatkozott média entitásokat és a kapcsolódó fájlokat.') . '</p>',
    ];
    $form['nodes'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Tartalmak'),
      '#target_type' => 'node',
      '#tags' => TRUE,
      '#required' => TRUE,
      '#description' => $this->t('Kezdj el gépelni, majd válassz ki egy vagy több tartalmat.'),
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
    $nodeIds = array_column($form_state->getValue('nodes'), 'target_id');
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
