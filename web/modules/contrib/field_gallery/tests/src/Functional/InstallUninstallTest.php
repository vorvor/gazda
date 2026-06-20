<?php

namespace Drupal\Tests\field_gallery\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test uninstall functionality of Site Version module.
 *
 * @group field_gallery
 */
class InstallUninstallTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['field_gallery'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $permissions = [
      'access administration pages',
      'administer modules',
    ];

    // User to set up entity_update.
    $this->admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->admin_user);
  }

  /**
   * Test uninstall the module without mishap.
   */
  public function testUninstall() {
    /* @var $installer \Drupal\Core\Extension\ModuleInstallerInterface */
    $installer = $this->container->get('module_installer');
    $this->assertTrue($installer->uninstall(['field_gallery']));
  }

  /**
   * Test that we can uninstall by interface.
   */
  public function testUninstallWeb() {
    $assert = $this->assertSession();

    // Tests if site opens with no errors.
    $this->drupalGet('');
    $assert->statusCodeEquals(200);

    // Uninstall the module field_gallery.
    $edit = [];
    $edit['uninstall[field_gallery]'] = TRUE;
    $this->drupalPostForm('admin/modules/uninstall', $edit, 'Uninstall');
    $assert->pageTextContains('Field Gallery');
    $this->drupalPostForm(NULL, NULL, 'Uninstall');
    $assert->pageTextContains('The selected modules have been uninstalled.');
  }

}
