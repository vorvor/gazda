<?php

declare(strict_types=1);

namespace Drupal\content_transfer\Form;

use Drupal\content_transfer\ContentImporter;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the portable content import form.
 */
final class ImportForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    private readonly ContentImporter $importer,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('content_transfer.importer'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'content_transfer_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Tölts fel egy Content Transfer ZIP csomagot. A tartalomtípusoknak, médiatípusoknak és mezőknek már létezniük kell ezen a webhelyen.') . '</p>',
    ];
    $form['archive'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Exportcsomag'),
      '#required' => TRUE,
      '#upload_location' => 'temporary://content-transfer-uploads',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'zip'],
        'FileSizeLimit' => ['fileLimit' => 104857600],
      ],
    ];
    $form['update_existing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Meglévő entitások frissítése'),
      '#description' => $this->t('Az egyezés UUID alapján történik. Ha nincs bejelölve, a már létező entitások kimaradnak.'),
      '#default_value' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Csomag importálása'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fileIds = $form_state->getValue('archive');
    $file = $fileIds === [] ? NULL : $this->entityTypeManager->getStorage('file')->load(reset($fileIds));
    if (!$file instanceof FileInterface) {
      $this->messenger()->addError($this->t('A feltöltött csomag nem található.'));
      return;
    }

    try {
      $path = $this->fileSystem->realpath($file->getFileUri());
      if ($path === FALSE) {
        throw new \RuntimeException('The uploaded package cannot be read.');
      }
      $result = $this->importer->import($path, (bool) $form_state->getValue('update_existing'));
      $created = array_sum($result['created']);
      $updated = array_sum($result['updated']);
      $skipped = array_sum($result['skipped']);
      $this->messenger()->addStatus($this->t('Az import befejeződött. Létrehozva: @created, frissítve: @updated, kihagyva: @skipped.', [
        '@created' => $created,
        '@updated' => $updated,
        '@skipped' => $skipped,
      ]));
    }
    catch (\Throwable $exception) {
      $this->messenger()->addError($this->t('Az import nem sikerült: @message', ['@message' => $exception->getMessage()]));
    }
    finally {
      $file->delete();
    }
  }

}
