# Clipboard.js

Integrates the Clipboard.js library with Drupal.

## Requirements

* [Clipboard.js library](https://github.com/zenorocha/clipboard.js)

### Installation via composer

It is assumed you are installing Drupal through Composer using the Drupal
Composer facade. See https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies#drupal-packagist

It is recommended downloading third-party libraries using composer. See https://www.drupal.org/docs/develop/using-composer/manage-dependencies#third-party-libraries
Follow the instructions to set up the Asset Packagist.

Install the library:

```
composer require npm-asset/clipboard:^2.0.11
```

## Installation

Install as you would normally install a contributed Drupal module. Visit
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules)
for further information.

## Usage

The Clipboard.js can be added to any text field by visiting the manage display
page for the entity and choosing Clipboard.js as the field formatter.

Custom form fields can also use clipboard.js using the form api or in a render
array using the theme function to display the element:

    ```PHP
      $form['clipboardjs'] = [
        '#theme' => 'clipboardjs_button',
        '#value' => 'Any copyable value.',
      ];
    ```

or a full example of one of the available templates e.g., clipboardjs_button,
clipboardjs_snippet, clipboardjs_textarea or clipboardjs_textfield:

    ```PHP
    $form['clipboardjs'] = [
      '#type' => 'item',
      '#theme' => 'clipboardjs_textfield',
      '#title' => $this->t('Clipboard.js Textfield'),
      '#value' => 'Any copyable value.',
      '#label' => $this->t('Click to copy'),
      '#alert_style' => 'tooltip', // e.g., 'tooltip', 'alert' or 'none'
      '#alert_text' => $this->t('Copied!'),
    ];
    ```

## Maintainers

Drupal 10/11 - Development, Maintenance
- [Tim Diels (tim-diels)](https://www.drupal.org/u/tim-diels)
- [Stefan Auditor (sanduhrs)](https://www.drupal.org/u/sanduhrs)

Drupal 8/9 - Development, Maintenance
- [Stefan Auditor (sanduhrs)](https://www.drupal.org/u/sanduhrs)

Initial Development
- [Norman Kerr (kenianbei)](https://www.drupal.org/u/kenianbei)
