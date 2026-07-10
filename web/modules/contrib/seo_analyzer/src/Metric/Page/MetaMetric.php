<?php

namespace Drupal\seo_analyzer\Metric\Page;

use Drupal\seo_analyzer\Metric\AbstractMetric;

class MetaMetric extends AbstractMetric {

  /**
   * @inheritdoc
   */
  public function analyze(): string {
    $this->description = $this->t('Length of Title and Description meta tags');

    // Check if there is a meta description.
    if (!isset($this->value['meta'][self::DESCRIPTION]) || empty($this->value['meta'][self::DESCRIPTION])) {
      $this->impact = 10;
      $this->value['meta'][self::DESCRIPTION] = $this->t('MISSING!');
      return $this->t('You don\'t have a meta description on your page. You should add this because it\'s really important for your SEO');
    }

    $description_length = strlen($this->value['meta'][self::DESCRIPTION]);
    $title_length = strlen($this->value['title']);
    switch (TRUE) {

      case ($title_length < 10 || $title_length > 60):
        $this->impact = 5;
        $message = $this->t("The page title length should be between 10 and 60 characters. Yours is <strong>@length</strong> characters", ['@length' => $title_length]);
        break;
      case (empty($this->value['meta'][self::DESCRIPTION])):
        $this->impact = 5;
        $message = $this->t('Missing page meta description tag. We strongly recommend to add it. It should be between 50 and 160 characters and contain your keyword');
        break;
      case ($description_length < 50 || $description_length > 160):
        $this->impact = 3;
        $message = $this->t("The page meta description length should be between 50 and 160 characters. Yours is <strong>@length</strong> characters", ['@length' => $description_length]);
        break;
      default:
        $message = $this->t('The site meta tags look good');
        break;
    }
    return $message;
  }
}
