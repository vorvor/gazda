<?php

namespace Drupal\field_gallery_test\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Note entity.
 *
 * @ContentEntityType(
 *   id = "fgt_entity",
 *   label = @Translation("FieldGalleryTestEntity"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "fgt_entity",
 *   admin_permission = "access administration pages",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/field_gallery_test/{fgt_entity}",
 *   },
 *   field_ui_base_route = "field_gallery_test.fgt_entity.settings",
 * )
 */
class FieldGalleryTestEntity extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // ID/UUID ... ...
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE);

    $fields['field_images'] = BaseFieldDefinition::create('image')
      ->setLabel('Images')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSettings([
        // 'target_type' => 'image',
        'default_value' => '',
        'text_processing' => 0,
        'file_extensions' => 'jpg',
        'alt_field' => 1,
        'alt_field_required' => 0,
        'title_field' => 1,
        'title_field_required' => 0,
        'max_resolution' => '',
        'min_resolution' => '',
        'default_image' => [
          'uuid' => NULL,
          'alt' => '',
          'title' => '',
          'width' => NULL,
          'height' => NULL,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'field_gallery_formatter',
      ])
      ->setDisplayOptions('form', [
        'type' => 'select',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
