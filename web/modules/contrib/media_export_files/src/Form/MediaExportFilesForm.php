<?php

namespace Drupal\media_export_files\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Load the library bundled with this module, regardless of installer path.
require_once dirname(__DIR__, 2) . '/vendor/pclzip/pclzip.lib.php';

/**
 * Media library export form.
 */
class MediaExportFilesForm extends FormBase
{

  public function getFormId()
  {
    return 'media_export_files_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['folder_structure'] = [
      '#type' => 'select',
      '#title' => $this->t('Folder Structure'),
      '#options' => [
        'single_folder' => $this->t('Single folder with all files'),
        'nested_folders' => $this->t('Nested folders'),
      ],
      '#default_value' => 'single_folder',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download Zip'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $folder_structure = $form_state->getValue('folder_structure');

    // Retrieve all files from the media library
    $fids = \Drupal::entityQuery('file')
      ->accessCheck(FALSE)
      ->execute();
    $files = File::loadMultiple($fids);

    // Create the ZIP file with a timestamp in the filename
    $timestamp = date('Y-m-d_H-i-s');
    $zip_file_path = 'temporary://media_library_' . $timestamp . '.zip';
    $real_zip_file_path = \Drupal::service('file_system')->realpath($zip_file_path);
    $zip = new \PclZip($real_zip_file_path);

    $file_paths = [];
    foreach ($files as $file) {
      $file_path = \Drupal::service('file_system')->realpath($file->getFileUri());
      $filename = $file->getFilename();

      // Define the file path based on the folder structure selected
      if ($folder_structure === 'nested_folders') {
        // Use relative path for nested folders
        $relative_path = str_replace('public://', '', $file->getFileUri());
        $file_paths[] = [
          PCLZIP_ATT_FILE_NAME => $file_path,
          PCLZIP_ATT_FILE_NEW_FULL_NAME => $relative_path,
        ];
      } else {
        // Use filename only for single folder
        $file_paths[] = [
          PCLZIP_ATT_FILE_NAME => $file_path,
          PCLZIP_ATT_FILE_NEW_FULL_NAME => $filename,
        ];
      }
    }

    // Add files to ZIP and check for success
    if ($zip->create($file_paths) == 0) {
      \Drupal::messenger()->addError($this->t('Error creating ZIP archive: @error', ['@error' => $zip->errorInfo(true)]));
      return;
    }

    // Stream the response
    $response = new StreamedResponse(function () use ($real_zip_file_path) {
      // Ensure the file is completely read and sent
      if (file_exists($real_zip_file_path)) {
        // Clear the output buffer to prevent corruption
        if (ob_get_level()) {
          ob_end_clean();
        }
        readfile($real_zip_file_path);

        // Optional: Remove the ZIP file after download
        unlink($real_zip_file_path);
      }
    });

    // Set headers for the response with the timestamped filename
    $response->headers->set('Content-Type', 'application/zip');
    $response->headers->set('Content-Disposition', 'attachment; filename="media_library_' . $timestamp . '.zip"');
    $response->headers->set('Content-Length', filesize($real_zip_file_path));

    // Prevent caching issues
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    $response->send();
    exit(); // Ensure no further output
  }

}
