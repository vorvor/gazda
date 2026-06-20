
Module : field_gallery



About Field Gallery
-------------------
Field Gallery provide a field formatter for image fields to render as an image
gallery.


Installation
------------
Download via composer and install via drush (Recommended).
composer require drupal/field_gallery
drush en field_gallery -y

Download by composer and install using drush.
drush en field_gallery -y


Requirements
------------
Basically the module does not require any third party libraries. But for 
enhanced options such as slider ...

Available options
-----------------
Image style
Previous/Next controls and texts
Thumbnails and thumbnails style
Number of thumbnails

Features for Developers
-----------------------
The template can be override via TWIG templating system.
- Globally (field-gallery.html.twig)
- For a field (field-gallery--field-image.html.twig)
- For an entity type (field-gallery--node.html.twig)
- For a bundle (field-gallery--article.html.twig)
- Field, entity & bundle (field-gallery--field-image--node--article.html.twig)

Similar modules
---------------
Imagefield Slideshow : https://www.drupal.org/project/imagefield_slideshow


Known Issues
------------
The pager is not working properly.


Road map
--------
1. Create slideshow mode
2. Load image by AJAX
