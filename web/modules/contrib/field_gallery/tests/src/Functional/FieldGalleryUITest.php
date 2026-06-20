<?php

namespace Drupal\Tests\field_gallery\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_gallery_test\Entity\FieldGalleryTestEntity;
use Drupal\file\Entity\File;
use Drupal\Tests\BrowserTestBase;

/**
 * Test install and uninstall Entity Update module.
 *
 * @group Field
 */
class FieldGalleryUITest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'image',
    'field_ui',
    'field_gallery',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Node Type.
   *
   * @var string
   */
  protected $nodeType;

  /**
   * Test for custom entity.
   */
  public function testCustomEntityConfig() {
    // Enable help module for testCustomEntityConfig() Only.
    \Drupal::service('module_installer')->install(['field_gallery_test']);
    $assert = $this->assertSession();

    // Change user.
    $user = $this->drupalCreateUser([
      'access administration pages',
      'administer fgt_entity fields',
      'administer fgt_entity display',
      'administer fgt_entity form display',
    ]);
    $this->drupalLogin($user);

    $path = 'admin/structure/field_gallery_test/overview';
    $this->drupalGet($path);
    $path = 'admin/structure/field_gallery_test/overview/display';
    $this->drupalGet($path);
    $assert->pageTextContains('Gallery HTML (large).');
    $assert->responseContains('Gallery HTML (large).');
    $assert->elementTextContains('css', '#edit-fields-field-images-type option[value=field_gallery_formatter]', "Field Gallery");

    $images = [];
    for ($i = 0; $i < 10; $i++) {
      $file = File::create([
        'uid' => $user->id(),
        'filename' => "test-$i.jpg",
        'alt' => "Image : $i",
        'uri' => "public://page/test-$i.jpg",
        'status' => 1,
      ]);
      $file->save();
      $images[] = $file->id();
    }
    $values = [
      'id' => 1,
      'field_images' => $images,
    ];
    $entity = FieldGalleryTestEntity::create($values);
    $entity->save();

    $urlBase = $entity->toUrl();
    $this->drupalGet($urlBase);

    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->elementAttributeContains('css', '.field-gallery-mainimage img', "src", "test-0.jpg");
    $assert->elementAttributeContains('css', '.field-gallery-mainimage a', "href", "index=");
    $assert->elementAttributeContains('css', '.field-gallery-prev a', "href", "index=0");
    $assert->elementAttributeContains('css', '.field-gallery-next a', "href", "index=1");

    $urlBase->setRouteParameter('index', '4');
    $this->drupalGet($urlBase);
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    // Check : newt, previous, main image ...
    $assert->elementAttributeContains('css', '.field-gallery-mainimage img', "src", "test-4.jpg");
    $assert->elementTextContains('css', '.field-gallery-prev a', "Previous");
    $assert->elementTextContains('css', '.field-gallery-next a', "Next");
    $assert->elementAttributeContains('css', '.field-gallery-mainimage a', "href", "index=5");
    $assert->elementAttributeContains('css', '.field-gallery-prev a', "href", "index=3");
    $assert->elementAttributeContains('css', '.field-gallery-next a', "href", "index=5");
    // Check pagination i = 4.
    $assert->elementAttributeContains('css', '.thumb-index-0 img', "src", "test-1.jpg");
    $assert->elementAttributeContains('css', '.thumb-index-4 img', "src", "test-5.jpg");

    $urlBase->setRouteParameter('index', '9');
    $this->drupalGet($urlBase);
    $assert->statusCodeEquals(200);
    $assert->elementAttributeContains('css', '.field-gallery-mainimage img', "src", "test-9.jpg");
    $assert->elementAttributeContains('css', '.field-gallery-mainimage a', "href", "index=0");
    $assert->elementAttributeContains('css', '.field-gallery-prev a', "href", "index=8");
    $assert->elementAttributeContains('css', '.field-gallery-next a', "href", "index=0");
    // Check pagination for i = 9.
    $assert->elementAttributeContains('css', '.thumb-index-0 img', "src", "test-5.jpg");
    $assert->elementAttributeContains('css', '.thumb-index-4 img', "src", "test-9.jpg");
  }

  /**
   * Test for node.
   */
  public function testNodeTypeConfig() {

    $assert = $this->assertSession();

    // Change user.
    $user = $this->drupalCreateUser([
      'administer nodes',
      'administer content types',
      'administer node fields',
      'administer node display',
    ]);
    $this->drupalLogin($user);

    // This will enable with drupal default config.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
      'display_submitted' => FALSE,
    ]);
    $node_type = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->load('page');
    $node_type->setDisplaySubmitted(TRUE);
    $node_type->save();

    // Check content page.
    $this->drupalGet('admin/structure/types');
    $assert->pageTextContains('Basic page');

    // Create field storage.
    FieldStorageConfig::create([
      'field_name' => 'field_images',
      'entity_type' => 'node',
      'type' => 'image',
      'cardinality' => -1,
    ])->save();

    // Attach to node.
    FieldConfig::create([
      'field_name' => 'field_images',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Images field',
    ])->save();

    // Check display config page.
    $path = 'admin/structure/types/manage/page/display';
    $this->drupalGet($path);
    // Check Image field/ field gallery present.
    $assert->elementTextContains('css', '#field-images td', "Images field");
    $assert->elementTextContains('css', '#edit-fields-field-images-type option[value=field_gallery_formatter]', "Field Gallery");

    // Change Field display via Field UI.
    $path = 'admin/structure/types/manage/page/display';
    $this->drupalGet($path);

    $edit = [];
    $edit['fields[field_images][region]'] = 'content';
    // @TODO : Change to image_url to field_gallery_formatter (Schema error).
    $edit['fields[field_images][type]'] = 'image_url';
    $this->drupalPostForm($path, $edit, 'Save');
    $assert->pageTextContains('Your settings have been saved.');

    // Create image files.
    $images = [];
    for ($i = 0; $i < 10; $i++) {
      $file = File::create([
        'uid' => $user->id(),
        'filename' => "test-$i.jpg",
        'alt' => "Image : $i",
        'uri' => "public://page/test-$i.jpg",
        'status' => 1,
      ]);
      $file->save();
      $images[] = $file->id();
    }

    // Create Node.
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Image CT',
      'status' => 1,
      'uid' => $user->id(),
      'field_images' => $images,
    ]);

    // Check node.
    $this->drupalGet($node->toUrl());
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Image CT');

  }

}
