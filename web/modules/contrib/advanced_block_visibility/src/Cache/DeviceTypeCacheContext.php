<?php

namespace Drupal\advanced_block_visibility\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;

/**
 * Cache context for device type (mobile, tablet, desktop).
 */
class DeviceTypeCacheContext implements CacheContextInterface {

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Device type');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    $detect = new \Detection\MobileDetect();
    if ($detect->isTablet()) {
      return 'tablet';
    }
    elseif ($detect->isMobile()) {
      return 'mobile';
    }
    else {
      return 'desktop';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}

