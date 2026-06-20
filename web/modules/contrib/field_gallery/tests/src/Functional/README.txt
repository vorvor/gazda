TODO : For the commit on prod, remove all tests.


SimpleTests
-----------
HOST="http://drupal.loc/"
php core/scripts/run-tests.sh --browser --url $HOST --verbose --class \
"Drupal\field_gallery\Tests\FieldGalleryInstallUninstallTest"

# Test without web browser.
php core/scripts/run-tests.sh --verbose --url $HOST --class \
"Drupal\field_gallery\Tests\FieldGalleryInstallUninstallTest"

php core/scripts/run-tests.sh --verbose --url $HOST --class \
"Drupal\field_gallery\Tests\FieldGalleryUITest"

php core/scripts/run-tests.sh --verbose --url $HOST --class \
"Drupal\field_gallery\Tests\FunctionalJavascript\FieldGalleryJSUiTest"
