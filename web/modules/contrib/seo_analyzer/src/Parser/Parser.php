<?php

namespace Drupal\seo_analyzer\Parser;

class Parser extends AbstractParser {
  /**
   * @inheritDoc
   */
  public function getMeta(): array {
    $meta = [];
    foreach ($this->getDomElements('meta') as $item) {
      $meta[$item->getAttribute('name')] = trim($item->getAttribute('content'));
    }
    return $meta;
  }

  /**
   * @inheritDoc
   */
  public function getHeaders($keyword = FALSE): array {
    $headers = [];
    for ($x = 1; $x <= 5; $x++) {
      foreach ($this->getDomElements('h' . $x) as $item) {
        $headertext = trim($item->nodeValue);
        if ($keyword && !empty($keyword) && !str_contains($headertext, 'strong')) {
          $headertext = str_ireplace($keyword, "<strong>$keyword</strong>", $headertext);
        }
        $headers['h' . $x][] = $headertext;
      }
    }
    return $headers;
  }

  /**
   * @inheritDoc
   */
  public function getTitle(): string {
    if ($this->getDomElements('title')->length > 0) {
      return trim($this->getDomElements('title')->item(0)->nodeValue);
    }
    return '';
  }

  /**
   * @inheritDoc
   */
  public function getImages(): array {
    $data = [
      'total_images' => 0,
      'images_with_alt' => 0,
      'images_without_alt' => 0,
      'images' => [],
    ];
    if ($this->getDomElements('img')->length > 0) {
      foreach ($this->getDomElements('img') as $img) {
        $data['total_images']++;
        $alt = trim($img->getAttribute('alt'));
        if (!empty($alt)) {
          $data['images_with_alt']++;
        }
        if (empty($alt)) {
          $data['images_without_alt']++;
          $alt = 'MISSSING!';
        }
        
        $data['images'][] = [
          'src' => trim($img->getAttribute('src')),
          'alt' => $alt,
          'title' => trim($img->getAttribute('title')),
          'loading' => trim($img->getAttribute('loading')),
          'width' => trim($img->getAttribute('width')),
          'height' => trim($img->getAttribute('height')),
        ];
      }
    }
    return $data;
  }

  /**
   * @inheritDoc
   */
  public function getText(): string {
    $this->removeTags('script');
    $this->removeTags('style');
    $text = strip_tags($this->dom->saveHTML());
    return preg_replace('!\s+!', ' ', strip_tags($text));
  }
}
