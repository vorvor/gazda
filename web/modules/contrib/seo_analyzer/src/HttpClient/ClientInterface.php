<?php

namespace Drupal\seo_analyzer\HttpClient;

use Psr\Http\Message\ResponseInterface;
use Drupal\seo_analyzer\HttpClient\Exception\HttpException;

interface ClientInterface {
  /**
   * @param string $url
   * @param array $options
   * @return ResponseInterface
   * @throws HttpException
   */
  public function get(string $url, array $options = []): ResponseInterface;
}
