<?php

namespace Drupal\seo_analyzer\Metric;

interface MetricInterface {
  /**
   * Returns description of the results of metric analysis.
   *
   * @return string
   */
  public function analyze(): string;
}
