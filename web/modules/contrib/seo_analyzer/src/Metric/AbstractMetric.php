<?php

namespace Drupal\seo_analyzer\Metric;

use ReflectionClass;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Annotation\Plugin;

abstract class AbstractMetric extends Plugin implements MetricInterface {
  use StringTranslationTrait;

  const HEADERS = 'headers';
  const DESCRIPTION = 'description';

  /**
   * @var string Metric name
   */
  public $name;

  /**
   * @var string The human-readable metric description.
   */
  public $description;

  /**
   * @var mixed Metric value
   */
  public $value;

  /**
   * @var int Negative impact on SEO. Higher value then bigger negative impact.
   */
  public $impact = 0;

  /**
   * @param mixed $inputData Input data to compute metric value
   * @throws \ReflectionException
   */
  public function __construct($inputData) {
    if (empty($this->name)) {
      $this->name = str_replace(['Drupal\\', 'seo_analyzer\\', 'Metric', '\\'], '', (new ReflectionClass($this))->getName());

    }
    $this->value = $inputData;
  }
}
